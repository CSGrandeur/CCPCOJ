#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
编译监控器
专门处理编译过程的监控，包括编译错误信息的获取和处理
"""

import os
import sys
import time
import logging
import subprocess
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


class CompileMonitor(TaskMonitorBase):
    """编译监控器 - 专门处理编译过程的监控"""
    
    def __init__(self, logger: logging.Logger, memory_limit_mb: int = 256, time_limit: float = 30, 
                 work_dir: str = None, test_case_name: str = None, language: str = None, output_file: str = None, task_type: str = None):
        # 调用基类构造函数
        super().__init__(logger, memory_limit_mb, time_limit, work_dir, test_case_name, language, task_type, output_file)
        
        # 设置监控器类型
        self.monitor_type = "compile"
        
    def _set_task_specific_limits(self):
        """设置编译特定的资源限制"""
        import resource
        
        # 编译模式的安全限制（相对宽松）
        resource.setrlimit(resource.RLIMIT_FSIZE, (500 * 1024 * 1024, 500 * 1024 * 1024))  # 500MB
        resource.setrlimit(resource.RLIMIT_NPROC, (100, 100))  # 100个进程
        resource.setrlimit(resource.RLIMIT_NOFILE, (256, 256))  # 256个文件
    
    def run_with_monitoring(self, cmd: list, **kwargs) -> Dict[str, Any]:
        """运行编译命令并监控"""
        from tools import status_constants as sc
        
        try:
            # syscallfree 模式：清空dmesg日志
            if is_syscallfree_enabled():
                subprocess.run(["dmesg", "-C"], capture_output=True)
                self.logger.info("编译监控：已清空dmesg日志")
            
            # 启动编译进程
            process = self.start_with_process(cmd, **kwargs)
            
            if is_debug_enabled():
                self.logger.debug(f"编译进程启动成功，PID: {process.pid}")
            
            # 等待编译完成并获取输出
            stdout_data = ""
            stderr_data = ""
            
            if kwargs.get('stdout') == subprocess.PIPE:
                stdout_data, stderr_data = process.communicate()
            else:
                process.wait()
            
            return_code = process.returncode
            
            # 获取监控数据
            monitoring_data = self._get_monitoring_data()
            memory_used = monitoring_data["memory_used"]
            cpu_time_used = monitoring_data["cpu_time_used"]
            
            # 分析编译结果
            result = self._analyze_result(return_code, memory_used, cpu_time_used, 
                                        stdout_data=stdout_data, stderr_data=stderr_data)
            
            # syscallfree模式：保存dmesg日志
            if is_syscallfree_enabled():
                self._save_dmesg_log()
            
            return result
            
        except Exception as e:
            self.logger.error(f"编译过程发生错误: {e}")
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "message": f"编译系统错误: {e}",
                "time": 0,
                "memory": 0,
                "compile_error": str(e)
            }
        finally:
            self.cleanup()
    
    def run_compile_with_monitoring(self, cmd: list, **kwargs) -> Dict[str, Any]:
        """运行编译命令并监控 - 兼容性方法"""
        return self.run_with_monitoring(cmd, **kwargs)
    
    def _analyze_result(self, return_code: int, memory_used: int, cpu_time_used: float, **kwargs) -> Dict[str, Any]:
        """分析编译结果"""
        from tools import status_constants as sc
        
        stdout_data = kwargs.get('stdout_data', '')
        stderr_data = kwargs.get('stderr_data', '')
        
        if is_debug_enabled():
            self.logger.debug(f"=== 编译结果分析 ===")
            self.logger.debug(f"返回码: {return_code}")
            self.logger.debug(f"内存使用: {memory_used} KB")
            self.logger.debug(f"CPU时间: {cpu_time_used} ms")
            self.logger.debug(f"stdout长度: {len(stdout_data)}")
            self.logger.debug(f"stderr长度: {len(stderr_data)}")
        
        # 1. 检查资源超限
        if sc.is_resource_limit_signal(return_code):
            limit_type = sc.get_resource_limit_type(return_code)
            
            if limit_type == "cpu_time":
                return {
                    "program_status": sc.PROGRAM_TIME_LIMIT_EXCEEDED,
                    "message": f"编译超时，时间限制: {self.time_limit}秒",
                    "time": int(self.time_limit * 1000),
                    "memory": memory_used,
                    "compile_error": "编译超时"
                }
            elif limit_type == "memory_or_system":
                if memory_used >= self.memory_limit_mb * 1024:
                    return {
                        "program_status": sc.PROGRAM_MEMORY_LIMIT_EXCEEDED,
                        "message": f"编译内存超限，使用: {memory_used}KB，限制: {self.memory_limit_mb}MB",
                        "time": int(cpu_time_used),
                        "memory": memory_used,
                        "compile_error": "编译内存超限"
                    }
                else:
                    return {
                        "program_status": sc.PROGRAM_TIME_LIMIT_EXCEEDED,
                        "message": f"编译超时，时间限制: {self.time_limit}秒",
                        "time": int(self.time_limit * 1000),
                        "memory": memory_used,
                        "compile_error": "编译超时"
                    }
        
        # 2. 检查seccomp错误
        elif return_code == -31 or return_code == 159:
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "message": "编译过程使用了不被允许的系统调用",
                "time": int(cpu_time_used),
                "memory": memory_used,
                "compile_error": "系统调用被seccomp阻止"
            }
        
        # 3. 编译成功
        elif return_code == 0:
            return {
                "program_status": sc.PROGRAM_COMPLETED,
                "message": "编译成功",
                "time": int(cpu_time_used),
                "memory": memory_used,
                "compile_output": stdout_data.strip() if stdout_data else ""
            }
        
        # 4. 编译失败（返回码1或其他非零值）
        else:
            # 提取编译错误信息
            compile_error = stderr_data.strip() if stderr_data else stdout_data.strip()
            
            # 清理错误信息
            if self.work_dir in compile_error:
                compile_error = compile_error.replace(self.work_dir + "/", "")
            
            return {
                "program_status": sc.PROGRAM_COMPILE_ERROR,
                "message": "编译失败",
                "time": int(cpu_time_used),
                "memory": memory_used,
                "compile_error": compile_error,
                "return_code": return_code
            }
    


def create_compile_monitor(logger: logging.Logger, memory_limit_mb: int = 256, time_limit: float = 30, 
                          work_dir: str = None, test_case_name: str = None, language: str = None, output_file: str = None, task_type: str = None) -> CompileMonitor:
    """创建编译监控器实例"""
    return CompileMonitor(logger, memory_limit_mb, time_limit, work_dir, test_case_name, language, output_file, task_type)
