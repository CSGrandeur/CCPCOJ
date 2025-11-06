#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 评测客户端 - 评测的唯一入口
负责协调不同评测类型的执行和资源管理
"""

import os
import shutil
import sys
import json
import signal
import logging
import argparse
from typing import Dict, Any, Optional

# 将当前目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)

from tools.config_loader import ConfigLoader
from tools.debug_manager import DebugManager, setup_unified_logging, is_debug_enabled, log_error_with_context
from tools.lock_manager import get_lock_manager
from tools.web_client import WebClient
from tools.work_dir_manager import WorkDirManager
from tools import status_constants as sc

# 导入评测系统异常类
from judge_type.base_judge_type import JudgeSysErr, JudgeSysErrTestData, JudgeSysErrCompile, JudgeSysErrProgram


class JudgeClient:
    """评测客户端 - 评测的唯一入口和主逻辑"""
    
    def __init__(self, config_path: str):
        """初始化评测客户端"""
        self.config = self.load_config(config_path)
        
        # 设置日志系统
        self.logger = setup_unified_logging("JudgeClient", self.config)
        
        # 初始化Web客户端
        self.web_client = WebClient(self.config)
        
        # 初始化锁管理器
        self.lock_manager = get_lock_manager(self.logger)
        
        # 初始化工作目录管理器
        base_work_dir = self.config.get("judge", {}).get("work_dir", os.path.dirname(os.path.abspath(__file__)))
        self.work_dir_manager = WorkDirManager(base_work_dir, self.logger)
        
        # 注册信号处理
        signal.signal(signal.SIGTERM, self.signal_handler)
        signal.signal(signal.SIGINT, self.signal_handler)
        
        self.logger.info("评测客户端启动")
    
    def handle_judger_error(self, error: JudgeSysErr, solution_id: int = None) -> Dict[str, Any]:
        """统一处理评测系统错误"""
        error_type = type(error).__name__
        error_code = getattr(error, 'error_code', 'UNKNOWN')
        
        # 记录错误日志
        self.logger.error(f"评测系统错误 [{error_type}]: {error.message}")
        if is_debug_enabled():
            self.logger.debug(f"错误代码: {error_code}")
            if solution_id:
                self.logger.debug(f"解决方案ID: {solution_id}")
        
        # 根据错误类型确定程序状态
        if isinstance(error, JudgeSysErrTestData):
            program_status = sc.PROGRAM_SYSTEM_ERROR
            message = f"测试数据错误: {error.message}"
        elif isinstance(error, JudgeSysErrCompile):
            program_status = sc.PROGRAM_SYSTEM_ERROR
            message = f"评测系统编译错误: {error.message}"
        elif isinstance(error, JudgeSysErrProgram):
            program_status = sc.PROGRAM_SYSTEM_ERROR
            message = f"评测程序错误: {error.message}"
        else:
            program_status = sc.PROGRAM_SYSTEM_ERROR
            message = f"评测系统错误: {error.message}"
        
        return {
            "program_status": program_status,
            "judge_result": sc.get_judge_result_from_program_status(program_status),
            "time": 0,
            "memory": 0,
            "message": message
        }
    
    def load_config(self, config_path: str) -> Dict[str, Any]:
        """加载配置文件"""
        return ConfigLoader.load_config(config_path)
    
    def signal_handler(self, signum, frame):
        """信号处理函数"""
        self.logger.info(f"收到信号 {signum}，正在停止评测...")
        sys.exit(1)
    
    def prepare_work_environment(self, solution_id: int) -> tuple[bool, str, Dict[str, Any]]:
        """准备工作环境（优先使用共享内存）"""
        try:
            self.logger.info("准备工作目录...")
            
            # 优先尝试使用共享内存
            success, work_dir, work_dir_info = self.work_dir_manager.prepare_work_dir()
            
            if not success:
                self.logger.error(f"准备工作目录失败：{work_dir_info}")
                error_result = self.work_dir_manager.create_system_error_result(work_dir_info)
                # 发送系统错误信息到后端
                self.web_client.add_runtime_error(solution_id, {
                    "data_type": "json",
                    "data": work_dir_info
                })
                return False, "", error_result
            
            # 记录工作目录信息
            if work_dir_info.get("is_shm", False):
                self.logger.info(f"使用共享内存工作目录：{work_dir} (大小: {work_dir_info.get('shm_size_mb', 0)}MB)")
            else:
                self.logger.info(f"使用普通工作目录：{work_dir}")
            
            return True, work_dir, work_dir_info
            
        except Exception as e:
            log_error_with_context(self.logger, "准备工作环境", e, {"solution_id": solution_id})
            return False, "", {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR),
                "time": 0,
                "memory": 0,
                "message": str(e)
            }
    
    def _get_test_data_files(self, problem_id: int) -> list:
        """
        获取测试数据文件列表
        
        Args:
            problem_id: 题目ID
            
        Returns:
            list: 测试数据文件列表 [(src_path, dst_name), ...]
        """
        try:
            data_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
            test_data_files = []
            
            if os.path.exists(data_dir):
                # 查找所有 .in 和 .out 文件
                for filename in os.listdir(data_dir):
                    if filename.endswith('.in') or filename.endswith('.out'):
                        src_path = os.path.join(data_dir, filename)
                        test_data_files.append((src_path, filename))
                
                if is_debug_enabled():
                    self.logger.debug(f"找到 {len(test_data_files)} 个测试数据文件")
            
            return test_data_files
            
        except Exception as e:
            self.logger.warning(f"获取测试数据文件失败: {e}")
            return []
    
    def sync_problem_data(self, problem_id: int) -> bool:
        """同步题目数据（由data_sync模块内部处理锁）"""
        try:
            self.logger.info(f"同步题目 {problem_id} 的数据...")
            
            # 执行数据同步（data_sync模块内部会处理写锁）
            from data_sync import DataSync
            data_sync = DataSync(self.config)
            return data_sync.sync_problem_data(problem_id)
            
        except Exception as e:
            log_error_with_context(self.logger, "同步题目数据", e, {"problem_id": problem_id})
            return False
    
    def prepare_source_code(self, solution_id: int, language: str, work_dir: str, lang_config: Dict[str, Any] = None) -> tuple[bool, str, str]:
        """准备源代码"""
        try:
            # 使用语言处理器获取文件名信息
            from lang.lang_factory import LanguageFactory
            # 优先使用来自后端的语言配置
            handler_config = lang_config if isinstance(lang_config, dict) else self.config
            lang_handler = LanguageFactory.create_language_handler(language, handler_config, work_dir)
            
            if not lang_handler:
                self.logger.error(f"不支持的语言：{language}")
                return False, "", ""
            
            # 从语言处理器获取标准文件名
            source_file = lang_handler.get_source_file_name()
            executable = lang_handler.get_executable_name()
            
            # 下载源代码
            source_code = self.web_client.get_solution_code(solution_id)
            if not source_code:
                return False, "", ""
            
            # 保存源代码到文件
            source_path = os.path.join(work_dir, source_file)
            with open(source_path, 'w', encoding='utf-8') as f:
                f.write(source_code)
            
            self.logger.info(f"源代码已保存到：{source_path}")
            return True, source_file, executable
            
        except Exception as e:
            log_error_with_context(self.logger, "准备源代码", e, {
                "solution_id": solution_id,
                "language": language,
                "work_dir": work_dir
            })
            return False, "", ""
    
    def compile_solution(self, language: str, source_file: str, executable: str, work_dir: str, task_type: str = None, lang_config: Dict[str, Any] = None) -> Dict[str, Any]:
        """编译解决方案，返回编译结果和错误信息"""
        try:
            # 使用语言工厂创建语言处理器
            from lang.lang_factory import LanguageFactory
            # 优先使用来自后端的语言配置
            handler_config = lang_config if isinstance(lang_config, dict) else self.config
            lang_handler = LanguageFactory.create_language_handler(language, handler_config, work_dir)
            
            if not lang_handler:
                self.logger.error(f"不支持的语言：{language}")
                return {
                    "success": False,
                    "message": f"不支持的语言：{language}",
                    "compile_error": f"不支持的语言：{language}"
                }
            
            # 使用语言处理器进行编译
            result = lang_handler.compile_solution(source_file, executable, task_type)
            
            if result["compile_status"] == "success":
                self.logger.info(f"编译成功：{result.get('message', '')}")
                return {
                    "success": True,
                    "message": result.get('message', '编译成功'),
                    "compile_error": None
                }
            else:
                compile_error = result.get('message', '编译失败')
                
                if is_debug_enabled():
                    self.logger.debug(f"编译失败：{compile_error}")
                return {
                    "success": False,
                    "message": "编译失败",
                    "compile_error": compile_error
                }
                
        except Exception as e:
            error_msg = f"编译过程中发生异常：{str(e)}"
            log_error_with_context(self.logger, "编译解决方案", e, {
                "language": language,
                "source_file": source_file,
                "executable": executable,
                "work_dir": work_dir
            })
            return {
                "success": False,
                "message": "编译失败",
                "compile_error": error_msg
            }
    
    def run_judge_type(self, spj: int, problem_id: int, executable: str, time_limit: float, 
                      memory_limit: int, language: str, work_dir: str) -> Dict[str, Any]:
        """运行相应的评测类型"""
        try:
            if spj == 0:
                # 默认评测 (spj=0)
                from judge_type.judge_type_default import JudgeTypeDefault
                default_judge = JudgeTypeDefault(self.config, work_dir)
                return default_judge.run_default_judge(problem_id, executable, time_limit, memory_limit, language)
            elif spj == 1:
                # TPJ特判评测 (spj=1)
                from judge_type.judge_type_tpj import JudgeTypeTpj
                tpj_judge = JudgeTypeTpj(self.config, work_dir)
                return tpj_judge.run_special_judge_mode(problem_id, executable, time_limit, memory_limit, language)
            elif spj == 2:
                # 交互题评测 (spj=2)
                from judge_type.judge_type_interactive import JudgeTypeInteractive
                interactive_judge = JudgeTypeInteractive(self.config, work_dir)
                return interactive_judge.run_interactive_judge_mode(problem_id, executable, time_limit, memory_limit)
            else:
                raise JudgeSysErr(f"不支持的评测类型：{spj}")
        except JudgeSysErr as e:
            # 捕获评测系统错误，统一处理
            return self.handle_judger_error(e)
        except Exception as e:
            # 其他未知异常
            log_error_with_context(self.logger, "运行评测类型", e, {
                "spj": spj,
                "problem_id": problem_id,
                "executable": executable,
                "time_limit": time_limit,
                "memory_limit": memory_limit,
                "language": language
            })
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR),
                "time": 0,
                "memory": 0,
                "message": str(e)
            }
    
    def feedback_result(self, solution_id: int, result: Dict[str, Any]) -> bool:
        """反馈评测结果到后端"""
        try:
            # 获取程序运行状态和评测结果
            program_status = result.get("program_status", sc.PROGRAM_SYSTEM_ERROR)
            judge_result = result.get("judge_result", sc.get_judge_result_from_program_status(program_status))
            time_used = result.get("time", 0)
            memory_used = result.get("memory", 0)
            message = result.get("message", "")
            pass_ratio = result.get("pass_ratio")  # 新增：通过率字段
            compile_error = result.get("compile_error")  # 新增：编译错误详情
            
            # 确定评测机执行状态
            if program_status in [sc.PROGRAM_COMPLETED, sc.PROGRAM_TIME_LIMIT_EXCEEDED, 
                                sc.PROGRAM_RUNTIME_ERROR, sc.PROGRAM_COMPILE_ERROR, sc.PROGRAM_SYSTEM_ERROR]:
                judge_task_status = sc.TASK_COMPLETED
            else:
                judge_task_status = sc.TASK_ERROR
            
            # 准备反馈数据
            feedback_data = {
                "solution_id": solution_id,
                "task_status": judge_task_status,
                "program_status": program_status,
                "judge_result": judge_result,
                "time": time_used,
                "memory": memory_used,
                "message": message
            }
            
            # 如果有通过率，添加到反馈数据中
            if pass_ratio is not None:
                feedback_data["pass_ratio"] = pass_ratio
            
            self.logger.info(f"反馈评测结果：{judge_result} (时间: {time_used}ms, 内存: {memory_used}KB)")
            if pass_ratio is not None:
                self.logger.info(f"通过率：{pass_ratio:.4f}")
            
            # 发送结果到后端
            if is_debug_enabled():
                self.logger.debug(f"准备调用 update_task_status:")
                self.logger.debug(f"  solution_id: {solution_id}")
                self.logger.debug(f"  judge_task_status: {judge_task_status}")
                self.logger.debug(f"  judge_result: {judge_result}")
                self.logger.debug(f"  pass_ratio: {pass_ratio}")
                self.logger.debug(f"  WebClient认证状态: {self.web_client.is_authenticated}")
            
            # 准备发送到后端的数据
            backend_data = {
                "judge_result": judge_result,
                "time": time_used,
                "memory": memory_used,
                "message": message
            }
            
            # 如果有通过率，添加到后端数据中
            if pass_ratio is not None:
                backend_data["pass_ratio"] = pass_ratio
            
            success = self.web_client.update_task_status(solution_id, judge_task_status, backend_data)
            
            if success:
                self.logger.info(f"评测结果已反馈到后端：{judge_result}")
                
                if program_status == sc.PROGRAM_COMPILE_ERROR:
                    # 编译错误：使用专门的编译错误详情
                    if compile_error:
                        structured_ce_info = {
                            "data_type": "txt",
                            "data": compile_error
                        }
                        self.web_client.add_compile_error(solution_id, structured_ce_info)
                elif judge_result != sc.JUDGE_ACCEPTED:
                    # 其它错误
                    reinfo = result.get("reinfo", {})
                    if reinfo:
                        structured_re_info = {
                            "data_type": "json",
                            "data": reinfo
                        }
                        self.web_client.add_runtime_error(solution_id, structured_re_info)
                        self.logger.info(f"累积错误信息已发送到reinfo：{len(str(reinfo))} 字符")
                
                
                return True
            else:
                self.logger.error("反馈评测结果到后端失败")
                return False
                
        except Exception as e:
            log_error_with_context(self.logger, "反馈评测结果", e, {
                "solution_id": solution_id,
                "result": result
            })
            return False
    
    def cleanup_work_environment(self, work_dir: str):
        """清理工作环境"""
        try:
            if is_debug_enabled():
                self.logger.info("调试模式：保留工作目录，不进行清理")
                self.logger.info(f"工作目录保留在：{work_dir}")
            else:
                self.logger.info("开始清理工作目录...")
                cleanup_success, cleanup_info = self.work_dir_manager.cleanup_work_dir()
                if cleanup_success:
                    self.logger.info(f"工作目录清理成功：{cleanup_info.get('message', '')}")
                else:
                    self.logger.warning(f"工作目录清理失败：{cleanup_info}")
        except Exception as e:
            self.logger.error(f"清理工作环境时发生错误：{e}")

    def _archive_logs_to_work_dir(self, work_dir: str):
        """在调试模式下将日志拷贝到 run0 目录，便于复现与排查"""
        try:
            if not work_dir or not os.path.isdir(work_dir):
                return
            from tools.debug_manager import DebugManager
            log_dir = DebugManager.get_log_dir()
            if not log_dir or not os.path.isdir(log_dir):
                return
            # 将 tmp.log 放置到 run0 根目录，便于快速查看
            tmp_src = os.path.join(log_dir, "tmp.log")
            tmp_dst = os.path.join(work_dir, "tmp.log")
            if os.path.isfile(tmp_src):
                try:
                    shutil.copy2(tmp_src, tmp_dst)
                except Exception:
                    pass
        except Exception:
            pass
    
    def judge_task(self, task: Dict[str, Any]) -> Dict[str, Any]:
        """评测任务 - 主评测流程（带读锁保护）"""
        solution_id = None
        work_dir = None
        read_lock_acquired = False
        local_data_dir = None
        
        try:
            self.logger.info(f"开始评测任务 {task['solution_id']}")
            
            # 获取任务信息
            solution_id = task["solution_id"]
            problem_id = task["problem_id"]
            language = task["language"]
            spj = task.get("spj", 0)
            original_time_limit = float(task.get("time_limit", 1))
            original_memory_limit = int(task.get("memory_limit", 512))
            
            # 确保 spj 是整数
            if isinstance(spj, str):
                spj = int(spj)
            
            # 从任务中获取评测参数（由后端提供）
            judge_common = task.get("judge_common", {}) or {}
            judge_lang_cfg = task.get("judge_lang_cfg", {}) or {}

            # 归一化语言键名，构造语言处理配置
            lang_key = language.lower().strip()
            if lang_key == "c++":
                lang_key = "cpp"
            elif lang_key == "golang":
                lang_key = "go"

            handler_config = {"common": judge_common}
            handler_config[lang_key] = judge_lang_cfg

            # 使用语言处理器获取调整后的运行时限制（使用后端配置）
            from lang.lang_factory import LanguageFactory
            lang_handler = LanguageFactory.create_language_handler(language, handler_config, work_dir)
            runtime_limits = lang_handler.get_runtime_limits(original_time_limit, original_memory_limit)
            time_limit = runtime_limits.get("time_limit", original_time_limit)
            memory_limit = runtime_limits.get("memory_limit", original_memory_limit)
            stack_limit = runtime_limits.get("stack_limit", original_memory_limit)

            # 施加全局最大限制（由后端 common 配置提供，单位：time(ms)、memory(MB)、output(MB)）
            try:
                max_time_ms = float(judge_common.get("max_time_limit")) if "max_time_limit" in judge_common else None
                if max_time_ms is not None:
                    # 题目/语言后的秒限制与上限（ms）取较小值
                    time_limit = min(time_limit, max_time_ms / 1000.0)
            except Exception:
                pass
            try:
                max_mem_mb = int(judge_common.get("max_memory_limit")) if "max_memory_limit" in judge_common else None
                if max_mem_mb is not None:
                    memory_limit = min(memory_limit, max_mem_mb)
                else:
                    # 兼容旧逻辑上限
                    if memory_limit > 2048:
                        memory_limit = 2048
            except Exception:
                # 兼容旧逻辑上限
                if memory_limit > 2048:
                    memory_limit = 2048
            
            # 使用统一的调试信息记录
            if is_debug_enabled():
                task_details = {
                    "解决方案ID": solution_id,
                    "题目ID": problem_id,
                    "编程语言": language,
                    "特判模式": spj,
                    "题目原始限制": {
                        "时间": f"{original_time_limit}s",
                        "内存": f"{original_memory_limit}MB"
                    },
                    "语言调整后限制": {
                        "时间": f"{time_limit}s",
                        "内存": f"{memory_limit}MB",
                        "栈": f"{stack_limit}MB"
                    }
                }
                self.logger.debug(f"任务详情: {task_details}")
            
            # 1. 准备工作环境
            success, work_dir, work_dir_info = self.prepare_work_environment(solution_id)
            if not success:
                return work_dir_info
            
            # 2. 获取数据目录读锁，确保评测过程中数据不被修改
            local_data_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
            self.logger.info(f"获取题目 {problem_id} 数据目录读锁...")
            if not self.lock_manager.acquire_read_lock(local_data_dir, timeout=300):
                return {
                    "program_status": sc.PROGRAM_SYSTEM_ERROR,
                    "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR),
                    "time": 0,
                    "memory": 0,
                    "message": f"获取题目 {problem_id} 数据目录读锁失败"
                }
            read_lock_acquired = True
            self.logger.info(f"成功获取题目 {problem_id} 数据目录读锁")
            
            # 3. 同步题目数据（data_sync内部会处理写锁）
            if not self.sync_problem_data(problem_id):
                return {
                    "program_status": sc.PROGRAM_SYSTEM_ERROR,
                    "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR),
                    "time": 0,
                    "memory": 0,
                    "message": f"同步题目 {problem_id} 数据失败"
                }
            
            # 4. 准备源代码（传入后端语言配置）
            success, source_file, executable = self.prepare_source_code(solution_id, language, work_dir, handler_config)
            if not success:
                return {
                    "program_status": sc.PROGRAM_COMPILE_ERROR,
                    "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_COMPILE_ERROR),
                    "time": 0,
                    "memory": 0,
                    "message": "无法获取源代码"
                }
            
            # 5. 编译解决方案（选手程序）
            # 无论 spj 是什么值，这里都是编译选手程序
            compile_result = self.compile_solution(language, source_file, executable, work_dir, sc.TASK_TYPE_PLAYER_COMPILE, handler_config)
            if not compile_result["success"]:
                # 限制编译错误信息长度（避免过长）
                compile_error = compile_result.get("compile_error", "编译失败")
                if len(compile_error) > 10000:  # 限制字符数
                    compile_error = compile_error[:10000] + "\n... (编译错误信息过长，已截断)"
                
                result = {
                    "program_status": sc.PROGRAM_COMPILE_ERROR,
                    "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_COMPILE_ERROR),
                    "time": 0,
                    "memory": 0,
                    "message": "编译失败",  # 简洁的描述性消息
                    "compile_error": compile_error  # 详细的编译错误信息
                }
                # 反馈编译错误结果到后端
                self.feedback_result(solution_id, result)
                return result
            
            # 5.5. 编译成功，更新任务状态为运行中
            self.web_client.update_task_status(solution_id, sc.TASK_RUNNING)
            
            # 6. 运行相应的评测类型
            result = self.run_judge_type(spj, problem_id, executable, time_limit, memory_limit, language, work_dir)
            # 7. 反馈评测结果到后端
            self.feedback_result(solution_id, result)
            
            return result
                
        except JudgeSysErr as e:
            # 捕获评测系统错误，统一处理
            error_result = self.handle_judger_error(e, solution_id)
            # 反馈错误结果到后端
            if solution_id:
                self.feedback_result(solution_id, error_result)
            return error_result
        except Exception as e:
            # 其他未知异常
            log_error_with_context(self.logger, "评测任务", e, {
                "solution_id": solution_id,
                "problem_id": task.get("problem_id"),
                "language": task.get("language")
            })
            error_result = {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR),
                "time": 0,
                "memory": 0,
                "message": str(e)
            }
            # 反馈错误结果到后端
            if solution_id:
                self.feedback_result(solution_id, error_result)
            
            return error_result
        finally:
            # 8. 释放读锁
            if read_lock_acquired and local_data_dir:
                if self.lock_manager.release_read_lock(local_data_dir):
                    self.logger.info(f"已释放题目 {problem_id} 数据目录读锁")
                else:
                    self.logger.warning(f"释放题目 {problem_id} 数据目录读锁失败")
            
            # 9. 清理工作环境
            if work_dir:
                # 调试模式：先归档日志到 run0
                if is_debug_enabled():
                    self._archive_logs_to_work_dir(work_dir)
                self.cleanup_work_environment(work_dir)
    

def main():
    """主函数"""
    parser = argparse.ArgumentParser(description="CSGOJ judge2 评测客户端")
    parser.add_argument("solution_id", help="解决方案ID")
    
    # 使用统一的调试参数解析（这会添加debug位置参数）
    # debug 开启debug输出
    # syscallfree 开启系统调用自由模式（seccomp LOG模式）
    args = DebugManager.parse_debug_args(parser)
    
    # 确定配置文件路径
    if args.config:
        config_path = args.config
    else:
        # 使用默认配置文件路径
        current_dir = os.path.dirname(os.path.abspath(__file__))
        config_path = os.path.join(current_dir, "config.json")
    
    # 创建评测客户端
    judge_client = JudgeClient(config_path)
    
    # 从服务器获取任务信息
    try:
        # 获取解决方案信息
        solution_info = judge_client.web_client.get_solution_info(int(args.solution_id))
        if not solution_info:
            print(f"错误：无法获取解决方案 {args.solution_id} 的信息")
            sys.exit(1)
        
        # 获取题目信息
        problem_info = judge_client.web_client.get_problem_info(solution_info["problem_id"])
        if not problem_info:
            print(f"错误：无法获取题目 {solution_info['problem_id']} 的信息")
            sys.exit(1)
        
        # 构建任务信息
        task = {
            "solution_id": int(args.solution_id),
            "problem_id": solution_info["problem_id"],
            "user_id": solution_info["user_id"],
            "language": solution_info["language"],
            "contest_id": solution_info.get("contest_id", 0),
            "spj": problem_info.get("spj", 0),
            "time_limit": problem_info.get("time_limit", 1),
            "memory_limit": problem_info.get("memory_limit", 256),
            # 透传后端评测参数
            "judge_common": solution_info.get("judge_common", {}),
            "judge_lang_cfg": solution_info.get("judge_lang_cfg", {})
        }
        
        judge_client.logger.info(f"开始评测解决方案 {args.solution_id}")
        judge_client.logger.info(f"题目ID: {task['problem_id']}, 语言: {task['language']}, SPJ: {task['spj']}")
        
    except Exception as e:
        print(f"错误：获取任务信息失败 - {e}")
        sys.exit(1)
    
    # 评测任务
    result = judge_client.judge_task(task)
    
    # 输出结果
    if is_debug_enabled():
        print("\n" + "="*60)
        print("评测结果:")
        print("="*60)
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()