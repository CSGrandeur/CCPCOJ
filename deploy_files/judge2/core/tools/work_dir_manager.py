#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
工作目录管理器
负责评测前后的目录清理和检查
"""

import os
import sys
import shutil
import logging
import time
import subprocess
import fcntl
import hashlib
from typing import Dict, Any, Optional, Tuple

# 将当前目录和父目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)

from tools.debug_manager import is_debug_enabled
from tools.shm_manager import get_shm_manager
from tools import status_constants as sc


class WorkDirManager:
    """工作目录管理器"""
    
    def __init__(self, base_work_dir: str, logger: logging.Logger = None):
        """
        初始化工作目录管理器
        
        Args:
            base_work_dir: 基础工作目录路径（回退目录）
            logger: 日志记录器
        """
        self.base_work_dir = base_work_dir
        self.logger = logger or logging.getLogger(self.__class__.__name__)
        self.current_work_dir = None
        
        # 初始化共享内存管理器
        self.shm_manager = get_shm_manager(self.logger)
        
    def find_available_work_dir(self, max_retries: int = 3, retry_delay: float = 1.0) -> Tuple[str, Dict[str, Any]]:
        """
        查找可用的工作目录 - 带重试机制
        
        Args:
            max_retries: 最大重试次数
            retry_delay: 重试间隔（秒）
            
        Returns:
            Tuple[str, Dict[str, Any]]: (工作目录绝对路径, 详细信息)
        """
        # 确保基础工作目录是绝对路径
        base_work_dir = os.path.abspath(self.base_work_dir)
        
        for retry in range(max_retries):
            self.logger.info(f"第 {retry + 1} 次尝试查找可用工作目录...")
            
            for i in range(10):  # run0 到 run9
                work_dir = os.path.join(base_work_dir, f"run{i}")
                
                # 检查目录状态
                status_info = self._check_dir_status(work_dir)
                
                if status_info["is_available"]:
                    self.current_work_dir = work_dir
                    self.logger.info(f"找到可用工作目录：{work_dir}")
                    return work_dir, status_info
                else:
                    self.logger.warning(f"工作目录 {work_dir} 不可用：{status_info['reason']}")
            
            # 如果所有目录都不可用，等待一段时间后重试
            if retry < max_retries - 1:
                self.logger.warning(f"所有工作目录都不可用，等待 {retry_delay} 秒后重试...")
                time.sleep(retry_delay)
                
                # 尝试强制清理一些目录
                self._force_cleanup_stale_dirs()
        
        # 所有重试都失败
        error_info = {
            "error_type": "NoAvailableWorkDir",
            "error_message": f"经过 {max_retries} 次重试，所有工作目录(run0-run9)都不可用",
            "details": {
                "base_work_dir": base_work_dir,
                "checked_dirs": [f"run{i}" for i in range(10)],
                "max_retries": max_retries,
                "retry_delay": retry_delay
            }
        }
        
        self.logger.error(f"所有工作目录都不可用：{error_info}")
        return None, error_info
    
    def _check_dir_status(self, work_dir: str) -> Dict[str, Any]:
        """
        检查目录状态 - 改进的占用判断逻辑
        
        Args:
            work_dir: 要检查的目录路径
            
        Returns:
            Dict[str, Any]: 目录状态信息
        """
        try:
            # 检查目录是否存在
            if not os.path.exists(work_dir):
                return {
                    "is_available": True,
                    "reason": "目录不存在，可以创建",
                    "dir_exists": False,
                    "is_empty": True,
                    "size": 0,
                    "processes": []
                }
            
            # 检查是否为目录
            if not os.path.isdir(work_dir):
                return {
                    "is_available": False,
                    "reason": "路径存在但不是目录",
                    "dir_exists": True,
                    "is_empty": False,
                    "size": 0,
                    "processes": []
                }
            
            # 检查目录是否为空
            try:
                files = os.listdir(work_dir)
                is_empty = len(files) == 0
            except PermissionError:
                return {
                    "is_available": False,
                    "reason": "无权限访问目录",
                    "dir_exists": True,
                    "is_empty": False,
                    "size": 0,
                    "processes": []
                }
            
            # 计算目录大小
            try:
                total_size = 0
                for dirpath, dirnames, filenames in os.walk(work_dir):
                    for filename in filenames:
                        filepath = os.path.join(dirpath, filename)
                        try:
                            total_size += os.path.getsize(filepath)
                        except (OSError, IOError):
                            pass
            except Exception:
                total_size = 0
            
            # 检查是否有活跃进程在使用此目录
            active_processes = self._find_active_processes_using_dir(work_dir)
            
            # 正确的可用性判断逻辑：只有活跃进程占用才不可用
            if self._is_dir_available(work_dir, is_empty, active_processes, files):
                reason = "目录可用（无活跃进程占用）"
                if not is_empty:
                    reason += f"，包含{len(files)}个文件"
                
                return {
                    "is_available": True,
                    "reason": reason,
                    "dir_exists": True,
                    "is_empty": is_empty,
                    "size": total_size,
                    "processes": active_processes,
                    "files": files[:10] if not is_empty else []  # 显示文件信息
                }
            else:
                return {
                    "is_available": False,
                    "reason": f"有{len(active_processes)}个活跃进程占用",
                    "dir_exists": True,
                    "is_empty": is_empty,
                    "size": total_size,
                    "processes": active_processes,
                    "files": files[:10] if not is_empty else []
                }
                
        except Exception as e:
            return {
                "is_available": False,
                "reason": f"检查目录时发生错误：{str(e)}",
                "dir_exists": False,
                "is_empty": False,
                "size": 0,
                "processes": [],
                "error": str(e)
            }
    
    def _find_active_processes_using_dir(self, work_dir: str) -> list:
        """
        查找活跃使用指定目录的进程 - 基于文件锁的检测逻辑
        
        Args:
            work_dir: 目录路径
            
        Returns:
            list: 活跃进程信息列表
        """
        active_processes = []
        try:
            # 使用文件锁检测是否有进程在使用目录
            lock_file = self._get_work_dir_lock_file(work_dir)
            
            if os.path.exists(lock_file):
                # 尝试以非阻塞方式获取排他锁
                try:
                    with open(lock_file, 'r') as f:
                        fcntl.flock(f.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
                        # 如果能获取到锁，说明没有其他进程持有锁
                        fcntl.flock(f.fileno(), fcntl.LOCK_UN)
                        # 锁文件存在但可以获取锁，说明是遗留的锁文件
                        self.logger.debug(f"发现遗留锁文件: {lock_file}")
                        return active_processes
                except (OSError, IOError) as e:
                    if e.errno == 11:  # EAGAIN - 资源被占用
                        # 有其他进程持有锁，说明目录正在被使用
                        active_processes.append({
                            "pid": "unknown",
                            "name": "unknown",
                            "cwd": work_dir,
                            "cmdline": ["lock_file_detected"],
                            "status": "active",
                            "create_time": 0,
                            "lock_file": lock_file
                        })
                        self.logger.debug(f"检测到目录被占用: {work_dir} (锁文件: {lock_file})")
                    else:
                        self.logger.warning(f"检查锁文件时发生错误: {e}")
            
            return active_processes
            
        except Exception as e:
            self.logger.warning(f"查找活跃进程时发生错误：{e}")
            return active_processes
    
    def _get_work_dir_lock_file(self, work_dir: str) -> str:
        """
        获取工作目录的锁文件路径
        
        Args:
            work_dir: 工作目录路径
            
        Returns:
            str: 锁文件路径
        """
        # 使用工作目录路径的哈希值作为锁文件名
        work_dir_hash = hashlib.md5(work_dir.encode()).hexdigest()
        lock_dir = "/tmp/csgoj_work_dir_locks"
        os.makedirs(lock_dir, exist_ok=True)
        return os.path.join(lock_dir, f"{work_dir_hash}.lock")
    
    def _acquire_work_dir_lock(self, work_dir: str) -> bool:
        """
        获取工作目录锁
        
        Args:
            work_dir: 工作目录路径
            
        Returns:
            bool: 是否成功获取锁
        """
        try:
            lock_file = self._get_work_dir_lock_file(work_dir)
            
            # 创建锁文件并获取排他锁
            fd = os.open(lock_file, os.O_CREAT | os.O_RDWR, 0o644)
            fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
            
            # 将文件描述符存储起来，用于后续释放
            if not hasattr(self, '_held_work_dir_locks'):
                self._held_work_dir_locks = {}
            self._held_work_dir_locks[work_dir] = fd
            
            self.logger.debug(f"成功获取工作目录锁: {work_dir}")
            return True
            
        except (OSError, IOError) as e:
            if e.errno == 11:  # EAGAIN - 资源被占用
                self.logger.debug(f"工作目录被占用: {work_dir}")
                return False
            else:
                self.logger.error(f"获取工作目录锁时发生错误: {e}")
                return False
    
    def _release_work_dir_lock(self, work_dir: str) -> bool:
        """
        释放工作目录锁
        
        Args:
            work_dir: 工作目录路径
            
        Returns:
            bool: 是否成功释放锁
        """
        try:
            if not hasattr(self, '_held_work_dir_locks') or work_dir not in self._held_work_dir_locks:
                return True
            
            fd = self._held_work_dir_locks[work_dir]
            
            # 释放锁
            fcntl.flock(fd, fcntl.LOCK_UN)
            os.close(fd)
            
            # 从持有锁列表中移除
            del self._held_work_dir_locks[work_dir]
            
            # 删除锁文件
            lock_file = self._get_work_dir_lock_file(work_dir)
            if os.path.exists(lock_file):
                os.unlink(lock_file)
            
            self.logger.debug(f"成功释放工作目录锁: {work_dir}")
            return True
            
        except Exception as e:
            self.logger.error(f"释放工作目录锁时发生错误: {e}")
            return False
    
    def _is_process_using_dir(self, proc_info: dict, work_dir: str) -> bool:
        """
        判断进程是否真正在使用指定目录
        
        Args:
            proc_info: 进程信息
            work_dir: 目录路径
            
        Returns:
            bool: 是否在使用目录
        """
        try:
            # 检查工作目录
            if proc_info.get('cwd') == work_dir:
                return True
            
            # 检查命令行参数中是否包含此目录
            cmdline = proc_info.get('cmdline', [])
            if cmdline and work_dir in ' '.join(cmdline):
                return True
            
            # 检查是否是评测相关的进程
            name = proc_info.get('name', '').lower()
            if any(keyword in name for keyword in ['judge', 'python', 'gcc', 'g++', 'java']):
                # 进一步检查是否在评测目录中
                if proc_info.get('cwd') and work_dir in proc_info['cwd']:
                    return True
            
            return False
        except Exception:
            return False
    
    def _is_dir_available(self, work_dir: str, is_empty: bool, active_processes: list, files: list) -> bool:
        """
        判断目录是否可用 - 正确的可用性判断逻辑
        
        Args:
            work_dir: 目录路径
            is_empty: 目录是否为空
            active_processes: 活跃进程列表
            files: 目录中的文件列表
            
        Returns:
            bool: 目录是否可用
        """
        # 关键逻辑：只有活跃进程占用目录时，目录才不可用
        # 目录有文件不等于目录不可用！
        
        if active_processes:
            # 有活跃进程在使用目录，不可用
            return False
        
        # 没有活跃进程占用，目录可用（无论是否有文件）
        # 如果有文件，会在后续的清理步骤中处理
        return True
    
    def _are_files_legacy(self, files: list) -> bool:
        """
        判断文件是否为遗留文件（可以安全清理）
        
        Args:
            files: 文件列表
            
        Returns:
            bool: 是否为遗留文件
        """
        if not files:
            return True
        
        # 定义遗留文件的特征
        legacy_patterns = [
            # 评测产生的临时文件
            '.in', '.out', '.ans', '.tmp',
            # 编译产生的文件
            '.o', '.exe', '.class',
            # 日志文件
            '.log', '.err',
            # 其他临时文件
            'core', 'a.out'
        ]
        
        # 检查所有文件是否都是遗留文件
        for file in files:
            file_lower = file.lower()
            is_legacy = any(pattern in file_lower for pattern in legacy_patterns)
            
            # 如果发现非遗留文件，则不是遗留文件
            if not is_legacy:
                return False
        
        return True
    
    def _force_cleanup_stale_dirs(self):
        """
        强制清理过期的目录（包括共享内存目录）
        """
        self.logger.info("尝试强制清理过期目录...")
        
        # 确保基础工作目录是绝对路径
        base_work_dir = os.path.abspath(self.base_work_dir)
        
        for i in range(10):  # run0 到 run9
            work_dir = os.path.join(base_work_dir, f"run{i}")
            
            if not os.path.exists(work_dir):
                continue
            
            try:
                # 检查目录中的文件
                files = os.listdir(work_dir)
                if not files:
                    continue
                
                # 检查文件修改时间，如果超过5分钟则认为过期
                current_time = time.time()
                all_files_old = True
                
                for file in files:
                    file_path = os.path.join(work_dir, file)
                    try:
                        file_mtime = os.path.getmtime(file_path)
                        if current_time - file_mtime < 300:  # 5分钟
                            all_files_old = False
                            break
                    except OSError:
                        continue
                
                # 如果所有文件都过期，尝试清理
                if all_files_old:
                    self.logger.info(f"目录 {work_dir} 中的文件已过期，尝试清理...")
                    success, clean_info = self.clean_work_dir(work_dir)
                    if success:
                        self.logger.info(f"成功清理过期目录：{work_dir}")
                        # 记录共享内存清理信息
                        if "shm_cleanup_info" in clean_info:
                            shm_info = clean_info["shm_cleanup_info"]
                            if shm_info.get("shm_cleaned"):
                                self.logger.info(f"同时清理了共享内存目录：{shm_info.get('shm_dir')}，清理了{shm_info.get('shm_files_cleaned', 0)}个文件")
                    else:
                        self.logger.warning(f"清理过期目录失败：{work_dir}, {clean_info}")
                        
            except Exception as e:
                self.logger.warning(f"检查目录 {work_dir} 时发生错误：{e}")
                continue
    
    def clean_work_dir(self, work_dir: str) -> Tuple[bool, Dict[str, Any]]:
        """
        清理工作目录（包括对应的共享内存目录）
        
        Args:
            work_dir: 要清理的目录路径
            
        Returns:
            Tuple[bool, Dict[str, Any]]: (是否成功, 详细信息)
        """
        try:
            if not os.path.exists(work_dir):
                return True, {"message": "目录不存在，无需清理"}
            
            if not os.path.isdir(work_dir):
                return False, {"error": "路径存在但不是目录"}
            
            # 获取清理前的信息
            files_before = []
            try:
                files_before = os.listdir(work_dir)
            except PermissionError:
                return False, {"error": "无权限访问目录"}
            
            # 先尝试卸载可能的挂载点（通过monitor_file模块）
            self._unmount_work_dir_mounts(work_dir)
            
            # 清理对应的共享内存目录
            shm_cleanup_info = self._cleanup_shm_work_dir(work_dir)
            
            # 尝试删除目录内容
            try:
                shutil.rmtree(work_dir)
                os.makedirs(work_dir, exist_ok=True)
                os.chmod(work_dir, 0o755)
            except Exception as e:
                # 如果rmtree失败，尝试逐个删除文件
                if is_debug_enabled():
                    self.logger.debug(f"rmtree失败，尝试逐个删除文件: {e}")
                
                try:
                    self._force_remove_directory_contents(work_dir)
                    os.makedirs(work_dir, exist_ok=True)
                    os.chmod(work_dir, 0o755)
                except Exception as e2:
                    return False, {
                        "error": f"清理目录失败：{str(e)}，逐个删除也失败：{str(e2)}",
                        "files_before": files_before,
                        "shm_cleanup_info": shm_cleanup_info
                    }
            
            # 验证清理结果
            files_after = os.listdir(work_dir)
            
            return True, {
                "message": "目录清理成功",
                "files_before": files_before,
                "files_after": files_after,
                "cleaned_files_count": len(files_before),
                "shm_cleanup_info": shm_cleanup_info
            }
            
        except Exception as e:
            return False, {
                "error": f"清理目录时发生错误：{str(e)}",
                "traceback": str(e)
            }
    
    def _unmount_work_dir_mounts(self, work_dir: str):
        """卸载工作目录下的挂载点（通过monitor_file模块）- 强清理版本"""
        try:
            if is_debug_enabled():
                self.logger.debug(f"开始卸载工作目录挂载点: {work_dir}")
            
            # 直接调用monitor_file模块的清理方法（已包含避免重复清理的逻辑）
            from monitor.monitor_file import MonitorFile
            temp_monitor = MonitorFile(work_dir, "", "", self.logger)
            temp_monitor.cleanup()
            
            # 等待一段时间确保挂载点完全卸载
            import time
            time.sleep(0.1)
            
            # 二次检查：如果仍有挂载点，进行强清理
            remaining_mounts = self._check_remaining_mounts(work_dir)
            if remaining_mounts:
                if is_debug_enabled():
                    self.logger.debug(f"发现 {len(remaining_mounts)} 个残留挂载点，进行二次强清理")
                self._force_unmount_all(work_dir, remaining_mounts)
            
            if is_debug_enabled():
                self.logger.debug(f"monitor_file模块清理完成")
                
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"卸载挂载点过程中发生错误: {e}")
            # 忽略卸载过程中的错误，不影响主流程
    
    def _check_remaining_mounts(self, work_dir: str) -> list:
        """检查工作目录下是否还有残留的挂载点"""
        try:
            result = subprocess.run(["mount"], capture_output=True, text=True, check=True)
            mount_lines = result.stdout.split('\n')
            
            remaining = []
            for line in mount_lines:
                if work_dir in line:
                    parts = line.split()
                    if len(parts) >= 3:
                        mount_point = parts[2]
                        if mount_point.startswith(work_dir):
                            remaining.append(mount_point)
            
            return remaining
        except Exception:
            return []
    
    def _force_unmount_all(self, work_dir: str, mount_points: list):
        """强制卸载所有挂载点（最强力清理）"""
        if not mount_points:
            return
        
        # 优先使用 monitor_file 模块的方法（标准清理）
        try:
            from monitor.monitor_file import MonitorFile
            temp_monitor = MonitorFile(work_dir, "", "", self.logger)
            
            # 使用通用方法批量卸载
            success_count, fail_count = temp_monitor._unmount_multiple_points(
                mount_points, mount_type="残留挂载点（强清理）"
            )
            
            # 如果仍有失败的，使用 force unmount（更强力）
            if fail_count > 0:
                remaining = [mp for mp in mount_points if temp_monitor._is_mounted(mp)]
                if remaining:
                    if is_debug_enabled():
                        self.logger.debug(f"仍有 {len(remaining)} 个挂载点未清理，使用 force unmount")
                    self._force_unmount_with_flag(remaining)
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"使用 monitor_file 清理失败，回退到 force unmount: {e}")
            # 回退到原始的 force unmount 方法
            self._force_unmount_with_flag(mount_points)
    
    def _force_unmount_with_flag(self, mount_points: list):
        """使用 force 标志强制卸载挂载点（最终手段）"""
        # 按深度排序，先卸载深层的
        mount_points.sort(key=lambda x: x.count('/'), reverse=True)
        
        for mount_point in mount_points:
            try:
                # 尝试多种卸载方法（包含 force 选项）
                for method in [["umount"], ["umount", "-l"], ["umount", "-f"], ["umount", "-fl"]]:
                    try:
                        subprocess.run(method + [mount_point], check=True, 
                                     capture_output=True, timeout=1)
                        if is_debug_enabled():
                            self.logger.debug(f"force unmount 挂载点成功: {mount_point} (方法: {' '.join(method)})")
                        break
                    except subprocess.CalledProcessError as e:
                        if e.returncode == 32:  # 挂载点不存在，已清理
                            break
                        continue
                    except subprocess.TimeoutExpired:
                        continue
            except Exception:
                pass
    
    def _cleanup_shm_work_dir(self, work_dir: str) -> Dict[str, Any]:
        """
        清理对应的共享内存工作目录
        
        Args:
            work_dir: 原始工作目录路径
            
        Returns:
            Dict[str, Any]: 清理信息
        """
        cleanup_info = {
            "shm_cleaned": False,
            "shm_dir": None,
            "shm_files_cleaned": 0,
            "error": None
        }
        
        try:
            # 检查共享内存是否可用
            if not self.shm_manager.shm_available:
                cleanup_info["message"] = "共享内存不可用，无需清理"
                return cleanup_info
            
            # 获取对应的共享内存工作目录
            work_dir_name = os.path.basename(work_dir)
            shm_work_dir = os.path.join(self.shm_manager.shm_base_dir, work_dir_name)
            
            cleanup_info["shm_dir"] = shm_work_dir
            
            if not os.path.exists(shm_work_dir):
                cleanup_info["message"] = "共享内存目录不存在，无需清理"
                return cleanup_info
            
            # 获取清理前的文件信息
            shm_files_before = []
            try:
                shm_files_before = os.listdir(shm_work_dir)
            except PermissionError:
                cleanup_info["error"] = "无权限访问共享内存目录"
                return cleanup_info
            
            # 清理共享内存目录
            try:
                shutil.rmtree(shm_work_dir)
                os.makedirs(shm_work_dir, exist_ok=True)
                os.chmod(shm_work_dir, 0o755)
                
                cleanup_info["shm_cleaned"] = True
                cleanup_info["shm_files_cleaned"] = len(shm_files_before)
                cleanup_info["message"] = f"共享内存目录清理成功，清理了{len(shm_files_before)}个文件"
                
                if is_debug_enabled():
                    self.logger.debug(f"共享内存目录清理成功: {shm_work_dir}")
                
            except Exception as e:
                # 如果rmtree失败，尝试逐个删除文件
                if is_debug_enabled():
                    self.logger.debug(f"共享内存目录rmtree失败，尝试逐个删除: {e}")
                
                try:
                    self._force_remove_directory_contents(shm_work_dir)
                    os.makedirs(shm_work_dir, exist_ok=True)
                    os.chmod(shm_work_dir, 0o755)
                    
                    cleanup_info["shm_cleaned"] = True
                    cleanup_info["shm_files_cleaned"] = len(shm_files_before)
                    cleanup_info["message"] = f"共享内存目录清理成功（逐个删除），清理了{len(shm_files_before)}个文件"
                    
                except Exception as e2:
                    cleanup_info["error"] = f"共享内存目录清理失败：{str(e)}，逐个删除也失败：{str(e2)}"
                    if is_debug_enabled():
                        self.logger.debug(f"共享内存目录清理失败: {shm_work_dir}, 错误: {e2}")
            
        except Exception as e:
            cleanup_info["error"] = f"清理共享内存目录时发生错误：{str(e)}"
            if is_debug_enabled():
                self.logger.debug(f"清理共享内存目录时发生错误: {e}")
        
        return cleanup_info
    
    def _force_remove_directory_contents(self, work_dir: str):
        """强制删除目录内容（逐个删除文件，跳过挂载点）"""
        try:
            if is_debug_enabled():
                self.logger.debug(f"开始强制删除目录内容: {work_dir}")
            
            # 获取当前系统的挂载点信息，避免删除挂载点
            mounted_paths = self._get_mounted_paths()
            
            # 获取目录中的所有文件和子目录
            for root, dirs, files in os.walk(work_dir, topdown=False):
                # 先删除文件
                for file in files:
                    file_path = os.path.join(root, file)
                    try:
                        # 检查是否是挂载点
                        if self._is_mounted_path(file_path, mounted_paths):
                            if is_debug_enabled():
                                self.logger.debug(f"跳过挂载点文件: {file_path}")
                            continue
                            
                        os.remove(file_path)
                        if is_debug_enabled():
                            self.logger.debug(f"删除文件成功: {file_path}")
                    except Exception as e:
                        if is_debug_enabled():
                            self.logger.debug(f"删除文件失败: {file_path}, 错误: {e}")
                        # 继续尝试删除其他文件
                
                # 再删除目录
                for dir_name in dirs:
                    dir_path = os.path.join(root, dir_name)
                    try:
                        # 检查是否是挂载点
                        if self._is_mounted_path(dir_path, mounted_paths):
                            if is_debug_enabled():
                                self.logger.debug(f"跳过挂载点目录: {dir_path}")
                            continue
                            
                        os.rmdir(dir_path)
                        if is_debug_enabled():
                            self.logger.debug(f"删除目录成功: {dir_path}")
                    except Exception as e:
                        if is_debug_enabled():
                            self.logger.debug(f"删除目录失败: {dir_path}, 错误: {e}")
                        # 继续尝试删除其他目录
            
            if is_debug_enabled():
                self.logger.debug(f"强制删除目录内容完成: {work_dir}")
                
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"强制删除目录内容时发生错误: {e}")
            raise e
    
    def _get_mounted_paths(self) -> set:
        """获取当前系统的挂载点路径"""
        mounted_paths = set()
        try:
            result = subprocess.run(["mount"], capture_output=True, text=True, check=True)
            mount_lines = result.stdout.split('\n')
            
            for line in mount_lines:
                parts = line.split()
                if len(parts) >= 3:
                    mount_point = parts[2]  # 挂载点通常在第三个字段
                    mounted_paths.add(mount_point)
                    
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"获取挂载点信息失败: {e}")
                
        return mounted_paths
    
    def _is_mounted_path(self, path: str, mounted_paths: set) -> bool:
        """检查路径是否是挂载点"""
        # 直接检查
        if path in mounted_paths:
            return True
            
        # 检查父目录是否是挂载点
        parent = os.path.dirname(path)
        while parent != path and parent != '/':
            if parent in mounted_paths:
                return True
            path = parent
            parent = os.path.dirname(path)
            
        return False
    
    def prepare_work_dir(self, max_retries: int = 3) -> Tuple[bool, str, Dict[str, Any]]:
        """
        准备工作目录（查找可用目录并清理）- 带重试机制
        
        Args:
            max_retries: 最大重试次数
            
        Returns:
            Tuple[bool, str, Dict[str, Any]]: (是否成功, 工作目录路径, 详细信息)
        """
        # 查找可用目录（带重试机制）
        work_dir, status_info = self.find_available_work_dir(max_retries)
        
        if work_dir is None:
            return False, None, status_info
        
        # 获取工作目录锁
        if not self._acquire_work_dir_lock(work_dir):
            self.logger.error(f"无法获取工作目录锁: {work_dir}")
            return False, None, {
                "error_type": "LockAcquisitionFailed",
                "error_message": f"无法获取工作目录锁: {work_dir}",
                "work_dir": work_dir,
                "status_info": status_info
            }
        
        try:
            # 如果目录不为空，尝试清理（但清理失败不应该影响目录可用性）
            if not status_info["is_empty"]:
                self.logger.info(f"工作目录 {work_dir} 不为空，开始清理...")
                success, clean_info = self.clean_work_dir(work_dir)
                
                if success:
                    self.logger.info(f"工作目录 {work_dir} 清理成功")
                    return True, work_dir, {
                        "work_dir": work_dir,
                        "status_info": status_info,
                        "clean_info": clean_info,
                        "lock_acquired": True
                    }
                else:
                    # 清理失败，但目录仍然可用（因为无活跃进程占用）
                    self.logger.warning(f"清理 {work_dir} 失败，但目录仍可用：{clean_info}")
                    return True, work_dir, {
                        "work_dir": work_dir,
                        "status_info": status_info,
                        "clean_info": clean_info,
                        "warning": "清理失败但目录仍可用",
                        "lock_acquired": True
                    }
            
            # 目录为空，确保目录存在
            self.logger.info(f"工作目录 {work_dir} 为空，确保目录存在...")
            try:
                os.makedirs(work_dir, exist_ok=True)
                os.chmod(work_dir, 0o755)
                self.logger.info(f"工作目录 {work_dir} 准备完成")
            except Exception as e:
                self.logger.error(f"创建工作目录失败：{e}")
                self._release_work_dir_lock(work_dir)
                return False, None, {
                    "error_type": "CreateWorkDirFailed",
                    "error_message": f"创建工作目录失败：{str(e)}",
                    "work_dir": work_dir,
                    "status_info": status_info
                }
            
            return True, work_dir, {
                "work_dir": work_dir,
                "status_info": status_info,
                "lock_acquired": True
            }
            
        except Exception as e:
            # 发生异常时释放锁
            self._release_work_dir_lock(work_dir)
            raise e
    
    def prepare_work_dir_with_shm(self, max_retries: int = 3) -> Tuple[bool, str, Dict[str, Any]]:
        """
        使用共享内存准备工作目录（如果可用）
        
        Args:
            max_retries: 最大重试次数
            
        Returns:
            Tuple[bool, str, Dict[str, Any]]: (是否成功, 工作目录路径, 详细信息)
        """
        try:
            # 获取共享内存信息
            shm_info = self.shm_manager.get_shm_info()
            
            if shm_info["available"]:
                self.logger.info(f"使用共享内存作为工作目录，大小: {shm_info['size_mb']}MB")
                
                # 在共享内存中查找可用目录
                for i in range(10):  # run0 到 run9
                    work_dir_name = f"run{i}"
                    success, work_dir, info = self.shm_manager.prepare_work_dir(work_dir_name)
                    
                    if success:
                        self.current_work_dir = work_dir
                        
                        # 合并信息
                        combined_info = {
                            "work_dir": work_dir,
                            "is_shm": True,
                            "shm_size_mb": shm_info["size_mb"],
                            "shm_info": shm_info
                        }
                        
                        return True, work_dir, combined_info
                
                # 如果共享内存中所有目录都不可用，回退到普通目录
                self.logger.warning("共享内存中所有目录都不可用，回退到普通目录")
            elif is_debug_enabled():
                self.logger.debug(f"环境 SHM 空间：{shm_info['size_mb']}MB，空间不足，未使用 SHM 运行")
            # 回退到普通目录
            self.logger.info("使用普通目录作为工作目录")
            return self.prepare_work_dir(max_retries)
            
        except Exception as e:
            self.logger.error(f"使用共享内存准备工作目录失败: {e}")
            # 回退到普通目录
            return self.prepare_work_dir(max_retries)
    
    def _try_next_work_dir(self, failed_dir: str) -> Tuple[bool, str, Dict[str, Any]]:
        """
        尝试下一个工作目录
        
        Args:
            failed_dir: 失败的目录路径
            
        Returns:
            Tuple[bool, str, Dict[str, Any]]: (是否成功, 工作目录绝对路径, 详细信息)
        """
        # 确保基础工作目录是绝对路径
        base_work_dir = os.path.abspath(self.base_work_dir)
        
        # 从失败目录的下一个开始尝试
        try:
            failed_index = int(os.path.basename(failed_dir).replace("run", ""))
        except (ValueError, AttributeError):
            failed_index = -1
        
        for i in range(failed_index + 1, 10):
            work_dir = os.path.join(base_work_dir, f"run{i}")
            status_info = self._check_dir_status(work_dir)
            
            if status_info["is_available"]:
                self.current_work_dir = work_dir
                self.logger.info(f"找到替代工作目录：{work_dir}")
                return True, work_dir, {
                    "work_dir": work_dir,
                    "status_info": status_info,
                    "previous_failed": failed_dir
                }
            else:
                self.logger.warning(f"工作目录 {work_dir} 不可用：{status_info['reason']}")
        
        # 所有目录都不可用
        error_info = {
            "error_type": "NoAvailableWorkDir",
            "error_message": "所有工作目录都不可用",
            "details": {
                "base_work_dir": base_work_dir,
                "failed_dirs": [failed_dir],
                "checked_dirs": [f"run{i}" for i in range(10)]
            }
        }
        
        self.logger.error(f"所有工作目录都不可用：{error_info}")
        return False, None, error_info
    
    def cleanup_work_dir(self) -> Tuple[bool, Dict[str, Any]]:
        """
        清理当前工作目录
        
        Returns:
            Tuple[bool, Dict[str, Any]]: (是否成功, 详细信息)
        """
        if not self.current_work_dir:
            return True, {"message": "没有当前工作目录需要清理"}
        
        self.logger.info(f"开始清理工作目录：{self.current_work_dir}")
        success, clean_info = self.clean_work_dir(self.current_work_dir)
        
        # 释放工作目录锁
        lock_released = self._release_work_dir_lock(self.current_work_dir)
        
        if success:
            self.logger.info(f"工作目录清理成功：{self.current_work_dir}")
        else:
            self.logger.error(f"工作目录清理失败：{self.current_work_dir}, {clean_info}")
        
        # 合并清理信息和锁释放信息
        clean_info["lock_released"] = lock_released
        
        return success, clean_info
    
    def get_current_work_dir(self) -> Optional[str]:
        """获取当前工作目录"""
        return self.current_work_dir
    
    def create_system_error_result(self, error_info: Dict[str, Any]) -> Dict[str, Any]:
        """
        创建系统错误结果
        
        Args:
            error_info: 错误信息
            
        Returns:
            Dict[str, Any]: 系统错误结果
        """
        return {
            "program_status": sc.PROGRAM_SYSTEM_ERROR,
            "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR),
            "time": 0,
            "memory": 0,
            "message": error_info.get("error_message", "工作目录管理错误"),
            "error_details": error_info
        }
