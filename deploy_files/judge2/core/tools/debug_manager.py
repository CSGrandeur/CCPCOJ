#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 调试管理器
统一的调试状态管理和日志输出系统
"""

import os
import sys
import json
import logging
import argparse
from typing import Dict, Any, Optional, List
from datetime import datetime

# 将当前目录和父目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)

class DebugManager:
    """调试管理器"""
    
    _instance = None
    _debug_mode = False
    _syscallfree_mode = False
    _log_level = logging.INFO
    _log_dir = None
    _handlers = []
    _tmp_log_created = False  # 标记 tmp.log 是否已经创建
    
    def __new__(cls):
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance
    
    @classmethod
    def init_debug_manager(cls, debug_mode: bool = False, syscallfree_mode: bool = False, log_dir: str = None):
        """初始化调试管理器"""
        cls._debug_mode = debug_mode
        cls._syscallfree_mode = syscallfree_mode
        cls._log_level = logging.DEBUG if debug_mode else logging.INFO
        
        if log_dir:
            cls._log_dir = log_dir
        else:
            # 默认日志目录
            base_dir = os.path.dirname(os.path.abspath(__file__))
            cls._log_dir = os.path.join(base_dir, "logs")
        
        # 确保日志目录存在
        os.makedirs(cls._log_dir, exist_ok=True)
    
    @classmethod
    def is_debug_mode(cls) -> bool:
        """检查是否为调试模式"""
        return cls._debug_mode
    
    @classmethod
    def is_syscallfree_mode(cls) -> bool:
        """检查是否为系统调用自由模式（seccomp LOG模式）"""
        return cls._syscallfree_mode
    
    @classmethod
    def get_log_level(cls) -> int:
        """获取日志级别"""
        return cls._log_level
    
    @classmethod
    def get_log_dir(cls) -> str:
        """获取日志目录"""
        return cls._log_dir
    
    @classmethod
    def setup_logging(cls, module_name: str, config: Dict[str, Any] = None) -> logging.Logger:
        """为模块设置统一的日志系统"""
        logger = logging.getLogger(module_name)
        
        # 检查是否已经初始化过（避免重复初始化）
        if hasattr(logger, '_csgoj_initialized'):
            return logger
        
        # 清除现有处理器（先关闭再移除，避免文件句柄泄漏）
        for handler in logger.handlers[:]:
            try:
                handler.close()  # 关闭 handler，释放文件句柄等资源
            except Exception:
                pass  # 忽略关闭时的错误
            logger.removeHandler(handler)
        
        # 设置日志级别
        logger.setLevel(cls._log_level)
        
        # 创建格式化器
        formatter = logging.Formatter(
            '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
        )
        
        # 若传入了配置，强制以配置中的 judge.log_dir 覆盖日志目录，以保证与 work_dir/data_dir 同根
        try:
            if isinstance(config, dict):
                cfg_log_dir = (config.get("judge", {}) or {}).get("log_dir")
                if cfg_log_dir and cfg_log_dir != cls._log_dir:
                    cls._log_dir = cfg_log_dir
        except Exception:
            pass

        # 确保日志目录存在
        try:
            if cls._log_dir:
                os.makedirs(cls._log_dir, exist_ok=True)
        except Exception:
            pass

        # 文件处理器
        today = datetime.now().strftime("%Y%m%d")
        log_file = os.path.join(cls._log_dir, f"{module_name}_{today}.log")
        file_handler = logging.FileHandler(log_file, encoding='utf-8')
        file_handler.setFormatter(formatter)
        file_handler.setLevel(logging.DEBUG)  # 文件总是记录DEBUG级别
        logger.addHandler(file_handler)
        
        # 控制台处理器
        console_handler = logging.StreamHandler(sys.stdout)
        console_handler.setFormatter(formatter)
        console_handler.setLevel(cls._log_level)
        logger.addHandler(console_handler)
        
        # 临时日志文件处理器（输出到日志目录的 tmp.log）
        try:
            tmp_log_path = os.path.join(cls._log_dir, "tmp.log")
            # 第一次创建时使用 'w' 模式覆盖，后续使用 'a' 模式追加
            mode = 'w' if not cls._tmp_log_created else 'a'
            tmp_handler = logging.FileHandler(tmp_log_path, mode=mode, encoding='utf-8')
            # 标记 tmp.log 已经创建
            cls._tmp_log_created = True
            tmp_handler.setFormatter(formatter)
            tmp_handler.setLevel(cls._log_level)
            logger.addHandler(tmp_handler)
        except Exception as e:
            # 如果无法创建临时日志文件，不影响主流程
            pass
        
        # 标记为已初始化
        logger._csgoj_initialized = True
        
        # 记录日志系统初始化（只在第一次初始化时记录）
        logger.info(f"日志系统初始化完成，日志文件：{log_file}")
        if cls._debug_mode:
            logger.info("调试模式已启用，详细日志将同时显示在终端")
        
        return logger
    
    @classmethod
    def parse_debug_args(cls, parser: argparse.ArgumentParser, args: List[str] = None) -> argparse.Namespace:
        """解析统一的调试参数"""
        # 添加统一的调试参数
        parser.add_argument("--debug", action="store_true", help="启用调试模式")
        parser.add_argument("--syscallfree", action="store_true", help="启用系统调用自由模式（seccomp LOG模式）")
        parser.add_argument("--log-dir", help="日志目录路径")
        parser.add_argument("--config", help="配置文件路径")
        
        # 支持位置参数 "debug" 和 "syscallfree"
        parser.add_argument("debug_mode", nargs="*", help="调试模式（位置参数：debug 或 syscallfree）")
        
        # 解析参数
        parsed_args = parser.parse_args(args)
        
        # 获取位置参数列表
        debug_modes = getattr(parsed_args, 'debug_mode', []) or []
        
        # 检查是否为debug模式（支持位置参数和选项参数）
        debug_mode = getattr(parsed_args, 'debug', False) or ("debug" in debug_modes)
        
        # 检查是否为syscallfree模式（支持位置参数和选项参数）
        syscallfree_mode = getattr(parsed_args, 'syscallfree', False) or ("syscallfree" in debug_modes)
        
        # 初始化调试管理器
        cls.init_debug_manager(debug_mode, syscallfree_mode, parsed_args.log_dir)
        
        return parsed_args
    
    @classmethod
    def get_debug_info(cls, context: str = "") -> Dict[str, Any]:
        """获取调试信息"""
        return {
            "debug_mode": cls._debug_mode,
            "syscallfree_mode": cls._syscallfree_mode,
            "log_level": logging.getLevelName(cls._log_level),
            "log_dir": cls._log_dir,
            "context": context,
            "python_version": sys.version,
            "platform": sys.platform,
            "working_directory": os.getcwd()
        }
    
    @classmethod
    def log_debug_info(cls, logger: logging.Logger, context: str = ""):
        """记录调试信息"""
        if cls._debug_mode:
            debug_info = cls.get_debug_info(context)
            logger.debug(f"调试信息: {debug_info}")
    
    @classmethod
    def create_progress_logger(cls, module_name: str, progress_desc: str = "处理中") -> logging.Logger:
        """创建带进度条的日志器（用于data_sync等需要进度显示的场景）"""
        logger = logging.getLogger(f"{module_name}_Progress")
        
        # 清除现有处理器
        for handler in logger.handlers[:]:
            logger.removeHandler(handler)
        
        # 设置日志级别
        logger.setLevel(cls._log_level)
        
        # 创建格式化器
        formatter = logging.Formatter(
            '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
        )
        
        # 文件处理器（总是记录到文件）
        today = datetime.now().strftime("%Y%m%d")
        log_file = os.path.join(cls._log_dir, f"{module_name}_{today}.log")
        file_handler = logging.FileHandler(log_file, encoding='utf-8')
        file_handler.setFormatter(formatter)
        file_handler.setLevel(logging.DEBUG)
        logger.addHandler(file_handler)
        
        # 控制台处理器（根据调试模式决定）
        if cls._debug_mode:
            console_handler = logging.StreamHandler(sys.stdout)
            console_handler.setFormatter(formatter)
            console_handler.setLevel(cls._log_level)
            logger.addHandler(console_handler)
        
        # 临时日志文件处理器（输出到日志目录的 tmp.log）
        try:
            tmp_log_path = os.path.join(cls._log_dir, "tmp.log")
            # 第一次创建时使用 'w' 模式覆盖，后续使用 'a' 模式追加
            mode = 'w' if not cls._tmp_log_created else 'a'
            tmp_handler = logging.FileHandler(tmp_log_path, mode=mode, encoding='utf-8')
            # 标记 tmp.log 已经创建
            cls._tmp_log_created = True
            tmp_handler.setFormatter(formatter)
            tmp_handler.setLevel(cls._log_level)
            logger.addHandler(tmp_handler)
        except Exception as e:
            # 如果无法创建临时日志文件，不影响主流程
            pass
        
        return logger

def setup_unified_logging(module_name: str, config: Dict[str, Any] = None) -> logging.Logger:
    """设置统一日志系统的便捷函数"""
    # 如果 DebugManager 还没有初始化，尝试从配置或环境自动初始化
    if DebugManager._log_dir is None:
        inferred_log_dir = None
        try:
            if isinstance(config, dict):
                # 仅从 judge.log_dir 读取（强约束三目录一致）
                inferred_log_dir = (config.get("judge", {}) or {}).get("log_dir")
            # 最后兜底：直接读取配置文件（不依赖现有日志系统）
            if not inferred_log_dir:
                try:
                    from tools.config_loader import ConfigLoader
                    cfg = ConfigLoader.load_config()
                    inferred_log_dir = (cfg.get("judge", {}) or {}).get("log_dir")
                except Exception:
                    inferred_log_dir = None
        except Exception:
            inferred_log_dir = None
        # 初始化（若无法推断，则使用默认路径）
        DebugManager.init_debug_manager(debug_mode=False, syscallfree_mode=False, log_dir=inferred_log_dir)
    
    return DebugManager.setup_logging(module_name, config)

 

def get_debug_logger(module_name: str) -> logging.Logger:
    """获取调试日志器的便捷函数"""
    logger = logging.getLogger(module_name)
    
    # 检查是否已经初始化过（避免重复初始化）
    if hasattr(logger, '_csgoj_initialized'):
        return logger
    
    # 如果logger已经有处理器，先清理（避免累积）
    if logger.handlers:
        for handler in logger.handlers[:]:
            try:
                handler.close()  # 关闭 handler，释放文件句柄等资源
            except Exception:
                pass  # 忽略关闭时的错误
            logger.removeHandler(handler)
    
    # 如果logger还没有处理器，设置统一的日志配置
    if not logger.handlers:
        # 如果还没有通过 init_debug_manager 初始化 log_dir，则在此设置默认目录并尝试创建
        if DebugManager._log_dir is None:
            # 优先直接读取配置文件，确保与 work_dir/data_dir 同根
            try:
                from tools.config_loader import ConfigLoader
                cfg = ConfigLoader.load_config()
                DebugManager._log_dir = (cfg.get('judge', {}) or {}).get('log_dir')
            except Exception:
                DebugManager._log_dir = None
            # 仍然缺失则使用默认 fallback，与 ConfigLoader._ensure_default_paths 对齐
            if DebugManager._log_dir is None:
                tools_dir = os.path.dirname(os.path.abspath(__file__))
                core_dir = os.path.dirname(tools_dir)
                parent_dir = os.path.dirname(core_dir)
                DebugManager._log_dir = os.path.join(parent_dir, 'judge', 'logs')
            try:
                os.makedirs(DebugManager._log_dir, exist_ok=True)
            except Exception:
                # 创建失败时保持为 None（仍然使用控制台日志）
                DebugManager._log_dir = None

        logger.setLevel(DebugManager._log_level)

        # 设置格式
        formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')

        # 添加控制台处理器（统一使用 stdout）
        console_handler = logging.StreamHandler(sys.stdout)
        console_handler.setLevel(DebugManager._log_level)
        console_handler.setFormatter(formatter)
        logger.addHandler(console_handler)

        # 添加文件处理器（如果 log_dir 可用）
        if DebugManager._log_dir:
            try:
                log_file = os.path.join(DebugManager._log_dir, f"{module_name}_{datetime.now().strftime('%Y%m%d')}.log")
                file_handler = logging.FileHandler(log_file, encoding='utf-8', mode='a')
                file_handler.setLevel(DebugManager._log_level)
                file_handler.setFormatter(formatter)
                logger.addHandler(file_handler)
            except Exception:
                # 无法写文件则忽略，保留控制台日志
                pass

        # 添加临时日志文件处理器（输出到日志目录 tmp.log）
        try:
            tmp_log_path = os.path.join(DebugManager._log_dir if DebugManager._log_dir else os.getcwd(), "tmp.log")
            mode = 'w' if not DebugManager._tmp_log_created else 'a'
            tmp_handler = logging.FileHandler(tmp_log_path, mode=mode, encoding='utf-8')
            DebugManager._tmp_log_created = True
            tmp_handler.setLevel(DebugManager._log_level)
            tmp_handler.setFormatter(formatter)
            logger.addHandler(tmp_handler)
        except Exception:
            # 如果无法创建临时日志文件，不影响主流程
            pass
        
        # 标记为已初始化
        logger._csgoj_initialized = True
    
    return logger

def log_debug_details(logger: logging.Logger, operation: str, details: Dict[str, Any]):
    """记录详细调试信息的便捷函数"""
    if DebugManager.is_debug_mode():
        logger.debug(f"{operation} 详细信息:")
        for key, value in details.items():
            logger.debug(f"  {key}: {value}")

def log_error_with_context(logger: logging.Logger, operation: str, error: Exception, context: Dict[str, Any] = None):
    """记录带上下文的错误信息的便捷函数"""
    if DebugManager.is_debug_mode():
        import traceback
        error_details = {
            "error_type": type(error).__name__,
            "error_message": str(error),
            "error_args": error.args if hasattr(error, 'args') else None,
            "traceback": traceback.format_exc()
        }
        if context:
            error_details.update(context)
        
        logger.error(f"{operation} 时发生详细错误：{error_details}")
    else:
        logger.error(f"{operation} 时发生错误：{error}")

# 全局调试状态检查函数
def check_debug_mode() -> bool:
    """检查当前是否为调试模式"""
    return DebugManager.is_debug_mode()

def is_debug_enabled() -> bool:
    """检查当前是否为调试模式（兼容性函数）"""
    return DebugManager.is_debug_mode()

def is_syscallfree_enabled() -> bool:
    """检查当前是否为系统调用自由模式"""
    return DebugManager.is_syscallfree_mode()

# 便捷的调试装饰器
def debug_log(func):
    """调试日志装饰器"""
    def wrapper(*args, **kwargs):
        if DebugManager.is_debug_mode():
            logger = logging.getLogger(func.__module__)
            logger.debug(f"调用函数: {func.__name__}")
            logger.debug(f"  参数: args={args}, kwargs={kwargs}")
        
        result = func(*args, **kwargs)
        
        if DebugManager.is_debug_mode():
            logger = logging.getLogger(func.__module__)
            logger.debug(f"函数 {func.__name__} 返回: {result}")
        
        return result
    return wrapper
