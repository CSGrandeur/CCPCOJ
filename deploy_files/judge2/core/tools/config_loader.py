#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 统一配置管理器
提供配置初始化和读取功能
"""

import os
import sys
import json
import logging
from typing import Dict, Any, Optional

# 将当前目录和父目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)

class ConfigLoader:
    """统一配置管理器"""
    
    @staticmethod
    def initialize_config(config_path: Optional[str] = None) -> Dict[str, Any]:
        """
        初始化配置文件（不再读取 config.json.default）
        1. 优先读取已有的 config.json
        2. 获取环境变量并进行非空覆盖
        3. 如有变更则保存到 config.json
        
        Args:
            config_path: 配置文件路径，默认为当前目录下的 config.json
            
        Returns:
            Dict[str, Any]: 初始化后的配置
        """
        # 确定配置文件路径
        if config_path is None:
            current_dir = os.path.dirname(os.path.abspath(__file__))
            config_path = os.path.join(current_dir, "config.json")
        
        # 如果存在 config.json 则读取，否则从空配置开始
        if os.path.exists(config_path):
            try:
                with open(config_path, 'r', encoding='utf-8') as f:
                    config = json.load(f)
            except Exception as e:
                raise RuntimeError(f"读取配置文件失败: {e}")
        else:
            config = {}
        
        # 先补全必要的默认字段（例如工作目录等），仅在缺失时补全
        ConfigLoader._ensure_default_paths(config)
        
        # 应用环境变量覆盖（非空优先级最高）
        config, changed = ConfigLoader._apply_env_overrides(config)
        
        # 如无文件或有变更则保存
        if changed or not os.path.exists(config_path):
            os.makedirs(os.path.dirname(config_path), exist_ok=True)
            try:
                with open(config_path, 'w', encoding='utf-8') as f:
                    json.dump(config, f, indent=2, ensure_ascii=False)
            except Exception as e:
                raise RuntimeError(f"保存配置文件失败: {e}")
        
        return config
    
    @staticmethod
    def load_config(config_path: Optional[str] = None) -> Dict[str, Any]:
        """
        加载配置文件
        1. 先尝试读取 config.json
        2. 应用非空环境变量覆盖并在发生变更时写回
        3. 如果不存在，则按初始化逻辑创建
        
        Args:
            config_path: 配置文件路径，默认为当前目录下的 config.json
            
        Returns:
            Dict[str, Any]: 配置字典
        """
        # 确定配置文件路径
        if config_path is None:
            current_dir = os.path.dirname(os.path.abspath(__file__))
            config_path = os.path.join(current_dir, "config.json")
        
        if os.path.exists(config_path):
            try:
                with open(config_path, 'r', encoding='utf-8') as f:
                    config = json.load(f)
            except Exception as e:
                raise RuntimeError(f"读取配置文件失败: {e}")
            
            # 仅在缺失时补全默认路径
            ConfigLoader._ensure_default_paths(config)
            
            # 应用环境变量覆盖并在变更时保存
            config, changed = ConfigLoader._apply_env_overrides(config)
            if changed:
                try:
                    with open(config_path, 'w', encoding='utf-8') as f:
                        json.dump(config, f, indent=2, ensure_ascii=False)
                except Exception as e:
                    raise RuntimeError(f"保存配置文件失败: {e}")
            return config
        
        # 如果 config.json 不存在，调用初始化方法
        return ConfigLoader.initialize_config(config_path)
    
    @staticmethod
    def _is_docker_environment() -> bool:
        """
        检测是否在 Docker 容器内环境
        
        Returns:
            bool: 如果在 Docker 容器内返回 True，否则返回 False
        """
        # 检查是否存在 /.dockerenv 文件
        if os.path.exists('/.dockerenv'):
            return True
        
        # 检查 cgroup 信息
        try:
            with open('/proc/1/cgroup', 'r') as f:
                content = f.read()
                if 'docker' in content or 'containerd' in content:
                    return True
        except (FileNotFoundError, PermissionError):
            pass
        
        # 检查环境变量
        if os.environ.get('container') == 'docker':
            return True
            
        return False
    
    @staticmethod
    def _apply_env_overrides(config: Dict[str, Any]) -> (Dict[str, Any], bool):
        """
        应用环境变量覆盖配置
        
        Args:
            config: 原始配置
            
        Returns:
            Tuple[Dict[str, Any], bool]: 应用环境变量后的配置，以及是否发生变更
        """
        changed = False
        # 环境变量映射表
        env_mappings = {
            # 服务器配置
            'CSGOJ_SERVER_BASE_URL': ('server', 'base_url'),
            'CSGOJ_SERVER_API_PATH': ('server', 'api_path'),
            'CSGOJ_SERVER_USERNAME': ('server', 'user_id'),
            'CSGOJ_SERVER_PASSWORD': ('server', 'password'),
            'CSGOJ_JUDGE_SLEEP_TIME': ('judge', 'sleep_time'),
        }
        
        # 应用环境变量
        for env_var, (section, key) in env_mappings.items():
            env_value = os.environ.get(env_var)
            # 仅当环境变量存在且非空时才覆盖
            if env_value is not None and str(env_value).strip() != '':
                # 确保配置节存在
                if section not in config:
                    config[section] = {}
                
                # 类型转换
                original_value = config[section].get(key)
                if isinstance(original_value, int):
                    try:
                        new_value = int(env_value)
                    except ValueError:
                        new_value = env_value
                elif isinstance(original_value, float):
                    try:
                        new_value = float(env_value)
                    except ValueError:
                        new_value = env_value
                elif isinstance(original_value, bool):
                    new_value = env_value.lower() in ('true', '1', 'yes', 'on')
                else:
                    new_value = env_value

                if config[section].get(key) != new_value:
                    config[section][key] = new_value
                    changed = True
        
        return config, changed

    @staticmethod
    def _ensure_default_paths(config: Dict[str, Any]) -> None:
        """在缺失时为 judge 相关目录设置默认路径"""
        if 'judge' not in config:
            config['judge'] = {}
        is_docker = ConfigLoader._is_docker_environment()
        if is_docker:
            config['judge'].setdefault('work_dir', '/judge/workspace')
            config['judge'].setdefault('data_dir', '/judge/data')
            config['judge'].setdefault('log_dir', '/judge/logs')
        else:
            current_dir = os.path.dirname(os.path.abspath(__file__))
            parent_dir = os.path.dirname(current_dir)
            config['judge'].setdefault('work_dir', os.path.join(parent_dir, 'judge', 'workspace'))
            config['judge'].setdefault('data_dir', os.path.join(parent_dir, 'judge', 'data'))
            config['judge'].setdefault('log_dir', os.path.join(parent_dir, 'judge', 'logs'))
    
        config['judge'].setdefault('sleep_time', '3')
