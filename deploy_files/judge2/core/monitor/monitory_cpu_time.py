#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CPU时间监控器
基于cgroups的精确CPU时间监控
不受系统负载和CPU压力影响
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


class MonitorCPUTime(MonitorBase):
    """CPU时间监控器 - 基于cgroups的精确CPU时间监控"""
    
    def __init__(self, logger: logging.Logger, time_limit: float = None, work_dir: str = None, test_case_name: str = None, 
                 task_type: str = None, language: str = None):
        super().__init__(logger, work_dir, test_case_name)
        # 确保time_limit是浮点数类型
        self.time_limit = float(time_limit) if time_limit is not None else None
        self.cpu_time_used = None  # 初始化为None，表示尚未获取数据
        self.monitor_type = "cpu_time"
        from tools import status_constants as sc
        self.task_type = task_type or sc.TASK_TYPE_PLAYER_RUN
        self.language = language  # 语言类型 (cpp, c, python, java, go...)
        
    def _setup_cgroup_limits(self):
        """设置cgroup CPU时间限制"""
        if not self.cgroup_path or not os.path.exists(self.cgroup_path):
            error_msg = f"cgroup路径无效，无法设置CPU时间限制: {self.cgroup_path}"
            self.logger.error(error_msg)
            raise RuntimeError(error_msg)
        
        if self.time_limit:
            # 设置实际的CPU时间限制（微秒）
            # 使用标准周期 100ms，确保精确的节流控制
            cpu_quota = int(self.time_limit * 1000000)  # 总CPU时间（微秒）
            cpu_period = 100000  # 标准周期 100ms，确保精确控制
            
            with open(f"{self.cgroup_path}/cpu.max", 'w') as f:
                f.write(f"{cpu_quota} {cpu_period}")  # 格式：quota period
            
            if is_debug_enabled():
                self.logger.debug(f"设置CPU时间限制: {self.time_limit}秒 (总CPU时间硬限制)")
                self.logger.debug(f"cgroup配置: quota={cpu_quota}微秒, period={cpu_period}微秒")
        else:
            # 如果没有时间限制，设置无限制
            with open(f"{self.cgroup_path}/cpu.max", 'w') as f:
                f.write("max 100000")  # 无限制，使用标准周期
            
            if is_debug_enabled():
                self.logger.debug("设置CPU时间监控（无限制）")

    def _set_process_limits(self):
        """设置进程CPU时间限制（RLIMIT等）- 已移至统一监控器管理"""
        # RLIMIT 设置已移至 unified_monitor.py 的 _setup_rlimit_restrictions 方法
        # 这里保留空实现以保持接口兼容性
        if is_debug_enabled():
            self.logger.debug("CPU时间RLIMIT设置已由统一监控器管理")
    
    def _get_monitoring_data(self):
        """获取CPU时间监控数据"""
        # 获取CPU时间统计
        self.cpu_time_used = self._get_cpu_time_usage()
        
        if is_debug_enabled():
            self.logger.debug(f"CPU时间监控完成，CPU时间: {self.cpu_time_used:.3f}秒")
    
    
    def get_cpu_time_usage(self) -> float:
        """获取CPU时间使用量（秒）"""
        # 返回已收集的数据
        if self.cpu_time_used is None:
            self.cpu_time_used = self._get_cpu_time_usage()
        if self.cpu_time_used is None:
            raise RuntimeError("CPU 监控数据获取失败")
        return self.cpu_time_used
    
    
    def _get_cpu_time_usage(self) -> float:
        """
        获取cgroup的CPU时间使用量
        
        Returns:
            float: CPU时间（毫秒）
            
        Raises:
            RuntimeError: 当CPU时间监控失败时抛出异常
        """
        try:
            if not self.cgroup_path or not os.path.exists(self.cgroup_path):
                error_msg = f"CPU监控器cgroup路径无效: {self.cgroup_path}"
                self.logger.error(error_msg)
                raise RuntimeError(error_msg)
            
            if is_debug_enabled():
                self.logger.debug(f"读取CPU统计文件: {self.cgroup_path}/cpu.stat")
            
            # 读取cgroup的CPU时间统计
            cpu_stat_file = f"{self.cgroup_path}/cpu.stat"
            if os.path.exists(cpu_stat_file):
                with open(cpu_stat_file, 'r') as f:
                    content = f.read()
                    if is_debug_enabled():
                        self.logger.debug(f"cpu.stat内容: {content}")
                    
                    for line in content.split('\n'):
                        if line.startswith('usage_usec'):
                            # usage_usec是微秒，转换为毫秒
                            usage_usec = int(line.split()[1])
                            cpu_time = usage_usec / 1_000.0  # 微秒转毫秒
                            if is_debug_enabled():
                                self.logger.debug(f"从usage_usec获取CPU时间: {cpu_time}毫秒")
                            
                            # 验证CPU时间的合理性
                            if cpu_time < 0:
                                error_msg = f"CPU时间值为负数: {cpu_time}毫秒，这可能表示监控异常"
                                self.logger.warning(error_msg)
                                raise RuntimeError(error_msg)
                            return cpu_time
                
                # 如果cpu.stat存在但没有usage_usec行
                error_msg = f"cpu.stat文件存在但缺少usage_usec统计: {cpu_stat_file}"
                self.logger.error(error_msg)
                raise RuntimeError(error_msg)
            else:
                if is_debug_enabled():
                    self.logger.debug(f"cpu.stat文件不存在: {cpu_stat_file}")
            
            # 如果没有cpu.stat，尝试读取cpuacct.usage
            cpuacct_usage_file = f"{self.cgroup_path}/cpuacct.usage"
            if os.path.exists(cpuacct_usage_file):
                with open(cpuacct_usage_file, 'r') as f:
                    usage_nsec = int(f.read().strip())
                    cpu_time = usage_nsec / 1_000_000.0  # 纳秒转毫秒
                    if is_debug_enabled():
                        self.logger.debug(f"从cpuacct.usage获取CPU时间: {cpu_time}毫秒")
                    
                    # 验证CPU时间的合理性
                    if cpu_time < 0:
                        error_msg = f"CPU时间值为负数: {cpu_time}毫秒，这可能表示监控异常"
                        self.logger.warning(error_msg)
                        raise RuntimeError(error_msg)
                    
                    return cpu_time
            else:
                if is_debug_enabled():
                    self.logger.debug(f"cpuacct.usage文件不存在: {cpuacct_usage_file}")
            
            # 如果两个文件都不存在，抛出异常
            error_msg = f"未找到CPU统计文件: cpu.stat和cpuacct.usage都不存在，cgroup路径: {self.cgroup_path}"
            self.logger.error(error_msg)
            raise RuntimeError(error_msg)
            
        except (ValueError, IndexError) as e:
            error_msg = f"解析CPU统计文件失败: {e}"
            self.logger.error(error_msg)
            raise RuntimeError(error_msg)
        except Exception as e:
            error_msg = f"获取cgroup CPU时间失败: {e}"
            self.logger.error(error_msg)
            raise RuntimeError(error_msg)
    


# 删除多余的包装类，直接使用 MonitorCPUTime


def create_monitor_cpu_time(logger: logging.Logger, time_limit: float, work_dir: str = None, test_case_name: str = None, 
                           task_type: str = None, language: str = None) -> MonitorCPUTime:
    """
    创建CPU时间监控器实例
    
    Args:
        logger: 日志记录器
        time_limit: 时间限制（秒）
        work_dir: 评测工作目录
        test_case_name: 测试用例名称
        task_type: 任务类型 (使用 status_constants 中的常量)
        language: 语言类型 (cpp, c, python, java, go)
        
    Returns:
        MonitorCPUTime: 监控器实例
    """
    return MonitorCPUTime(logger, time_limit, work_dir, test_case_name, task_type, language)
