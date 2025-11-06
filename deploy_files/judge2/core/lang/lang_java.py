#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 Java语言处理器
提供Java语言的编译和运行支持
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


class LanguageJava(LanguageBase):
    """Java语言处理器"""
    
    def get_language_name(self) -> str:
        """获取编程语言名称"""
        return "Java"
    
    def get_source_extension(self) -> str:
        """获取源文件扩展名"""
        return ".java"
    
    def get_executable_extension(self) -> str:
        """获取可执行文件扩展名"""
        return ".class"
    
    def get_executable_name(self) -> str:
        """获取标准可执行文件名（Java需要类名，不包含.class扩展名）"""
        return "Main"  # Java运行需要类名，不是文件名
    
    def _check_compilation_success(self, executable: str, compile_time: int) -> bool:
        """检查Java编译是否成功（检查.class文件是否存在）"""
        # Java编译后生成的是 .class 文件，不是可执行文件名
        class_file_path = os.path.join(self.work_dir, f"{executable}.class")
        if os.path.exists(class_file_path):
            self.logger.info(f"{self.get_language_name()} 编译成功，耗时: {compile_time}ms")
            return True
        return False
    
    def get_compile_command(self, source_file: str, executable: str) -> List[str]:
        """获取编译命令"""
        # 从配置中获取Java编译参数
        java_config = self.config.get("java", {})
        
        # 获取编译内存限制
        compile_memory = self._get_compile_memory_limit()
        
        # 从配置中获取Java内存参数，参考get_run_command的实现方式
        xms = max(java_config.get("xms", 1024), compile_memory)
        xmx = max(java_config.get("xmx", 1024), compile_memory)
        xss = java_config.get("xss", 64)
                
        # 构建编译命令，参考开源评测机的参数设置
        # 使用基类方法获取源文件绝对路径
        source_path = self.get_source_path_for_compile(source_file)
        return [
            "javac",
            f"-J-Xms{xms}M",
            f"-J-Xmx{xmx}M",
            f"-J-Xss{xss}M",
            "-encoding", "UTF-8",
            source_path
        ]
    
    def get_run_command(self, executable: str, memory_limit: int = None) -> List[str]:
        """获取运行命令"""
        # Java运行命令需要指定类名（去掉.class扩展名）
        class_name = os.path.splitext(executable)[0]
        
        # 从配置中获取Java参数
        java_config = self.config.get("java", {})
        # 运行期的额外内存，来自新后端配置（MB）
        memory_bonus = java_config.get("memory_bonus", 512)
                
        # 计算Java实际使用的内存：task memory_limit + memory_bonus
        if memory_limit is None:
            memory_limit = 512
        java_memory_limit = memory_limit + memory_bonus
        
        # 从配置中获取Java内存参数，如果没有则使用计算值
        xms = max(java_config.get("xms", 1024), java_memory_limit)
        xmx = max(java_config.get("xmx", 1024), java_memory_limit)
        xss = java_config.get("xss", 64)
        
        
        return [
            "java",
            "-Dfile.encoding=UTF-8",
            "-XX:+UseSerialGC",
            f"-Xss{xss}M",
            f"-Xms{xms}M",
            f"-Xmx{xmx}M",
            "-cp", ".",
            class_name
        ]
    
    def _get_compile_memory_limit(self) -> int:
        """获取Java编译所需的内存限制"""
        java_config = self.config.get("java", {})
        return java_config.get("xmx", 1024)
    
    def get_runtime_limits(self, time_limit: int, memory_limit: int) -> Dict[str, Any]:
        """获取运行时限制（按新配置：先乘后加，时间加值单位ms）"""
        java_config = self.config.get("java", {})
        time_bonus_multiply = java_config.get("time_bonus_multiply", 1)
        time_bonus_plus_ms = java_config.get("time_bonus_plus", 0)
        memory_bonus = java_config.get("memory_bonus", 512)

        # 输入 time_limit 为秒，这里应用：先乘后加（毫秒转秒）
        adj_time_limit = float(time_limit) * float(time_bonus_multiply)
        adj_time_limit += float(time_bonus_plus_ms) / 1000.0
        adj_memory_limit = int(memory_limit) + int(memory_bonus)
        
        return {
            "time_limit": adj_time_limit,
            "memory_limit": adj_memory_limit,
            "stack_limit": adj_memory_limit,
        }
    
    def get_compile_dependency_dirs(self) -> List[str]:
        """获取编译依赖目录列表"""
        return [
            "/usr/bin",
            "/usr/lib/jvm",
            "/usr/share/java",
            "/usr/lib/java"
        ]
    
    def get_runtime_dependency_dirs(self) -> List[str]:
        """获取运行依赖目录列表"""
        return [
            "/usr/bin",
            "/usr/lib/jvm",
            "/usr/share/java",
            "/usr/lib/java"
        ]
    
    def check_syntax(self, source_file: str, task_type: str = None) -> Dict[str, Any]:
        """检查Java语言语法"""
        try:
            source_path = os.path.join(self.work_dir, source_file)
            
            # 使用javac进行语法检查
            cmd = [
                "javac",
                "-encoding", "UTF-8",
                "-cp", ".",
                "-Xlint",
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
                    "message": "Java语言语法检查通过"
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
        """验证Java源文件"""
        # 先调用基类的验证
        result = super().validate_source_file(source_file)
        if not result["valid"]:
            return result
        
        # Java特定的验证
        try:
            source_path = os.path.join(self.work_dir, source_file)
            with open(source_path, 'r', encoding='utf-8') as f:
                content = f.read()
            
            # 检查是否包含Main类
            if "class Main" not in content and "public class Main" not in content:
                return {
                    "valid": False,
                    "message": "Java源文件必须包含Main类"
                }
            
            # 检查是否包含main方法
            if "public static void main" not in content:
                return {
                    "valid": False,
                    "message": "Java源文件必须包含main方法"
                }
            
            return {
                "valid": True,
                "message": "Java源文件验证通过",
                "file_size": result["file_size"]
            }
            
        except Exception as e:
            return {
                "valid": False,
                "message": f"验证Java源文件时发生错误: {str(e)}"
            }
