#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
专业内存监控模块
提供基于cgroups的智能内存监控方案
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

from tools.debug_manager import is_debug_enabled
from monitor_base import MonitorBase

def CalMemoryLimit(memory_limit_mb: int, language:str=None) -> int:
    """计算内存限制，补充冗余内存"""
    
    if language in ["java", "scala", "kotlin"]:
        factor = 0.5
        min_extra = 16
    elif language in ["python", "pypy"]:
        factor = 0.2
        min_extra = 8
    elif language in ["cpp", "c", "rust"]:
        return memory_limit_mb + 8
    else:
        factor = 0.1
        min_extra = 8

    extra = max(min_extra, int(memory_limit_mb * factor))
    total = memory_limit_mb + extra
    return max(total, 48)


class MonitorMemory(MonitorBase):
    """内存监控器 - 基于cgroups的专业内存监控"""
    
    def __init__(self, logger: logging.Logger, memory_limit_mb: int = 1024, work_dir: str = None, test_case_name: str = None, 
                 task_type: str = None, language: str = None):
        super().__init__(logger, work_dir, test_case_name)
        # 确保memory_limit_mb是整数类型
        self.memory_limit_mb = int(memory_limit_mb) if memory_limit_mb is not None else None
        self.max_memory = None  # 初始化为None，表示尚未获取数据
        self.monitor_type = "memory"
        from tools import status_constants as sc
        self.task_type = task_type or sc.TASK_TYPE_PLAYER_RUN
        self.language = language  # 语言类型 (cpp, c, python, java, go)
    
    def _setup_cgroup_limits(self):
        """设置cgroup内存限制"""
        if not self.cgroup_path or not os.path.exists(self.cgroup_path):
            error_msg = f"cgroup路径无效，无法设置内存限制: {self.cgroup_path}"
            self.logger.error(error_msg)
            raise RuntimeError(error_msg)
        
        if not self.memory_limit_mb:
            raise RuntimeError("未设置内存限制")
        
        # 设置内存限制（字节）
        memory_limit_bytes = CalMemoryLimit(self.memory_limit_mb, self.language) * 1024 * 1024
        with open(f"{self.cgroup_path}/memory.max", 'w') as f:
            f.write(str(memory_limit_bytes))
        
        # 设置内存交换限制（禁止交换，确保内存限制生效）
        try:
            with open(f"{self.cgroup_path}/memory.swap.max", 'w') as f:
                f.write("0")  # 禁止交换
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"设置内存交换限制失败: {e}")
        
        if is_debug_enabled():
            self.logger.debug(f"设置cgroup内存限制: {self.memory_limit_mb}MB")
            

    
    def _set_process_limits(self):
        """设置进程内存限制（RLIMIT等）- 已移至统一监控器管理，子进程中不输出日志"""
        # RLIMIT 设置已移至 unified_monitor.py 的 _setup_rlimit_restrictions 方法
        # 这里保留空实现以保持接口兼容性
        pass
    
    def _get_monitoring_data(self):
        """获取内存监控数据"""
        # 在清理cgroup之前先获取内存使用量
        # 这确保在进程还在运行时能获取到正确的峰值
        self.max_memory = self._get_memory_usage()
        
        if is_debug_enabled():
            self.logger.debug(f"cgroups内存监控停止，最终内存使用: {self.max_memory}KB")
    
    
    def get_max_memory(self) -> int:
        """获取最大内存使用量（KB）"""
        # 返回已收集的数据
        if self.max_memory is None:
            self._get_monitoring_data()
        if self.max_memory is None:
            raise RuntimeError("内存监控数据未获取，可能监控失败")
        return self.max_memory
    
    def _get_memory_usage(self) -> int:
        """获取cgroup的内存使用量 - 根据cgroups版本读取相应的峰值文件"""
        try:
            if not self.cgroup_path or not os.path.exists(self.cgroup_path):
                return None
            
            # 检查cgroups版本并读取相应的峰值文件
            if not self._is_cgroups_v2():
                raise RuntimeError("不支持 cgroups v2")
            
            # cgroups v2: 优先使用 memory.peak，如果为0则使用 memory.current
            peak_file = f"{self.cgroup_path}/memory.peak"
            current_file = f"{self.cgroup_path}/memory.current"
            # 先尝试读取峰值
            with open(peak_file, 'r') as f:
                peak_bytes = int(f.read().strip())
            
            # 如果峰值为0（进程还在运行），读取当前内存使用量
            if peak_bytes == 0 and os.path.exists(current_file):
                with open(current_file, 'r') as f:
                    current_bytes = int(f.read().strip())
                memory_bytes = current_bytes
                if is_debug_enabled():
                    self.logger.debug(f"cgroups v2 峰值为0，使用当前内存使用: {memory_bytes // 1024}KB")
            else:
                memory_bytes = peak_bytes
                if is_debug_enabled():
                    self.logger.debug(f"cgroups v2 峰值内存使用: {memory_bytes // 1024}KB")
                       
            memory_kb = memory_bytes // 1024
            return memory_kb
            
        except Exception as e:
            self.logger.warning(f"获取cgroup内存使用量失败: {e}")
            return None
    


# 删除多余的包装类，直接使用 MonitorMemory


def create_monitor_memory(logger: logging.Logger, memory_limit_mb: int = 1024, work_dir: str = None, test_case_name: str = None, 
                         task_type: str = None, language: str = None) -> MonitorMemory:
    """
    创建内存监控器的工厂函数
    
    Args:
        logger: 日志记录器
        memory_limit_mb: 内存限制（MB）
        work_dir: 评测工作目录
        test_case_name: 测试用例名称
        task_type: 任务类型 (使用 status_constants 中的常量)
        language: 语言类型 (cpp, c, python, java, go)
    
    Returns:
        MonitorMemory: 内存监控器实例
    """
    return MonitorMemory(logger, memory_limit_mb, work_dir, test_case_name, task_type, language)


