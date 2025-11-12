#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 评测机主进程
基于seccomp的安全评测系统
"""

import configparser
import os
import sys
import time
import signal
import argparse
import subprocess
from typing import Dict, Any

# 将当前目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)

from tools.web_client import WebClient
from tools.config_loader import ConfigLoader
from tools.debug_manager import DebugManager, setup_unified_logging, is_debug_enabled

class JudgeHost:
    def __init__(self, config_path: str = None, initialize_config: bool = False):
        """初始化评测机主进程"""
        # 根据参数决定是否初始化配置
        if initialize_config:
            self.config = ConfigLoader.initialize_config(config_path)
        else:
            self.config = ConfigLoader.load_config(config_path)
        
        self.setup_logging()
        self.running = True
        self.judge_process = None
        
        # 初始化Web客户端
        self.web_client = WebClient(self.config)
        
        # 注册信号处理
        signal.signal(signal.SIGTERM, self.signal_handler)
        signal.signal(signal.SIGINT, self.signal_handler)
        
        self.logger.info("评测机主进程启动")
    
    def setup_logging(self):
        """设置日志系统"""
        self.logger = setup_unified_logging("JudgeHost", self.config)
    
    def signal_handler(self, signum, frame):
        """信号处理函数"""
        self.logger.info(f"收到信号 {signum}，正在停止评测机...")
        self.running = False
        
        if self.judge_process:
            self.logger.info("终止当前评测进程")
            self.judge_process.terminate()
            try:
                self.judge_process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                self.judge_process.kill()
        
        sys.exit(0)
    
    
    def get_pending_tasks(self) -> list:
        """获取待评测任务"""
        if is_debug_enabled():
            self.logger.debug("获取待评测任务...")
        
        tasks = self.web_client.get_pending_tasks(max_tasks=1)
        
        if is_debug_enabled():
            self.logger.debug(f"获取到 {len(tasks)} 个待评测任务")
            for i, task in enumerate(tasks):
                self.logger.debug(f"  任务 {i+1}: {task}")
        
        return tasks
    
    def run_judge_client(self, solution_id: int) -> int:
        """运行评测客户端
        
        Args:
            solution_id: 解决方案ID
            
        Returns:
            int: 返回码（0表示成功）
        """
        try:
            # 构建评测客户端命令（judge_client.py 接收 solution_id 作为位置参数）
            # judge_client.py 会自动查找配置文件，不需要传递 --config 参数
            cmd = ["python3", "judge_client.py", str(solution_id)]
            
            self.logger.info(f"启动评测客户端：{' '.join(cmd)}")
            
            # 启动评测进程
            self.judge_process = subprocess.Popen(
                cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                cwd=current_dir  # 确保在正确的目录下运行
            )
            
            # 等待评测完成
            stdout, stderr = self.judge_process.communicate()
            return_code = self.judge_process.returncode
            
            self.logger.info(f"评测客户端完成，返回码：{return_code}")
            if is_debug_enabled():
                if stdout:
                    self.logger.debug(f"评测输出：{stdout}")
                if stderr:
                    self.logger.debug(f"评测错误：{stderr}")
            
            return return_code
            
        except Exception as e:
            self.logger.error(f"运行评测客户端时发生错误：{e}")
            return -1
    
    def process_task(self, task: Dict[str, Any]):
        """处理单个评测任务
        
        注意：所有任务状态更新、数据下载、工作目录管理等都由 judge_client.py 负责处理
        主进程只负责启动评测客户端并等待其完成
        """
        solution_id = task["solution_id"]
        self.logger.info(f"开始处理任务 {solution_id}")
        
        try:
            # 运行评测客户端（所有逻辑都在 judge_client.py 中处理）
            result_code = self.run_judge_client(solution_id)
            
            if result_code == 0:
                self.logger.info(f"任务 {solution_id} 评测完成")
            else:
                self.logger.warning(f"任务 {solution_id} 评测失败，返回码：{result_code}")
                
        except Exception as e:
            self.logger.error(f"处理任务 {solution_id} 时发生错误：{e}")
        finally:
            # 每次任务后强制清理内存
            self._force_memory_cleanup()
    
    def _force_memory_cleanup(self):
        """暴力内存回收（评测机主进程）"""
        try:
            import gc
            collected = gc.collect()
            if collected > 0 and is_debug_enabled():
                self.logger.debug(f"GC 回收了 {collected} 个对象")
            
            self._force_cleanup_mounts()
            self._recycle_web_client()
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"暴力内存清理时发生错误: {e}")
    
    def _force_cleanup_mounts(self):
        """强制清理当前容器工作目录下的所有挂载点"""
        import subprocess
        work_dir_base = self.config.get("judge", {}).get("work_dir", "/judge/workspace")
        if not os.path.exists(work_dir_base):
            return
        try:
            result = subprocess.run(
                ["findmnt", "-n", "-o", "TARGET"],
                capture_output=True, text=True, timeout=5
            )
            if result.returncode == 0:
                unmount_count = 0
                for mount_point in result.stdout.split('\n'):
                    mount_point = mount_point.strip()
                    if mount_point and work_dir_base in mount_point:
                        try:
                            subprocess.run(["umount", "-l", mount_point], 
                                        timeout=2, capture_output=True)
                            unmount_count += 1
                        except Exception:
                            pass
                if unmount_count > 0 and is_debug_enabled():
                    self.logger.debug(f"清理了 {unmount_count} 个残留挂载点")
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"清理挂载点时发生错误: {e}")
    
    def _recycle_web_client(self):
        """关闭并重新创建 WebClient Session（释放连接池内存）"""
        try:
            if hasattr(self, 'web_client') and hasattr(self.web_client, 'session'):
                try:
                    self.web_client.close()
                    if is_debug_enabled():
                        self.logger.debug("WebClient Session 已关闭")
                except Exception as e:
                    if is_debug_enabled():
                        self.logger.debug(f"关闭 WebClient Session 时发生错误: {e}")
                # 重新创建 WebClient
                self.web_client = WebClient(self.config)
                if is_debug_enabled():
                    self.logger.debug("WebClient Session 已回收")
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"回收 WebClient 时发生错误: {e}")
    
    def main_loop(self):
        """主循环"""
        self.logger.info("开始主循环")
        sleep_time = 3
        try:
            sleep_time = int(self.config["judge"]["sleep_time"])
        except Exception:
            sleep_time = 3
            self.logger.warning("评测机配置文件中没有设置 sleep_time，使用默认值 3")
        while self.running:
            try:
                # 获取待评测任务
                tasks = self.get_pending_tasks()
                
                if tasks:
                    self.logger.info(f"获取到 {len(tasks)} 个待评测任务")
                    
                    # 处理第一个任务（单任务模式）
                    task = tasks[0]
                    self.process_task(task)
                    # 处理完成后立即进入下一次循环（不等待）
                else:
                    # 没有任务时休眠
                    time.sleep(sleep_time)
                
            except KeyboardInterrupt:
                self.logger.info("收到中断信号，退出主循环")
                break
            except Exception as e:
                self.logger.error(f"主循环中发生错误：{e}")
                time.sleep(5)  # 错误后等待5秒再继续
        
        self.logger.info("主循环结束")

def main():
    """主函数"""
    parser = argparse.ArgumentParser(description="CSGOJ judge2 评测机主进程")
    parser.add_argument("--work-dir", help="工作目录")
    
    # 使用统一的调试参数解析
    args = DebugManager.parse_debug_args(parser)
    
    # 设置工作目录
    if args.work_dir:
        os.chdir(args.work_dir)
    
    # 确定配置文件路径（与 judge_client.py 保持一致）
    if args.config:
        config_path = args.config
    else:
        # 使用默认配置文件路径（judge_host.py 所在目录的 config.json）
        config_path = os.path.join(current_dir, "config.json")
    print('use config:', config_path)
    # 确定是否初始化配置（非debug模式启动时初始化）
    initialize_config = not is_debug_enabled()
    
    # 创建评测机主进程
    judge_host = JudgeHost(config_path, initialize_config=initialize_config)
    
    # 启动主循环
    judge_host.main_loop()

if __name__ == "__main__":
    main()
