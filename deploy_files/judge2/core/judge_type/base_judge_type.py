#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 评测类型基类
提供所有评测类型的公共功能和基本框架
"""

import os
import sys
import time
import logging
import subprocess
import shutil
from typing import Dict, Any, Optional, List
from abc import ABC, abstractmethod

# 将当前目录和父目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)

from tools.debug_manager import is_debug_enabled, log_error_with_context, setup_unified_logging
from monitor.run_monitor import create_run_monitor
from tools import status_constants as sc


class JudgeSysErr(Exception):
    """评测机错误基类"""
    def __init__(self, message: str, error_code: str = None):
        super().__init__(message)
        self.message = message
        self.error_code = error_code or "JUDGER_ERROR"


class JudgeSysErrTestData(JudgeSysErr):
    """评测机测试数据相关错误"""
    def __init__(self, message: str):
        super().__init__(message, "JUDGER_ERROR_TEST_DATA")


class JudgeSysErrCompile(JudgeSysErr):
    """评测机编译相关错误"""
    def __init__(self, message: str):
        super().__init__(message, "JUDGER_ERROR_COMPILE")


class JudgeSysErrProgram(JudgeSysErr):
    """评测机程序相关错误"""
    def __init__(self, message: str):
        super().__init__(message, "JUDGER_ERROR_PROGRAM")


class BaseJudgeType(ABC):
    """评测类型基类 - 所有评测类型的共同逻辑"""
    
    @staticmethod
    def get_core_dir() -> str:
        """获取core目录的绝对路径"""
        # 获取当前文件的父目录，即core目录
        return os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    
    def __init__(self, config: Dict[str, Any], work_dir: str = None):
        """初始化评测类型基类"""
        self.config = config
        # 使用统一的日志系统配置logger
        self.logger = setup_unified_logging(self.__class__.__name__, config)
        
        # 确保工作目录是相对于core目录
        core_dir = self.get_core_dir()
        if work_dir:
            if not os.path.isabs(work_dir):
                work_dir = os.path.join(core_dir, work_dir)
        else:
            work_dir = config.get("judge", {}).get("work_dir", core_dir)
            if not os.path.isabs(work_dir):
                work_dir = os.path.join(core_dir, work_dir)
        
        self.work_dir = work_dir
        self.result = {
            "program_status": sc.PROGRAM_UNKNOWN,  # 程序执行状态
            "judge_result": sc.JUDGE_UNKNOWN,      # 评测结果
            "time": 0,
            "memory": 0,
            "message": ""
        }
    
    def setup_filesystem_isolation(self):
        """设置文件系统隔离"""
        try:
            # 确保工作目录存在且权限正确
            os.makedirs(self.work_dir, exist_ok=True)
            os.chmod(self.work_dir, 0o755)
            
            # 设置工作目录为只读（除了必要的写入权限）
            # 这里可以添加更多的文件系统隔离措施
            self.logger.info(f"文件系统隔离设置完成，工作目录：{self.work_dir}")
            
        except Exception as e:
            # Debug模式：提供详细的错误信息
            if self.logger.isEnabledFor(logging.DEBUG):
                import traceback
                error_details = {
                    "error_type": type(e).__name__,
                    "error_message": str(e),
                    "error_args": e.args if hasattr(e, 'args') else None,
                    "traceback": traceback.format_exc(),
                    "filesystem_info": {
                        "work_dir": self.work_dir,
                        "work_dir_exists": os.path.exists(self.work_dir) if self.work_dir else False,
                        "work_dir_isdir": os.path.isdir(self.work_dir) if self.work_dir else False,
                        "current_dir": os.getcwd(),
                        "user_id": os.getuid() if hasattr(os, 'getuid') else None,
                        "group_id": os.getgid() if hasattr(os, 'getgid') else None
                    }
                }
                self.logger.error(f"设置文件系统隔离时发生详细错误：{error_details}")
            else:
                self.logger.error(f"设置文件系统隔离失败：{e}")
    
    def get_evaluation_config(self) -> Dict[str, Any]:
        """获取评测配置"""
        return {
            "flg_stop_when_not_ac": self.config.get("evaluation", {}).get("flg_stop_when_not_ac", 1),
            "shm_run": self.config.get("evaluation", {}).get("shm_run", 0),
            "flg_use_max_time": self.config.get("evaluation", {}).get("flg_use_max_time", 1),
            "time_limit_to_total": self.config.get("evaluation", {}).get("time_limit_to_total", 0),
            "full_diff": self.config.get("evaluation", {}).get("full_diff", 1),
            "top_diff_bytes": self.config.get("evaluation", {}).get("top_diff_bytes", 2048),
            "cpu_compensation": self.config.get("evaluation", {}).get("cpu_compensation", 1.0)
        }
    
    def convert_to_chroot_path(self, file_path: str) -> str:
        """将绝对路径转换为 chroot 环境下的相对路径
        
        统一规则：
        - 绝对路径：转换为相对于工作目录的路径，统一加上 ./ 前缀
        - 相对路径：统一加上 ./ 前缀（如果还没有）
        - 返回的路径格式统一为 ./path/to/file 的形式
        """
        if not file_path:
            return file_path
            
        # 如果是绝对路径，转换为相对于工作目录的路径
        if os.path.isabs(file_path):
            # 检查文件是否在工作目录内
            try:
                rel_path = os.path.relpath(file_path, self.work_dir)
                # 如果相对路径不包含 '..'，说明文件在工作目录内
                if not rel_path.startswith('..'):
                    # 确保有 ./ 前缀
                    if not rel_path.startswith('./'):
                        return f"./{rel_path}"
                    return rel_path
                else:
                    # 文件不在工作目录内，只返回文件名
                    return f"./{os.path.basename(file_path)}"
            except ValueError:
                # 如果无法计算相对路径，返回文件名
                return f"./{os.path.basename(file_path)}"
        else:
            # 已经是相对路径，统一加上 ./ 前缀（如果还没有）
            if not file_path.startswith('./'):
                return f"./{file_path}"
            return file_path
    
    def convert_cmd_for_chroot(self, cmd: list) -> list:
        """转换命令中的路径为 chroot 环境下的路径
        
        规则：
        1. 系统命令（纯命令名，如 python3, java 等）：
           - 解释型语言：转换为绝对路径（/usr/bin/python3 等），确保在 chroot 环境中能找到
           - 编译型语言：保持原样，通过 PATH 查找
        2. 系统目录中的绝对路径（/usr/bin/python3 等）不转换
        3. 工作目录内的可执行文件（如 ./Main, Main.py 等）需要转换为 ./path 格式
        4. 其他参数中的文件路径需要转换
        """
        if not cmd:
            return cmd
        
        converted_cmd = []
        
        # 处理第一个参数（可执行文件或命令）
        first_arg = cmd[0]
        
        # 判断第一个参数的转换规则：
        # 1. 绝对路径：如果是系统目录（/usr/bin 等），不转换；否则转换
        # 2. 纯命令名（无路径分隔符）：
        #    - 如果文件在工作目录中存在，视为可执行文件，转换为 ./filename
        #    - 否则视为系统命令：
        #      * 解释型语言：转换为绝对路径，确保在 chroot 环境中能找到
        #      * 编译型语言：保持原样，通过 PATH 查找
        # 3. 相对路径（./开头或包含/）：转换，确保有 ./ 前缀
        if os.path.isabs(first_arg):
            # 绝对路径：如果是系统目录中的命令，不转换；否则转换
            system_dirs = ['/usr/bin', '/bin', '/usr/local/bin', '/usr/sbin', '/sbin']
            is_system_command = any(first_arg.startswith(d + '/') for d in system_dirs)
            if is_system_command:
                converted_cmd.append(first_arg)  # 系统命令，不转换
            else:
                converted_cmd.append(self.convert_to_chroot_path(first_arg))
        elif '/' not in first_arg and not first_arg.startswith('./'):
            # 纯命令名（无路径分隔符，且不是 ./ 开头）
            # 检查文件是否在工作目录中存在
            file_path = os.path.join(self.work_dir, first_arg)
            if os.path.exists(file_path) and os.path.isfile(file_path):
                # 文件存在于工作目录，视为可执行文件，转换为 ./filename
                converted_cmd.append(self.convert_to_chroot_path(first_arg))
            else:
                # 文件不存在，视为系统命令
                # 对于解释型语言，转换为绝对路径以确保在 chroot 环境中能找到
                abs_path = self._resolve_command_path(first_arg)
                if abs_path:
                    converted_cmd.append(abs_path)
                    if is_debug_enabled():
                        self.logger.debug(f"解释型语言命令转换为绝对路径: {first_arg} -> {abs_path}")
                else:
                    # 找不到绝对路径，保持原样（可能是编译型语言，通过 PATH 查找）
                    converted_cmd.append(first_arg)
        else:
            # 包含路径的相对路径（./Main, Main.py, subdir/file.py 等），需要转换
            converted_cmd.append(self.convert_to_chroot_path(first_arg))
        
        # 对于其他参数，只转换看起来像文件路径的参数
        for arg in cmd[1:]:
            # 如果参数是命令行选项（- 开头），不转换
            if arg.startswith('-'):
                converted_cmd.append(arg)
            # 如果参数包含路径分隔符或看起来像文件路径，则转换
            elif ('/' in arg or arg.endswith(('.in', '.out', '.txt', '.cc', '.cpp', '.c', '.py', '.java', '.go', '.class'))):
                converted_cmd.append(self.convert_to_chroot_path(arg))
            else:
                # 不转换其他参数（如数字、字符串参数等）
                converted_cmd.append(arg)
        
        if is_debug_enabled():
            self.logger.debug(f"路径转换: {' '.join(cmd)} -> {' '.join(converted_cmd)}")
        
        return converted_cmd
    
    def _resolve_command_path(self, cmd: str) -> str:
        """解析命令的绝对路径
        
        对于解释型语言（Python、Java等），在 chroot 之前查找其绝对路径，
        确保在 chroot 环境中能直接执行，不依赖 PATH 环境变量。
        
        Args:
            cmd: 命令名（如 'python3', 'java' 等）
            
        Returns:
            str: 命令的绝对路径，如果找不到或不是解释型语言命令则返回 None
        """
        # 解释型语言的常见命令列表
        interpreted_commands = {
            'python3', 'python', 'python2', 'python2.7',
            'java', 'javac',
            'node', 'nodejs',
            'php', 'php8.3',
            'ruby', 'perl', 'lua',
            'bash', 'sh'
        }
        
        # 只处理解释型语言命令
        if cmd not in interpreted_commands:
            return None
        
        # 按优先级检查常见位置（在 chroot 之前，使用主系统的路径）
        common_paths = [
            f'/usr/bin/{cmd}',
            f'/bin/{cmd}',
            f'/usr/local/bin/{cmd}'
        ]
        
        for path in common_paths:
            if os.path.exists(path):
                # 如果是符号链接，解析真实路径
                if os.path.islink(path):
                    real_path = os.path.realpath(path)
                    # 如果真实路径也在系统目录中，使用真实路径
                    if any(real_path.startswith(d + '/') for d in ['/usr/bin', '/bin', '/usr/local/bin']):
                        return real_path
                return path
        
        # 如果找不到，尝试使用 which（在主进程中，chroot 之前）
        try:
            import shutil
            abs_path = shutil.which(cmd)
            if abs_path and os.path.exists(abs_path):
                # 检查路径是否在系统目录中（确保挂载后能访问）
                if any(abs_path.startswith(d + '/') for d in ['/usr/bin', '/bin', '/usr/local/bin']):
                    return abs_path
        except Exception:
            pass
        
        return None
    
    def run_process_with_atomic_monitoring(self, cmd: List[str], time_limit: float, memory_limit: int, 
                                         stdin_file=None, stdout_file=None, stderr_file=None, test_case_name: str = None, 
                                         task_type: str = "player_run", language: str = None, out_file: str = None) -> Dict[str, Any]:
        """使用运行监控器运行进程"""
        try:
            # 转换命令路径为 chroot 环境下的路径
            chroot_cmd = self.convert_cmd_for_chroot(cmd)
            
            # 创建运行监控器
            run_monitor = create_run_monitor(self.logger, memory_limit, time_limit, self.work_dir, test_case_name, language, out_file)
            
            # 使用运行监控器运行进程并获取监控结果
            monitoring_result = run_monitor.run_program_with_monitoring(
                chroot_cmd,
                stdin_file=stdin_file,
                stdout_file=stdout_file,
                stderr_file=stderr_file,
                cwd=self.work_dir
            )
            
            # 验证时间值的合理性
            cpu_time_used = monitoring_result.get("time", -1)
            if cpu_time_used == -1:
                # CPU时间获取失败，返回系统错误
                self.logger.error(f"CPU时间监控失败，无法获取CPU时间数据。CPU时间: {cpu_time_used}")
                return {
                    "program_status": sc.PROGRAM_SYSTEM_ERROR,
                    "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR),  # TODO: 这里不应该包含judge_result
                    "time": 0,
                    "memory": 0,
                    "message": f"CPU时间监控失败，无法准确测量程序执行时间。CPU时间: {cpu_time_used}",
                    "time_source": "monitoring_failed",
                    "cpu_time_used": cpu_time_used
                }
            else:
                # CPU时间有效（包括0），使用CPU时间
                final_time = cpu_time_used
                time_source = "cpu_time"
            
            # 汇总完整结果（包含 judge_result）
            result = {
                "program_status": monitoring_result["program_status"],
                "judge_result": sc.get_judge_result_from_program_status(monitoring_result["program_status"]), # TODO: 这里不应该包含judge_result
                "time": final_time,
                "memory": monitoring_result.get("memory", 0),
                "message": monitoring_result.get("message", ""),
                "time_source": time_source,
                "cpu_time_used": cpu_time_used if cpu_time_used > 0 else None,
                "return_code": monitoring_result.get("return_code", 0)
            }
            
            # 传递详细的错误信息字段
            if "runtime_error" in monitoring_result:
                result["runtime_error"] = monitoring_result["runtime_error"]
            if "compile_error" in monitoring_result:
                result["compile_error"] = monitoring_result["compile_error"]
            if "wrong_answer_info" in monitoring_result:
                result["wrong_answer_info"] = monitoring_result["wrong_answer_info"]
            if "seccomp_error" in monitoring_result:
                result["seccomp_error"] = monitoring_result["seccomp_error"]
            if "error_type" in monitoring_result:
                result["error_type"] = monitoring_result["error_type"]
            
            return result
                
        except Exception as e:
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR),
                "time": 0,
                "memory": 0,
                "message": str(e)
            }
    
    def run_solution(self, language: str, executable: str, in_file: str, out_file: str, user_output: str,
                     time_limit: float, memory_limit: int, test_case_name: str = None) -> Dict[str, Any]:
        """运行解决方案"""
        try:
            self.logger.info(f"开始运行 {language} 程序")
            
            # 设置文件系统隔离
            self.setup_filesystem_isolation()
            
            # 准备共享内存临时文件
            shm_files = self._prepare_shm_temp_files(in_file, user_output)
            
            # 准备输入输出文件
            stdin_file = open(shm_files['input'], 'r') if shm_files['input'] else None
            stdout_file = open(shm_files['output'], 'w') if shm_files['output'] else None
            stderr_file = open(shm_files['error'], 'w') if shm_files['error'] else None
            
            try:
                # 使用语言工厂创建语言处理器
                from lang.lang_factory import LanguageFactory
                lang_handler = LanguageFactory.create_language_handler(language, self.config, self.work_dir)
                
                if not lang_handler:
                    raise ValueError(f"不支持的语言: {language}")
                
                # 使用语言处理器获取运行命令
                cmd = lang_handler.get_run_command(executable)
                
                # 使用原子化统一监控运行进程
                result = self.run_process_with_atomic_monitoring(
                    cmd, time_limit, memory_limit,
                    stdin_file, stdout_file, stderr_file, test_case_name,
                    task_type=sc.TASK_TYPE_PLAYER_RUN, language=language, out_file=out_file  # 传递任务类型、语言信息和输出文件
                )
                
                # seccomp错误信息已经在unified_monitor中处理，这里不需要额外处理
                
                self.logger.info(f"程序运行完成：{result}")
                return result
                
            finally:
                # 关闭文件句柄
                if stdin_file:
                    stdin_file.close()
                if stdout_file:
                    stdout_file.close()
                if stderr_file:
                    stderr_file.close()
                
                # 处理输出文件：从共享内存复制到最终位置
                self._finalize_output_files(shm_files, user_output)
                
                # 清理共享内存临时文件
                self._cleanup_shm_temp_files(shm_files)
                
        except Exception as e:
            # 确保清理临时文件
            if 'shm_files' in locals():
                self._cleanup_shm_temp_files(shm_files)
            
            self.logger.error(f"运行程序时发生错误：{e}")
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR),
                "time": 0,
                "memory": 0,
                "message": str(e)
            }
    
    def _is_running_in_shm(self) -> bool:
        """检查是否在共享内存中运行"""
        return self.work_dir.startswith("/dev/shm")
    
    def _can_use_shm_for_input(self) -> bool:
        """检查是否可以使用共享内存优化输入文件读取"""
        # 检查共享内存是否可用
        if not os.path.exists("/dev/shm"):
            return False
        
        # 检查共享内存是否可写
        try:
            test_file = "/dev/shm/.shm_test"
            with open(test_file, 'w') as f:
                f.write("test")
            os.remove(test_file)
            return True
        except (OSError, IOError):
            return False
    
    def _prepare_shm_temp_files(self, in_file: str, user_output: str) -> Dict[str, str]:
        """
        准备共享内存临时文件
        
        Args:
            in_file: 题目输入文件路径
            user_output: 选手输出文件路径
            
        Returns:
            Dict[str, str]: 包含 input, output, error 文件路径的字典
        """
        try:
            from tools.shm_manager import get_shm_manager
            shm_manager = get_shm_manager(self.logger)
            
            # 使用 ShmManager 准备临时文件
            return shm_manager.prepare_temp_files(self.work_dir, in_file, user_output)
                
        except Exception as e:
            self.logger.warning(f"准备共享内存临时文件失败: {e}，使用原始路径")
            return {
                'input': in_file,
                'output': user_output,
                'error': None
            }
    
    def _finalize_output_files(self, shm_files: Dict[str, str], user_output: str) -> None:
        """
        处理输出文件：从共享内存复制到最终位置
        
        Args:
            shm_files: 共享内存文件路径字典
            user_output: 最终输出文件路径（选手输出）
        """
        try:
            from tools.shm_manager import get_shm_manager
            shm_manager = get_shm_manager(self.logger)
            
            # 使用 ShmManager 处理输出文件
            shm_manager.finalize_output_file(shm_files, user_output)
                    
        except Exception as e:
            self.logger.warning(f"处理输出文件失败: {e}")
    
    def _cleanup_shm_temp_files(self, shm_files: Dict[str, str]) -> None:
        """
        清理共享内存临时文件
        
        Args:
            shm_files: 共享内存文件路径字典
        """
        try:
            from tools.shm_manager import get_shm_manager
            shm_manager = get_shm_manager(self.logger)
            
            # 使用 ShmManager 清理临时文件
            shm_manager.cleanup_temp_files(shm_files)
            
        except Exception as e:
            self.logger.warning(f"清理共享内存临时文件失败: {e}")
    
    def discover_test_cases(self, problem_id: int, data_dir: str) -> List[tuple]:
        """发现测试用例 - 公共逻辑"""
        try:
            if data_dir is None:
                raise JudgeSysErrTestData("data_dir 不能为 None")
            
            # 转换为绝对路径
            data_dir = os.path.abspath(data_dir)
            if not os.path.exists(data_dir):
                raise JudgeSysErrTestData(f"测试数据目录不存在：{data_dir}")
            
            test_cases = []
            for file in sorted(os.listdir(data_dir)):
                if file.endswith('.in'):
                    base_name = file[:-3]
                    in_file = os.path.join(data_dir, file)
                    out_file = os.path.join(data_dir, f"{base_name}.out")
                    if os.path.exists(out_file):
                        test_cases.append((in_file, out_file, base_name))
            
            if not test_cases:
                raise JudgeSysErrTestData(f"没有找到测试用例，目录：{data_dir}")
            
            return test_cases
            
        except JudgeSysErrTestData:
            # 重新抛出测试数据错误
            raise
        except Exception as e:
            # 其他异常转换为测试数据错误
            raise JudgeSysErrTestData(f"发现测试用例时发生错误：{e}")
    
    def run_test_cases_loop(self, test_cases: List[tuple], executable: str, time_limit: float, 
                           memory_limit: int, language: str = "cpp") -> Dict[str, Any]:
        """运行测试用例循环 - 公共逻辑"""
        try:
            passed = 0
            total_time = 0
            max_memory = 0
            max_single_time = 0  # 记录单个测试用例的最大时间
            total_cases = len(test_cases)
            
            # 累积错误信息
            accumulated_reinfo = []  # 累积所有测试用例的错误信息
            
            # 获取评测配置
            eval_config = self.get_evaluation_config()
            flg_stop_when_not_ac = eval_config.get("flg_stop_when_not_ac", 0)
            flg_use_max_time = eval_config.get("flg_use_max_time", 1)
            
            for i, (in_file, out_file, case_name) in enumerate(test_cases):
                self.logger.info(f"运行测试用例：{case_name} ({i+1}/{total_cases})")
                
                # 运行单个测试用例 - 子类需要重载此方法
                result = self.run_single_test_case(in_file, out_file, case_name, executable, 
                                                 time_limit, memory_limit, language)
                
                case_time = result.get("time", 0)
                case_memory = result.get("memory", 0)
                
                # 验证时间值的合理性（防止异常大的时间值）
                max_reasonable_time = time_limit * 1000 * 2  # 最大合理时间：时间限制的2倍
                if case_time > max_reasonable_time:
                    self.logger.warning(f"测试用例 {case_name} 的时间值异常大: {case_time}ms，限制为 {max_reasonable_time}ms")
                    case_time = max_reasonable_time
                
                # 更新时间和内存统计
                total_time += case_time
                max_memory = max(max_memory, case_memory)
                max_single_time = max(max_single_time, case_time)
                
                # 累积错误信息
                self._accumulate_error_info(accumulated_reinfo, result, case_name)
                
                if result["program_status"] == sc.PROGRAM_COMPLETED:
                    if result.get("judge_result") == sc.JUDGE_ACCEPTED:
                        passed += 1
                        self.logger.info(f"测试用例 {case_name} 通过")
                    else:
                        # 测试用例失败
                        if flg_stop_when_not_ac == 1:
                            # 遇到非AC就停止，返回失败结果
                            final_time = max_single_time if flg_use_max_time == 1 else total_time
                            
                            # 验证时间值的合理性
                            max_reasonable_time = time_limit * 1000 * (i + 1) * 2  # 最大合理时间
                            if final_time > max_reasonable_time:
                                self.logger.warning(f"返回时时间值异常大: {final_time}ms，限制为 {max_reasonable_time}ms")
                                final_time = max_reasonable_time
                            
                            return {
                                "program_status": sc.PROGRAM_COMPLETED,
                                "judge_result": result.get("judge_result", sc.JUDGE_WRONG_ANSWER),
                                "time": final_time,
                                "memory": max_memory,
                                "message": f"测试用例 {case_name} {result.get('message', '失败')}",
                                "reinfo": self._format_accumulated_reinfo(accumulated_reinfo)
                            }
                        else:
                            # 继续运行所有测试用例，记录失败但继续
                            self.logger.info(f"测试用例 {case_name} 失败，继续运行剩余测试用例")
                else:
                    # 程序运行失败（TLE、RE等）
                    if flg_stop_when_not_ac == 1:
                        # 遇到非AC就停止，返回失败结果
                        final_time = max_single_time if flg_use_max_time == 1 else total_time
                        
                        # 验证时间值的合理性
                        max_reasonable_time = time_limit * 1000 * (i + 1) * 2  # 最大合理时间
                        if final_time > max_reasonable_time:
                            self.logger.warning(f"返回时时间值异常大: {final_time}ms，限制为 {max_reasonable_time}ms")
                            final_time = max_reasonable_time
                        
                        return {
                            "program_status": result["program_status"],
                            "judge_result": result.get("judge_result", sc.get_judge_result_from_program_status(result["program_status"])),
                            "time": final_time,
                            "memory": max_memory,
                            "message": f"测试用例 {case_name} {result.get('message', '运行失败')}",
                            "reinfo": self._format_accumulated_reinfo(accumulated_reinfo)
                        }
                    else:
                        # 继续运行所有测试用例
                        self.logger.info(f"测试用例 {case_name} 运行失败，继续运行剩余测试用例")
            
            # 所有测试用例运行完成
            final_time = max_single_time if flg_use_max_time == 1 else total_time
            
            # 最终时间值验证
            max_reasonable_total_time = time_limit * 1000 * total_cases * 2  # 最大合理总时间
            if final_time > max_reasonable_total_time:
                self.logger.warning(f"最终时间值异常大: {final_time}ms，限制为 {max_reasonable_total_time}ms")
                final_time = max_reasonable_total_time
            
            if flg_stop_when_not_ac == 0:
                # 运行所有测试用例，计算通过率
                pass_ratio = round(passed / total_cases, 4) if total_cases > 0 else 0.0
                
                if passed == total_cases:
                    # 全部通过
                    return {
                        "program_status": sc.PROGRAM_COMPLETED,
                        "judge_result": sc.JUDGE_ACCEPTED,
                        "time": final_time,
                        "memory": max_memory,
                        "message": f"所有 {passed} 个测试用例通过",
                        "pass_ratio": pass_ratio,
                        "reinfo": self._format_accumulated_reinfo(accumulated_reinfo)
                    }
                else:
                    # 部分通过，返回WA结果
                    return {
                        "program_status": sc.PROGRAM_COMPLETED,
                        "judge_result": sc.JUDGE_WRONG_ANSWER,
                        "time": final_time,
                        "memory": max_memory,
                        "message": f"通过 {passed}/{total_cases} 个测试用例",
                        "pass_ratio": pass_ratio,
                        "reinfo": self._format_accumulated_reinfo(accumulated_reinfo)
                    }
            else:
                # 遇到非AC就停止的模式，如果到这里说明全部通过
                return {
                    "program_status": sc.PROGRAM_COMPLETED,
                    "judge_result": sc.JUDGE_ACCEPTED,
                    "time": final_time,
                    "memory": max_memory,
                    "message": f"所有 {passed} 个测试用例通过",
                    "reinfo": self._format_accumulated_reinfo(accumulated_reinfo)
                }
            
        except Exception as e:
            self.logger.error(f"运行测试用例循环时发生错误：{e}")
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR),
                "time": 0,
                "memory": 0,
                "message": str(e)
            }
    
    def _accumulate_error_info(self, accumulated_reinfo: List[Dict[str, Any]], result: Dict[str, Any], case_name: str):
        """累积错误信息"""
        # 只累积非AC的测试用例信息
        if result.get("judge_result") != sc.JUDGE_ACCEPTED:
            error_info = {
                "test_case": case_name,
                "judge_result": result.get("judge_result", "UNKNOWN"),
                "message": result.get("message", ""),
            }
            # 添加具体的错误详情
            if "judge_info" in result:
                error_info["judge_info"] = result["judge_info"]
            if "runtime_error" in result:
                error_info["runtime_error"] = result["runtime_error"]
            if "compile_error" in result:
                error_info["compile_error"] = result["compile_error"]
            
            accumulated_reinfo.append(error_info)
    
    def _format_accumulated_reinfo(self, accumulated_reinfo: List[Dict[str, Any]]) -> Dict[str, Any]:
        """格式化累积的错误信息为结构化数据"""
        if not accumulated_reinfo:
            return {}
        
        return {
            "error_summary": {
                "total_failed_cases": len(accumulated_reinfo),
                "failed_cases": accumulated_reinfo
            }
        }
    
    @abstractmethod
    def run_single_test_case(self, in_file: str, out_file: str, case_name: str, 
                           executable: str, time_limit: float, memory_limit: int, 
                           language: str = "cpp") -> Dict[str, Any]:
        """运行单个测试用例 - 子类必须实现此方法"""
        pass
    
    def compile_solution(self, language: str, source_file: str, output_file: str, task_type: str = None) -> bool:
        """编译解决方案（使用语言处理器）"""
        try:
            self.logger.info(f"开始编译 {language} 代码")
            
            # 使用语言工厂创建语言处理器
            from lang.lang_factory import LanguageFactory
            lang_handler = LanguageFactory.create_language_handler(language, self.config, self.work_dir)
            
            if not lang_handler:
                supported_langs = LanguageFactory.get_supported_languages()
                raise JudgeSysErrCompile(f"不支持的语言：{language}，支持的语言：{supported_langs}")
            
            # 使用语言处理器进行编译
            result = lang_handler.compile_solution(source_file, output_file, task_type)
            
            if result["compile_status"] == "success":
                self.logger.info(f"编译成功：{result.get('message', '')}")
                return True
            else:
                # 编译失败，直接抛出错误（seccomp信息已经在unified_monitor中处理）
                error_message = result.get('message', '')
                raise JudgeSysErrCompile(f"编译失败：{error_message}")
                
        except JudgeSysErrCompile:
            # 重新抛出编译错误
            raise
        except Exception as e:
            # 其他异常转换为编译错误
            raise JudgeSysErrCompile(f"编译过程中发生错误：{e}")
    
    def _get_cached_tpj_executable(self, problem_id: int, tpj_source: str) -> Optional[str]:
        """
        检查是否有缓存的 TPJ 可执行文件
        
        Args:
            problem_id: 题目ID
            tpj_source: TPJ 源代码文件路径
            
        Returns:
            Optional[str]: 如果存在有效的缓存可执行文件，返回其路径；否则返回 None
        """
        try:
            data_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
            cached_tpj = os.path.join(data_dir, "tpj")
            
            # 检查缓存的可执行文件是否存在
            if not os.path.exists(cached_tpj):
                if is_debug_enabled():
                    self.logger.debug(f"TPJ 缓存文件不存在: {cached_tpj}")
                return None
            
            # 检查缓存文件是否可执行
            if not os.access(cached_tpj, os.X_OK):
                if is_debug_enabled():
                    self.logger.debug(f"TPJ 缓存文件不可执行: {cached_tpj}")
                return None
            
            # 检查时间戳：缓存文件必须晚于源代码文件
            if os.path.exists(tpj_source):
                source_mtime = os.path.getmtime(tpj_source)
                cached_mtime = os.path.getmtime(cached_tpj)
                
                if cached_mtime >= source_mtime:
                    if is_debug_enabled():
                        self.logger.debug(f"TPJ 缓存文件有效: {cached_tpj} (缓存时间: {cached_mtime}, 源码时间: {source_mtime})")
                    return cached_tpj
                else:
                    if is_debug_enabled():
                        self.logger.debug(f"TPJ 缓存文件过期: {cached_tpj} (缓存时间: {cached_mtime}, 源码时间: {source_mtime})")
                    return None
            
            # # 如果源代码不存在，但缓存文件存在，也认为有效（可能是首次编译后缓存）
            # if is_debug_enabled():
            #     self.logger.debug(f"TPJ 缓存文件存在但源码不存在，使用缓存: {cached_tpj}")
            # return cached_tpj
            return None
            
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"检查 TPJ 缓存时发生错误: {e}")
            return None
    
    def _cache_tpj_executable(self, problem_id: int, compiled_tpj: str) -> bool:
        """
        将编译好的 TPJ 可执行文件缓存到数据目录
        
        Args:
            problem_id: 题目ID
            compiled_tpj: 编译好的 TPJ 可执行文件路径（在工作目录中）
            
        Returns:
            bool: 是否成功缓存
        """
        try:
            if not os.path.exists(compiled_tpj):
                self.logger.warning(f"要缓存的 TPJ 文件不存在: {compiled_tpj}")
                return False
            
            data_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
            cached_tpj = os.path.join(data_dir, "tpj")
            
            # 确保数据目录存在
            os.makedirs(data_dir, exist_ok=True)
            
            # 复制可执行文件到数据目录（覆盖式）
            shutil.copy2(compiled_tpj, cached_tpj)
            
            # 设置执行权限
            os.chmod(cached_tpj, 0o755)
            
            if is_debug_enabled():
                self.logger.debug(f"TPJ 可执行文件已缓存: {compiled_tpj} -> {cached_tpj}")
            
            return True
            
        except Exception as e:
            self.logger.warning(f"缓存 TPJ 可执行文件失败: {e}")
            return False
    
