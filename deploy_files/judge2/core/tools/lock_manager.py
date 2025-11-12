#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 跨容器系统级读写锁管理器
基于fcntl的高性能读写锁实现
"""

import os
import sys
import time
import fcntl
import signal
import atexit
import logging
from typing import Dict, Any, Optional, Set

# 将当前目录和父目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)

from .debug_manager import is_debug_enabled, log_error_with_context


class LockManager:
    """跨容器系统级读写锁管理器 - 基于fcntl实现"""
    
    def __init__(self, base_dir: str = None, logger: logging.Logger = None):
        """
        初始化锁管理器
        
        Args:
            base_dir: 锁文件存储的基础目录，默认为/tmp
            logger: 日志记录器
        """
        self.logger = logger or logging.getLogger(self.__class__.__name__)
        
        # 确定锁文件存储目录
        if base_dir is None:
            # 使用系统临时目录，确保跨容器可见
            self.lock_base_dir = "/tmp/csgoj_locks"
        else:
            self.lock_base_dir = os.path.join(base_dir, "locks")
        
        os.makedirs(self.lock_base_dir, exist_ok=True)
        
        # 进程信息
        self.process_id = os.getpid()
        self.container_id = self._get_container_id()
        self.hostname = os.uname().nodename
        
        # 当前进程持有的锁文件句柄
        self.held_locks: Dict[str, int] = {}  # resource_path -> file_descriptor
        
        # 注册清理函数
        atexit.register(self._cleanup_all_locks)
        signal.signal(signal.SIGTERM, self._signal_handler)
        signal.signal(signal.SIGINT, self._signal_handler)
        
        if is_debug_enabled():
            self.logger.debug(f"锁管理器初始化完成: PID={self.process_id}, 容器={self.container_id}, 主机={self.hostname}")
    
    def _get_container_id(self) -> str:
        """获取容器ID"""
        try:
            # 尝试从cgroup获取容器ID
            with open('/proc/self/cgroup', 'r') as f:
                for line in f:
                    if 'docker' in line or 'containerd' in line:
                        parts = line.strip().split('/')
                        if len(parts) > 2:
                            return parts[-1][:12]  # 取前12位
        except:
            pass
        
        # 如果无法获取容器ID，使用主机名+进程ID
        return f"{os.uname().nodename}_{self.process_id}"
    
    def _get_lock_file_path(self, resource_path: str, lock_type: str) -> str:
        """获取锁文件路径"""
        # 使用资源路径的哈希值作为文件名，避免路径过长
        import hashlib
        resource_hash = hashlib.md5(resource_path.encode()).hexdigest()
        return os.path.join(self.lock_base_dir, f"{resource_hash}_{lock_type}.lock")
    
    def _has_read_lock(self, read_lock_file: str) -> bool:
        """检查是否有读锁存在"""
        try:
            if not os.path.exists(read_lock_file):
                return False
            
            # 尝试以非阻塞方式获取共享锁
            with open(read_lock_file, 'r') as f:
                try:
                    fcntl.flock(f.fileno(), fcntl.LOCK_SH | fcntl.LOCK_NB)
                    # 如果能获取到锁，说明没有其他进程持有锁
                    fcntl.flock(f.fileno(), fcntl.LOCK_UN)
                    return False
                except (OSError, IOError) as e:
                    if e.errno == 11:  # EAGAIN - 资源被占用
                        return True
                    return False
        except Exception:
            return False
    
    def _has_write_lock(self, write_lock_file: str) -> bool:
        """检查是否有写锁存在"""
        try:
            if not os.path.exists(write_lock_file):
                return False
            
            # 尝试以非阻塞方式获取排他锁
            with open(write_lock_file, 'r') as f:
                try:
                    fcntl.flock(f.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
                    # 如果能获取到锁，说明没有其他进程持有锁
                    fcntl.flock(f.fileno(), fcntl.LOCK_UN)
                    return False
                except (OSError, IOError) as e:
                    if e.errno == 11:  # EAGAIN - 资源被占用
                        return True
                    return False
        except Exception:
            return False
    
    def _signal_handler(self, signum, frame):
        """信号处理器，确保进程退出时清理锁"""
        self.logger.info(f"收到信号 {signum}，清理所有锁...")
        self._cleanup_all_locks()
        sys.exit(0)
    
    def _cleanup_all_locks(self):
        """清理当前进程持有的所有锁"""
        try:
            for resource_path, fd in list(self.held_locks.items()):
                try:
                    fcntl.flock(fd, fcntl.LOCK_UN)  # 释放锁
                    os.close(fd)  # 关闭文件句柄
                except Exception as e:
                    self.logger.warning(f"释放锁时发生错误: {e}")
            
            self.held_locks.clear()
            if is_debug_enabled():
                self.logger.debug("进程退出时清理所有锁完成")
        except Exception as e:
            self.logger.error(f"清理锁时发生错误: {e}")
    
    
    def acquire_read_lock(self, resource_path: str, timeout: int = 300) -> bool:
        """
        获取读锁（共享锁）
        如果有写锁存在，则等待写锁释放
        
        Args:
            resource_path: 资源路径
            timeout: 超时时间（秒）
            
        Returns:
            bool: 是否成功获取锁
        """
        try:
            # 检查是否已经持有读锁
            if resource_path in self.held_locks:
                if is_debug_enabled():
                    self.logger.debug(f"已经持有读锁: {resource_path}")
                return True
            
            # 获取锁文件路径
            read_lock_file = self._get_lock_file_path(resource_path, "read")
            write_lock_file = self._get_lock_file_path(resource_path, "write")
            
            # 打开读锁文件
            read_fd = os.open(read_lock_file, os.O_CREAT | os.O_RDWR, 0o644)
            
            # 尝试获取共享锁（读锁）
            start_time = time.time()
            while time.time() - start_time < timeout:
                try:
                    # 检查是否有写锁存在
                    if self._has_write_lock(write_lock_file):
                        if is_debug_enabled():
                            self.logger.debug(f"检测到写锁存在，等待写锁释放: {resource_path}")
                        time.sleep(0.1)
                        continue
                    
                    # 非阻塞方式尝试获取共享锁
                    fcntl.flock(read_fd, fcntl.LOCK_SH | fcntl.LOCK_NB)
                    
                    # 成功获取锁
                    self.held_locks[resource_path] = read_fd
                    if is_debug_enabled():
                        self.logger.debug(f"成功获取读锁: {resource_path}")
                    return True
                    
                except (OSError, IOError) as e:
                    if e.errno == 11:  # EAGAIN - 资源暂时不可用
                        # 锁被占用，等待后重试
                        time.sleep(0.1)
                        continue
                    else:
                        # 其他错误
                        os.close(read_fd)
                        raise
            
            # 超时
            os.close(read_fd)
            self.logger.warning(f"获取读锁超时: {resource_path}")
            return False
            
        except Exception as e:
            self.logger.error(f"获取读锁时发生错误: {e}")
            return False
    
    def acquire_write_lock(self, resource_path: str, timeout: int = 600) -> bool:
        """
        获取写锁（排他锁）
        如果有读锁或写锁存在，则等待所有锁释放
        
        Args:
            resource_path: 资源路径
            timeout: 超时时间（秒）
            
        Returns:
            bool: 是否成功获取锁
        """
        try:
            # 检查是否已经持有写锁
            if resource_path in self.held_locks:
                if is_debug_enabled():
                    self.logger.debug(f"已经持有写锁: {resource_path}")
                return True
            
            # 获取锁文件路径
            read_lock_file = self._get_lock_file_path(resource_path, "read")
            write_lock_file = self._get_lock_file_path(resource_path, "write")
            
            # 打开写锁文件
            write_fd = os.open(write_lock_file, os.O_CREAT | os.O_RDWR, 0o644)
            
            # 尝试获取排他锁（写锁）
            start_time = time.time()
            while time.time() - start_time < timeout:
                try:
                    # 检查是否有读锁或写锁存在
                    if self._has_read_lock(read_lock_file) or self._has_write_lock(write_lock_file):
                        if is_debug_enabled():
                            self.logger.debug(f"检测到读锁或写锁存在，等待锁释放: {resource_path}")
                        time.sleep(0.1)
                        continue
                    
                    # 非阻塞方式尝试获取排他锁
                    fcntl.flock(write_fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
                    
                    # 成功获取锁
                    self.held_locks[resource_path] = write_fd
                    if is_debug_enabled():
                        self.logger.debug(f"成功获取写锁: {resource_path}")
                    return True
                    
                except (OSError, IOError) as e:
                    if e.errno == 11:  # EAGAIN - 资源暂时不可用
                        # 锁被占用，等待后重试
                        time.sleep(0.1)
                        continue
                    else:
                        # 其他错误
                        os.close(write_fd)
                        raise
            
            # 超时
            os.close(write_fd)
            self.logger.warning(f"获取写锁超时: {resource_path}")
            return False
            
        except Exception as e:
            self.logger.error(f"获取写锁时发生错误: {e}")
            return False
    
    def release_read_lock(self, resource_path: str) -> bool:
        """释放读锁"""
        return self._release_lock(resource_path)
    
    def release_write_lock(self, resource_path: str) -> bool:
        """释放写锁"""
        return self._release_lock(resource_path)
    
    def _release_lock(self, resource_path: str) -> bool:
        """释放锁的内部方法"""
        try:
            if resource_path not in self.held_locks:
                if is_debug_enabled():
                    self.logger.debug(f"没有持有锁: {resource_path}")
                return True
            
            fd = self.held_locks[resource_path]
            
            # 释放锁
            fcntl.flock(fd, fcntl.LOCK_UN)
            
            # 关闭文件句柄
            os.close(fd)
            
            # 从持有锁列表中移除
            del self.held_locks[resource_path]
            
            if is_debug_enabled():
                self.logger.debug(f"成功释放锁: {resource_path}")
            return True
            
        except Exception as e:
            self.logger.error(f"释放锁时发生错误: {e}")
            return False
    
    def get_lock_status(self, resource_path: str) -> Dict[str, Any]:
        """获取锁状态信息（简化版本）"""
        try:
            # 检查是否持有锁
            has_lock = resource_path in self.held_locks
            
            return {
                "has_lock": has_lock,
                "process_id": self.process_id,
                "container_id": self.container_id,
                "hostname": self.hostname
            }
                
        except Exception as e:
            self.logger.error(f"获取锁状态时发生错误: {e}")
            return {"has_lock": False}
    
    def cleanup_resource_locks(self, resource_path: str) -> bool:
        """清理指定资源的所有锁"""
        try:
            # 释放当前进程持有的锁
            if resource_path in self.held_locks:
                self._release_lock(resource_path)
            
            # 删除锁文件
            read_lock_file = self._get_lock_file_path(resource_path, "read")
            write_lock_file = self._get_lock_file_path(resource_path, "write")
            
            for lock_file in [read_lock_file, write_lock_file]:
                if os.path.exists(lock_file):
                    try:
                        os.unlink(lock_file)
                    except Exception as e:
                        self.logger.warning(f"删除锁文件失败: {e}")
            
            self.logger.info(f"清理了资源的所有锁: {resource_path}")
            return True
            
        except Exception as e:
            self.logger.error(f"清理资源锁时发生错误: {e}")
            return False


# 全局锁管理器实例
_global_lock_manager: Optional[LockManager] = None


def get_lock_manager(logger: logging.Logger = None) -> LockManager:
    """获取全局锁管理器实例"""
    global _global_lock_manager
    if _global_lock_manager is None:
        _global_lock_manager = LockManager(logger=logger)
    return _global_lock_manager
