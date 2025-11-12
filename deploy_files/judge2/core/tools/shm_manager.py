#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
共享内存管理器
智能选择工作目录：优先使用 /dev/shm，回退到普通目录
"""

import os
import sys
import shutil
import logging
import subprocess
from typing import Tuple, Optional, Dict, Any

# 将当前目录和父目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)

from tools.debug_manager import is_debug_enabled, setup_unified_logging


class ShmManager:
    """共享内存管理器"""
    
    def __init__(self, logger: logging.Logger = None):
        self.logger = logger or setup_unified_logging("ShmManager")
        self.shm_base_dir = "/dev/shm/csgoj"
        self.fallback_base_dir = None  # 将在首次使用时设置
        self.shm_available = False
        self.shm_size_mb = 0
        self.min_shm_size_mb = 900  # 最小共享内存大小要求
        
        # 初始化时检查共享内存状态
        self._check_shm_availability()
    
    def _check_shm_availability(self):
        """检查共享内存可用性和大小"""
        try:
            # 检查 /dev/shm 是否存在且可写
            if not os.path.exists("/dev/shm"):
                self.logger.warning("/dev/shm 不存在")
                return
            
            # 检查 /dev/shm 是否可写
            test_file = "/dev/shm/.shm_test"
            try:
                with open(test_file, 'w') as f:
                    f.write("test")
                os.remove(test_file)
            except (OSError, IOError):
                self.logger.warning("/dev/shm 不可写")
                return
            
            # 获取共享内存大小
            self.shm_size_mb = self._get_shm_size()
            
            if self.shm_size_mb >= self.min_shm_size_mb:
                self.shm_available = True
                self.logger.info(f"共享内存可用，大小: {self.shm_size_mb}MB")
            else:
                self.logger.warning(f"共享内存太小: {self.shm_size_mb}MB < {self.min_shm_size_mb}MB")
                
        except Exception as e:
            self.logger.warning(f"检查共享内存失败: {e}")
    
    def _get_shm_size(self) -> int:
        """获取共享内存大小（MB）"""
        try:
            # 方法1: 通过 df 命令获取
            result = subprocess.run(
                ["df", "-m", "/dev/shm"], 
                capture_output=True, 
                text=True, 
                timeout=5
            )
            if result.returncode == 0:
                lines = result.stdout.strip().split('\n')
                if len(lines) >= 2:
                    # 解析 df 输出，获取可用空间
                    parts = lines[1].split()
                    if len(parts) >= 4:
                        available_mb = int(parts[3])
                        if is_debug_enabled():
                            self.logger.debug(f"df 获取共享内存大小: {available_mb}MB")
                        return available_mb
            
            # 方法2: 通过 /proc/meminfo 获取
            with open("/proc/meminfo", "r") as f:
                for line in f:
                    if line.startswith("Shmem:"):
                        shmem_kb = int(line.split()[1])
                        shmem_mb = shmem_kb // 1024
                        if is_debug_enabled():
                            self.logger.debug(f"meminfo 获取共享内存大小: {shmem_mb}MB")
                        return shmem_mb
            
            # 方法3: 通过 statvfs 获取
            stat = os.statvfs("/dev/shm")
            available_bytes = stat.f_bavail * stat.f_frsize
            available_mb = available_bytes // (1024 * 1024)
            if is_debug_enabled():
                self.logger.debug(f"statvfs 获取共享内存大小: {available_mb}MB")
            return available_mb
            
        except Exception as e:
            self.logger.warning(f"获取共享内存大小失败: {e}")
            return 0
    
    def get_work_dir_base(self) -> str:
        """获取工作目录基础路径"""
        if self.shm_available:
            return self.shm_base_dir
        else:
            if self.fallback_base_dir is None:
                # 设置回退目录
                self.fallback_base_dir = os.path.join(
                    os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 
                    "work_dirs"
                )
            return self.fallback_base_dir
    
    def prepare_work_dir(self, work_dir_name: str) -> Tuple[bool, str, Dict[str, Any]]:
        """
        准备工作目录
        
        Args:
            work_dir_name: 工作目录名称（如 "run0"）
            
        Returns:
            Tuple[bool, str, Dict[str, Any]]: (是否成功, 工作目录路径, 详细信息)
        """
        try:
            base_dir = self.get_work_dir_base()
            work_dir = os.path.join(base_dir, work_dir_name)
            
            # 创建基础目录
            os.makedirs(base_dir, exist_ok=True)
            
            # 创建工作目录
            if os.path.exists(work_dir):
                # 清理现有目录
                shutil.rmtree(work_dir)
            
            os.makedirs(work_dir, exist_ok=True)
            os.chmod(work_dir, 0o755)
            
            info = {
                "work_dir": work_dir,
                "base_dir": base_dir,
                "is_shm": self.shm_available,
                "shm_size_mb": self.shm_size_mb,
                "fallback_used": not self.shm_available
            }
            
            if is_debug_enabled():
                self.logger.debug(f"工作目录准备完成: {work_dir}")
                self.logger.debug(f"使用共享内存: {self.shm_available}")
                if self.shm_available:
                    self.logger.debug(f"共享内存大小: {self.shm_size_mb}MB")
            
            return True, work_dir, info
            
        except Exception as e:
            error_info = {
                "error": str(e),
                "work_dir": work_dir if 'work_dir' in locals() else None,
                "base_dir": base_dir if 'base_dir' in locals() else None,
                "is_shm": self.shm_available
            }
            self.logger.error(f"准备工作目录失败: {e}")
            return False, "", error_info
    
    def copy_single_test_case(self, work_dir: str, input_file: str, dst_file: str = None) -> bool:
        """
        复制单个测试用例的输入文件到工作目录
        
        Args:
            work_dir: 工作目录路径
            input_file: 输入文件路径
            dst_file: 目标文件路径（可选，默认使用 tmp.in）
            
        Returns:
            bool: 是否成功
        """
        try:
            # 如果没有指定目标文件，使用默认的 tmp.in
            if dst_file is None:
                dst_path = os.path.join(work_dir, "tmp.in")
            else:
                dst_path = dst_file
            
            shutil.copy2(input_file, dst_path)
            
            if is_debug_enabled():
                self.logger.debug(f"复制测试用例输入文件: {input_file} -> {dst_path}")
            
            return True
            
        except Exception as e:
            self.logger.error(f"复制测试用例输入文件失败: {e}")
            return False
    
    def prepare_temp_files(self, work_dir: str, in_file: str, user_output: str) -> Dict[str, str]:
        """
        准备共享内存临时文件
        
        Args:
            work_dir: 工作目录路径
            in_file: 题目输入文件路径
            user_output: 选手输出文件路径
            
        Returns:
            Dict[str, str]: 包含 input, output, error 文件路径的字典
        """
        temp_files = {
            'input': None,
            'output': None,
            'error': None
        }
        
        # 检查是否可以使用共享内存
        if not self.shm_available:
            # 如果共享内存不可用，使用原始路径
            temp_files['input'] = in_file
            temp_files['output'] = user_output
            return temp_files
        
        try:
            # 获取共享内存工作目录
            shm_work_dir = self._get_shm_work_dir(work_dir)
            if not shm_work_dir:
                # 无法获取共享内存工作目录，使用原始路径
                temp_files['input'] = in_file
                temp_files['output'] = user_output
                return temp_files
            
            pid = os.getpid()
            
            # 准备输入文件
            if in_file:
                shm_input = os.path.join(shm_work_dir, f"tmp_input_{pid}.in")
                if self.copy_single_test_case(shm_work_dir, in_file, shm_input):
                    temp_files['input'] = shm_input
                    if is_debug_enabled():
                        self.logger.debug(f"准备共享内存输入文件: {in_file} -> {shm_input}")
                else:
                    temp_files['input'] = in_file
                    self.logger.warning("共享内存输入文件复制失败，使用原始文件")
            else:
                temp_files['input'] = in_file
            
            # 准备输出文件
            if user_output:
                shm_output = os.path.join(shm_work_dir, f"tmp_output_{pid}.out")
                temp_files['output'] = shm_output
                if is_debug_enabled():
                    self.logger.debug(f"准备共享内存输出文件: {user_output} -> {shm_output}")
            
            # 准备错误输出文件
            shm_error = os.path.join(shm_work_dir, f"tmp_error_{pid}.err")
            temp_files['error'] = shm_error
            if is_debug_enabled():
                self.logger.debug(f"准备共享内存错误文件: {shm_error}")
                
        except Exception as e:
            self.logger.warning(f"准备共享内存临时文件失败: {e}，使用原始路径")
            temp_files['input'] = in_file
            temp_files['output'] = user_output
        
        return temp_files
    
    def finalize_output_file(self, temp_files: Dict[str, str], user_output: str) -> None:
        """
        处理输出文件：从共享内存复制到最终位置
        
        Args:
            temp_files: 临时文件路径字典
            user_output: 最终输出文件路径（选手输出）
        """
        try:
            # 复制输出文件到最终位置
            if temp_files['output'] and user_output and os.path.exists(temp_files['output']):
                shutil.copy2(temp_files['output'], user_output)
                if is_debug_enabled():
                    self.logger.debug(f"复制输出文件到最终位置: {temp_files['output']} -> {user_output}")
                    
        except Exception as e:
            self.logger.warning(f"处理输出文件失败: {e}")
    
    def cleanup_temp_files(self, temp_files: Dict[str, str]) -> None:
        """
        清理共享内存临时文件
        
        Args:
            temp_files: 临时文件路径字典
        """
        for file_type, file_path in temp_files.items():
            if file_path and file_path.startswith("/dev/shm") and os.path.exists(file_path):
                try:
                    os.remove(file_path)
                    if is_debug_enabled():
                        self.logger.debug(f"清理共享内存{file_type}文件: {file_path}")
                except Exception as e:
                    self.logger.warning(f"清理共享内存{file_type}文件失败: {e}")
    
    def _get_shm_work_dir(self, work_dir: str) -> Optional[str]:
        """
        获取对应的共享内存工作目录
        
        Args:
            work_dir: 原始工作目录路径
            
        Returns:
            Optional[str]: 共享内存工作目录路径，如果无法获取则返回None
        """
        try:
            # 从原始工作目录中提取目录名（如 run0, run1 等）
            work_dir_name = os.path.basename(work_dir)
            
            # 在共享内存中创建对应的工作目录
            shm_work_dir = os.path.join(self.shm_base_dir, work_dir_name)
            
            # 确保目录存在
            os.makedirs(shm_work_dir, exist_ok=True)
            os.chmod(shm_work_dir, 0o755)
            
            return shm_work_dir
            
        except Exception as e:
            self.logger.warning(f"获取共享内存工作目录失败: {e}")
            return None
    
    def cleanup_test_case(self, work_dir: str) -> bool:
        """
        清理当前测试用例文件
        
        Args:
            work_dir: 工作目录路径
            
        Returns:
            bool: 是否成功
        """
        try:
            tmp_in_path = os.path.join(work_dir, "tmp.in")
            if os.path.exists(tmp_in_path):
                os.remove(tmp_in_path)
                
                if is_debug_enabled():
                    self.logger.debug(f"清理测试用例文件: {tmp_in_path}")
            
            return True
            
        except Exception as e:
            self.logger.error(f"清理测试用例文件失败: {e}")
            return False
    
    def cleanup_work_dir(self, work_dir: str) -> bool:
        """
        清理工作目录
        
        Args:
            work_dir: 工作目录路径
            
        Returns:
            bool: 是否成功
        """
        try:
            if os.path.exists(work_dir):
                shutil.rmtree(work_dir)
                
                if is_debug_enabled():
                    self.logger.debug(f"清理工作目录: {work_dir}")
            
            return True
            
        except Exception as e:
            self.logger.error(f"清理工作目录失败: {e}")
            return False
    
    def get_shm_info(self) -> Dict[str, Any]:
        """获取共享内存信息"""
        return {
            "available": self.shm_available,
            "size_mb": self.shm_size_mb,
            "min_required_mb": self.min_shm_size_mb,
            "base_dir": self.shm_base_dir if self.shm_available else self.get_work_dir_base()
        }
    
    def is_running_in_shm(self, work_dir: str) -> bool:
        """
        检查工作目录是否在共享内存中运行
        
        Args:
            work_dir: 工作目录路径
            
        Returns:
            bool: 是否在共享内存中
        """
        return work_dir.startswith("/dev/shm")


# 全局实例
_shm_manager = None

def get_shm_manager(logger: logging.Logger = None) -> ShmManager:
    """获取共享内存管理器实例"""
    global _shm_manager
    if _shm_manager is None:
        _shm_manager = ShmManager(logger)
    return _shm_manager


def test_shm_functionality():
    """测试共享内存功能"""
    from tools.debug_manager import DebugManager
    DebugManager.init_debug_manager(debug_mode=True, log_dir="/tmp/test_logs")
    
    logger = setup_unified_logging("ShmTest")
    shm_manager = ShmManager(logger)
    
    print("=== 共享内存功能测试 ===")
    
    # 获取共享内存信息
    shm_info = shm_manager.get_shm_info()
    print(f"共享内存可用: {shm_info['available']}")
    print(f"共享内存大小: {shm_info['size_mb']}MB")
    print(f"最小要求: {shm_info['min_required_mb']}MB")
    print(f"基础目录: {shm_info['base_dir']}")
    
    # 测试工作目录创建
    success, work_dir, info = shm_manager.prepare_work_dir("test_run")
    if success:
        print(f"✅ 工作目录创建成功: {work_dir}")
        print(f"   使用共享内存: {info['is_shm']}")
        
        # 测试单个测试用例复制
        if shm_manager.copy_single_test_case(work_dir, "/etc/passwd"):
            print("✅ 单个测试用例复制成功")
            
            # 检查是否在共享内存中
            if shm_manager.is_running_in_shm(work_dir):
                print("✅ 确认在共享内存中运行")
            else:
                print("ℹ️  在普通目录中运行")
            
            # 测试清理
            if shm_manager.cleanup_test_case(work_dir):
                print("✅ 测试用例清理成功")
        
        # 清理
        if shm_manager.cleanup_work_dir(work_dir):
            print("✅ 工作目录清理成功")
    else:
        print(f"❌ 工作目录创建失败: {info}")


if __name__ == "__main__":
    test_shm_functionality()
