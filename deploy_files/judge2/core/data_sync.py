#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 数据同步模块
支持gz压缩和哈希校验的数据同步系统
"""

import os
import sys
import json
import time
import hashlib
import gzip
import shutil
import logging
import tempfile
import argparse
from typing import Dict, Any, List, Optional, Tuple
from datetime import datetime
import tarfile
import zipfile
import tqdm

# 将当前目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)

from tools.web_client import WebClient
from tools.config_loader import ConfigLoader
from tools.debug_manager import DebugManager, setup_unified_logging, is_debug_enabled, log_error_with_context
from tools.lock_manager import get_lock_manager

class DataSync:
    def __init__(self, config: Dict[str, Any]):
        """初始化数据同步器"""
        self.config = config
        self.logger = setup_unified_logging("DataSync", config)
        self.web_client = WebClient(config)
        
        # 初始化锁管理器
        self.lock_manager = get_lock_manager(self.logger)
        
        # 确保数据目录存在，使用相对于当前文件所在目录的路径
        base_dir = os.path.dirname(os.path.abspath(__file__))
        data_dir = self.config.get("judge", {}).get("data_dir", "data")
        if not os.path.isabs(data_dir):
            data_dir = os.path.join(base_dir, data_dir)
        os.makedirs(data_dir, exist_ok=True)
        
    def calculate_file_hash(self, file_path: str, algorithm: str = 'sha256') -> str:
        """计算文件哈希值"""
        try:
            hash_obj = hashlib.new(algorithm)
            with open(file_path, 'rb') as f:
                for chunk in iter(lambda: f.read(4096), b""):
                    hash_obj.update(chunk)
            return hash_obj.hexdigest()
        except Exception as e:
            self.logger.error(f"计算文件哈希失败 {file_path}: {e}")
            return ""
    
    def is_judge_file(self, file_path: str) -> bool:
        """判断是否为评测相关文件"""
        try:
            filename = os.path.basename(file_path)
            name, ext = os.path.splitext(filename)
            ext = ext.lower()
            
            # 只包含评测相关文件：.in, .out, .cc, .c
            if ext in ['.in', '.out', '.cc', '.c']:
                return True
            
            # 排除不需要的文件
            exclude_files = [
                'datalock',  # 数据锁文件
                'tpj',       # 可执行程序
                '.hash',     # 哈希文件
                '.lock',     # 锁文件
                '.tmp',      # 临时文件
                '.bak',      # 备份文件
                '.swp',      # vim交换文件
                '.swo',      # vim交换文件
            ]
            
            if filename in exclude_files:
                return False
            
            # 排除可执行文件（没有扩展名且可执行）
            if not ext and os.access(file_path, os.X_OK):
                return False
            
            return False  # 默认不包含其他文件
            
        except Exception as e:
            self.logger.error(f"判断文件类型失败 {file_path}: {e}")
            return False
    
    def calculate_directory_hash(self, dir_path: str, algorithm: str = 'sha256') -> str:
        """计算目录哈希值（只包含评测相关文件）"""
        try:
            hash_obj = hashlib.new(algorithm)
            
            # 遍历目录中的所有文件
            for root, dirs, files in os.walk(dir_path):
                # 按文件名排序确保一致性
                files.sort()
                for file in files:
                    file_path = os.path.join(root, file)
                    if os.path.isfile(file_path):
                        # 只处理评测相关文件
                        if self.is_judge_file(file_path):
                            # 添加文件路径到哈希
                            rel_path = os.path.relpath(file_path, dir_path)
                            hash_obj.update(rel_path.encode('utf-8'))
                            
                            # 添加文件内容到哈希
                            with open(file_path, 'rb') as f:
                                for chunk in iter(lambda: f.read(4096), b""):
                                    hash_obj.update(chunk)
            
            return hash_obj.hexdigest()
        except Exception as e:
            self.logger.error(f"计算目录哈希失败 {dir_path}: {e}")
            return ""
    
    def compress_directory(self, source_dir: str, output_file: str, compression: str = 'gzip') -> bool:
        """压缩目录"""
        try:
            if compression == 'gzip':
                # 使用tar.gz格式
                with tarfile.open(output_file, 'w:gz') as tar:
                    tar.add(source_dir, arcname=os.path.basename(source_dir))
            elif compression == 'zip':
                # 使用zip格式
                with zipfile.ZipFile(output_file, 'w', zipfile.ZIP_DEFLATED) as zipf:
                    for root, dirs, files in os.walk(source_dir):
                        for file in files:
                            file_path = os.path.join(root, file)
                            arcname = os.path.relpath(file_path, source_dir)
                            zipf.write(file_path, arcname)
            else:
                self.logger.error(f"不支持的压缩格式: {compression}")
                return False
            
            self.logger.info(f"目录压缩完成: {source_dir} -> {output_file}")
            return True
            
        except Exception as e:
            self.logger.error(f"压缩目录失败: {e}")
            return False
    
    def decompress_file(self, compressed_file: str, extract_dir: str, compression: str = 'gzip') -> bool:
        """解压文件"""
        try:
            os.makedirs(extract_dir, exist_ok=True)
            
            if compression == 'gzip':
                # 解压tar.gz格式
                with tarfile.open(compressed_file, 'r:gz') as tar:
                    # 检查是否有顶层目录
                    members = tar.getmembers()
                    if members:
                        # 获取第一个成员的路径
                        first_member = members[0]
                        if first_member.isdir():
                            # 如果第一个成员是目录，说明有顶层目录
                            # 需要调整解压路径，避免多套一层目录
                            parent_dir = os.path.dirname(extract_dir)
                            temp_dir = os.path.join(parent_dir, 'temp_extract')
                            tar.extractall(temp_dir)
                            
                            # 移动文件到正确位置
                            source_dir = os.path.join(temp_dir, first_member.name)
                            if os.path.exists(source_dir):
                                # 移动所有文件到目标目录
                                for item in os.listdir(source_dir):
                                    src = os.path.join(source_dir, item)
                                    dst = os.path.join(extract_dir, item)
                                    if os.path.isdir(src):
                                        shutil.copytree(src, dst, dirs_exist_ok=True)
                                    else:
                                        shutil.copy2(src, dst)
                                
                                # 清理临时目录
                                shutil.rmtree(temp_dir)
                            else:
                                # 如果没有找到预期的目录结构，直接解压
                                tar.extractall(extract_dir)
                        else:
                            # 没有顶层目录，直接解压
                            tar.extractall(extract_dir)
                    else:
                        # 空压缩包
                        return True
            elif compression == 'zip':
                # 解压zip格式
                with zipfile.ZipFile(compressed_file, 'r') as zipf:
                    zipf.extractall(extract_dir)
            else:
                self.logger.error(f"不支持的压缩格式: {compression}")
                return False
            
            self.logger.info(f"文件解压完成: {compressed_file} -> {extract_dir}")
            return True
            
        except Exception as e:
            self.logger.error(f"解压文件失败: {e}")
            return False
    
    def sync_problem_data(self, problem_id: int, force_update: bool = False) -> bool:
        """同步题目数据（基于文件哈希的增量同步，带写锁保护）"""
        local_data_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
        write_lock_acquired = False
        
        try:
            self.logger.info(f"开始同步题目 {problem_id} 的数据")
            
            # 获取远程文件列表和哈希
            remote_files = self.web_client.get_problem_files(problem_id)
            if not remote_files:
                self.logger.error(f"无法获取题目 {problem_id} 的远程文件列表")
                return False
            
            # 智能检查是否需要更新（基于文件哈希）
            if not force_update and self.should_skip_sync(problem_id, remote_files, local_data_dir):
                self.logger.info(f"题目 {problem_id} 数据已是最新版本")
                return True
            
            # 需要更新数据，获取写锁
            self.logger.info(f"题目 {problem_id} 需要更新数据，等待获取写锁...")
            if not self.lock_manager.acquire_write_lock(local_data_dir, timeout=600):
                self.logger.error(f"获取题目 {problem_id} 写锁失败，同步中止")
                return False
            
            write_lock_acquired = True
            self.logger.info(f"成功获取题目 {problem_id} 写锁，开始数据同步")
            
            # 增量同步：只下载变化的文件
            if not self.sync_problem_files_incremental(problem_id, remote_files, local_data_dir):
                self.logger.error(f"增量同步题目 {problem_id} 数据失败")
                return False
            
            self.logger.info(f"题目 {problem_id} 数据同步完成")
            return True
            
        except Exception as e:
            self.logger.error(f"同步题目 {problem_id} 数据失败: {e}")
            return False
        finally:
            # 释放写锁
            if write_lock_acquired:
                if self.lock_manager.release_write_lock(local_data_dir):
                    self.logger.info(f"已释放题目 {problem_id} 写锁")
                else:
                    self.logger.warning(f"释放题目 {problem_id} 写锁失败")
    
    def get_remote_data_hash(self, problem_id: int) -> Optional[str]:
        """获取远程数据哈希"""
        try:
            # 使用WebClient获取数据哈希
            result = self.web_client._make_request('GET', 'getdatahash', params={"problem_id": problem_id})
            return self.web_client._get_response_data(result, '')
                
        except Exception as e:
            self.logger.error(f"获取远程数据哈希时发生错误: {e}")
        
        return None
    
    def calculate_files_hash(self, files_info: List[Dict[str, Any]]) -> str:
        """计算文件列表的哈希值"""
        try:
            hash_obj = hashlib.sha256()
            
            # 按文件路径排序确保一致性
            sorted_files = sorted(files_info, key=lambda x: x['path'])
            
            for file_info in sorted_files:
                # 添加文件路径到哈希
                hash_obj.update(file_info['path'].encode('utf-8'))
                # 添加文件哈希到哈希
                hash_obj.update(file_info['hash'].encode('utf-8'))
            
            return hash_obj.hexdigest()
        except Exception as e:
            self.logger.error(f"计算文件列表哈希失败: {e}")
            return ""
    
    def should_skip_sync(self, problem_id: int, remote_files: List[Dict[str, Any]], 
                       local_data_dir: str) -> bool:
        """基于文件哈希的校验逻辑（无缓存文件）"""
        try:
            # 1. 快速检查：比较文件数量
            local_judge_files = self.get_local_judge_files(local_data_dir)
            if len(remote_files) != len(local_judge_files):
                self.logger.debug(f"题目 {problem_id} 文件数量不匹配，需要同步")
                return False
            
            # 2. 文件级哈希校验（核心逻辑）
            for remote_file in remote_files:
                file_path = remote_file['path']
                remote_hash = remote_file['hash']
                local_file_path = os.path.join(local_data_dir, file_path)
                
                # 检查文件是否存在
                if not os.path.exists(local_file_path):
                    self.logger.debug(f"题目 {problem_id} 文件 {file_path} 不存在，需要同步")
                    return False
                
                # 计算本地文件哈希
                local_hash = self.calculate_file_hash(local_file_path)
                if local_hash != remote_hash:
                    self.logger.debug(f"题目 {problem_id} 文件 {file_path} 哈希不匹配，需要同步")
                    return False
            
            # 所有检查都通过
            self.logger.debug(f"题目 {problem_id} 数据完整性验证通过，跳过同步")
            return True
            
        except Exception as e:
            self.logger.error(f"检查题目 {problem_id} 同步状态时发生错误: {e}")
            return False
    
    def get_local_judge_files(self, local_data_dir: str) -> List[str]:
        """获取本地评测相关文件列表"""
        try:
            judge_files = []
            
            if not os.path.exists(local_data_dir):
                return judge_files
            
            # 扫描目录，只收集评测相关文件
            for root, dirs, files in os.walk(local_data_dir):
                for filename in files:
                    # 跳过隐藏文件和系统文件
                    if filename.startswith('.'):
                        continue
                    
                    # 检查文件扩展名
                    _, ext = os.path.splitext(filename)
                    ext = ext.lower()
                    
                    if ext in ['.in', '.out', '.cc', '.c']:
                        rel_path = os.path.relpath(os.path.join(root, filename), local_data_dir)
                        judge_files.append(rel_path)
            
            return judge_files
            
        except Exception as e:
            self.logger.error(f"获取本地评测文件列表失败: {e}")
            return []
    
    def sync_problem_files_incremental(self, problem_id: int, remote_files: List[Dict[str, Any]], local_data_dir: str) -> bool:
        """增量同步题目文件"""
        try:
            downloaded_count = 0
            skipped_count = 0
            
            for file_info in remote_files:
                file_path = file_info['path']
                remote_hash = file_info['hash']
                remote_size = file_info['size']
                
                local_file_path = os.path.join(local_data_dir, file_path)
                
                # 检查本地文件是否存在且哈希相同
                if os.path.exists(local_file_path):
                    local_hash = self.calculate_file_hash(local_file_path)
                    if local_hash == remote_hash:
                        self.logger.debug(f"题目 {problem_id} 文件 {file_path} 已是最新版本，跳过")
                        skipped_count += 1
                        continue
                
                # 下载文件
                self.logger.info(f"题目 {problem_id} 下载文件: {file_path}")
                if self.web_client.download_problem_file(problem_id, file_path, local_file_path):
                    downloaded_count += 1
                    self.logger.debug(f"题目 {problem_id} 文件 {file_path} 下载完成")
                    
                    # 如果下载的是 tpj.cc，记录更新（用于 TPJ 编译缓存失效）
                    if file_path == "tpj.cc":
                        self.logger.info(f"题目 {problem_id} 的 tpj.cc 已更新，TPJ 编译缓存将失效")
                else:
                    self.logger.error(f"题目 {problem_id} 文件 {file_path} 下载失败")
                    return False
            
            # 删除本地不在远程文件列表中的文件（仅当远程文件列表不为空时）
            if remote_files:
                deleted_count = self._cleanup_orphaned_files(problem_id, remote_files, local_data_dir)
                if deleted_count > 0:
                    self.logger.info(f"清理了 {deleted_count} 个本地多余文件")
            
            self.logger.info(f"增量同步完成: 下载 {downloaded_count} 个文件，跳过 {skipped_count} 个文件")
            return True
            
        except Exception as e:
            self.logger.error(f"增量同步文件失败: {e}")
            return False
    
    def _cleanup_orphaned_files(self, problem_id: int, remote_files: List[Dict[str, Any]], local_data_dir: str) -> int:
        """
        清理本地不在远程文件列表中的文件
        
        Args:
            problem_id: 题目ID
            remote_files: 远程文件列表
            local_data_dir: 本地数据目录
            
        Returns:
            int: 删除的文件数量
        """
        try:
            if not os.path.exists(local_data_dir):
                return 0
            
            # 获取远程文件路径集合（用于快速查找）
            remote_file_paths = {file_info['path'] for file_info in remote_files}
            
            # 获取本地所有评测相关文件
            local_files = self.get_local_judge_files(local_data_dir)
            
            deleted_count = 0
            
            # 遍历本地文件，删除不在远程列表中的文件
            for local_file_path in local_files:
                if local_file_path not in remote_file_paths:
                    full_path = os.path.join(local_data_dir, local_file_path)
                    try:
                        if os.path.exists(full_path):
                            os.remove(full_path)
                            deleted_count += 1
                            self.logger.info(f"题目 {problem_id} 删除多余文件: {local_file_path}")
                    except Exception as e:
                        self.logger.warning(f"题目 {problem_id} 删除文件失败 {local_file_path}: {e}")
            
            return deleted_count
            
        except Exception as e:
            self.logger.error(f"清理多余文件时发生错误: {e}")
            return 0
    
    def download_problem_data(self, problem_id: int) -> bool:
        """下载题目数据"""
        try:
            # 创建临时文件
            temp_file = tempfile.NamedTemporaryFile(delete=False, suffix='.tar.gz')
            temp_file.close()
            
            # 使用WebClient下载数据
            if self.web_client.download_problem_data(problem_id, temp_file.name):
                # 解压数据
                extract_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
                if self.decompress_file(temp_file.name, extract_dir, 'gzip'):
                    # 清理临时文件
                    os.unlink(temp_file.name)
                    return True
                else:
                    # 清理临时文件
                    os.unlink(temp_file.name)
                    return False
            else:
                self.logger.error(f"下载题目 {problem_id} 数据失败")
                return False
                
        except Exception as e:
            self.logger.error(f"下载题目 {problem_id} 数据时发生错误: {e}")
            return False
    
    def upload_problem_data(self, problem_id: int, data_dir: str) -> bool:
        """上传题目数据"""
        try:
            self.logger.info(f"开始上传题目 {problem_id} 的数据")
            
            # 计算数据哈希
            data_hash = self.calculate_directory_hash(data_dir)
            if not data_hash:
                self.logger.error(f"计算题目 {problem_id} 数据哈希失败")
                return False
            
            # 压缩数据
            temp_file = tempfile.NamedTemporaryFile(delete=False, suffix='.tar.gz')
            temp_file.close()
            
            if not self.compress_directory(data_dir, temp_file.name, 'gzip'):
                os.unlink(temp_file.name)
                return False
            
            # 上传数据
            url = f"{self.config['server']['base_url']}{self.config['server']['api_path']}/uploaddata"
            files = {
                'data': open(temp_file.name, 'rb')
            }
            data = {
                'problem_id': problem_id,
                'hash': data_hash
            }
            
            response = self.session.post(url, files=files, data=data, timeout=120)
            files['data'].close()
            os.unlink(temp_file.name)
            
            if response.status_code == 200:
                result = response.json()
                if result.get("success"):
                    self.logger.info(f"题目 {problem_id} 数据上传成功")
                    return True
                else:
                    self.logger.error(f"上传题目 {problem_id} 数据失败: {result.get('message', '未知错误')}")
            else:
                self.logger.error(f"上传题目 {problem_id} 数据请求失败，状态码: {response.status_code}")
                
        except Exception as e:
            self.logger.error(f"上传题目 {problem_id} 数据时发生错误: {e}")
        
        return False
    
    def sync_all_problems(self, force_update: bool = False, show_progress: bool = True) -> Dict[str, Any]:
        """同步所有题目数据"""
        try:
            self.logger.info("开始同步所有题目数据")
            
            # 获取题目列表
            problems = self.get_problem_list()
            if not problems:
                self.logger.error("无法获取题目列表")
                return {"success": False, "message": "无法获取题目列表"}
            
            # 获取完整数据信息
            self.logger.info("获取题目数据信息...")
            problem_info = self.get_all_problems_info(problems)
            if not problem_info:
                self.logger.error("无法获取题目数据信息")
                return {"success": False, "message": "无法获取题目数据信息"}
            
            # 计算总数据大小
            total_size = sum(info.get("size", 0) for info in problem_info.values())
            self.logger.info(f"需要同步 {len(problems)} 个题目，总大小: {self.format_size(total_size)}")
            
            # 同步每个题目
            results = {
                "success": True,
                "total": len(problems),
                "successful": 0,
                "failed": 0,
                "skipped": 0,
                "total_size": total_size,
                "downloaded_size": 0,
                "details": []
            }
            
            # 创建进度条（固定在底部）
            if show_progress:
                # 使用position=0确保进度条在底部，leave=True保持显示
                pbar = tqdm.tqdm(
                    total=len(problems), 
                    desc="同步题目数据", 
                    unit="个",
                    position=0,
                    leave=True,
                    ncols=80,
                    dynamic_ncols=True,
                    file=sys.stdout,
                    bar_format='{l_bar}{bar}| {n_fmt}/{total_fmt} [{elapsed}<{remaining}, {rate_fmt}]'
                )
                
                # 重定向日志输出到进度条
                class TqdmLoggingHandler(logging.Handler):
                    def __init__(self, tqdm_obj):
                        super().__init__()
                        self.tqdm_obj = tqdm_obj
                    
                    def emit(self, record):
                        msg = self.format(record)
                        # 使用tqdm.write()确保日志在进度条上方显示
                        self.tqdm_obj.write(msg)
                
                # 添加自定义日志处理器
                tqdm_handler = TqdmLoggingHandler(pbar)
                tqdm_handler.setFormatter(logging.Formatter('%(asctime)s - %(levelname)s - %(message)s'))
                self.logger.addHandler(tqdm_handler)
                
                # 临时移除控制台处理器，避免重复输出
                # 移除根日志器的控制台处理器
                root_logger = logging.getLogger()
                console_handlers = [h for h in root_logger.handlers if isinstance(h, logging.StreamHandler) and h.stream == sys.stderr]
                for handler in console_handlers:
                    root_logger.removeHandler(handler)
                
                # 移除当前日志器的控制台处理器
                current_console_handlers = [h for h in self.logger.handlers if isinstance(h, logging.StreamHandler) and h.stream == sys.stderr]
                for handler in current_console_handlers:
                    self.logger.removeHandler(handler)
            
            for problem_id in problems:
                try:
                    # 检查是否需要更新
                    if not force_update and self.is_data_up_to_date(problem_id, problem_info.get(problem_id, {})):
                        results["skipped"] += 1
                        results["details"].append({
                            "problem_id": problem_id,
                            "sync_status": "skipped",
                            "message": "数据已是最新"
                        })
                        if show_progress:
                            pbar.set_postfix({"状态": "跳过"})
                    else:
                        # 同步数据
                        if self.sync_problem_data(problem_id, force_update):
                            results["successful"] += 1
                            results["downloaded_size"] += problem_info.get(problem_id, {}).get("size", 0)
                            results["details"].append({
                                "problem_id": problem_id,
                                "sync_status": "success",
                                "size": problem_info.get(problem_id, {}).get("size", 0)
                            })
                            if show_progress:
                                pbar.set_postfix({"状态": "成功"})
                        else:
                            results["failed"] += 1
                            results["details"].append({
                                "problem_id": problem_id,
                                "sync_status": "failed",
                                "message": "同步失败"
                            })
                            if show_progress:
                                pbar.set_postfix({"状态": "失败"})
                
                except Exception as e:
                    results["failed"] += 1
                    results["details"].append({
                        "problem_id": problem_id,
                        "sync_status": "error",
                        "message": str(e)
                    })
                    if show_progress:
                        pbar.set_postfix({"状态": "错误"})
                
                if show_progress:
                    pbar.update(1)
                    # 刷新进度条显示
                    pbar.refresh()
            
            if show_progress:
                # 清理自定义日志处理器
                for handler in self.logger.handlers[:]:
                    if isinstance(handler, TqdmLoggingHandler):
                        self.logger.removeHandler(handler)
                
                # 恢复控制台处理器
                console_handler = logging.StreamHandler(sys.stderr)
                console_handler.setFormatter(logging.Formatter('%(asctime)s - %(levelname)s - %(message)s'))
                console_handler.setLevel(logging.INFO)
                
                # 恢复到根日志器
                root_logger = logging.getLogger()
                root_logger.addHandler(console_handler)
                
                # 也添加到当前日志器
                self.logger.addHandler(console_handler)
                
                # 关闭进度条
                pbar.close()
            
            if results["failed"] > 0:
                results["success"] = False
            
            self.logger.info(f"同步完成: 成功 {results['successful']}, 跳过 {results['skipped']}, 失败 {results['failed']}/{results['total']}")
            self.logger.info(f"下载数据: {self.format_size(results['downloaded_size'])}/{self.format_size(results['total_size'])}")
            return results
            
        except Exception as e:
            self.logger.error(f"同步所有题目数据时发生错误: {e}")
            return {"success": False, "message": str(e)}
    
    def get_problem_list(self) -> List[int]:
        """获取题目列表"""
        try:
            if is_debug_enabled():
                self.logger.debug("获取题目列表...")
            
            result = self.web_client._make_request('GET', 'getproblemlist')
            problem_list = self.web_client._get_response_data(result, [])
            
            if is_debug_enabled():
                self.logger.debug(f"获取到 {len(problem_list)} 个题目")
                if len(problem_list) <= 10:  # 如果题目数量不多，显示所有题目ID
                    self.logger.debug(f"题目ID列表: {problem_list}")
                else:  # 如果题目很多，只显示前几个
                    self.logger.debug(f"题目ID列表（前10个）: {problem_list[:10]}...")
            
            return problem_list
                
        except Exception as e:
            log_error_with_context(self.logger, "获取题目列表", e)
        
        return []
    
    def verify_data_integrity(self, problem_id: int) -> bool:
        """验证数据完整性"""
        try:
            self.logger.info(f"验证题目 {problem_id} 数据完整性")
            
            local_data_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
            if not os.path.exists(local_data_dir):
                self.logger.error(f"题目 {problem_id} 本地数据不存在")
                return False
            
            # 计算本地数据哈希
            local_hash = self.calculate_directory_hash(local_data_dir)
            if not local_hash:
                self.logger.error(f"计算题目 {problem_id} 本地数据哈希失败")
                return False
            
            # 获取远程数据哈希
            remote_hash = self.get_remote_data_hash(problem_id)
            if not remote_hash:
                self.logger.error(f"获取题目 {problem_id} 远程数据哈希失败")
                return False
            
            # 比较哈希
            if local_hash == remote_hash:
                self.logger.info(f"题目 {problem_id} 数据完整性验证通过")
                return True
            else:
                self.logger.warning(f"题目 {problem_id} 数据完整性验证失败")
                return False
                
        except Exception as e:
            self.logger.error(f"验证题目 {problem_id} 数据完整性时发生错误: {e}")
            return False
    
    def cleanup_old_data(self, days: int = 30) -> bool:
        """清理旧数据"""
        try:
            self.logger.info(f"开始清理 {days} 天前的旧数据")
            
            data_dir = self.config["judge"]["data_dir"]
            if not os.path.exists(data_dir):
                return True
            
            current_time = time.time()
            cutoff_time = current_time - (days * 24 * 60 * 60)
            
            cleaned_count = 0
            for item in os.listdir(data_dir):
                item_path = os.path.join(data_dir, item)
                if os.path.isdir(item_path):
                    # 检查目录修改时间
                    if os.path.getmtime(item_path) < cutoff_time:
                        shutil.rmtree(item_path)
                        cleaned_count += 1
                        self.logger.info(f"清理旧数据目录: {item}")
            
            self.logger.info(f"清理完成，共清理 {cleaned_count} 个目录")
            return True
            
        except Exception as e:
            self.logger.error(f"清理旧数据时发生错误: {e}")
            return False
    
    def get_sync_status(self) -> Dict[str, Any]:
        """获取同步状态"""
        try:
            data_dir = self.config["judge"]["data_dir"]
            if not os.path.exists(data_dir):
                return {
                    "total_problems": 0,
                    "synced_problems": 0,
                    "sync_rate": 0.0
                }
            
            # 统计本地题目数量
            local_problems = []
            for item in os.listdir(data_dir):
                item_path = os.path.join(data_dir, item)
                if os.path.isdir(item_path) and item.isdigit():
                    local_problems.append(int(item))
            
            # 获取远程题目列表
            remote_problems = self.get_problem_list()
            
            # 计算同步率
            synced_count = len(set(local_problems) & set(remote_problems))
            total_count = len(remote_problems)
            sync_rate = synced_count / total_count if total_count > 0 else 0.0
            
            return {
                "total_problems": total_count,
                "synced_problems": synced_count,
                "sync_rate": sync_rate,
                "local_problems": local_problems,
                "remote_problems": remote_problems
            }
            
        except Exception as e:
            self.logger.error(f"获取同步状态时发生错误: {e}")
            return {
                "total_problems": 0,
                "synced_problems": 0,
                "sync_rate": 0.0
            }
    
    def get_all_problems_info(self, problem_ids: List[int]) -> Dict[int, Dict[str, Any]]:
        """获取所有题目的数据信息"""
        try:
            if is_debug_enabled():
                info_details = {
                    "题目数量": len(problem_ids),
                    "题目ID列表": problem_ids[:10] if len(problem_ids) > 10 else problem_ids
                }
                self.logger.debug(f"获取题目数据信息: {info_details}")
            
            result = self.web_client._make_request('POST', 'getallproblemsinfo', json={"problem_ids": problem_ids})
            problems_info = self.web_client._get_response_data(result, {})
            
            if is_debug_enabled():
                self.logger.debug(f"获取到 {len(problems_info)} 个题目的数据信息")
                # 显示前几个题目的详细信息
                for i, (pid, info) in enumerate(list(problems_info.items())[:5]):
                    self.logger.debug(f"  题目 {pid}: 大小={info.get('size', 0)}B, 哈希={info.get('hash', '')[:16]}..., 有数据={info.get('has_data', False)}")
            
            return problems_info
                
        except Exception as e:
            log_error_with_context(self.logger, "获取题目数据信息", e, {
                "problem_count": len(problem_ids)
            })
        
        return {}
    
    def is_data_up_to_date(self, problem_id: int, problem_info: Dict[str, Any]) -> bool:
        """检查数据是否是最新的"""
        try:
            local_data_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
            local_hash_file = os.path.join(local_data_dir, ".hash")
            
            if not os.path.exists(local_hash_file):
                return False
            
            with open(local_hash_file, 'r') as f:
                local_hash = f.read().strip()
            
            remote_hash = problem_info.get("hash", "")
            return local_hash == remote_hash
            
        except Exception as e:
            self.logger.error(f"检查数据更新状态时发生错误: {e}")
            return False
    
    def format_size(self, size_bytes: int) -> str:
        """格式化文件大小"""
        if size_bytes == 0:
            return "0B"
        
        size_names = ["B", "KB", "MB", "GB", "TB"]
        i = 0
        while size_bytes >= 1024 and i < len(size_names) - 1:
            size_bytes /= 1024.0
            i += 1
        
        return f"{size_bytes:.1f}{size_names[i]}"
    
    def setup_logging(self, debug_mode: bool = False):
        """设置日志系统（已由__init__中的setup_unified_logging处理）"""
        pass

def main():
    """主函数"""
    parser = argparse.ArgumentParser(description="CSGOJ judge2 数据同步工具")
    parser.add_argument("--force", action="store_true", help="强制更新所有数据")
    parser.add_argument("--no-progress", action="store_true", help="不显示进度条")
    parser.add_argument("--status", action="store_true", help="显示同步状态")
    parser.add_argument("--cleanup", type=int, metavar="DAYS", help="清理指定天数前的旧数据")
    
    # 使用统一的调试参数解析
    args = DebugManager.parse_debug_args(parser)
    
    # 确定配置文件路径
    if args.config:
        config_path = args.config
    else:
        current_dir = os.path.dirname(os.path.abspath(__file__))
        config_path = os.path.join(current_dir, "config.json")
    
    # 加载配置
    try:
        config = ConfigLoader.load_config(config_path)
    except Exception as e:
        print(f"错误：加载配置文件失败 - {e}")
        sys.exit(1)
    
    # 创建数据同步器
    data_sync = DataSync(config)
    
    try:
        if args.status:
            # 显示同步状态
            print("获取同步状态...")
            status = data_sync.get_sync_status()
            print(f"总题目数: {status['total_problems']}")
            print(f"已同步题目数: {status['synced_problems']}")
            print(f"同步率: {status['sync_rate']:.1%}")
            print(f"本地题目: {status['local_problems']}")
            print(f"远程题目: {status['remote_problems']}")
            
        elif args.cleanup:
            # 清理旧数据
            print(f"清理 {args.cleanup} 天前的旧数据...")
            if data_sync.cleanup_old_data(args.cleanup):
                print("清理完成")
            else:
                print("清理失败")
                sys.exit(1)
                
        else:
            # 执行全量同步
            print("开始全量数据同步...")
            print("=" * 60)
            
            result = data_sync.sync_all_problems(
                force_update=args.force,
                show_progress=not args.no_progress
            )
            
            print("=" * 60)
            if result["success"]:
                print("同步完成！")
                print(f"总计: {result['total']} 个题目")
                print(f"成功: {result['successful']} 个")
                print(f"跳过: {result['skipped']} 个")
                print(f"失败: {result['failed']} 个")
                print(f"下载数据: {data_sync.format_size(result['downloaded_size'])}/{data_sync.format_size(result['total_size'])}")
            else:
                print(f"同步失败: {result.get('message', '未知错误')}")
                sys.exit(1)
                
    except KeyboardInterrupt:
        print("\n用户中断同步")
        sys.exit(1)
    except Exception as e:
        print(f"同步过程中发生错误: {e}")
        if is_debug_enabled():
            import traceback
            traceback.print_exc()
        sys.exit(1)

if __name__ == "__main__":
    main()
