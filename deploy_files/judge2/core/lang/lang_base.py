#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 编程语言处理基类
提供所有编程语言的公共编译和运行逻辑
"""

from ntpath import basename
import os
import sys
import time
import logging
import subprocess
import tempfile
from typing import Dict, Any, Optional, List
from abc import ABC, abstractmethod

# 将当前目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)

from tools.debug_manager import is_debug_enabled, log_error_with_context
from tools import status_constants as sc
from tools.debug_manager import DebugManager, setup_unified_logging, is_debug_enabled, log_error_with_context


class LanguageBase(ABC):
    """编程语言处理基类"""
    
    def __init__(self, config: Dict[str, Any], work_dir: str = None):
        """初始化语言处理器"""
        self.config = config
        self.work_dir = work_dir or os.getcwd()
        self.logger = setup_unified_logging("Lang", config)
        
        # 编译配置
        self.compile_timeout = 30
        self.compile_memory = 512
        
    @abstractmethod
    def get_language_name(self) -> str:
        """获取编程语言名称"""
        pass
    
    def _normalize_language_name(self) -> str:
        """标准化语言名称，用于与 ok_call.py 中的键匹配"""
        language_name = self.get_language_name()
        # 将语言名称转换为小写，并处理特殊情况
        normalized = language_name.lower()
        if normalized == "c++":
            return "cpp"
        elif normalized == "golang":
            return "go"
        elif normalized == "py":
            return "python"
        else:
            return normalized
    
    
    @abstractmethod
    def get_source_extension(self) -> str:
        """获取源文件扩展名"""
        pass
    
    @abstractmethod
    def get_executable_extension(self) -> str:
        """获取可执行文件扩展名"""
        pass
    
    @abstractmethod
    def get_compile_command(self, source_file: str, executable: str) -> List[str]:
        """获取编译命令"""
        pass
    
    @abstractmethod
    def get_run_command(self, executable: str, memory_limit: int = None) -> List[str]:
        """获取运行命令"""
        pass
    
    @abstractmethod
    def get_compile_dependency_dirs(self) -> List[str]:
        """获取编译依赖目录列表"""
        pass
    
    @abstractmethod
    def get_runtime_dependency_dirs(self) -> List[str]:
        """获取运行依赖目录列表"""
        pass
    
    def get_source_file_name(self) -> str:
        """获取标准源文件名"""
        return f"Main{self.get_source_extension()}"
    
    def get_executable_name(self) -> str:
        """获取标准可执行文件名"""
        return f"Main{self.get_executable_extension()}"
    
    def compile_solution(self, source_file: str, executable: str, task_type: str = None) -> Dict[str, Any]:
        """编译解决方案 - 带安全监管的编译过程"""
        try:
            self.logger.info(f"开始编译 {self.get_language_name()} 程序")
            
            # 检查源文件是否存在
            source_path = self.get_source_path(source_file)
            if not os.path.exists(source_path):
                return {
                    "compile_status": "error",
                    "message": f"源文件不存在: {source_file}"
                }
            
            # 获取编译命令
            compile_cmd = self.get_compile_command(source_file, executable)
            
            # 使用安全监管的编译过程
            self.logger.info(f"编译启动： {compile_cmd}")
            return self._compile_with_security(compile_cmd, executable, source_file, task_type)
                
        except Exception as e:
            log_error_with_context(self.logger, f"编译{self.get_language_name()}程序", e, {
                "source_file": source_file,
                "executable": executable,
                "work_dir": self.work_dir
            })
            return {
                "compile_status": "error",
                "message": f"编译过程发生错误: {str(e)}"
            }
    
    def _compile_with_security(self, compile_cmd: List[str], executable: str, source_file: str, task_type: str = None) -> Dict[str, Any]:
        """带安全监管的编译过程"""
        compile_monitor = None
        try:
            # 创建专门的编译监控器
            from monitor.compile_monitor import create_compile_monitor
            import logging
            
            # 获取语言特定的编译内存限制
            compile_memory = self._get_compile_memory_limit()
            
            compile_logger = logging.getLogger(f"CompileMonitor_{self.get_language_name()}")
            # 确定任务类型（使用传入的 task_type 或默认为 player_compile）
            final_task_type = task_type or sc.TASK_TYPE_PLAYER_COMPILE
            
            # 验证任务类型
            if not sc.is_valid_task_type(final_task_type):
                self.logger.warning(f"无效的任务类型: {final_task_type}，使用默认值: {sc.TASK_TYPE_PLAYER_COMPILE}")
                final_task_type = sc.TASK_TYPE_PLAYER_COMPILE
            
            compile_monitor = create_compile_monitor(
                logger=compile_logger,
                memory_limit_mb=compile_memory,
                time_limit=float(self.compile_timeout),
                work_dir=self.work_dir,
                test_case_name=f"compile_{source_file}",
                language=self._normalize_language_name(),
                task_type=final_task_type
            )
            
            # 执行编译
            start_time = time.time()
            
            # 转换编译命令路径为 chroot 环境下的路径
            chroot_compile_cmd = self._convert_compile_cmd_for_chroot(compile_cmd)
            # 使用编译监控器运行编译命令
            result = compile_monitor.run_compile_with_monitoring(
                cmd=chroot_compile_cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                cwd=self.work_dir
            )
            
            end_time = time.time()
            compile_time = int((end_time - start_time) * 1000)  # 毫秒
            
            # 详细的debug输出
            if is_debug_enabled():
                self.logger.debug(f"=== 编译结果分析 ===")
                self.logger.debug(f"编译命令: {' '.join(compile_cmd)}")
                self.logger.debug(f"工作目录: {self.work_dir}")
                self.logger.debug(f"编译时间: {compile_time} ms")
                self.logger.debug(f"编译结果: {result}")
                if "return_code" in result:
                    self.logger.debug(f"返回码: {result['return_code']}")
                if "message" in result:
                    self.logger.debug(f"消息: {result['message']}")
            
            # 分析编译结果
            return self._analyze_compile_result(result, executable, source_file, compile_time)
                
        except Exception as e:
            self.logger.error(f"安全编译过程失败: {e}")
            raise RuntimeError(f"安全编译过程失败: {e}")
        finally:
            # 显式清理编译监控器资源（双重保险，虽然 run_compile_with_monitoring 内部会清理）
            if compile_monitor:
                try:
                    compile_monitor.cleanup()
                except Exception as cleanup_error:
                    if is_debug_enabled():
                        self.logger.debug(f"清理编译监控器时发生错误: {cleanup_error}")
    
    def check_syntax(self, source_file: str, task_type: str = None) -> Dict[str, Any]:
        """检查语法（默认实现，子类可重写）"""
        # 对于大多数语言，编译过程就包含了语法检查
        return self.compile_solution(source_file, "syntax_check", task_type)
    
    def _get_compile_memory_limit(self) -> int:
        """获取编译所需的内存限制（子类可重写以添加语言特定的限制）"""
        return self.compile_memory
    
    def _convert_compile_cmd_for_chroot(self, compile_cmd: list) -> list:
        """转换编译命令中的路径为 chroot 环境下的路径"""
        if not compile_cmd:
            return compile_cmd
            
        converted_cmd = []
        for arg in compile_cmd:
            # 转换看起来像文件路径的参数
            if ('/' in arg or arg.endswith(('.cc', '.cpp', '.c', '.py', '.java', '.go'))):
                # 如果是绝对路径，转换为相对于工作目录的路径
                if os.path.isabs(arg):
                    try:
                        rel_path = os.path.relpath(arg, self.work_dir)
                        if not rel_path.startswith('..'):
                            converted_cmd.append(f"./{rel_path}")
                        else:
                            converted_cmd.append(f"./{os.path.basename(arg)}")
                    except ValueError:
                        converted_cmd.append(f"./{os.path.basename(arg)}")
                else:
                    converted_cmd.append(arg)
            else:
                # 不转换其他参数（如命令行选项、数字等）
                converted_cmd.append(arg)
        
        if is_debug_enabled():
            self.logger.debug(f"编译路径转换: {' '.join(compile_cmd)} -> {' '.join(converted_cmd)}")
        
        return converted_cmd
    
    def get_source_path(self, source_file: str) -> str:
        """获取源文件的绝对路径"""
        return os.path.join(self.work_dir, source_file)
    
    def get_source_path_for_compile(self, source_file: str) -> str:
        """实际编译状态的source_file路径，比如chroot之后"""
        return basename(source_file)
    
    def _check_compilation_success(self, executable: str, compile_time: int) -> bool:
        """检查编译是否成功（子类可重写以添加语言特定的检查逻辑）"""
        executable_path = os.path.join(self.work_dir, executable)
        if os.path.exists(executable_path):
            # 设置执行权限（关键修复：确保可执行文件可以被执行）
            try:
                os.chmod(executable_path, 0o755)
                if is_debug_enabled():
                    self.logger.debug(f"已设置可执行文件权限: {executable_path}")
            except Exception as e:
                self.logger.warning(f"设置可执行文件权限失败: {e}")
            
            self.logger.info(f"{self.get_language_name()} 编译成功，耗时: {compile_time}ms")
            return True
        return False
    
    def _analyze_compile_result(self, result: Dict[str, Any], executable: str, source_file: str, compile_time: int) -> Dict[str, Any]:
        """分析编译结果（子类可重写以添加语言特定的分析逻辑）"""
        if result["program_status"] == sc.PROGRAM_COMPLETED:
            # 编译成功，检查可执行文件是否生成
            if self._check_compilation_success(executable, compile_time):
                return {
                    "compile_status": "success",
                    "message": f"编译成功，耗时: {compile_time}ms",
                    "compile_time": compile_time
                }
            else:
                return {
                    "compile_status": "error",
                    "message": "编译成功但未生成可执行文件"
                }
        elif result["program_status"] == sc.PROGRAM_TIME_LIMIT_EXCEEDED:
            return {
                "compile_status": "error",
                "message": f"编译超时（{self.compile_timeout}秒）"
            }
        elif result["program_status"] == sc.PROGRAM_MEMORY_LIMIT_EXCEEDED:
            return {
                "compile_status": "error",
                "message": f"编译内存超限（{self.compile_memory}MB）"
            }
        elif result["program_status"] == sc.PROGRAM_COMPILE_ERROR:
            # 编译错误（语法错误、链接错误等）
            compile_error = result.get("compile_error", "编译失败")
            
            # 清理错误信息，移除文件路径前缀
            source_path = os.path.join(self.work_dir, source_file)
            if source_path in compile_error:
                compile_error = compile_error.replace(source_path, source_file)
            
            # 移除工作目录前缀
            if self.work_dir in compile_error:
                compile_error = compile_error.replace(self.work_dir + "/", "")
            
            if is_debug_enabled():
                self.logger.debug(f"{self.get_language_name()} 编译失败: {compile_error}")
            else:
                self.logger.info(f"{self.get_language_name()} 编译失败")
            
            return {
                "compile_status": "error",
                "message": compile_error,
                "compile_time": compile_time
            }
        elif result["program_status"] == sc.PROGRAM_SYSTEM_ERROR:
            # 系统错误（seccomp等）
            error_message = result.get('message', '编译系统错误')
            return {
                "compile_status": "error",
                "message": error_message,
                "compile_time": compile_time
            }
        else:
            # 其他错误
            error_msg = result.get("message", "编译失败")
            return {
                "compile_status": "error",
                "message": error_msg,
                "compile_time": compile_time
            }
    
    def get_runtime_limits(self, time_limit: float, memory_limit: int) -> Dict[str, Any]:
        """获取运行时限制（子类可重写以添加语言特定的限制）"""
        return {
            "time_limit": time_limit,
            "memory_limit": memory_limit,
            "stack_limit": memory_limit
        }
    
    def preprocess_source(self, source_code: str) -> str:
        """预处理源代码（子类可重写）"""
        return source_code
    
    def postprocess_output(self, output: str) -> str:
        """后处理输出（子类可重写）"""
        return output
    
    def validate_source_file(self, source_file: str) -> Dict[str, Any]:
        """验证源文件"""
        try:
            source_path = os.path.join(self.work_dir, source_file)
            
            # 检查文件是否存在
            if not os.path.exists(source_path):
                return {
                    "valid": False,
                    "message": f"源文件不存在: {source_file}"
                }
            
            # 检查文件大小
            file_size = os.path.getsize(source_path)
            max_size = 1024 * 1024  # 1MB
            if file_size > max_size:
                return {
                    "valid": False,
                    "message": f"源文件过大: {file_size} bytes (最大: {max_size} bytes)"
                }
            
            # 检查文件扩展名
            _, ext = os.path.splitext(source_file)
            if ext != self.get_source_extension():
                return {
                    "valid": False,
                    "message": f"文件扩展名不匹配: 期望 {self.get_source_extension()}, 实际 {ext}"
                }
            
            return {
                "valid": True,
                "message": "源文件验证通过",
                "file_size": file_size
            }
            
        except Exception as e:
            return {
                "valid": False,
                "message": f"验证源文件时发生错误: {str(e)}"
            }
