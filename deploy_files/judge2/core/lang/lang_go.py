#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 Go语言处理器
提供Go语言的编译和运行支持
"""

import os
import sys
import subprocess
from typing import Dict, Any, List

# 将当前目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)

from lang_base import LanguageBase


class LanguageGo(LanguageBase):
    """Go语言处理器"""
    
    def get_language_name(self) -> str:
        """获取编程语言名称"""
        return "Go"
    
    def get_source_extension(self) -> str:
        """获取源文件扩展名"""
        return ".go"
    
    def get_executable_extension(self) -> str:
        """获取可执行文件扩展名"""
        return ""
    
    def get_compile_command(self, source_file: str, executable: str) -> List[str]:
        """获取编译命令"""
        # 使用基类方法获取源文件绝对路径
        source_path = self.get_source_path_for_compile(source_file)
        return [
            "go", "build",
            "-o", executable,
            source_path
        ]
    
    def get_run_command(self, executable: str, memory_limit: int = None) -> List[str]:
        """获取运行命令"""
        return [f"./{executable}"]
    
    def get_runtime_limits(self, time_limit: int, memory_limit: int) -> Dict[str, Any]:
        """获取运行时限制"""
        return {
            "time_limit": time_limit,
            "memory_limit": memory_limit,
            "stack_limit": memory_limit * 1024 * 1024  # 栈限制等于内存限制
        }
    
    def get_compile_dependency_dirs(self) -> List[str]:
        """获取编译依赖目录列表"""
        return [
            "/usr/bin",
            "/usr/lib/go",
            "/usr/local/go"
        ]
    
    def get_runtime_dependency_dirs(self) -> List[str]:
        """获取运行依赖目录列表（Go语言运行依赖为空）"""
        return []
    
    def preprocess_source(self, source_code: str) -> str:
        """预处理源代码"""
        # 确保Go代码有正确的包名和main函数
        if "package main" not in source_code:
            source_code = "package main\n\n" + source_code
        
        if "func main()" not in source_code:
            # 如果没有main函数，添加一个空的main函数
            source_code += "\n\nfunc main() {\n\t// TODO: 实现main函数\n}"
        
        return source_code
    
    def check_syntax(self, source_file: str, task_type: str = None) -> Dict[str, Any]:
        """检查Go语言语法"""
        try:
            source_path = os.path.join(self.work_dir, source_file)
            
            # 使用go build进行语法检查
            cmd = [
                "go", "build",
                "-o", "/dev/null",  # 输出到/dev/null，只检查语法
                source_path
            ]
            
            process = subprocess.Popen(
                cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                cwd=self.work_dir
            )
            
            stdout, stderr = process.communicate(timeout=10)
            return_code = process.returncode
            
            if return_code == 0:
                return {
                    "compile_status": "success",
                    "message": "Go语言语法检查通过"
                }
            else:
                # 提取错误信息
                error_msg = stderr.strip() if stderr else "语法检查失败"
                
                # 清理错误信息
                if source_path in error_msg:
                    error_msg = error_msg.replace(source_path, source_file)
                
                return {
                    "compile_status": "error",
                    "message": error_msg
                }
                
        except Exception as e:
            return {
                "compile_status": "error",
                "message": f"语法检查时发生错误: {str(e)}"
            }
    
    def validate_source_file(self, source_file: str) -> Dict[str, Any]:
        """验证Go源文件"""
        # 先调用基类的验证
        result = super().validate_source_file(source_file)
        if not result["valid"]:
            return result
        
        # Go特定的验证
        try:
            source_path = os.path.join(self.work_dir, source_file)
            with open(source_path, 'r', encoding='utf-8') as f:
                content = f.read()
            
            # 检查是否包含package main
            if "package main" not in content:
                return {
                    "valid": False,
                    "message": "Go源文件必须包含 'package main'"
                }
            
            # 检查是否包含main函数
            if "func main()" not in content:
                return {
                    "valid": False,
                    "message": "Go源文件必须包含main函数"
                }
            
            return {
                "valid": True,
                "message": "Go源文件验证通过",
                "file_size": result["file_size"]
            }
            
        except Exception as e:
            return {
                "valid": False,
                "message": f"验证Go源文件时发生错误: {str(e)}"
            }
