#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
任务监控器基类
专门用于编译和运行任务的监控器基类，管理共同的功能
"""

import os
import sys
import time
import logging
import subprocess
import socket
from typing import Dict, Any, Optional
from abc import ABC, abstractmethod

# 将当前目录和父目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)

from tools.debug_manager import is_debug_enabled, is_syscallfree_enabled
from monitor_memory import create_monitor_memory, CalMemoryLimit
from monitory_cpu_time import create_monitor_cpu_time
from monitor_syscall import create_monitor_syscall
from monitor_file import create_monitor_file


class TaskMonitorBase:
    """任务监控器基类 - 编译和运行监控器的共同基类"""
    
    def __init__(self, logger: logging.Logger, memory_limit_mb: int, time_limit: float, 
                 work_dir: str = None, test_case_name: str = None, language: str = None, 
                 task_type: str = None, output_file: str = None):
        # 初始化基础属性
        self.logger = logger
        self.work_dir = work_dir
        self.test_case_name = test_case_name
        self.cgroup_name = self._generate_unique_cgroup_name()
        self.cgroup_path = None
        self.process = None
        self.pid = None
        self.monitor_type = None
        
        # 设置监控器类型和任务类型
        from tools import status_constants as sc
        self.task_type = task_type or sc.TASK_TYPE_PLAYER_RUN
        self.language = language
        self.output_file = output_file  # 答案文件
        
        # 资源限制
        self.memory_limit_mb = int(memory_limit_mb) if memory_limit_mb is not None else 1024
        self.time_limit = float(time_limit) if time_limit is not None else 1.0
        
        # 创建监控组件
        self.memory_monitor = create_monitor_memory(logger, memory_limit_mb, work_dir, test_case_name)
        self.cpu_monitor = create_monitor_cpu_time(logger, time_limit, work_dir, test_case_name)
        self.syscall_monitor = create_monitor_syscall(logger, work_dir, test_case_name, task_type, language)
        self.file_monitor = create_monitor_file(work_dir, language, task_type, logger)
        
    def start_with_process(self, cmd: list, **kwargs) -> subprocess.Popen:
        """启动进程"""
        # 设置文件系统jail
        self.file_monitor.setup_jail(self.output_file)
        
        # 创建cgroup
        if not self._create_cgroup():
            raise RuntimeError("创建cgroup失败")
        
        # 绑定监控器到cgroup
        self.memory_monitor.cgroup_path = self.cgroup_path
        self.cpu_monitor.cgroup_path = self.cgroup_path
        
        # 设置cgroup限制，【需要在主进程进行】
        self._setup_cgroup_limits()
        
        # 创建preexec_fn
        preexec_fn = self.create_preexec_fn()
        
        # 启动进程
        self.logger.info(f"开始运行 `{' '.join(cmd)}`")
        process = subprocess.Popen(cmd, preexec_fn=preexec_fn, **kwargs)
        
        if is_debug_enabled():
            self.logger.debug(f"{self.task_type}进程启动成功，PID: {process.pid}")
        
        return process

    
    def create_preexec_fn(self):
        """创建进程的preexec_fn"""
        def set_process_limits():
            """设置进程的限制 - 子进程中不输出任何日志，避免干扰用户程序输出"""
            """
            ##### 子进程内运行，所有输出静默！#####
            """
            try:
                # 将子进程 ID 加入 cgroup
                pid = os.getpid()
                procs_file = f"{self.cgroup_path}/cgroup.procs"
                
                # 在子进程设置 rilimit 资源限制
                self._set_process_limits()
                
                # 加入cgroup
                with open(procs_file, "w") as f:
                    f.write(str(pid))
                
                # 应用chroot jail
                self.file_monitor.apply_chroot()
                
                # 应用seccomp过滤
                self.syscall_monitor.apply_seccomp_filter()
                
            except Exception as e:
                # 子进程中不输出日志，直接抛出异常
                raise RuntimeError(f"{self.task_type}进程安全设置失败: {e}")
        
        return set_process_limits
    
    def _setup_cgroup_limits(self):
        """设置cgroup限制"""
        try:
            self.memory_monitor._setup_cgroup_limits()
            self.cpu_monitor._setup_cgroup_limits()
            
            if is_debug_enabled():
                self.logger.debug(f"{self.task_type}cgroup限制设置完成")
        except Exception as e:
            err_msg = f"设置{self.task_type} cgroup限制失败: {e}"
            if is_debug_enabled():
                self.logger.debug(f"设置{self.task_type}cgroup限制失败: {e}")
            raise RuntimeError(err_msg)
    
    def _set_process_limits(self):
        """设置进程资源限制 - 子进程中不输出日志，避免干扰用户程序输出"""
        try:
            import resource
            import math
            
            # CPU时间限制
            time_limit_int = math.ceil(self.time_limit)
            resource.setrlimit(resource.RLIMIT_CPU, (time_limit_int, time_limit_int))
            
            # 内存限制
            if self.language != 'java':
                memory_bytes = CalMemoryLimit(self.memory_limit_mb, self.language) * 1024 * 1024
                resource.setrlimit(resource.RLIMIT_AS, (memory_bytes, memory_bytes))
            
            # 输出文件大小限制
            output_limit = self.file_monitor.get_output_limit()
            if output_limit > 0:
                resource.setrlimit(resource.RLIMIT_FSIZE, (output_limit, output_limit))
            
            # 调用子类的具体限制设置
            self._set_task_specific_limits()
            
        except Exception as e:
            # 子进程中不输出日志，直接抛出异常
            raise RuntimeError(f"设置{self.task_type}RLIMIT限制失败: {e}")
    
    @abstractmethod
    def _set_task_specific_limits(self):
        """设置任务特定的资源限制 - 子类必须实现"""
        pass
    
    def _get_monitoring_data(self):
        """获取监控数据"""
        try:
            memory_used = self.memory_monitor.get_max_memory()
            cpu_time_used = self.cpu_monitor.get_cpu_time_usage()
            
            if cpu_time_used is None or cpu_time_used < 0:
                cpu_time_used = -1.0
                
            return {
                "memory_used": memory_used,
                "cpu_time_used": cpu_time_used
            }
        except Exception as e:
            self.logger.error(f"获取{self.task_type}监控数据失败: {e}")
            raise e
    
    def _save_dmesg_log(self):
        """保存dmesg日志到独立目录"""
        if not is_syscallfree_enabled() or not self.work_dir:
            return
        
        try:
            # 获取dmesg日志
            result = subprocess.run(["dmesg"], capture_output=True, text=True, timeout=10)
            if result.returncode == 0:
                dmesg_content = result.stdout
                
                # 创建独立的dmesg目录
                dmesg_dir = os.path.join(self.work_dir, "dmesg_logs")
                os.makedirs(dmesg_dir, exist_ok=True)
                
                # 确定文件名
                if self.test_case_name:
                    dmesg_filename = f"dmesg_{self.test_case_name}.log"
                else:
                    dmesg_filename = f"dmesg_{self.task_type}.log"
                
                dmesg_path = os.path.join(dmesg_dir, dmesg_filename)
                
                # 保存到文件
                with open(dmesg_path, 'w', encoding='utf-8') as f:
                    f.write(dmesg_content)
                
                if is_debug_enabled():
                    self.logger.debug(f"已保存dmesg日志到: {dmesg_path}")
                
                # 解析当前日志文件并更新汇总
                self._update_syscall_summary(dmesg_path, dmesg_dir)
                
            else:
                if is_debug_enabled():
                    self.logger.debug(f"获取dmesg日志失败，返回码: {result.returncode}")
                    
        except subprocess.TimeoutExpired:
            if is_debug_enabled():
                self.logger.debug("获取dmesg日志超时")
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"保存dmesg日志时发生错误: {e}")
    
    def _update_syscall_summary(self, dmesg_file: str, dmesg_dir: str):
        """解析单个dmesg文件并更新syscall汇总JSON"""
        try:
            import json
            
            # 解析当前dmesg文件中的syscall
            syscalls = self._parse_dmesg_syscalls(dmesg_file)
            
            if not syscalls:
                if is_debug_enabled():
                    self.logger.debug(f"从 {os.path.basename(dmesg_file)} 未解析到syscall")
                return
            
            # 直接使用当前实例的task_type
            task_type = self.task_type
            
            # 读取现有的汇总文件
            summary_path = os.path.join(dmesg_dir, "syscall_summary.json")
            
            existing_summary = {}
            
            if os.path.exists(summary_path):
                try:
                    with open(summary_path, 'r', encoding='utf-8') as f:
                        existing_summary = json.load(f)
                except Exception as e:
                    if is_debug_enabled():
                        self.logger.debug(f"读取现有汇总文件失败: {e}")
                    existing_summary = {}
            
            # 更新汇总
            if task_type not in existing_summary:
                existing_summary[task_type] = []
            
            # 将现有列表转换为集合，添加新的syscall，再转回排序列表
            existing_syscalls = set(existing_summary[task_type])
            existing_syscalls.update(syscalls)
            existing_summary[task_type] = sorted(list(existing_syscalls))
            
            # 保存更新后的汇总文件
            with open(summary_path, 'w', encoding='utf-8') as f:
                json.dump(existing_summary, f, indent=2, ensure_ascii=False)
            
            if is_debug_enabled():
                self.logger.debug(f"已更新syscall汇总: {task_type} 新增 {len(syscalls)} 个syscall")
                self.logger.debug(f"当前 {task_type} 总计 {len(existing_syscalls)} 个syscall")
                self.logger.debug(f"新增的syscall: {sorted(syscalls)}")
                
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"更新syscall汇总时发生错误: {e}")
    
    def _parse_dmesg_syscalls(self, dmesg_file: str) -> set:
        """解析dmesg文件中的syscall"""
        try:
            # 导入check_secccomp_log_call模块的解析函数
            import sys
            tool_dir = os.path.join(os.path.dirname(__file__), "tool_syscall")
            if tool_dir not in sys.path:
                sys.path.insert(0, tool_dir)
            
            from check_secccomp_log_call import parse_log_file
            
            # 解析文件获取syscall集合
            syscalls = parse_log_file(dmesg_file)
            return syscalls
            
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"解析dmesg文件 {dmesg_file} 时发生错误: {e}")
            return set()
    
    def stop(self):
        """停止监控"""
        self.cleanup()
        if is_debug_enabled():
            self.logger.debug(f"{self.task_type}监控器已清理")
    
    def cleanup(self):
        """清理cgroup资源"""
        if is_debug_enabled():
            self.logger.debug(f"开始清理{self.task_type}监控器")
        
        self._cleanup_cgroup()
        self.file_monitor.cleanup()
        
        if is_debug_enabled():
            self.logger.debug(f"{self.task_type}监控器清理完成")
    
    def _create_cgroup(self) -> bool:
        """创建cgroup"""
        # 检查cgroups v2支持
        if not self._check_cgroups_v2_support():
            if is_debug_enabled():
                self.logger.debug("系统不支持cgroups v2")
            return False
        
        # 创建cgroup目录
        self.cgroup_path = f"/sys/fs/cgroup/{self.cgroup_name}"
        
        # 创建新的cgroup目录
        os.makedirs(self.cgroup_path, exist_ok=True)
                    
        # 验证目录创建成功
        if not os.path.exists(self.cgroup_path):
            if is_debug_enabled():
                self.logger.debug(f"cgroup目录创建失败: {self.cgroup_path}")
            return False
        
        # 验证关键文件存在
        procs_file = f"{self.cgroup_path}/cgroup.procs"
        if not os.path.exists(procs_file):
            if is_debug_enabled():
                self.logger.debug(f"cgroup.procs文件不存在: {procs_file}")
            return False
        
        # 再次验证cgroup可用性
        with open(procs_file, "r") as f:
            f.read()  # 测试读取
        with open(procs_file, "w") as f:
            f.write("")  # 测试写入
        
        if is_debug_enabled():
            self.logger.debug(f"创建cgroup成功: {self.cgroup_name}")
        
        return True
    
    def _cleanup_cgroup(self):
        """清理cgroup"""
        if self.cgroup_path and os.path.exists(self.cgroup_path):
            # 如果是debug模式，先复制cgroup目录到工作目录
            if is_debug_enabled() and self.work_dir:
                self._copy_cgroup_for_debug()
            
            # 移除所有进程
            with open(f"{self.cgroup_path}/cgroup.procs", 'w') as f:
                f.write("")
            
            # 删除cgroup目录
            os.rmdir(self.cgroup_path)
            
            if is_debug_enabled():
                self.logger.debug(f"已清理cgroup: {self.cgroup_name}")
    
    def _copy_cgroup_for_debug(self):
        """复制cgroup目录用于调试"""
        # 创建cgroup目录
        cgroup_base_dir = os.path.join(self.work_dir, "cgroup")
        os.makedirs(cgroup_base_dir, exist_ok=True)
        
        # 根据测试用例名称确定目标目录名
        if self.test_case_name:
            debug_cgroup_name = f"cgroup_{self.test_case_name}"
        else:
            debug_cgroup_name = f"cgroup_default"
        
        debug_cgroup_path = os.path.join(cgroup_base_dir, debug_cgroup_name)
        
        # 创建目标目录
        if os.path.exists(debug_cgroup_path):
            import shutil
            shutil.rmtree(debug_cgroup_path)
        os.makedirs(debug_cgroup_path, exist_ok=True)
        
        # 逐个复制cgroup文件，跳过无法复制的特殊文件
        import shutil
        for item in os.listdir(self.cgroup_path):
            src_path = os.path.join(self.cgroup_path, item)
            dst_path = os.path.join(debug_cgroup_path, item)
            
            try:
                if os.path.isfile(src_path):
                    # 尝试复制文件内容
                    try:
                        with open(src_path, 'r') as src_file:
                            content = src_file.read()
                        with open(dst_path, 'w') as dst_file:
                            dst_file.write(content)
                    except (OSError, IOError):
                        # 如果无法读取或写入，跳过这个文件
                        if is_debug_enabled():
                            self.logger.debug(f"跳过无法复制的cgroup文件: {item}")
                        continue
                elif os.path.isdir(src_path):
                    # 递归复制子目录
                    shutil.copytree(src_path, dst_path)
            except (OSError, IOError) as e:
                # 跳过无法复制的文件或目录
                if is_debug_enabled():
                    self.logger.debug(f"跳过无法复制的cgroup项目: {item}, 错误: {e}")
                continue
        
        if is_debug_enabled():
            self.logger.debug(f"已复制cgroup目录到: {debug_cgroup_path}")
    
    def _check_cgroups_v2_support(self) -> bool:
        """检查系统是否支持cgroups v2"""
        # 检查cgroups v2挂载点
        with open('/proc/mounts', 'r') as f:
            mounts = f.read()
            if 'cgroup2' in mounts and '/sys/fs/cgroup' in mounts:
                return True
        
        # 检查cgroup文件系统
        if os.path.exists('/sys/fs/cgroup/memory.max'):
            return True
            
        return False
    
    
    def _generate_unique_cgroup_name(self) -> str:
        """生成唯一的cgroup名称，避免多容器冲突"""
        # 获取容器ID或主机名
        container_id = self._get_container_id()
        hostname = socket.gethostname()
        
        # 生成唯一标识符
        unique_id = f"{container_id}_{hostname}_{int(time.time() * 1000)}"
        return f"judge_{unique_id}"
    
    def _get_container_id(self) -> str:
        """获取容器ID"""
        # 尝试从cgroup文件获取容器ID
        with open('/proc/self/cgroup', 'r') as f:
            for line in f:
                if 'docker' in line or 'containerd' in line:
                    parts = line.strip().split('/')
                    if len(parts) > 2:
                        return parts[-1][:12]  # 取前12位
        
        # 尝试从环境变量获取
        container_id = os.environ.get('HOSTNAME', '')
        if container_id:
            return container_id[:12]
        
        # 回退到进程ID
        return f"host_{os.getpid()}"
    
    @abstractmethod
    def run_with_monitoring(self, cmd: list, **kwargs) -> Dict[str, Any]:
        """运行并监控 - 子类必须实现"""
        pass
    
    @abstractmethod
    def _analyze_result(self, return_code: int, memory_used: int, cpu_time_used: float, **kwargs) -> Dict[str, Any]:
        """分析结果 - 子类必须实现"""
        pass
