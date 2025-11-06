#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
监控器基类
提供基础的抽象接口
"""

import os
import sys
import time
import socket
import logging
from typing import Dict, Any, Optional
from abc import ABC, abstractmethod

# 将当前目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)

from tools.debug_manager import is_debug_enabled


class MonitorBase(ABC):
    """监控器基类 - 提供抽象接口"""
    
    def __init__(self, logger: logging.Logger, work_dir: str = None, test_case_name: str = None):
        self.logger = logger
        self.work_dir = work_dir  # 评测工作目录
        self.test_case_name = test_case_name  # 测试用例名称
        self.cgroup_path = None  # 由子类设置
        self.process = None
        self.pid = None
        self.monitor_type = None
    
    
    def cleanup(self):
        """清理资源 - 子类实现"""
        pass
    
    def _is_cgroups_v2(self) -> bool:
        """检查当前cgroup是否为v2版本"""
        # print("\n\n\n", self.cgroup_path, "\n\n\n")
        # exit()
        if not self.cgroup_path:
            return False
        
        # 检查是否存在v2特有的文件
        if os.path.exists(f"{self.cgroup_path}/memory.peak"):
            return True
        
        # 检查是否存在v1特有的文件
        if os.path.exists(f"{self.cgroup_path}/memory.max_usage_in_bytes"):
            return False
        
        # 如果都不存在，说明cgroup创建失败，返回False
        return False
    
    # 抽象方法，子类必须实现
    @abstractmethod
    def _setup_cgroup_limits(self):
        """设置cgroup资源限制 - 子类必须实现"""
        pass
    
    @abstractmethod
    def _set_process_limits(self):
        """设置进程资源限制（RLIMIT等）- 子类必须实现"""
        pass
    
    @abstractmethod
    def _get_monitoring_data(self):
        """获取监控数据 - 子类必须实现"""
        pass
    
   