#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 编程语言工厂
提供语言处理器的创建和管理功能
"""

import os
import sys
from typing import Dict, Any, Optional

# 将当前目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)

from lang_base import LanguageBase
from lang_c import LanguageC
from lang_cpp import LanguageCpp
from lang_java import LanguageJava
from lang_go import LanguageGo
from lang_python import LanguagePython


class LanguageFactory:
    """编程语言工厂类"""
    
    # 支持的语言映射
    _language_map = {
        'c': LanguageC,
        'cpp': LanguageCpp,
        'c++': LanguageCpp,
        'java': LanguageJava,
        'go': LanguageGo,
        'golang': LanguageGo,
        'python': LanguagePython,
        'py': LanguagePython,
    }
    
    # 语言处理器缓存
    _handler_cache = {}
    
    @classmethod
    def create_language_handler(cls, language: str, config: Dict[str, Any], work_dir: str = None) -> Optional[LanguageBase]:
        """
        创建语言处理器
        
        Args:
            language: 编程语言名称
            config: 配置字典
            work_dir: 工作目录
            
        Returns:
            LanguageBase: 语言处理器实例，如果不支持则返回None
        """
        # 标准化语言名称
        normalized_lang = language.lower().strip()
        
        # 查找对应的语言类
        language_class = cls._language_map.get(normalized_lang)
        
        if language_class:
            # 创建缓存键（基于语言和配置）
            cache_key = f"{normalized_lang}_{id(config)}_{work_dir or 'default'}"
            
            # 检查缓存
            if cache_key in cls._handler_cache:
                return cls._handler_cache[cache_key]
            
            # 创建新的语言处理器实例
            handler = language_class(config, work_dir)
            
            # 缓存实例（避免重复创建和日志初始化）
            cls._handler_cache[cache_key] = handler
            
            return handler
        else:
            return None
    
    @classmethod
    def get_supported_languages(cls) -> list:
        """获取支持的语言列表"""
        return list(cls._language_map.keys())
    
    @classmethod
    def is_language_supported(cls, language: str) -> bool:
        """检查是否支持指定语言"""
        normalized_lang = language.lower().strip()
        return normalized_lang in cls._language_map
    
    @classmethod
    def get_language_info(cls, language: str) -> Dict[str, Any]:
        """获取语言信息"""
        normalized_lang = language.lower().strip()
        
        if normalized_lang in cls._language_map:
            # 创建临时实例获取信息
            temp_instance = cls._language_map[normalized_lang]({}, None)
            return {
                "name": temp_instance.get_language_name(),
                "source_extension": temp_instance.get_source_extension(),
                "executable_extension": temp_instance.get_executable_extension(),
                "supported": True
            }
        else:
            return {
                "name": language,
                "source_extension": "unknown",
                "executable_extension": "unknown", 
                "supported": False
            }
    
    @classmethod
    def register_language(cls, name: str, language_class: type):
        """
        注册新的语言处理器
        
        Args:
            name: 语言名称
            language_class: 语言处理器类
        """
        if not issubclass(language_class, LanguageBase):
            raise ValueError("语言处理器必须继承自LanguageBase")
        
        cls._language_map[name.lower()] = language_class
    
    @classmethod
    def unregister_language(cls, name: str):
        """
        注销语言处理器
        
        Args:
            name: 语言名称
        """
        normalized_name = name.lower()
        if normalized_name in cls._language_map:
            del cls._language_map[normalized_name]
    
    @classmethod
    def clear_cache(cls):
        """清理语言处理器缓存"""
        cls._handler_cache.clear()
    
    @classmethod
    def get_cache_size(cls) -> int:
        """获取缓存大小"""
        return len(cls._handler_cache)


def create_language_handler(language: str, config: Dict[str, Any], work_dir: str = None) -> Optional[LanguageBase]:
    """
    创建语言处理器的便捷函数
    
    Args:
        language: 编程语言名称
        config: 配置字典
        work_dir: 工作目录
        
    Returns:
        LanguageBase: 语言处理器实例，如果不支持则返回None
    """
    return LanguageFactory.create_language_handler(language, config, work_dir)


def get_supported_languages() -> list:
    """获取支持的语言列表的便捷函数"""
    return LanguageFactory.get_supported_languages()


def is_language_supported(language: str) -> bool:
    """检查是否支持指定语言的便捷函数"""
    return LanguageFactory.is_language_supported(language)


def get_language_info(language: str) -> Dict[str, Any]:
    """获取语言信息的便捷函数"""
    return LanguageFactory.get_language_info(language)
