#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 C++语言处理器
提供C++语言的编译和运行支持
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


class LanguageCpp(LanguageBase):
    """C++语言处理器"""
    
    def get_language_name(self) -> str:
        """获取编程语言名称"""
        return "C++"
    
    def get_source_extension(self) -> str:
        """获取源文件扩展名"""
        return ".cpp"
    
    def get_executable_extension(self) -> str:
        """获取可执行文件扩展名"""
        return ""
    
    def get_compile_command(self, source_file: str, executable: str) -> List[str]:
        """获取编译命令"""
        # 从后端配置中获取编译选项（新结构：cpp 分组）
        cpp_cfg = self.config.get("cpp", {}) if isinstance(self.config, dict) else {}
        cpp_std = cpp_cfg.get("cpp_std", "-std=c++17")
        cc_opt = cpp_cfg.get("cpp_opt", "-O2")
        
        # 基础编译命令
        # 使用基类方法获取源文件绝对路径
        source_path = self.get_source_path_for_compile(source_file)
        cmd = [
            "g++",
            cpp_std,
            cc_opt,
            "-Wall",
            "-Wextra",
            "-DONLINE_JUDGE",
            "-o", executable,
            source_path
        ]
        
        # 检查是否为 TPJ 编译，如果是则添加 judge_lib 路径
        try:
            from tools.judge_lib_manager import get_compile_flags_for_source, get_chroot_compile_flags
            # 使用源文件的绝对路径进行检测
            source_abs_path = self.get_source_path(source_file)
            tpj_flags = get_compile_flags_for_source(source_abs_path)
            if tpj_flags:
                # 在 chroot 环境中，使用 chroot 路径的编译标志
                chroot_flags = get_chroot_compile_flags()
                # 在 -o 之前插入 TPJ 编译标志
                insert_pos = cmd.index("-o")
                cmd[insert_pos:insert_pos] = chroot_flags
        except ImportError:
            # 如果 judge_lib_manager 不可用，跳过
            pass
        
        return cmd
    
    def get_run_command(self, executable: str, memory_limit: int = None) -> List[str]:
        """获取运行命令"""
        return [f"./{executable}"]
    
    def get_compile_dependency_dirs(self) -> List[str]:
        """获取编译依赖目录列表"""
        return [
            "/usr/bin",
            "/usr/lib",
            "/usr/lib64",
            "/usr/include",
            "/usr/include/c++",
            "/lib",
            "/lib64"
        ]
    
    def get_runtime_dependency_dirs(self) -> List[str]:
        """获取运行依赖目录列表（C++语言运行依赖为空）"""
        return []
    
    
    def preprocess_source(self, source_code: str) -> str:
        """预处理源代码"""
        # C++语言不需要特殊预处理
        return source_code
    
    def check_syntax(self, source_file: str, task_type: str = None) -> Dict[str, Any]:
        """检查C++语言语法"""
        try:
            source_path = self.get_source_path(source_file)
            
            # 从后端配置中获取编译选项（新结构：cpp 分组）
            cpp_cfg = self.config.get("cpp", {}) if isinstance(self.config, dict) else {}
            cpp_std = cpp_cfg.get("cpp_std", "-std=c++17")
            
            # 使用g++进行语法检查
            cmd = [
                "g++",
                cpp_std,
                "-fsyntax-only",
                "-Wall",
                "-Wextra",
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
                    "message": "C++语言语法检查通过"
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
