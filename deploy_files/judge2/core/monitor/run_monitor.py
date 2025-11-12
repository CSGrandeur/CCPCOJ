#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
运行监控器
专门处理程序运行过程的监控，包括运行时错误和资源限制
"""

import os
import sys
import time
import logging
import subprocess
import traceback
from typing import Dict, Any, Optional

# 将当前目录和父目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)

from tools.debug_manager import is_debug_enabled, is_syscallfree_enabled
from task_monitor_base import TaskMonitorBase


class RunMonitor(TaskMonitorBase):
    """运行监控器 - 专门处理程序运行过程的监控"""
    
    def __init__(self, logger: logging.Logger, memory_limit_mb: int = 1024, time_limit: float = 1.0, 
                 work_dir: str = None, test_case_name: str = None, language: str = None, output_file: str = None, task_type: str = None):
        # 调用基类构造函数
        from tools import status_constants as sc
        final_task_type = task_type or sc.TASK_TYPE_PLAYER_RUN
        super().__init__(logger, memory_limit_mb, time_limit, work_dir, test_case_name, language, final_task_type, output_file)
        
        # 设置监控器类型
        self.monitor_type = "run"
        
    def _set_task_specific_limits(self):
        """设置运行特定的资源限制"""
        import resource
        
        # 运行模式的安全限制（更严格）
        resource.setrlimit(resource.RLIMIT_FSIZE, (100 * 1024 * 1024, 100 * 1024 * 1024))  # 100MB
        resource.setrlimit(resource.RLIMIT_NPROC, (50, 50))  # 50个进程
        resource.setrlimit(resource.RLIMIT_NOFILE, (128, 128))  # 128个文件
    
    def run_with_monitoring(self, cmd: list, **kwargs) -> Dict[str, Any]:
        """运行程序并监控"""
        from tools import status_constants as sc
        
        try:
            # syscallfree模式：清空dmesg日志
            if is_syscallfree_enabled():
                subprocess.run(["dmesg", "-C"], capture_output=True)
                self.logger.info("运行监控：已清空dmesg日志")
            
            # 准备进程参数
            # 提取额外的 kwargs（排除已处理的参数）
            extra_kwargs = {k: v for k, v in kwargs.items() if k not in ['stdin_file', 'stdout_file', 'stderr_file']}
            
            process_kwargs = {
                'stdin': kwargs.get('stdin_file'),
                'stdout': kwargs.get('stdout_file'),
                'stderr': kwargs.get('stderr_file') or subprocess.DEVNULL,
                'text': True,
                # 明确指定编码参数，使用 errors='replace' 处理非 UTF-8 字节
                'encoding': extra_kwargs.get('encoding', 'utf-8'),
                'errors': extra_kwargs.get('errors', 'replace'),
                **extra_kwargs
            }
            
            # 启动运行进程
            process = self.start_with_process(cmd, **process_kwargs)
            
            if is_debug_enabled():
                self.logger.debug(f"运行进程启动成功，PID: {process.pid}")
            
            # 等待程序完成并捕获输出
            stdout_data = ""
            stderr_data = ""
            
            if process_kwargs.get('stdout') == subprocess.PIPE:
                try:
                    stdout_data, stderr_data = process.communicate()
                    # 确保是字符串类型（处理 None 情况）
                    stdout_data = stdout_data or "" if stdout_data is not None else ""
                    stderr_data = stderr_data or "" if stderr_data is not None else ""
                    # 如果返回的是 bytes（某些边缘情况），手动解码
                    if isinstance(stdout_data, bytes):
                        stdout_data = stdout_data.decode('utf-8', errors='replace')
                    if isinstance(stderr_data, bytes):
                        stderr_data = stderr_data.decode('utf-8', errors='replace')
                except (UnicodeDecodeError, UnicodeError) as e:
                    # 如果解码失败（边缘情况），记录警告并使用空字符串
                    self.logger.warning(f"输出解码失败，使用空字符串: {e}")
                    stdout_data = ""
                    stderr_data = ""
            else:
                process.wait()
                
            
            return_code = process.returncode
            
            # 获取监控数据
            monitoring_data = self._get_monitoring_data()
            memory_used = monitoring_data["memory_used"]
            cpu_time_used = monitoring_data["cpu_time_used"]
            
            if cpu_time_used < 0:
                raise RuntimeError("CPU时间监控失败，无法获取CPU时间数据")
            
            # 分析运行结果
            result = self._analyze_result(return_code, memory_used, cpu_time_used, 
                                        stderr_data=stderr_data, stdout_data=stdout_data)
            
            # syscallfree模式：保存dmesg日志
            if is_syscallfree_enabled():
                self._save_dmesg_log()
            
            return result
            
        except Exception as e:
            self.logger.error(f"运行程序时发生错误: {e}")
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "message": f"运行系统错误: {e}",
                "time": 0,
                "memory": 0
            }
        finally:
            self.cleanup()
    
    def run_program_with_monitoring(self, cmd: list, stdin_file=None, stdout_file=None, stderr_file=None, **kwargs) -> Dict[str, Any]:
        """运行程序并监控 - 兼容性方法"""
        return self.run_with_monitoring(cmd, stdin_file=stdin_file, stdout_file=stdout_file, stderr_file=stderr_file, **kwargs)
    
    def _normalize_resource_values(self, cpu_time_used: float, memory_used: int) -> tuple[int, int]:
        """规范化资源使用值：如果超过限制，返回限制值；否则返回实际值
        
        Args:
            cpu_time_used: CPU时间使用量（毫秒）
            memory_used: 内存使用量（KB）
        
        Returns:
            tuple[int, int]: (规范化后的CPU时间, 规范化后的内存)
        """
        time_limit_ms = int(self.time_limit * 1000)
        memory_limit_kb = self.memory_limit_mb * 1024
        
        normalized_time = min(int(cpu_time_used), time_limit_ms) if cpu_time_used > 0 else 0
        
        normalized_memory = min(memory_used, memory_limit_kb) if memory_used > 0 else 0
        
        return normalized_time, normalized_memory
    
    def _analyze_result(self, return_code: int, memory_used: int, cpu_time_used: float, **kwargs) -> Dict[str, Any]:
        """分析运行结果"""
        
        def process_result_tle(msg_pre, time_limit, cpu_time_used, memory_used):
            time_limit_ms = int(time_limit * 1000)
            final_cpu_time = max(cpu_time_used, time_limit_ms) if cpu_time_used > 0 else time_limit_ms
            
            return {
                "program_status": sc.PROGRAM_TIME_LIMIT_EXCEEDED,
                "message": f"{msg_pre}，时间限制: {self.time_limit}秒",
                "time": time_limit_ms,                  # 杀死程序时间并非严格等于时限，超时时按程序时限返回时间
                "kill_time": int(final_cpu_time),
                "memory": memory_used
            }
        
        from tools import status_constants as sc
        import signal
        
        # 获取 stderr 和 stdout 数据用于分析
        stderr_data = kwargs.get('stderr_data', '')
        stdout_data = kwargs.get('stdout_data', '')

        if is_debug_enabled():
            self.logger.debug(f"=== 运行结果分析 ===")
            self.logger.debug(f"返回码: {return_code}")
            self.logger.debug(f"内存使用: {memory_used} KB")
            self.logger.debug(f"CPU时间: {cpu_time_used} ms")
            self.logger.debug(f"stderr: {stderr_data[:200]}...")
            self.logger.debug(f"stdout: {stdout_data[:200]}...")
        
        # 1. 检查资源超限信号
        if sc.is_resource_limit_signal(return_code):
            limit_type = sc.get_resource_limit_type(return_code)
            
            if limit_type == "cpu_time":
                return process_result_tle("程序CPU时间超限", self.time_limit, cpu_time_used, memory_used)
                
            elif limit_type == "timeout":
                return process_result_tle("程序运行超时", self.time_limit, cpu_time_used, memory_used)
                
            elif limit_type == "memory_or_system":
                memory_limit_kb = self.memory_limit_mb * 1024
                
                if memory_used >= memory_limit_kb:
                    return {
                        "program_status": sc.PROGRAM_MEMORY_LIMIT_EXCEEDED,
                        "message": f"程序内存超限，使用: {memory_used}KB，限制: {self.memory_limit_mb}MB",
                        "time": int(cpu_time_used),
                        "memory": max(memory_used, memory_limit_kb)
                    }
                else:
                    return process_result_tle("程序运行超时", self.time_limit, cpu_time_used, memory_used)
        
        # 2. 检查seccomp错误
        elif return_code == -31 or return_code == 159:
            # 规范化资源值（如果超过限制，使用限制值）
            normalized_time, normalized_memory = self._normalize_resource_values(cpu_time_used, memory_used)
            # 根据任务类型判断是系统错误还是运行时错误
            if self.task_type in [sc.TASK_TYPE_PLAYER_RUN, sc.TASK_TYPE_PLAYER_COMPILE]:
                # 选手程序被seccomp杀死属于运行时错误（恶意行为惩戒）
                return {
                    "program_status": sc.PROGRAM_RUNTIME_ERROR,
                    "message": "系统调用错误",
                    "time": normalized_time,
                    "memory": normalized_memory,
                    "runtime_error": "选手程序使用了不被允许的系统调用",
                    "seccomp_error": True,
                    "error_type": "player_seccomp"
                }
            else:
                # OJ程序（TPJ、编译器等）被seccomp杀死属于系统错误
                return {
                    "program_status": sc.PROGRAM_SYSTEM_ERROR,
                    "message": "TPJ程序使用了不被允许的系统调用",
                    "time": normalized_time,
                    "memory": normalized_memory,
                    "seccomp_error": True,
                    "error_type": "system_seccomp"
                }
        # 3. tpj_run 情况 - 使用结果分析器直接返回完整结果
        elif self.task_type == sc.TASK_TYPE_TPJ_RUN:
            from tools.tpj_result_analyzer import analyze_tpj_result
            tpj_analyze_result = analyze_tpj_result(return_code, stderr_data, cpu_time_used, memory_used)
            if is_debug_enabled():
                self.logger.debug(f"TPJ分析结果: {tpj_analyze_result}")
            return tpj_analyze_result
        
        # 4. 检查CPU时间是否超限（即使程序正常结束，如果CPU时间超过限制，也应判定为超时）
        # 这个检查必须在检查 return_code == 0 之前，确保超时边缘的程序不会被误判为正常完成
        time_limit_ms = int(self.time_limit * 1000)
        if cpu_time_used > 0 and cpu_time_used >= time_limit_ms:
            # CPU 时间超过限制，判定为超时
            return process_result_tle("程序CPU时间超限（正常结束但超时）", self.time_limit, cpu_time_used, memory_used)
        
        # 4.5. 检查内存是否超限（即使程序正常结束，如果内存超过限制，也应判定为MLE）
        # 这个检查必须在检查 return_code == 0 之前，确保内存超限的程序不会被误判为正常完成
        memory_limit_kb = self.memory_limit_mb * 1024
        if memory_used > 0 and memory_used >= memory_limit_kb:
            # 内存超过限制，判定为MLE
            return {
                "program_status": sc.PROGRAM_MEMORY_LIMIT_EXCEEDED,
                "message": f"程序内存超限（正常结束但超限），使用: {memory_used}KB，限制: {self.memory_limit_mb}MB",
                "time": int(cpu_time_used),
                "memory": memory_limit_kb
            }
        
        # 5. 程序正常完成
        elif return_code == 0:
            return {
                "program_status": sc.PROGRAM_COMPLETED,
                "message": "程序正常完成",
                "time": int(cpu_time_used),
                "memory": memory_used
            }
        
        # 6. 系统级错误码（125-128等，必须先检查）
        elif sc.is_system_error(return_code):
            # 规范化资源值（如果超过限制，使用限制值）
            normalized_time, normalized_memory = self._normalize_resource_values(cpu_time_used, memory_used)
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "message": sc.get_system_error_message(return_code),
                "time": normalized_time,
                "memory": normalized_memory
            }
        
        # 7. 程序运行时错误信号（选手程序错误）
        elif sc.is_runtime_error_signal(return_code):
            # 规范化资源值（如果超过限制，使用限制值）
            normalized_time, normalized_memory = self._normalize_resource_values(cpu_time_used, memory_used)
            return {
                "program_status": sc.PROGRAM_RUNTIME_ERROR,
                "message": "运行错误",
                "time": normalized_time,
                "memory": normalized_memory,
                "runtime_error": sc.get_runtime_error_message(return_code),
            }
        
        # 8. 系统级错误信号
        elif sc.is_system_error_signal(return_code):
            # 规范化资源值（如果超过限制，使用限制值）
            normalized_time, normalized_memory = self._normalize_resource_values(cpu_time_used, memory_used)
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "message": sc.get_system_error_message(return_code),
                "time": normalized_time,
                "memory": normalized_memory
            }
        
        # 9. 其他返回码（程序异常退出）
        else:
            # 规范化资源值（如果超过限制，使用限制值）
            normalized_time, normalized_memory = self._normalize_resource_values(cpu_time_used, memory_used)
            msg, detail = self._extract_error_info(return_code, stderr_data, stdout_data)
            return {
                "program_status": sc.PROGRAM_RUNTIME_ERROR,
                "message": msg,
                "time": normalized_time,
                "memory": normalized_memory,
                "runtime_error": detail,
            }
    
    def _extract_error_info(self, return_code: int, stderr_data: str, stdout_data: str) -> tuple[str, str]:
        """提取程序异常退出的错误信息（简洁版）"""
        error_text = (stderr_data or stdout_data or "").strip()
        
        if not error_text:
            return f"程序异常退出（返回码: {return_code}）", f"程序运行时错误，返回码: {return_code}"
        
        # 提取最后一行非空且非调试信息的行作为错误类型
        lines = [l.strip() for l in error_text.split('\n') if l.strip()]
        for line in reversed(lines[-5:]):  # 只看最后5行
            if any(line.startswith(x) for x in ['File ', '  at ', 'Traceback']):
                continue
            if ':' in line:
                err_type = line.split(':')[0].strip()
                if err_type and len(err_type) < 60:
                    return f"程序异常退出：{err_type}", f"程序运行时错误，返回码: {return_code}\n{error_text[:500]}"
        
        # 返回第一行摘要
        summary = lines[0][:80] if lines else f"返回码: {return_code}"
        return f"程序执行失败：{summary}", f"程序运行时错误，返回码: {return_code}\n{error_text[:500]}"
    


def create_run_monitor(logger: logging.Logger, memory_limit_mb: int = 1024, time_limit: float = 1.0, 
                      work_dir: str = None, test_case_name: str = None, language: str = None, out_file: str = None, task_type: str = None) -> RunMonitor:
    """创建运行监控器实例"""
    return RunMonitor(logger, memory_limit_mb, time_limit, work_dir, test_case_name, language, out_file, task_type)
