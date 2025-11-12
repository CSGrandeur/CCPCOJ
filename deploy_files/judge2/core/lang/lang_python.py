#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 Python语言处理器
提供Python语言的编译和运行支持
"""

import os
import sys
import time
import tempfile
import subprocess
from typing import Dict, Any, List

# 将当前目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)

from lang_base import LanguageBase
from tools.debug_manager import is_debug_enabled
from tools import status_constants as sc


class LanguagePython(LanguageBase):
    """Python语言处理器"""
    
    def get_language_name(self) -> str:
        """获取编程语言名称"""
        return "Python"
    
    def get_source_extension(self) -> str:
        """获取源文件扩展名"""
        return ".py"
    
    def get_executable_extension(self) -> str:
        """获取可执行文件扩展名"""
        return ".py"
    
    def _check_compilation_success(self, executable: str, compile_time: int) -> bool:
        """检查Python语法检查是否成功（Python不需要传统编译）"""
        # Python语法检查通过即可，不需要检查可执行文件
        self.logger.info(f"{self.get_language_name()} 语法检查成功，耗时: {compile_time}ms")
        return True
    
    def _compile_with_security(self, compile_cmd: List[str], executable: str, source_file: str, task_type: str = None) -> Dict[str, Any]:
        """Python语法检查不需要开沙盒，直接执行"""
        try:
            # Python语法检查相对安全，直接执行，不需要复杂的监控
            start_time = time.time()
            
            # 执行语法检查
            process = subprocess.Popen(
                compile_cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                cwd=self.work_dir
            )
            
            try:
                stdout, stderr = process.communicate(timeout=self.compile_timeout)
                return_code = process.returncode
                end_time = time.time()
                
                compile_time = int((end_time - start_time) * 1000)  # 毫秒
                
                # 模拟监控结果格式，复用基类的分析逻辑
                mock_result = {
                    "program_status": sc.PROGRAM_COMPLETED if return_code == 0 else sc.PROGRAM_COMPILE_ERROR,
                    "return_code": return_code,
                    "message": stderr.strip() if stderr else ("语法检查通过" if return_code == 0 else "语法检查失败"),
                    "compile_error": stderr.strip() if stderr and return_code != 0 else None
                }
                
                # 详细的debug输出
                if is_debug_enabled():
                    self.logger.debug(f"=== Python语法检查结果分析 ===")
                    self.logger.debug(f"语法检查命令: {' '.join(compile_cmd)}")
                    self.logger.debug(f"工作目录: {self.work_dir}")
                    self.logger.debug(f"语法检查时间: {compile_time} ms")
                    self.logger.debug(f"返回码: {return_code}")
                    if stderr:
                        self.logger.debug(f"stderr: {stderr}")
                
                # 复用基类的分析逻辑
                return self._analyze_compile_result(mock_result, executable, source_file, compile_time)
                    
            except subprocess.TimeoutExpired:
                process.kill()
                return {
                    "compile_status": "error",
                    "message": f"语法检查超时（{self.compile_timeout}秒）"
                }
                
        except Exception as e:
            self.logger.error(f"Python语法检查过程失败: {e}")
            raise RuntimeError(f"Python语法检查过程失败: {e}")
    
    def _analyze_compile_result(self, result: Dict[str, Any], executable: str, source_file: str, compile_time: int) -> Dict[str, Any]:
        """分析编译结果（Python特定版本）"""
        # 先调用基类的分析逻辑
        base_result = super()._analyze_compile_result(result, executable, source_file, compile_time)
        
        # Python特定的处理：如果语法检查通过，预编译字节码
        if base_result["compile_status"] == "success":
            source_path = os.path.join(self.work_dir, source_file)
            self._precompile_python_bytecode(source_path)
            # 更新消息为"语法检查通过"
            base_result["message"] = f"语法检查通过，耗时: {compile_time}ms"
        
        return base_result
    
    def get_compile_command(self, source_file: str, executable: str) -> List[str]:
        """获取编译命令（Python不需要传统编译）"""
        # Python使用解释执行，这里返回语法检查命令
        # 使用基类方法获取源文件绝对路径
        source_path = self.get_source_path_for_compile(source_file)
        return [
            "python3", "-m", "py_compile",
            source_path
        ]
    
    def get_run_command(self, executable: str, memory_limit: int = None) -> List[str]:
        """获取运行命令"""
        return ["python3", executable]
    
    def get_runtime_limits(self, time_limit: int, memory_limit: int) -> Dict[str, Any]:
        """获取运行时限制（按新配置：先乘后加，时间加值单位ms）"""
        py_cfg = self.config.get("python", {}) if isinstance(self.config, dict) else {}
        time_bonus_multiply = py_cfg.get("time_bonus_multiply", 1)
        time_bonus_plus_ms = py_cfg.get("time_bonus_plus", 0)
        memory_bonus = py_cfg.get("memory_bonus", 512)

        adj_time_limit = float(time_limit) * float(time_bonus_multiply)
        adj_time_limit += float(time_bonus_plus_ms) / 1000.0
        adj_memory_limit = int(memory_limit) + int(memory_bonus)

        return {
            "time_limit": adj_time_limit,
            "memory_limit": adj_memory_limit
        }
    
    def get_compile_dependency_dirs(self) -> List[str]:
        """获取编译依赖目录列表"""
        return [
            "/usr/bin",
            "/usr/lib/python3*",
            "/usr/local/lib/python3*"
        ]
    
    def get_runtime_dependency_dirs(self) -> List[str]:
        """获取运行依赖目录列表"""
        # Python 运行时需要 python3 可执行文件和 Python 库
        # 注意：使用 glob 模式匹配不同版本的 Python 目录
        return [
            "/bin",  # 某些系统中 python3 可能在 /bin 中
            "/usr/bin",  # python3 可执行文件（标准位置）
            "/usr/lib/python3*",  # Python 标准库（通过 glob 展开）
            "/usr/local/lib/python3*"  # 本地安装的 Python 库（通过 glob 展开）
        ]
    
    def preprocess_source(self, source_code: str) -> str:
        """预处理源代码"""
        # Python不需要特殊预处理
        return source_code
    
    def _precompile_python_bytecode(self, source_path: str):
        """预编译Python字节码以提高执行性能"""
        try:
            # 使用compileall模块预编译字节码
            cmd = [
                "python3", "-m", "compileall",
                "-b",  # 生成.pyc文件
                source_path
            ]
            
            process = subprocess.Popen(
                cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                cwd=self.work_dir
            )
            
            process.communicate(timeout=5)  # 短超时，因为这只是优化
            
            if self.logger.isEnabledFor(20):  # DEBUG level
                self.logger.debug("Python字节码预编译完成")
                
        except Exception as e:
            # 预编译失败不影响主要功能
            if self.logger.isEnabledFor(20):  # DEBUG level
                self.logger.debug(f"Python字节码预编译失败: {e}")
    
    def check_syntax(self, source_file: str, task_type: str = None) -> Dict[str, Any]:
        """检查Python语言语法"""
        try:
            source_path = os.path.join(self.work_dir, source_file)
            
            # 使用python3 -m py_compile进行语法检查
            # 这是Python官方推荐的语法检查方式
            cmd = ["python3", "-m", "py_compile", source_path]
            
            if self.logger.isEnabledFor(20):  # DEBUG level
                self.logger.debug(f"Python语法检查命令: {' '.join(cmd)}")
            
            process = subprocess.Popen(
                cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                cwd=self.work_dir
            )
            
            try:
                stdout, stderr = process.communicate(timeout=10)
                return_code = process.returncode
                
                if return_code == 0:
                    # 语法检查通过，预编译字节码以提高执行性能
                    self._precompile_python_bytecode(source_path)
                    return {
                        "compile_status": "success",
                        "message": "Python syntax check passed and bytecode precompiled"
                    }
                else:
                    # 语法检查失败，提取错误信息
                    error_msg = stderr.strip() if stderr else "Python syntax check failed"
                    
                    # 清理错误信息，移除文件路径前缀
                    if source_path in error_msg:
                        error_msg = error_msg.replace(source_path, source_file)
                    
                    # 移除工作目录前缀
                    if self.work_dir in error_msg:
                        error_msg = error_msg.replace(self.work_dir + "/", "")
                    
                    return {
                        "compile_status": "error",
                        "message": error_msg
                    }
                    
            except subprocess.TimeoutExpired:
                process.kill()
                return {
                    "compile_status": "error",
                    "message": "Python syntax check timeout"
                }
                
        except Exception as e:
            from tools.debug_manager import log_error_with_context
            log_error_with_context(self.logger, f"检查{self.get_language_name()}程序语法", e, {
                "source_file": source_file,
                "work_dir": self.work_dir
            })
            return {
                "compile_status": "error",
                "message": f"Python syntax check error: {str(e)}"
            }
    
    def validate_source_file(self, source_file: str) -> Dict[str, Any]:
        """验证Python源文件"""
        # 先调用基类的验证
        result = super().validate_source_file(source_file)
        if not result["valid"]:
            return result
        
        # Python特定的验证
        try:
            source_path = os.path.join(self.work_dir, source_file)
            with open(source_path, 'r', encoding='utf-8') as f:
                content = f.read()
            
            # 检查是否有语法错误（基本检查）
            try:
                compile(content, source_file, 'exec')
            except SyntaxError as e:
                return {
                    "valid": False,
                    "message": f"Python语法错误: {e.msg} (行 {e.lineno})"
                }
            except Exception as e:
                return {
                    "valid": False,
                    "message": f"Python代码验证失败: {str(e)}"
                }
            
            return {
                "valid": True,
                "message": "Python源文件验证通过",
                "file_size": result["file_size"]
            }
            
        except Exception as e:
            return {
                "valid": False,
                "message": f"验证Python源文件时发生错误: {str(e)}"
            }
