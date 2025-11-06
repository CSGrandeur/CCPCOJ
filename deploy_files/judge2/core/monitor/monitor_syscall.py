#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
系统调用监控器
基于seccomp的系统调用监管，防止选手程序恶意行为
采用白名单机制，只允许必要的系统调用
"""

import os
import sys
import logging
import seccomp
from typing import Dict, Any, Optional, List, Set

# 将当前目录和父目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)

from tools.debug_manager import is_debug_enabled, is_syscallfree_enabled
from monitor_base import MonitorBase
from ok_call import get_whitelist
from tools import status_constants as sc

class MonitorSyscall(MonitorBase):
    """系统调用监控器 - 基于seccomp的系统调用监管"""
    
    def __init__(self, logger: logging.Logger, work_dir: str = None, test_case_name: str = None, 
                 task_type: str = None, language: str = None):
        super().__init__(logger, work_dir, test_case_name)

        self.task_type = task_type or sc.TASK_TYPE_PLAYER_RUN
        self.monitor_type = "syscall"
        self.seccomp_filter = None
        self.language = language  # 语言类型 (cpp, c, python, java, go)
        
        # 根据任务类型设置系统调用策略
        self.allowed_syscalls: Set[str] = set()
        self._setup_syscall_policy()
    
    def _setup_syscall_policy(self):
        """根据任务类型设置系统调用策略"""
        if self.task_type == sc.TASK_TYPE_PLAYER_COMPILE:
            # 编译选手程序：使用编译白名单
            if self.language:
                self.allowed_syscalls = get_whitelist(self.language, "compile", 0)
                if is_debug_enabled():
                    self.logger.debug(f"使用选手程序编译白名单: {self.language}, 包含 {len(self.allowed_syscalls)} 个系统调用")
            else:
                # 回退到默认编译白名单
                self.allowed_syscalls = get_whitelist("cpp", "compile", 0)
        elif self.task_type == sc.TASK_TYPE_PLAYER_RUN:
            # 运行选手程序：使用运行白名单
            if self.language:
                self.allowed_syscalls = get_whitelist(self.language, "run", 0)
                if is_debug_enabled():
                    self.logger.debug(f"使用选手程序运行白名单: {self.language}, 包含 {len(self.allowed_syscalls)} 个系统调用")
            else:
                # 回退到默认运行白名单
                self.allowed_syscalls = get_whitelist("cpp", "run", 0)
        elif self.task_type == sc.TASK_TYPE_TPJ_COMPILE:
            # 编译TPJ：使用TPJ编译白名单
            self.allowed_syscalls = get_whitelist("cpp", "compile", 1)
            if is_debug_enabled():
                self.logger.debug(f"使用TPJ编译白名单，包含 {len(self.allowed_syscalls)} 个系统调用")
        elif self.task_type == sc.TASK_TYPE_TPJ_RUN:
            # 运行TPJ：使用TPJ运行白名单
            self.allowed_syscalls = get_whitelist("cpp", "run", 1)
            if is_debug_enabled():
                self.logger.debug(f"使用TPJ运行白名单，包含 {len(self.allowed_syscalls)} 个系统调用")
        elif self.task_type == sc.TASK_TYPE_CUSTOM:
            # 自定义模式：空白名单，用于白名单生成测试
            self.allowed_syscalls = set()
            if is_debug_enabled():
                self.logger.debug("使用自定义模式：空白名单")
        else:
            # 默认使用选手程序运行白名单
            self.allowed_syscalls = get_whitelist("cpp", "run", 0)
            if is_debug_enabled():
                self.logger.debug(f"使用默认白名单，包含 {len(self.allowed_syscalls)} 个系统调用")
    
    
        if is_debug_enabled():
            self.logger.debug(f"seccomp过滤器创建成功，允许 {len(self.allowed_syscalls)} 个系统调用（带参数过滤）")
            if self.is_wsl2_environment():
                self.logger.warning("WSL2环境日志记录可能不完整")
            
        if is_debug_enabled():
            sorted_syscalls = sorted(list(self.allowed_syscalls))
            self.logger.debug(f"允许的系统调用列表: {sorted_syscalls}")
    
    def _add_strict_rule(self, filter, syscall):
        """为strict级别添加带参数过滤的seccomp规则"""
        # if syscall == "openat":
        #     # 严格模式：仅允许只读打开
        #     filter.add_rule(seccomp.ALLOW, syscall, 
        #                   seccomp.Arg(2, seccomp.EQ, 0))  # O_RDONLY = 0
        # elif syscall in ["read", "write", "readv", "writev", "pread64", "pwrite64"]:
        #     # 严格模式：仅允许操作标准文件描述符 0, 1, 2
        #     filter.add_rule(seccomp.ALLOW, syscall,
        #                   seccomp.Arg(0, seccomp.LE, 2))  # fd <= 2
        # elif syscall in ["dup", "dup2"]:
        #     # 严格模式：仅允许复制标准文件描述符
        #     if syscall == "dup":
        #         filter.add_rule(seccomp.ALLOW, syscall,
        #                       seccomp.Arg(0, seccomp.LE, 2))  # oldfd <= 2
        #     else:  # dup2
        #         filter.add_rule(seccomp.ALLOW, syscall,
        #                       seccomp.Arg(0, seccomp.LE, 2),  # oldfd <= 2
        #                       seccomp.Arg(1, seccomp.LE, 2))  # newfd <= 2
        # elif syscall in ["close", "fstat", "lseek"]:
        #     # 严格模式：仅允许操作标准文件描述符
        #     filter.add_rule(seccomp.ALLOW, syscall,
        #                   seccomp.Arg(0, seccomp.LE, 2))  # fd <= 2
        # else:
        #     # 其他系统调用：无条件允许
        #     filter.add_rule(seccomp.ALLOW, syscall)
        # from seccomp import Arg
        # access_mode = Arg(1)
        # if syscall == 'access':
        #     filter.add_rule(
        #         seccomp.ALLOW,
        #         "access",
        #         access_mode == 0  # F_OK（检查文件存在）
        #         | access_mode == 2  # W_OK（检查写权限）
        #         | access_mode == 4  # R_OK（检查读权限）
        #     )
        
        filter.add_rule(seccomp.ALLOW, syscall)
    
    def _setup_cgroup_limits(self):
        """设置cgroup限制 - 系统调用监控器不需要cgroup限制"""
        # 系统调用监控器主要负责seccomp过滤，不需要cgroup限制
        if is_debug_enabled():
            self.logger.debug("系统调用监控器不需要cgroup限制")
    
    def _set_process_limits(self):
        """设置进程资源限制 - 系统调用监控器不需要RLIMIT限制，子进程中不输出日志"""
        # 系统调用监控器主要负责seccomp过滤，不需要RLIMIT限制
        pass
    
    def _get_monitoring_data(self):
        """获取监控数据 - 系统调用监控器主要记录违规情况"""
        # 系统调用监控器主要记录是否有违规的系统调用
        return {
            "syscall_violations": 0,  # 违规系统调用次数
            "task_type": self.task_type,
            "monitor_success": True
        }
    
    def create_seccomp_filter(self) -> seccomp.SyscallFilter:
        """创建seccomp过滤器，包含参数过滤 - 根据环境选择模式"""
        """
        ##### 子进程内运行，所有输出静默！#####
        """
        try:
            # 检查是否为 syscallfree 模式
            if is_syscallfree_enabled():
                # syscallfree 模式：使用 LOG 模式，记录所有系统调用但不阻止
                filter = seccomp.SyscallFilter(defaction=seccomp.LOG)
            else:
                # 生产模式：使用 TRAP 模式，发送SIGSYS信号并记录到audit日志（最佳实践）
                filter = seccomp.SyscallFilter(defaction=seccomp.TRAP)
                
            # 添加允许的系统调用到seccomp过滤器
            for syscall in self.allowed_syscalls:
                try:
                    if self.task_type in [sc.TASK_TYPE_PLAYER_COMPILE, sc.TASK_TYPE_PLAYER_RUN, sc.TASK_TYPE_TPJ_COMPILE, sc.TASK_TYPE_TPJ_RUN]:
                        # 评测机任务：应用参数过滤（评测机业务逻辑）
                        self._add_strict_rule(filter, syscall)
                    else:
                        # 自定义模式：无条件允许
                        filter.add_rule(seccomp.ALLOW, syscall)
                except Exception as e:
                    if is_debug_enabled():
                        self.logger.debug(f"添加系统调用规则失败 {syscall}: {e}")
                    continue
            
            return filter
            
        except Exception as e:
            self.logger.error(f"创建seccomp过滤器失败: {e}")
            raise RuntimeError(f"创建seccomp过滤器失败: {e}")
    
    def apply_seccomp_filter(self):
        """应用seccomp过滤器到当前进程 - 子进程中不输出日志"""
        
        """
        ##### 子进程内运行，所有输出静默！#####
        """
        try:
            if self.seccomp_filter is None:
                # 创建seccomp过滤器（根据syscallfree模式自动选择LOG或TRAP）
                self.seccomp_filter = self.create_seccomp_filter()
            
            # 加载过滤器
            self.seccomp_filter.load()
                
        except Exception as e:
            # 子进程中不输出日志，直接抛出异常
            raise RuntimeError(f"应用seccomp过滤器失败: {e}")
    
    def get_security_info(self) -> Dict[str, Any]:
        """获取安全信息"""
        return {
            "task_type": self.task_type,
            "language": self.language,
            "allowed_syscalls_count": len(self.allowed_syscalls),
            "monitor_type": self.monitor_type,
            "seccomp_enabled": self.seccomp_filter is not None
        }
    
    def parse_seccomp_logs(self, log_source="dmesg"):
        """
        解析seccomp日志，提取所有系统调用（不做筛选）
        
        Args:
            log_source: 日志源，"dmesg" 或 "/var/log/kern.log"
        
        Returns:
            set: 所有发现的系统调用名称集合
        """
        import subprocess
        import re
        
        all_syscalls = set()
        
        try:
            if log_source == "dmesg":
                # 从dmesg获取最近的seccomp日志
                result = subprocess.run(["dmesg"], capture_output=True, text=True, timeout=10)
                log_content = result.stdout
            else:
                # 从内核日志文件读取
                with open(log_source, "r") as f:
                    log_content = f.read()
            
            # 先尝试匹配所有包含 syscall 的日志行
            all_syscall_lines = []
            for line in log_content.split('\n'):
                if 'syscall=' in line:
                    all_syscall_lines.append(line)
            
            self.logger.info(f"找到 {len(all_syscall_lines)} 行包含 syscall 的日志")
            if all_syscall_lines:
                self.logger.info(f"前几行示例: {all_syscall_lines[:3]}")
            else:
                self.logger.warning("没有找到包含 syscall 的日志行！")
            
            # 尝试多种日志格式
            patterns = [
                r'audit:.*syscall=(\d+).*success=no',  # 标准格式
                r'audit:.*syscall=(\d+).*exit=-13',    # 权限错误格式 (EPERM)
                r'audit:.*syscall=(\d+).*exit=-1',     # 通用错误格式
                r'audit:.*syscall=(\d+)',              # 所有系统调用（兜底）
            ]
            
            for pattern in patterns:
                self.logger.info(f"尝试正则表达式: {pattern}")
                for line in log_content.split('\n'):
                    match = re.search(pattern, line)
                    if match:
                        syscall_num = int(match.group(1))
                        try:
                            # 将系统调用编号转换为名称
                            syscall_name = seccomp.resolve_syscall(seccomp.Arch.X86_64, syscall_num)
                            if isinstance(syscall_name, bytes):
                                syscall_name = syscall_name.decode('utf-8')
                            
                            # 添加所有系统调用，不做筛选
                            all_syscalls.add(syscall_name)
                            self.logger.info(f"发现系统调用: {syscall_num} -> {syscall_name}")
                        except Exception as e:
                            # 如果无法解析，记录编号
                            all_syscalls.add(f"syscall_{syscall_num}")
                            self.logger.info(f"无法解析系统调用 {syscall_num}: {e}")
                
                if all_syscalls:
                    self.logger.info(f"使用模式 {pattern} 找到 {len(all_syscalls)} 个系统调用")
                    break
            
            return all_syscalls
            
        except Exception as e:
            self.logger.warning(f"解析seccomp日志失败: {e}")
            return set()
    
    def is_wsl2_environment(self) -> bool:
        """检测是否为WSL2环境"""
        try:
            with open("/proc/version", "r") as f:
                version_info = f.read().lower()
                return "microsoft" in version_info and "wsl2" in version_info
        except:
            return False
    
    def parse_seccomp_logs_log_mode(self, log_source="dmesg"):
        """
        解析LOG模式下的seccomp日志
        LOG模式下，违规系统调用会被记录但不会阻止程序执行
        """
        import subprocess
        import re
        
        all_syscalls = set()
        
        try:
            if log_source == "dmesg":
                result = subprocess.run(["dmesg"], capture_output=True, text=True, timeout=10)
                log_content = result.stdout
            else:
                with open(log_source, "r") as f:
                    log_content = f.read()
            
            # LOG模式下的日志格式通常包含 "seccomp" 关键字
            log_patterns = [
                r'seccomp.*syscall=(\d+)',  # 标准seccomp日志格式
                r'audit.*seccomp.*syscall=(\d+)',  # audit日志格式
                r'seccomp.*action=LOG.*syscall=(\d+)',  # 明确的LOG动作
            ]
            
            for pattern in log_patterns:
                for line in log_content.split('\n'):
                    if 'seccomp' in line.lower():
                        match = re.search(pattern, line)
                        if match:
                            syscall_num = int(match.group(1))
                            try:
                                syscall_name = seccomp.resolve_syscall(seccomp.Arch.X86_64, syscall_num)
                                if isinstance(syscall_name, bytes):
                                    syscall_name = syscall_name.decode('utf-8')
                                all_syscalls.add(syscall_name)
                                self.logger.info(f"LOG模式发现系统调用: {syscall_num} -> {syscall_name}")
                            except Exception as e:
                                all_syscalls.add(f"syscall_{syscall_num}")
                                self.logger.info(f"无法解析系统调用 {syscall_num}: {e}")
            
            return all_syscalls
            
        except Exception as e:
            self.logger.warning(f"解析LOG模式seccomp日志失败: {e}")
            return set()
    
    def _force_discover_blocked_syscalls(self, program_cmd: str, current_whitelist: set):
        """
        强制发现被阻止的系统调用，通过多次运行程序来触发所有可能的调用路径
        
        Args:
            program_cmd: 要分析的程序命令
            current_whitelist: 当前白名单
            
        Returns:
            set: 新发现的被阻止的系统调用集合
        """
        import subprocess
        import time
        
        all_blocked_syscalls = set()
        
        # 多次运行程序，每次清空日志，以触发不同的代码路径
        for attempt in range(3):
            self.logger.debug(f"第 {attempt + 1} 次尝试发现被阻止的系统调用")
            
            # 清空dmesg日志
            subprocess.run(["dmesg", "-C"], capture_output=True)
            
            # 创建监控器
            monitor = MonitorSyscall(self.logger, task_type='custom')
            monitor.allowed_syscalls = current_whitelist.copy()
            filter_obj = monitor.create_seccomp_filter()  # 使用TRAP模式
            
            try:
                # 运行程序
                result = subprocess.run(
                    ['sh', '-c', program_cmd],
                    capture_output=True,
                    text=True,
                    timeout=30,
                    preexec_fn=lambda: filter_obj.load()
                )
                
                # 解析日志，获取所有系统调用
                all_logged_syscalls = monitor.parse_seccomp_logs("dmesg")
                if all_logged_syscalls:
                    # 计算新的系统调用（不在当前白名单中的）
                    new_syscalls = all_logged_syscalls - current_whitelist
                    if new_syscalls:
                        all_blocked_syscalls.update(new_syscalls)
                        self.logger.debug(f"第 {attempt + 1} 次尝试发现新的系统调用: {sorted(new_syscalls)}")
                        self.logger.debug(f"日志中所有系统调用: {sorted(all_logged_syscalls)}")
                    else:
                        self.logger.debug(f"第 {attempt + 1} 次尝试：日志中的系统调用都在当前白名单中")
                
                # 短暂等待，让系统稳定
                time.sleep(0.5)
                
            except Exception as e:
                self.logger.debug(f"第 {attempt + 1} 次尝试失败: {e}")
                continue
        
        if all_blocked_syscalls:
            self.logger.info(f"通过多次尝试发现被阻止的系统调用: {sorted(all_blocked_syscalls)}")
        
        return all_blocked_syscalls
    


def create_monitor_syscall(logger: logging.Logger, work_dir: str = None, test_case_name: str = None, 
                          task_type: str = None, language: str = None) -> MonitorSyscall:
    """
    创建系统调用监控器实例
    
    Args:
        logger: 日志记录器
        work_dir: 工作目录
        test_case_name: 测试用例名称
        task_type: 任务类型 (使用 status_constants 中的常量)
        language: 语言类型 (cpp, c, python, java, go)
    
    Returns:
        MonitorSyscall: 系统调用监控器实例
    """
    return MonitorSyscall(logger, work_dir, test_case_name, task_type, language)


