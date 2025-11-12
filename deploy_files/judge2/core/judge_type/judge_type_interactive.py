#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 交互题评测类型模块 (spj=2)
基于testlib的tpj交互题评测系统
"""

import os
import sys
import time
import subprocess
import shutil
from typing import Dict, Any

# 将当前目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)

from base_judge_type import BaseJudgeType, JudgeSysErrTestData, JudgeSysErrCompile, JudgeSysErrProgram
from tools import status_constants as sc
from tools.debug_manager import is_debug_enabled

class JudgeTypeInteractive(BaseJudgeType):
    """交互题评测类型 (spj=2) - 基于testlib的交互题评测"""
    
    def __init__(self, config: Dict[str, Any], work_dir: str = None):
        """初始化交互题评测器"""
        super().__init__(config, work_dir)
        self.tpj_executable = None
        self.tpj_work_dir = None  # TPJ 工作目录（用于编译和运行）
    
    def run_interactive_judge(self, tpj_executable: str, user_executable: str, in_file: str, 
                            out_file: str, time_limit: float, memory_limit: int) -> Dict[str, Any]:
        """运行交互题评测（使用新的monitor方案）
        
        注意：tpj_executable 参数保留以保持接口兼容，但实际不使用
        TPJ 可执行文件已经在 self.tpj_work_dir 中（编译时已准备好）
        """
        user_monitor = None
        tpj_monitor = None
        user_process = None
        tpj_process = None
        
        try:
            self.logger.info("开始交互题评测")
            
            if is_debug_enabled():
                self.logger.debug(f"交互题评测详情:")
                self.logger.debug(f"  TPJ程序: {self.tpj_executable} (在工作目录 {self.tpj_work_dir} 中)")
                self.logger.debug(f"  用户程序: {user_executable}")
                self.logger.debug(f"  输入文件: {in_file}")
                self.logger.debug(f"  输出文件: {out_file}")
                self.logger.debug(f"  时间限制: {time_limit}s")
                self.logger.debug(f"  内存限制: {memory_limit}MB")
            
            # 直接使用 self.tpj_work_dir（已经在 run_interactive_judge_mode 中设置）
            # 只复制测试数据文件，TPJ 可执行文件已经在工作目录中
            files_to_copy = {
                "input.in": in_file,
                "output.out": out_file
            }
            chroot_files = self._copy_files_to_tpj_work_dir(self.tpj_work_dir, files_to_copy)
            
            chroot_input_file = chroot_files["input.in"]
            chroot_output_file = chroot_files["output.out"]
            
            # 创建交互进程管道（返回进程和监控器）
            user_process, tpj_process, user_monitor, tpj_monitor = self._create_interactive_processes(
                user_executable, chroot_input_file, chroot_output_file, 
                time_limit, memory_limit
            )
            try:
                # 等待两个进程完成
                start_time = time.time()
                
                # 对于交互题，stdin/stdout 已经通过管道连接
                # 不能使用 communicate()，因为它会尝试读取 stdout/stdin，会阻塞交互
                # 直接使用 wait() 等待进程完成，与其他评测模式保持一致
                
                # 等待TPJ进程完成（TPJ进程通常会先结束）
                try:
                    tpj_process.wait(timeout=time_limit)
                except subprocess.TimeoutExpired:
                    tpj_process.kill()
                    user_process.kill()
                    raise
                
                tpj_return_code = tpj_process.returncode
                
                # 等待用户进程完成（如果还没结束）
                try:
                    user_process.wait(timeout=time_limit)
                except subprocess.TimeoutExpired:
                    user_process.kill()
                    raise
                
                user_return_code = user_process.returncode
                
                end_time = time.time()
                run_time = int((end_time - start_time) * 1000)  # 毫秒
                
                # 读取 TPJ 的 stderr（进程结束后读取，避免阻塞）
                # 参考 judge_type_tpj.py 的处理方式
                tpj_stderr = ""
                try:
                    if tpj_process.stderr:
                        tpj_stderr = tpj_process.stderr.read()
                        tpj_process.stderr.close()
                except Exception:
                    pass
                
                # 分析TPJ结果（TPJ的返回码和stderr包含评测信息）
                from tools.tpj_result_analyzer import analyze_tpj_result
                tpj_result = analyze_tpj_result(tpj_return_code, tpj_stderr, run_time, 0)
                
                if is_debug_enabled():
                    self.logger.debug(f"TPJ分析结果: {tpj_result}")
                    self.logger.debug(f"用户进程返回码: {user_return_code}")
                
                return tpj_result
                    
            except subprocess.TimeoutExpired:
                # 超时处理
                if user_process:
                    user_process.kill()
                if tpj_process:
                    tpj_process.kill()
                return {
                    "program_status": sc.PROGRAM_TIME_LIMIT_EXCEEDED,
                    "judge_result": sc.JUDGE_TIME_LIMIT_EXCEEDED,
                    "time": int(time_limit * 1000),
                    "memory": 0,
                    "message": "交互题评测超时"
                }
            finally:
                # 确保关闭所有进程管道（进程结束后可以安全关闭）
                try:
                    if user_process:
                        if user_process.stdin:
                            try:
                                user_process.stdin.close()
                            except Exception:
                                pass
                        if user_process.stdout:
                            try:
                                user_process.stdout.close()
                            except Exception:
                                pass
                    if tpj_process:
                        if tpj_process.stdin:
                            try:
                                tpj_process.stdin.close()
                            except Exception:
                                pass
                        if tpj_process.stdout:
                            try:
                                tpj_process.stdout.close()
                            except Exception:
                                pass
                        if tpj_process.stderr:
                            try:
                                tpj_process.stderr.close()
                            except Exception:
                                pass
                except Exception as pipe_cleanup_error:
                    if is_debug_enabled():
                        self.logger.debug(f"关闭进程管道时发生错误: {pipe_cleanup_error}")
                
        except Exception as e:
            self.logger.error(f"运行交互题评测时发生错误：{e}")
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "judge_result": sc.JUDGE_SYSTEM_ERROR,
                "time": 0,
                "memory": 0,
                "message": str(e)
            }
        finally:
            # 清理监控器资源（cgroup、挂载点等）
            try:
                if user_monitor:
                    user_monitor.cleanup()
                if tpj_monitor:
                    tpj_monitor.cleanup()
            except Exception as monitor_cleanup_error:
                self.logger.warning(f"清理监控器资源失败: {monitor_cleanup_error}")
            
            # 注意：不需要清理 TPJ 工作目录，因为工作目录在 run_interactive_judge_mode 结束时统一清理
    
    def _create_interactive_processes(self, user_executable: str, 
                                    chroot_input_file: str, chroot_output_file: str,
                                    time_limit: float, memory_limit: int):
        """创建交互进程管道
        
        Args:
            user_executable: 用户程序可执行文件路径
            chroot_input_file: TPJ 工作目录中的输入文件路径
            chroot_output_file: TPJ 工作目录中的输出文件路径
            time_limit: 时间限制
            memory_limit: 内存限制
        
        Returns:
            tuple: (user_process, tpj_process, user_monitor, tpj_monitor)
        """
        from monitor.run_monitor import create_run_monitor
        
        # 创建用户程序监控器（使用选手工作目录）
        user_monitor = create_run_monitor(
            logger=self.logger,
            memory_limit_mb=memory_limit,
            time_limit=time_limit,
            work_dir=self.work_dir,
            test_case_name="user_interactive",
            language="cpp",  # 假设用户程序是C++
            task_type=sc.TASK_TYPE_PLAYER_RUN
        )
        
        # 创建TPJ程序监控器（使用 TPJ 工作目录）
        tpj_monitor = create_run_monitor(
            logger=self.logger,
            memory_limit_mb=1024,  # TPJ程序内存限制
            time_limit=time_limit,
            work_dir=self.tpj_work_dir,  # 使用实例变量
            test_case_name="tpj_interactive",
            language="cpp",
            task_type=sc.TASK_TYPE_TPJ_RUN
        )
        
        # 创建临时文件用于 TPJ 的 argv[2]（tout 输出文件）
        # 根据 testlib.h registerInteraction：tout.open(argv[2]) 用于 TPJ 写入给选手的输出
        # 这个文件对评测机来说不重要，使用临时文件即可
        tpj_tout_file = os.path.join(self.tpj_work_dir, "tpj_tout.tmp")
        
        # 构建命令
        # 选手程序：直接从 stdin 读取，向 stdout 输出
        user_cmd = [user_executable]
        
        # TPJ程序：根据 testlib.h registerInteraction 的参数要求
        # TPJ 可执行文件已经在工作目录中，使用相对路径
        # argv[1] = input-file (问题输入数据，inf.init)
        # argv[2] = output-file (TPJ 通过 tout 写入，临时文件)
        # argv[3] = answer-file (答案数据，ans.init)
        tpj_cmd = ["./tpj", chroot_input_file, tpj_tout_file, chroot_output_file]
        
        # 统一使用 convert_cmd_for_chroot 转换命令路径
        user_cmd = self.convert_cmd_for_chroot(user_cmd)
        tpj_cmd = self.convert_cmd_for_chroot(tpj_cmd, work_dir=self.tpj_work_dir)
        if is_debug_enabled():
            self.logger.debug(f"用户程序命令: {' '.join(user_cmd)}")
            self.logger.debug(f"TPJ程序命令: {' '.join(tpj_cmd)}")
            self.logger.debug(f"交互管道说明:")
            self.logger.debug(f"  TPJ stdout -> 选手程序 stdin (TPJ向选手发送问题/提示)")
            self.logger.debug(f"  选手程序 stdout -> TPJ stdin (选手向TPJ发送答案/询问)")
            self.logger.debug(f"  TPJ argv[1] = {tpj_cmd[1]} (问题输入数据)")
            self.logger.debug(f"  TPJ argv[2] = {tpj_cmd[2]} (TPJ输出文件，临时文件)")
            self.logger.debug(f"  TPJ argv[3] = {tpj_cmd[3]} (答案文件)")
        
        # 启动用户程序（使用监控器）
        # 选手程序从 stdin 读取（来自TPJ的stdout），向 stdout 输出（发送给TPJ的stdin）
        # stderr 使用 DEVNULL，与其他评测模式保持一致（参考 run_monitor.py）
        user_process = user_monitor.start_with_process(
            user_cmd,
            stdin=subprocess.PIPE,           # 接收 TPJ 的输出
            stdout=subprocess.PIPE,           # 发送给 TPJ 的输入
            stderr=subprocess.DEVNULL,       # 选手程序的 stderr 不需要收集
            text=True
        )
        
        # 启动TPJ程序（使用监控器）
        # TPJ 从 stdin 读取（选手程序的stdout），向 stdout 输出（发送给选手程序的stdin）
        # stderr 使用 PIPE，用于 analyze_tpj_result 分析结果（参考 judge_type_tpj.py）
        tpj_process = tpj_monitor.start_with_process(
            tpj_cmd,
            stdin=user_process.stdout,        # TPJ 从选手程序的输出读取（ouf.init(stdin)）
            stdout=user_process.stdin,        # TPJ 向选手程序的输入写入（通过stdout发送，选手从stdin读取）
            stderr=subprocess.PIPE,           # TPJ 的 stderr 需要收集（用于 analyze_tpj_result）
            text=True
        )
        
        # 返回进程和监控器，以便后续清理资源
        return user_process, tpj_process, user_monitor, tpj_monitor
    
    
    def run_single_test_case(self, in_file: str, out_file: str, case_name: str, 
                           executable: str, time_limit: float, memory_limit: int, 
                           language: str = "cpp") -> Dict[str, Any]:
        """运行单个测试用例 - 交互题评测实现"""
        try:
            # 运行交互题评测
            result = self.run_interactive_judge(self.tpj_executable, executable, in_file, out_file, time_limit, memory_limit)
            return result
            
        except Exception as e:
            self.logger.error(f"运行交互题测试用例 {case_name} 时发生错误：{e}")
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR),
                "time": 0,
                "memory": 0,
                "message": str(e)
            }
    
    def run_interactive_judge_mode(self, problem_id: int, executable: str, time_limit: float, memory_limit: int, language: str = "cpp") -> Dict[str, Any]:
        """运行交互题评测模式"""
        # 确定评测模式后的初入口
        try:
            # 构建data_dir路径
            data_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
            
            # 使用基类的公共方法发现测试用例（会自动检查data_dir和测试用例）
            test_cases = self.discover_test_cases(problem_id, data_dir)
            
            # 查找 TPJ 程序
            tpj_program = os.path.join(data_dir, "tpj.cc")
            if not os.path.exists(tpj_program):
                raise JudgeSysErrTestData("tpj.cc 不存在")
            
            # 准备 TPJ 工作目录（用于编译和运行，保存为实例变量）
            self.tpj_work_dir, _ = self._prepare_tpj_work_dir()
            
            # 检查是否有缓存的 TPJ 可执行文件
            cached_tpj = self._get_cached_tpj_executable(problem_id, tpj_program)
            
            if cached_tpj:
                # 使用缓存的 TPJ 可执行文件，复制到 TPJ 工作目录
                self.logger.info(f"使用缓存的 TPJ 可执行文件: {cached_tpj}")
                self.tpj_executable = os.path.join(self.tpj_work_dir, "tpj")
                shutil.copy2(cached_tpj, self.tpj_executable)
                os.chmod(self.tpj_executable, 0o755)
            else:
                # 需要编译 TPJ 程序（在 TPJ 工作目录编译）
                self.logger.info(f"编译 TPJ 程序: {tpj_program}")
                # 将tpj程序复制到 TPJ 工作目录
                tpj_source_path = os.path.join(self.tpj_work_dir, "tpj.cc")
                shutil.copy2(tpj_program, tpj_source_path)
                
                # 在 TPJ 工作目录编译
                if not self.compile_solution("cpp", "tpj.cc", "tpj", sc.TASK_TYPE_TPJ_COMPILE, work_dir=self.tpj_work_dir):
                    raise JudgeSysErrCompile("tpj.cc 编译失败")
                
                # 编译好的可执行文件在 TPJ 工作目录
                self.tpj_executable = os.path.join(self.tpj_work_dir, "tpj")
                
                # 编译完成后立即删除 tpj.cc 源代码
                try:
                    os.remove(tpj_source_path)
                    self.logger.debug(f"已删除 TPJ 源代码文件: {tpj_source_path}")
                except Exception as e:
                    self.logger.warning(f"删除 TPJ 源代码文件失败: {e}")
                
                # 缓存编译好的 TPJ 可执行文件
                self._cache_tpj_executable(problem_id, self.tpj_executable)
            
            # 使用基类的公共方法运行测试用例循环
            return self.run_test_cases_loop(test_cases, executable, time_limit, memory_limit, language)
                
        except (JudgeSysErrTestData, JudgeSysErrCompile):
            # 重新抛出已知的评测系统错误
            raise
        except Exception as e:
            # 其他异常转换为测试数据错误
            raise JudgeSysErrTestData(f"交互题评测时发生错误：{e}")
        finally:
            # 清理 TPJ 工作目录（使用基类方法）
            if self.tpj_work_dir:
                self._cleanup_tpj_work_dir(self.tpj_work_dir)
