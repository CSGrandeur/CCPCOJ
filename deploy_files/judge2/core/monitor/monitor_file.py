#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
文件系统隔离监控器
提供基于chroot的文件系统隔离和输出文件大小监控
"""

import os
import sys
import logging
import subprocess
import shutil
from typing import List

# 将当前目录和父目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)

from tools.debug_manager import is_debug_enabled
from tools.judge_lib_manager import get_judge_lib_manager
from tools import status_constants as sc


class MonitorFile:
    """文件系统隔离监控器"""
    
    def __init__(self, work_dir: str, language: str, task_type: str = None, logger: logging.Logger = None):
        self.work_dir = work_dir  # 就是run0目录
        self.language = language
        self.task_type = task_type or sc.TASK_TYPE_PLAYER_COMPILE
        self.mount_points = []  # 记录所有挂载点路径
        self.mount_info = {}  # 记录挂载点详细信息 {mount_path: {'type': 'system/language/judge_lib', 'src': src_path}}
        self.output_limit_bytes = 0
        self._cleaned = False  # 标记是否已清理，避免重复清理
        
        # 设置日志记录器
        if logger is None:
            from tools.debug_manager import setup_unified_logging, DebugManager
            # 确保 DebugManager 已初始化
            if DebugManager._log_dir is None:
                DebugManager.init_debug_manager(debug_mode=False, log_dir="/tmp/logs")
            self.logger = setup_unified_logging("MonitorFile")
        else:
            self.logger = logger
    
    def setup_jail(self, output_file: str = None):
        """设置chroot环境"""
        if not self.work_dir:
            raise RuntimeError("工作目录未设置")
        
        try:
            # 在run0目录下创建系统目录结构
            self._create_system_directories()
            
            # 挂载系统依赖
            self._mount_system_dependencies()
            
            # 挂载语言依赖
            self._mount_language_dependencies()
            
            # 挂载 judge_lib（如果是 TPJ 编译）
            self._mount_judge_lib()
            
            if is_debug_enabled():
                self.logger.debug(f"文件系统chroot环境设置完成: {self.work_dir}")
        except Exception as e:
            # 在非root环境下，挂载会失败，这是正常的
            if is_debug_enabled():
                self.logger.debug(f"文件系统chroot环境设置失败（非root环境）: {e}")
            # 不抛出异常，允许程序继续运行
        
        # 设置输出大小限制（这个总是可以设置的）
        self._set_output_limit(output_file)
    
    def _create_system_directories(self):
        """在run0目录下创建系统目录结构"""
        system_dirs = ["bin", "lib", "lib64", "usr", "etc", "dev", "proc", "sys", "tmp"]
        for dir_name in system_dirs:
            dir_path = os.path.join(self.work_dir, dir_name)
            if not os.path.exists(dir_path):
                os.makedirs(dir_path, exist_ok=True)
    
    def _mount_single_point(self, src_path: str, dst_path: str, mount_type: str = "unknown") -> bool:
        """
        挂载单个挂载点（统一接口，确保记录和挂载的原子性）
        
        Args:
            src_path: 源路径（系统路径）
            dst_path: 目标路径（chroot 内的路径）
            mount_type: 挂载类型（'system', 'language', 'judge_lib'）
            
        Returns:
            bool: 是否成功挂载（包括已挂载的情况）
        """
        # 检查目标路径是否存在
        if not os.path.exists(dst_path):
            # 创建目录（如果不存在）
            os.makedirs(dst_path, exist_ok=True)
        
        # 检查是否已挂载（幂等性）
        if self._is_mounted(dst_path):
            if is_debug_enabled():
                self.logger.debug(f"挂载点已存在，跳过: {src_path} -> {dst_path}")
            # 即使已挂载，也记录到列表（可能之前记录丢失）
            if dst_path not in self.mount_points:
                self.mount_points.append(dst_path)
                self.mount_info[dst_path] = {'type': mount_type, 'src': src_path}
            return True
        
        try:
            # 执行挂载
            subprocess.run(["mount", "--bind", src_path, dst_path], 
                         check=True, 
                         capture_output=True,
                         timeout=5)
            
            # 挂载成功后才记录（原子性保证）
            if dst_path not in self.mount_points:
                self.mount_points.append(dst_path)
                self.mount_info[dst_path] = {'type': mount_type, 'src': src_path}
            
            if is_debug_enabled():
                self.logger.debug(f"挂载成功 [{mount_type}]: {src_path} -> {dst_path}")
            return True
            
        except subprocess.CalledProcessError as e:
            # 挂载失败，不记录
            if is_debug_enabled():
                self.logger.debug(f"挂载失败 [{mount_type}]: {src_path} -> {dst_path}, 错误: {e}")
            return False
        except subprocess.TimeoutExpired:
            if is_debug_enabled():
                self.logger.debug(f"挂载超时 [{mount_type}]: {src_path} -> {dst_path}")
            return False
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"挂载异常 [{mount_type}]: {src_path} -> {dst_path}, 错误: {e}")
            return False
    
    def _mount_system_dependencies(self):
        """挂载系统依赖到run0目录下，根据任务类型决定挂载内容"""
        # 编译任务：需要完整的系统环境（编译器、工具等）
        if self.task_type in [sc.TASK_TYPE_PLAYER_COMPILE, sc.TASK_TYPE_TPJ_COMPILE, 
                              sc.TASK_TYPE_INTERACTIVE_COMPILE]:
            system_dirs = ["/bin", "/lib", "/lib64", "/usr", "/etc", "/dev"]
        # 运行任务（选手程序）：只需要动态库，不需要可执行程序目录
        elif self.task_type == sc.TASK_TYPE_PLAYER_RUN:
            # 只挂载动态库和必要的设备文件，不挂载 /bin, /usr/bin 等可执行程序目录
            # 注意：/usr/lib 和 /usr/lib64 也是动态库目录，需要挂载
            system_dirs = ["/lib", "/lib64", "/usr/lib", "/usr/lib64", "/dev"]
        # TPJ 运行：可能需要更多系统支持，但主要依赖通过 judge_lib 挂载
        else:
            # 其他运行任务（TPJ_RUN, INTERACTIVE_RUN 等）：只挂载动态库
            system_dirs = ["/lib", "/lib64", "/usr/lib", "/usr/lib64", "/dev"]
        
        for src_dir in system_dirs:
            if os.path.exists(src_dir):
                dst_path = os.path.join(self.work_dir, src_dir[1:])  # 去掉开头的/
                self._mount_single_point(src_dir, dst_path, mount_type="system")
    
    def _mount_language_dependencies(self):
        """挂载语言依赖到run0目录下"""
        if not self.language:
            return
        
        # 获取语言依赖目录
        from lang.lang_factory import LanguageFactory
        lang_handler = LanguageFactory.create_language_handler(self.language, {})
        
        if not lang_handler:
            return
        
        # 根据任务类型选择依赖目录
        if self.task_type in [sc.TASK_TYPE_PLAYER_COMPILE, sc.TASK_TYPE_TPJ_COMPILE]:
            deps = lang_handler.get_compile_dependency_dirs()
        else:
            deps = lang_handler.get_runtime_dependency_dirs()
        
        for dep_dir in deps:
            if os.path.exists(dep_dir):
                dst_path = os.path.join(self.work_dir, dep_dir[1:])  # 去掉开头的/
                self._mount_single_point(dep_dir, dst_path, mount_type="language")
    
    def _mount_judge_lib(self):
        """挂载 judge_lib 到 chroot 环境中"""
        try:
            # 获取 judge_lib 管理器
            judge_lib_manager = get_judge_lib_manager()
            judge_lib_dir = judge_lib_manager.get_judge_lib_dir()
            chroot_mount_path = judge_lib_manager.get_mount_path_for_chroot()
            
            # 检查是否需要挂载 judge_lib
            if self.task_type == sc.TASK_TYPE_TPJ_COMPILE and os.path.exists(judge_lib_dir):
                # 在 chroot 环境中创建挂载点
                dst_path = os.path.join(self.work_dir, chroot_mount_path[1:])  # 去掉开头的/
                self._mount_single_point(judge_lib_dir, dst_path, mount_type="judge_lib")
                    
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"挂载 judge_lib 失败: {e}")
            # 挂载失败不应该影响程序运行
    
    def _set_output_limit(self, output_file: str):
        """设置输出大小限制"""
        if output_file and os.path.exists(output_file):
            answer_size = os.path.getsize(output_file)
            self.output_limit_bytes = max(30 * 1024 * 1024, answer_size * 4)  # MB * 1024 * 1024
        else:
            self.output_limit_bytes = 30 * 1024 * 1024
    
    def get_output_limit(self) -> int:
        """获取输出大小限制"""
        return self.output_limit_bytes
    
    def _mount_virtual_filesystem(self, vfs_type: str, mount_point: str):
        """
        挂载虚拟文件系统（proc/sys），如果已挂载则跳过（幂等性）
        
        Args:
            vfs_type: 文件系统类型 ("proc" 或 "sysfs")
            mount_point: 挂载点路径（相对于chroot根目录，如 "/proc" 或 "/sys"）
        """
        # 检查是否已挂载（如果/proc已挂载，可以通过/proc/mounts检查）
        try:
            with open("/proc/mounts", "r") as f:
                for line in f:
                    parts = line.strip().split()
                    if len(parts) >= 2 and parts[1] == mount_point:
                        # 已挂载，跳过
                        return
        except (FileNotFoundError, PermissionError, OSError):
            # /proc/mounts不可读（可能/proc未挂载或权限问题），继续尝试挂载
            pass
        
        # 尝试挂载（静默处理错误，避免错误信息泄露到进程stderr）
        # 如果已挂载，mount会返回非0，但check=False确保不会抛异常
        subprocess.run(
            ["mount", "-t", vfs_type, vfs_type, mount_point],
            check=False,
            stderr=subprocess.DEVNULL,
            stdout=subprocess.DEVNULL
        )
    
    def apply_chroot(self):
        """应用chroot（在子进程中调用）"""
        if not self.work_dir or not os.path.exists(self.work_dir):
            raise RuntimeError(f"工作目录不存在: {self.work_dir}")
        
        try:
            # 直接chroot到run0目录
            os.chroot(self.work_dir)
            os.chdir("/")  # 切换到根目录
            
            # 挂载proc和sys（幂等性：如果已挂载则跳过）
            # 先挂载/proc（如果/proc未挂载，检查会失败，但仍会尝试挂载）
            self._mount_virtual_filesystem("proc", "/proc")
            # 再挂载/sys（此时/proc已挂载，可以正常检查/sys是否已挂载）
            self._mount_virtual_filesystem("sysfs", "/sys")
            
        except PermissionError:
            # 没有root权限，chroot失败，这是正常的
            # 在非root环境下，我们无法使用chroot，但不应该影响程序运行
            pass
        except Exception as e:
            # 其他chroot相关错误，也不应该影响程序运行
            pass
    
    def cleanup(self):
        """清理挂载点 - 确保在finally中执行，避免重复清理"""
        # 避免重复清理
        if self._cleaned:
            if is_debug_enabled():
                self.logger.debug(f"文件监控器已清理过，跳过重复清理")
            return
        
        if is_debug_enabled():
            self.logger.debug(f"开始清理文件监控器，工作目录: {self.work_dir}")
        
        try:
            # 1. 先卸载 proc 和 sys（如果存在）- 使用强检查
            self._unmount_virtual_filesystems()
            
            # 2. 卸载所有记录的挂载点
            self._unmount_recorded_mount_points()
            
            # 3. 扫描并卸载剩余的挂载点（强清理）
            self._unmount_remaining_mount_points()
            
            # 标记为已清理
            self._cleaned = True
            
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"清理挂载点时发生异常: {e}")
        
        if is_debug_enabled():
            self.logger.debug(f"文件监控器清理完成")
    
    def _is_mounted(self, mount_path: str) -> bool:
        """检查路径是否已挂载"""
        try:
            # 方法1：使用 findmnt 命令（更准确）
            try:
                result = subprocess.run(["findmnt", mount_path], 
                                      capture_output=True, 
                                      timeout=1)
                if result.returncode == 0:
                    return True
            except (subprocess.TimeoutExpired, FileNotFoundError):
                pass
            
            # 方法2：fallback 到读取 /proc/mounts
            try:
                with open("/proc/mounts", "r") as f:
                    mounts = f.read()
                    # 检查精确匹配或作为前缀
                    for line in mounts.split('\n'):
                        if not line.strip():
                            continue
                        parts = line.split()
                        if len(parts) >= 2:
                            mount_point = parts[1]
                            if mount_point == mount_path or mount_path.startswith(mount_point + '/'):
                                return True
            except Exception:
                pass
            
            return False
        except Exception:
            return False
    
    def _unmount_single_point(self, mount_path: str, mount_type: str = "挂载点", 
                              check_mounted: bool = True, use_lazy: bool = True, 
                              verbose_log: bool = True) -> bool:
        """
        卸载单个挂载点（通用方法）
        
        Args:
            mount_path: 挂载点路径
            mount_type: 挂载点类型描述（用于日志）
            check_mounted: 是否先检查挂载状态（默认True）
            use_lazy: 失败后是否使用lazy unmount（默认True）
            verbose_log: 是否输出详细日志（默认True，批量卸载时可设为False减少日志）
            
        Returns:
            bool: 是否成功卸载（包括挂载点不存在的情况也返回True）
        """
        # 检查路径是否存在
        if not os.path.exists(mount_path):
            if verbose_log and is_debug_enabled():
                self.logger.debug(f"{mount_type}不存在，跳过卸载: {mount_path}")
            return True  # 不存在视为成功
        
        # 检查是否已挂载
        if check_mounted and not self._is_mounted(mount_path):
            if verbose_log and is_debug_enabled():
                self.logger.debug(f"{mount_type}未挂载，跳过卸载: {mount_path}")
            return True  # 未挂载视为成功
        
        try:
            # 方法1：尝试正常卸载
            try:
                subprocess.run(["umount", mount_path], check=True, 
                             capture_output=True, timeout=2)
                if verbose_log and is_debug_enabled():
                    self.logger.debug(f"卸载{mount_type}成功: {mount_path}")
                return True
            except subprocess.TimeoutExpired:
                if verbose_log and is_debug_enabled():
                    self.logger.debug(f"卸载{mount_type}超时，尝试 lazy unmount: {mount_path}")
            except subprocess.CalledProcessError as e:
                # exit code 32 表示挂载点不存在（已被内核清理）
                if e.returncode == 32:
                    # 不存在的情况不需要详细日志（批量处理时会产生大量日志）
                    return True  # 不存在视为成功
                if verbose_log and is_debug_enabled():
                    self.logger.debug(f"正常卸载{mount_type}失败，尝试 lazy unmount: {mount_path}, 错误: {e}")
            
            # 方法2：如果启用lazy unmount且正常卸载失败，使用lazy unmount
            if use_lazy:
                try:
                    subprocess.run(["umount", "-l", mount_path], check=True, 
                                 capture_output=True, timeout=2)
                    if verbose_log and is_debug_enabled():
                        self.logger.debug(f"lazy unmount {mount_type}成功: {mount_path}")
                    return True
                except subprocess.CalledProcessError as e:
                    # exit code 32 表示挂载点不存在
                    if e.returncode == 32:
                        # 不存在的情况不需要详细日志
                        return True  # 不存在视为成功
                    if verbose_log and is_debug_enabled():
                        self.logger.debug(f"lazy unmount {mount_type}失败: {mount_path}, 错误: {e}")
                    return False
                except subprocess.TimeoutExpired:
                    if verbose_log and is_debug_enabled():
                        self.logger.debug(f"lazy unmount {mount_type}超时: {mount_path}")
                    return False
            
            return False
            
        except Exception as e:
            if verbose_log and is_debug_enabled():
                self.logger.debug(f"卸载{mount_type}异常: {mount_path}, 错误: {e}")
            return False
    
    def _unmount_multiple_points(self, mount_points: list, mount_type: str = "挂载点",
                                 sort_by_depth: bool = True, verbose: bool = False) -> tuple[int, int]:
        """
        批量卸载多个挂载点（通用方法）
        
        Args:
            mount_points: 挂载点路径列表
            mount_type: 挂载点类型描述（用于日志）
            sort_by_depth: 是否按深度排序（默认True，深层优先）
            verbose: 是否输出每个挂载点的详细日志（默认False，减少日志量）
            
        Returns:
            tuple[int, int]: (成功数量, 失败数量)
        """
        if not mount_points:
            return 0, 0
        
        # 按深度排序，先卸载深层的挂载点
        if sort_by_depth:
            mount_points = sorted(mount_points, key=lambda x: x.count('/'), reverse=True)
        
        if is_debug_enabled():
            self.logger.debug(f"开始卸载 {len(mount_points)} 个{mount_type}")
        
        success_count = 0
        fail_count = 0
        
        for mount_point in mount_points:
            # 如果 verbose=False，仅在真正卸载时才输出日志
            success = self._unmount_single_point(mount_point, mount_type, verbose_log=verbose)
            if success:
                success_count += 1
            else:
                fail_count += 1
        
        if is_debug_enabled():
            self.logger.debug(f"卸载{mount_type}完成: 成功 {success_count}, 失败 {fail_count}")
        
        return success_count, fail_count
    
    def _unmount_virtual_filesystems(self):
        """卸载虚拟文件系统（proc, sys）"""
        if not self.work_dir:
            return
        
        virtual_fs = ["proc", "sys"]
        mount_points = [os.path.join(self.work_dir, fs) for fs in virtual_fs]
        self._unmount_multiple_points(mount_points, mount_type="虚拟文件系统")
    
    def _unmount_recorded_mount_points(self):
        """卸载记录的挂载点（优先使用记录，最精确）"""
        if not self.mount_points:
            return
        
        if is_debug_enabled():
            mount_types = {}
            for mp in self.mount_points:
                mount_type = self.mount_info.get(mp, {}).get('type', 'unknown')
                mount_types[mount_type] = mount_types.get(mount_type, 0) + 1
            self.logger.debug(f"开始卸载记录的挂载点: 总计 {len(self.mount_points)} 个, 类型分布: {mount_types}")
        
        # 使用通用方法批量卸载
        self._unmount_multiple_points(self.mount_points, mount_type="记录的挂载点")
        
        # 清空挂载点列表和信息
        self.mount_points.clear()
        self.mount_info.clear()
    
    def _unmount_remaining_mount_points(self):
        """扫描并卸载剩余的挂载点"""
        if not self.work_dir or not os.path.exists(self.work_dir):
            return
        
        if is_debug_enabled():
            self.logger.debug(f"扫描工作目录中的剩余挂载点: {self.work_dir}")
        
        # 获取当前系统的挂载点信息
        try:
            # 优先使用 findmnt 命令（更准确、格式统一）
            try:
                result = subprocess.run(
                    ["findmnt", "-n", "-o", "TARGET"],
                    capture_output=True, 
                    text=True, 
                    check=True,
                    timeout=5
                )
                mount_points = []
                for line in result.stdout.split('\n'):
                    line = line.strip()
                    if line and line.startswith(self.work_dir):
                        mount_points.append(line)
                
                # 去重
                work_dir_mounts = list(set(mount_points))
                
            except (subprocess.CalledProcessError, FileNotFoundError, subprocess.TimeoutExpired):
                # fallback 到 mount 命令
                result = subprocess.run(["mount"], capture_output=True, text=True, check=True)
                mount_lines = result.stdout.split('\n')
                
                # 查找工作目录下的挂载点（使用 set 去重）
                work_dir_mounts_set = set()
                for line in mount_lines:
                    if self.work_dir not in line:
                        continue
                    
                    # 解析 mount 命令输出格式：
                    # 格式：<source> on <mount_point> type <type> (<options>)
                    # 或：<source> on <mount_point> type <type>
                    parts = line.split()
                    
                    # 查找 "on" 关键字后面的挂载点
                    try:
                        on_index = parts.index("on")
                        if on_index + 1 < len(parts):
                            mount_point = parts[on_index + 1]
                            # 过滤出以 work_dir 开头的挂载点
                            if mount_point.startswith(self.work_dir):
                                work_dir_mounts_set.add(mount_point)
                    except ValueError:
                        # 没有找到 "on" 关键字，可能是旧格式或特殊格式，跳过
                        continue
                
                work_dir_mounts = list(work_dir_mounts_set)
            
            if not work_dir_mounts:
                if is_debug_enabled():
                    self.logger.debug(f"未发现工作目录下的剩余挂载点")
                return
            
            if is_debug_enabled():
                self.logger.debug(f"发现 {len(work_dir_mounts)} 个剩余挂载点（已去重）")
            
            # 使用通用方法批量卸载（verbose=False 减少日志输出）
            self._unmount_multiple_points(work_dir_mounts, mount_type="剩余挂载点", verbose=False)
                        
        except subprocess.CalledProcessError as e:
            if is_debug_enabled():
                self.logger.debug(f"获取挂载点信息失败: {e}")
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"扫描挂载点时发生异常: {e}")
    
    def _cleanup_unknown_mount_points(self):
        """清理未知的挂载点（当没有mount_points记录时使用）"""
        if not self.work_dir or not os.path.exists(self.work_dir):
            return
        
        # 检查常见的挂载点
        mount_point_names = ["bin", "lib", "lib64", "usr", "etc", "dev", "proc", "sys", "tmp"]
        mount_points = [os.path.join(self.work_dir, name) for name in mount_point_names]
        
        # 使用通用方法批量卸载（只使用lazy unmount，因为这是未知挂载点）
        for mount_path in mount_points:
            self._unmount_single_point(mount_path, mount_type="未知挂载点", 
                                     check_mounted=False, use_lazy=True)


def create_monitor_file(work_dir: str, language: str, task_type: str = None, logger: logging.Logger = None) -> MonitorFile:
    """创建文件系统监控器"""
    return MonitorFile(work_dir, language, task_type, logger)