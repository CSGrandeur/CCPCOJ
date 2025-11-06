#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Judge Lib 管理器
管理 testlib.h 等评测库的路径和编译标志
"""

import os
import sys
from typing import List, Optional, Dict, Any

# 将当前目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)


class JudgeLibManager:
    """Judge Lib 管理器 - 单例模式"""
    
    _instance = None
    _initialized = False
    
    def __new__(cls):
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance
    
    def __init__(self):
        if self._initialized:
            return
        
        # 获取 judge_lib 目录的绝对路径
        self.judge_lib_dir = self._get_judge_lib_dir()
        self.testlib_h_path = os.path.join(self.judge_lib_dir, "testlib.h")
        
        # 验证 judge_lib 目录和文件
        self._validate_judge_lib()
        
        self._initialized = True
    
    def _get_judge_lib_dir(self) -> str:
        """获取 judge_lib 目录的绝对路径"""
        # 从当前文件位置计算 judge_lib 目录
        # core/tools/judge_lib_manager.py -> ../../judge_lib/
        current_file = os.path.abspath(__file__)
        core_dir = os.path.dirname(os.path.dirname(current_file))  # core/
        deploy_dir = os.path.dirname(core_dir)  # deploy_files/judge2/
        judge_lib_dir = os.path.join(deploy_dir, "judge_lib")
        
        return os.path.abspath(judge_lib_dir)
    
    def _validate_judge_lib(self):
        """验证 judge_lib 目录和文件是否存在"""
        if not os.path.exists(self.judge_lib_dir):
            raise RuntimeError(f"judge_lib 目录不存在: {self.judge_lib_dir}")
        
        if not os.path.exists(self.testlib_h_path):
            raise RuntimeError(f"testlib.h 文件不存在: {self.testlib_h_path}")
    
    def get_judge_lib_dir(self) -> str:
        """获取 judge_lib 目录路径"""
        return self.judge_lib_dir
    
    def get_testlib_h_path(self) -> str:
        """获取 testlib.h 文件路径"""
        return self.testlib_h_path
    
    def get_compile_flags_for_tpj(self) -> List[str]:
        """获取 TPJ 编译所需的编译标志"""
        return [
            f"-I{self.judge_lib_dir}",  # 添加头文件搜索路径
        ]
    
    def get_compile_flags_for_source(self, source_file: str) -> Optional[List[str]]:
        """根据源文件判断是否需要添加 judge_lib 编译标志"""
        try:
            # 检查源文件是否包含 testlib.h
            source_path = os.path.abspath(source_file)
            if not os.path.exists(source_path):
                return None
            
            with open(source_path, 'r', encoding='utf-8') as f:
                content = f.read()
                
            # 检查是否包含 testlib.h 相关的包含语句
            testlib_includes = [
                '#include "testlib.h"',
                '#include <testlib.h>',
                '#include "judge_lib/testlib.h"',
                '#include <judge_lib/testlib.h>'
            ]
            
            for include in testlib_includes:
                if include in content:
                    return self.get_compile_flags_for_tpj()
            
            return None
            
        except Exception:
            # 如果读取文件失败，返回 None
            return None
    
    def get_mount_path_for_chroot(self) -> str:
        """获取在 chroot 环境中的挂载路径"""
        return "/judge_lib"
    
    def get_chroot_judge_lib_path(self) -> str:
        """获取在 chroot 环境中的 judge_lib 路径"""
        return self.get_mount_path_for_chroot()
    
    def get_chroot_compile_flags(self) -> List[str]:
        """获取在 chroot 环境中的编译标志"""
        return [
            f"-I{self.get_chroot_judge_lib_path()}",  # 使用 chroot 内的路径
        ]
    
    def is_tpj_source(self, source_file: str) -> bool:
        """判断源文件是否为 TPJ 源文件"""
        return self.get_compile_flags_for_source(source_file) is not None
    
    def get_judge_lib_info(self) -> Dict[str, Any]:
        """获取 judge_lib 信息"""
        return {
            "judge_lib_dir": self.judge_lib_dir,
            "testlib_h_path": self.testlib_h_path,
            "judge_lib_exists": os.path.exists(self.judge_lib_dir),
            "testlib_h_exists": os.path.exists(self.testlib_h_path),
            "chroot_mount_path": self.get_mount_path_for_chroot(),
            "chroot_judge_lib_path": self.get_chroot_judge_lib_path()
        }


# 全局实例
_judge_lib_manager = JudgeLibManager()


def get_judge_lib_manager() -> JudgeLibManager:
    """获取 JudgeLibManager 实例"""
    return _judge_lib_manager


def get_compile_flags_for_source(source_file: str) -> Optional[List[str]]:
    """便捷函数：获取源文件的编译标志"""
    return _judge_lib_manager.get_compile_flags_for_source(source_file)


def get_compile_flags_for_tpj() -> List[str]:
    """便捷函数：获取 TPJ 编译标志"""
    return _judge_lib_manager.get_compile_flags_for_tpj()


def get_judge_lib_dir() -> str:
    """便捷函数：获取 judge_lib 目录路径"""
    return _judge_lib_manager.get_judge_lib_dir()


def get_chroot_compile_flags() -> List[str]:
    """便捷函数：获取 chroot 环境中的编译标志"""
    return _judge_lib_manager.get_chroot_compile_flags()


def is_tpj_source(source_file: str) -> bool:
    """便捷函数：判断是否为 TPJ 源文件"""
    return _judge_lib_manager.is_tpj_source(source_file)


if __name__ == "__main__":
    # 测试代码
    manager = get_judge_lib_manager()
    info = manager.get_judge_lib_info()
    
    print("=== Judge Lib Manager 测试 ===")
    print(f"Judge Lib 目录: {info['judge_lib_dir']}")
    print(f"testlib.h 路径: {info['testlib_h_path']}")
    print(f"目录存在: {info['judge_lib_exists']}")
    print(f"testlib.h 存在: {info['testlib_h_exists']}")
    print(f"Chroot 挂载路径: {info['chroot_mount_path']}")
    print(f"Chroot 内路径: {info['chroot_judge_lib_path']}")
    
    print("\n=== 编译标志测试 ===")
    print(f"TPJ 编译标志: {get_compile_flags_for_tpj()}")
    print(f"Chroot 编译标志: {get_chroot_compile_flags()}")