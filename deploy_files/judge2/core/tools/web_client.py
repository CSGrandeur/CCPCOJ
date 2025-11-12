#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 Web交互基类
封装与Web后端的通用交互逻辑
"""

import os
import sys
import json
import time
import logging
import requests
import requests.utils
import tempfile
from functools import wraps
from typing import Dict, Any, Optional, List, Callable
from datetime import datetime
from urllib.parse import urljoin
import re

# 将当前目录和父目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)

from tools.debug_manager import is_debug_enabled, log_error_with_context
import json as json_module
from tools import status_constants as sc

def sanitize_error_info(error_info: Dict[str, Any]) -> Dict[str, Any]:
    """
    清理错误信息中的非打印控制字符和异常字符
    
    Args:
        error_info: 结构化的错误信息 {"data_type": "txt", "data": "..."}
    
    Returns:
        清理后的错误信息，包含额外的元数据字段
    """
    if not isinstance(error_info, dict):
        return error_info
    
    # 创建清理后的副本
    sanitized_info = error_info.copy()
    
    # 如果包含 data 字段，进行清理
    if 'data' in sanitized_info and isinstance(sanitized_info['data'], str):
        data = sanitized_info['data']
        
        # 移除或替换非打印控制字符
        # 保留常见的控制字符：\n, \r, \t
        # 移除其他控制字符（ASCII 0-31，除了 9, 10, 13）
        cleaned_data = ''
        for char in data:
            char_code = ord(char)
            if char_code == 9 or char_code == 10 or char_code == 13:  # \t, \n, \r
                cleaned_data += char
            elif char_code >= 32 and char_code <= 126:  # 可打印ASCII字符
                cleaned_data += char
            elif char_code >= 128:  # 扩展字符（包括中文等）
                cleaned_data += char
            else:
                # 替换其他控制字符为可见的占位符
                cleaned_data += f'[\\x{char_code:02x}]'
        
        # 检测常见的二进制文件头标识
        binary_headers = {
            b'\x7fELF': 'ELF可执行文件',
            b'PE\x00\x00': 'Windows PE可执行文件', 
            b'MZ': 'DOS/Windows可执行文件',
            b'\x89PNG': 'PNG图片文件',
            b'\xff\xd8\xff': 'JPEG图片文件',
            b'GIF8': 'GIF图片文件',
            b'PK\x03\x04': 'ZIP压缩文件',
            b'Rar!': 'RAR压缩文件',
            b'\x1f\x8b': 'GZIP压缩文件',
        }
        
        detected_binary = None
        for header, description in binary_headers.items():
            if header in data.encode('latin-1', errors='ignore'):
                detected_binary = {
                    'header_hex': header.hex(),
                    'description': description,
                    'header_bytes': list(header)
                }
                break
        
        # 如果检测到二进制文件头，添加到元数据中
        if detected_binary:
            sanitized_info['binary_detection'] = detected_binary
        
        # 限制长度，避免过长的错误信息
        if len(cleaned_data) > 10000:  # 10KB限制
            sanitized_info['truncated'] = True
            sanitized_info['original_length'] = len(cleaned_data)
            cleaned_data = cleaned_data[:10000] + "\n[错误信息过长，已截断...]"
        
        sanitized_info['data'] = cleaned_data
    
    return sanitized_info

def require_auth(func):
    """认证装饰器：确保方法调用前已认证"""
    @wraps(func)
    def wrapper(self, *args, **kwargs):
        # 强制显示调试信息
        if is_debug_enabled():
            self.logger.debug(f"  方法: {func.__name__}")
            self.logger.debug(f"  认证状态: {self.is_authenticated}")
            self.logger.debug(f"  参数: args={args}, kwargs={kwargs}")
        
        # 确保已认证
        if not self.is_authenticated:
            if is_debug_enabled():
                self.logger.debug(f"认证失败，尝试重新登录...")
            if not self._authenticate():
                if is_debug_enabled():
                    self.logger.debug(f"认证失败，跳过方法调用: {func.__name__}")
                # 根据函数返回类型返回适当的默认值
                if func.__annotations__.get('return') == bool:
                    return False
                elif 'Optional' in str(func.__annotations__.get('return', '')):
                    return None
                elif 'List' in str(func.__annotations__.get('return', '')):
                    return []
                else:
                    return None
        
        if is_debug_enabled():
            self.logger.debug(f"认证成功，调用方法: {func.__name__}")
        
        return func(self, *args, **kwargs)
    return wrapper

class WebClient:
    def __init__(self, config: Dict[str, Any]):
        """初始化Web客户端"""
        self.config = config
        
        # 使用统一的日志系统
        from tools.debug_manager import get_debug_logger
        self.logger = get_debug_logger("WebClient")
        
        # 强制显示调试信息
        if is_debug_enabled():
            self.logger.debug(f"=== WebClient 初始化 ===")
            self.logger.debug(f"  配置: {config}")
        
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'CSGOJ-25-WebClient/1.0',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',    # ThinkPHP 的 ajax 判断依据
            'Accept-Encoding': 'gzip, deflate'       # 显式声明支持 gzip 与 deflate
        })
        
        # 设置基础URL
        self.base_url = config.get('server', {}).get('base_url', 'http://localhost')
        self.api_path = config.get('server', {}).get('api_path', '/ojtool/judge2')
        
        # 设置 cookie 缓存文件路径
        work_dir = config.get('judge', {}).get('work_dir', '/tmp')
        # if not os.path.isabs(work_dir):
        #     # 如果是相对路径，使用当前目录
        #     work_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))), work_dir)
        cookie_dir = os.path.join(work_dir, '.webclient_cache')
        os.makedirs(cookie_dir, exist_ok=True)
        self.cookie_file = os.path.join(cookie_dir, 'cookies.json')
        
        # 认证状态
        self.is_authenticated = False
        self.max_retries = 3
        self.retry_delay = 1  # 秒
        
        # 加载缓存的 cookie
        self._load_cookies()
    
    def _load_cookies(self):
        """从文件加载缓存的 cookie"""
        try:
            if os.path.exists(self.cookie_file):
                with open(self.cookie_file, 'r', encoding='utf-8') as f:
                    cookies_dict = json.load(f)
                    # 使用 requests.utils.cookiejar_from_dict 将字典转换为 CookieJar
                    # 然后更新到 session 的 cookies
                    if cookies_dict:
                        cookie_jar = requests.utils.cookiejar_from_dict(cookies_dict)
                        self.session.cookies.update(cookie_jar)
                        # 如果成功加载了 cookie，乐观假设认证有效，避免不必要的登录请求
                        # 如果 cookie 已过期，后续请求会返回 AUTH_FAILED，届时会重新登录
                        self.is_authenticated = True
                        if is_debug_enabled():
                            self.logger.debug(f"已加载缓存的 cookie: {len(cookies_dict)} 个，设置认证状态为已认证")
        except json.JSONDecodeError as e:
            self.logger.warning(f"加载 cookie 文件失败（JSON格式错误）: {e}")
            self.is_authenticated = False
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"加载 cookie 文件失败: {e}")
            self.is_authenticated = False
    
    def _save_cookies(self):
        """保存 cookie 到文件"""
        try:
            # 将 session 的 cookie 转换为字典格式
            cookies_dict = {}
            for cookie in self.session.cookies:
                cookies_dict[cookie.name] = cookie.value
            
            # 确保目录存在
            os.makedirs(os.path.dirname(self.cookie_file), exist_ok=True)
            
            # 保存到文件
            with open(self.cookie_file, 'w', encoding='utf-8') as f:
                json.dump(cookies_dict, f, ensure_ascii=False, indent=2)
            
            if is_debug_enabled():
                self.logger.debug(f"已保存 cookie 到文件: {self.cookie_file} ({len(cookies_dict)} 个)")
        except Exception as e:
            self.logger.warning(f"保存 cookie 文件失败: {e}")
    
    def close(self):
        """关闭 Session，释放连接池和资源"""
        try:
            if hasattr(self, 'session') and self.session:
                self.session.close()
                if is_debug_enabled():
                    self.logger.debug("WebClient Session 已关闭")
        except Exception as e:
            if is_debug_enabled():
                self.logger.debug(f"关闭 WebClient Session 时发生错误: {e}")
        
    def _make_request(self, method: str, endpoint: str, **kwargs) -> Optional[Dict[str, Any]]:
        """发起HTTP请求的通用方法（带重试机制）"""
        for attempt in range(self.max_retries):
            try:
                # 使用封装的URL构建方法
                url = self._build_url(endpoint)
                
                # Debug模式：显示请求详情
                if is_debug_enabled():
                    request_details = {
                        "尝试次数": f"{attempt + 1}/{self.max_retries}",
                        "方法": method,
                        "URL": url
                    }
                    if 'params' in kwargs:
                        request_details["URL参数"] = kwargs['params']
                    if 'json' in kwargs:
                        request_details["JSON负载"] = kwargs['json']
                    if 'data' in kwargs:
                        request_details["表单数据"] = kwargs['data']
                    if 'headers' in kwargs:
                        request_details["请求头"] = kwargs['headers']
                    
                    # 分别显示每个部分，确保JSON负载清晰可见
                    self.logger.debug(f"发起HTTP请求:")
                    self.logger.debug(f"  方法: {method}")
                    self.logger.debug(f"  URL: {url}")
                    if 'params' in kwargs:
                        self.logger.debug(f"  URL参数: {kwargs['params']}")
                    if 'json' in kwargs:
                        # 输出标准JSON格式，方便复制使用
                        json_str = json_module.dumps(kwargs['json'], ensure_ascii=False, indent=2)
                        self.logger.debug(f"  JSON负载:\n{json_str}")
                    if 'data' in kwargs:
                        self.logger.debug(f"  表单数据: {kwargs['data']}")
                    if 'headers' in kwargs:
                        self.logger.debug(f"  请求头: {kwargs['headers']}")
                
                # 设置超时
                timeout = kwargs.pop('timeout', 30)
                
                response = self.session.request(method, url, timeout=timeout, **kwargs)
                
                # Debug模式：显示响应详情
                if is_debug_enabled():
                    response_details = {
                        "状态码": response.status_code,
                        "响应头": dict(response.headers)
                    }
                    if response.status_code == 200:
                        try:
                            response_json = response.json()
                            response_details["响应JSON"] = response_json
                        except json.JSONDecodeError:
                            response_details["响应文本"] = response.text[:500] + "..."
                    self.logger.debug(f"收到HTTP响应: {response_details}")
                
                if response.status_code == 200:
                    try:
                        result = response.json()
                        # 检查认证失败：ThinkPHP错误响应code=0且data.code='AUTH_FAILED'
                        if result.get('code') == 0 and result.get('data', {}).get('code') == 'AUTH_FAILED':
                            self.logger.warning("认证失败，尝试重新登录")
                            self.is_authenticated = False
                            if self._authenticate():
                                # 重新发起请求
                                continue
                            else:
                                return None
                        return result
                    except json.JSONDecodeError:
                        # 如果不是JSON响应，返回文本内容
                        return {"success": True, "data": response.text}
                else:
                    # 详细的错误信息
                    error_details = {
                        "状态码": response.status_code,
                        "URL": url,
                        "方法": method
                    }
                    
                    # 尝试获取响应内容
                    try:
                        response_text = response.text
                        error_details["响应内容"] = response_text[:500] + "..." if len(response_text) > 500 else response_text
                    except:
                        error_details["响应内容"] = "无法读取响应内容"
                    
                    # 尝试解析JSON错误信息
                    try:
                        error_json = response.json()
                        error_details["JSON错误"] = error_json
                    except:
                        pass
                    
                    log_error_with_context(self.logger, "HTTP请求失败", None, error_details)
                    
                    if attempt < self.max_retries - 1:
                        self.logger.info(f"等待 {self.retry_delay} 秒后重试...")
                        time.sleep(self.retry_delay)
                        continue
                    return None
                    
            except requests.exceptions.RequestException as e:
                self.logger.error(f"请求异常：{e}")
                if attempt < self.max_retries - 1:
                    self.logger.info(f"等待 {self.retry_delay} 秒后重试...")
                    time.sleep(self.retry_delay)
                    continue
                return None
            except Exception as e:
                self.logger.error(f"请求处理异常：{e}")
                return None
        
        return None
    
    def _build_url(self, endpoint: str) -> str:
        """构建完整的请求URL，避免双斜杠题目"""
        from urllib.parse import urljoin
        
        # 清理所有路径组件的斜杠
        base_url = self.base_url.rstrip('/')
        api_path = self.api_path.strip('/')
        endpoint = endpoint.strip('/')
        
        # 构建路径组件列表，过滤空字符串
        path_parts = [part for part in [base_url, api_path] if part]
        
        # 使用join连接，然后添加斜杠
        base_path = '/'.join(path_parts) + '/'
        
        # 使用urljoin进行安全的URL拼接
        return urljoin(base_path, endpoint)
    
    def _handle_response(self, result: Optional[Dict[str, Any]], success_msg: str = "", error_msg: str = "") -> bool:
        """处理ThinkPHP响应，返回是否成功"""
        if result and result.get('code') == 1:
            if success_msg:
                self.logger.info(success_msg)
            return True
        else:
            error_text = result.get('msg', '未知错误') if result else '无响应'
            
            # 详细的错误调试信息
            if is_debug_enabled():
                error_details = {
                    "响应数据": result,
                    "错误代码": result.get('code') if result else None,
                    "错误消息": error_text,
                    "完整响应": result
                }
                log_error_with_context(self.logger, "处理响应", None, error_details)
            
            if error_msg:
                self.logger.error(f"{error_msg}：{error_text}")
            return False
    
    def _get_response_data(self, result: Optional[Dict[str, Any]], default_value=None):
        """从响应中提取数据"""
        if result and result.get('code') == 1:
            return result.get('data', default_value)
        return default_value
    
    def _authenticate(self) -> bool:
        """认证登录"""
        try:
            user_id = self.config.get('server', {}).get('user_id', '')
            password = self.config.get('server', {}).get('password', '')
            
            # Debug模式：显示登录过程
            if is_debug_enabled():
                login_details = {
                    "用户名": user_id,
                    "密码": '*' * len(password) if password else '未设置',
                    "登录URL": f"{self.base_url}{self.api_path}/judge_login"
                }
                self.logger.debug(f"开始认证登录: {login_details}")
            
            if not user_id or not password:
                self.logger.error("未配置用户名或密码")
                return False
            
            data = {
                "user_id": user_id,
                "password": password
            }
            
            # Debug模式：显示登录请求详情
            if is_debug_enabled():
                self.logger.debug(f"发送登录请求: {data}")
            
            result = self._make_request('POST', 'judge_login', json=data)
            
            # Debug模式：显示登录响应详情
            if is_debug_enabled():
                response_details = {
                    "响应数据": result,
                    "状态码": result.get('code', 'N/A') if result else 'N/A',
                    "消息": result.get('msg', 'N/A') if result else 'N/A',
                    "用户ID": result.get('data', {}).get('user_id', 'N/A') if result else 'N/A'
                }
                self.logger.debug(f"收到登录响应: {response_details}")
            
            # 使用封装的响应处理方法
            if self._handle_response(result, "评测机登录成功", "登录失败"):
                self.is_authenticated = True
                # 登录成功后保存 cookie
                self._save_cookies()
                return True
            else:
                return False
                
        except Exception as e:
            self.logger.error(f"登录时发生错误：{e}")
            return False
    
    @require_auth
    def get_pending_tasks(self, max_tasks: int = 1) -> List[Dict[str, Any]]:
        """获取待评测任务"""
        try:
            params = {"max_tasks": max_tasks}
            result = self._make_request('GET', 'getpending', params=params)
            return self._get_response_data(result, [])
                
        except Exception as e:
            self.logger.error(f"获取评测任务时发生错误：{e}")
            return []
    
    @require_auth
    def get_solution_info(self, solution_id: int) -> Optional[Dict[str, Any]]:
        """获取解决方案信息"""
        try:
            params = {"sid": solution_id}
            result = self._make_request('GET', 'getsolutioninfo', params=params)
            return self._get_response_data(result)
                
        except Exception as e:
            self.logger.error(f"获取解决方案信息时发生错误：{e}")
            return None
    
    @require_auth
    def get_solution_code(self, solution_id: int) -> Optional[str]:
        """获取解决方案代码"""
        try:
            params = {"sid": solution_id}
            result = self._make_request('GET', 'getsolution', params=params)
            return self._get_response_data(result)
                
        except Exception as e:
            self.logger.error(f"获取解决方案代码时发生错误：{e}")
            return None
    
    @require_auth
    def get_problem_info(self, problem_id: int) -> Optional[Dict[str, Any]]:
        """获取题目信息"""
        try:
            params = {"pid": problem_id}
            result = self._make_request('GET', 'getprobleminfo', params=params)
            return self._get_response_data(result)
                
        except Exception as e:
            self.logger.error(f"获取题目信息时发生错误：{e}")
            return None
    
    @require_auth
    def download_problem_data(self, problem_id: int, save_path: str) -> bool:
        """下载题目测试数据"""
        try:
            params = {"problem_id": problem_id}
            url = f"{self.base_url}{self.api_path}/getdata"
            
            # Debug模式：显示下载请求详情
            if self.logger.isEnabledFor(logging.DEBUG):
                self.logger.debug(f"发起数据下载请求:")
                self.logger.debug(f"  题目ID: {problem_id}")
                self.logger.debug(f"  URL: {url}")
                self.logger.debug(f"  参数: {params}")
                self.logger.debug(f"  保存路径: {save_path}")
            
            response = self.session.get(url, params=params, timeout=60, stream=True)
            # 确保对 gzip 等压缩内容进行透明解压
            try:
                if hasattr(response, 'raw') and hasattr(response.raw, 'decode_content'):
                    response.raw.decode_content = True
            except Exception:
                pass
            
            # Debug模式：显示下载响应详情
            if self.logger.isEnabledFor(logging.DEBUG):
                self.logger.debug(f"收到下载响应:")
                self.logger.debug(f"  状态码: {response.status_code}")
                self.logger.debug(f"  响应头: {dict(response.headers)}")
                if 'Content-Length' in response.headers:
                    self.logger.debug(f"  文件大小: {response.headers['Content-Length']} bytes")
            
            if response.status_code == 200:
                # 确保保存目录存在
                os.makedirs(os.path.dirname(save_path), exist_ok=True)
                
                # 保存数据文件
                downloaded_size = 0
                with open(save_path, 'wb') as f:
                    for chunk in response.iter_content(chunk_size=8192):
                        if chunk:
                            f.write(chunk)
                            downloaded_size += len(chunk)
                            
                            # Debug模式：显示下载进度
                            if self.logger.isEnabledFor(logging.DEBUG) and downloaded_size % (1024 * 1024) == 0:  # 每MB显示一次
                                self.logger.debug(f"  已下载: {downloaded_size / 1024 / 1024:.1f} MB")
                
                self.logger.info(f"题目 {problem_id} 数据下载完成：{save_path} ({downloaded_size} bytes)")
                return True
            else:
                self.logger.error(f"下载题目 {problem_id} 数据失败，状态码：{response.status_code}")
                return False
                
        except Exception as e:
            self.logger.error(f"下载题目 {problem_id} 数据时发生错误：{e}")
            return False
    
    @require_auth
    def get_problem_files(self, problem_id: int) -> List[Dict[str, Any]]:
        """获取问题文件列表和哈希"""
        try:
            params = {"problem_id": problem_id}
            result = self._make_request('GET', 'get_datafile_list', params=params)
            return self._get_response_data(result, [])
                
        except Exception as e:
            self.logger.error(f"获取问题 {problem_id} 文件列表时发生错误：{e}")
            return []
    
    @require_auth
    def download_problem_file(self, problem_id: int, file_path: str, save_path: str) -> bool:
        """下载问题单个文件"""
        try:
            params = {
                "problem_id": problem_id,
                "file_path": file_path
            }
            url = self._build_url('getdatafile')
            
            # Debug模式：显示下载请求详情
            if self.logger.isEnabledFor(logging.DEBUG):
                self.logger.debug(f"发起单文件下载请求:")
                self.logger.debug(f"  问题ID: {problem_id}")
                self.logger.debug(f"  文件路径: {file_path}")
                self.logger.debug(f"  URL: {url}")
                self.logger.debug(f"  保存路径: {save_path}")
                self.logger.debug(f"  参数: {params}")
            
            response = self.session.get(url, params=params, timeout=60, stream=True)
            # 确保对 gzip 等压缩内容进行透明解压
            try:
                if hasattr(response, 'raw') and hasattr(response.raw, 'decode_content'):
                    response.raw.decode_content = True
            except Exception:
                pass
            
            # Debug模式：显示下载响应详情
            if self.logger.isEnabledFor(logging.DEBUG):
                self.logger.debug(f"收到单文件下载响应:")
                self.logger.debug(f"  状态码: {response.status_code}")
                self.logger.debug(f"  响应头: {dict(response.headers)}")
                if 'Content-Length' in response.headers:
                    self.logger.debug(f"  文件大小: {response.headers['Content-Length']} bytes")
            
            if response.status_code == 200:
                # 确保保存目录存在
                os.makedirs(os.path.dirname(save_path), exist_ok=True)
                
                # 保存文件
                with open(save_path, 'wb') as f:
                    for chunk in response.iter_content(chunk_size=8192):
                        if chunk:
                            f.write(chunk)
                
                self.logger.info(f"题目 {problem_id} 文件下载完成：{file_path} -> {save_path}")
                return True
            else:
                self.logger.error(f"题目 {problem_id} 下载文件 {file_path} 失败，状态码：{response.status_code}")
                return False
                
        except Exception as e:
            self.logger.error(f"题目 {problem_id} 下载文件 {file_path} 时发生错误：{e}")
            return False
    
    @require_auth
    def update_task_status(self, task_id: int, status: str, result: Dict[str, Any] = None) -> bool:
        """更新任务状态"""
        # 强制显示调试信息，确认方法被调用
        self.logger.debug(f"=== update_task_status 被调用 ===")
        self.logger.debug(f"task_id: {task_id}, status: {status}, result: {result}")
        
        try:
            data = {
                "solution_id": task_id,  # 修正参数名
                "task_status": status,   # 评测机任务状态
                "judge_result_data": result or {}  # 完整评测数据包
            }
            
            # Debug模式：显示发送的数据
            if is_debug_enabled():
                self.logger.debug(f"准备发送更新任务状态请求:")
                self.logger.debug(f"  任务ID: {task_id}")
                self.logger.debug(f"  状态: {status}")
                self.logger.debug(f"  结果: {result}")
                self.logger.debug(f"  完整数据: {data}")
            
            response = self._make_request('POST', 'updatesolution', json=data)
            
            # 根据状态和结果生成更清晰的日志信息
            if status == sc.TASK_COMPLETED:
                result_name = result.get('judge_result', sc.JUDGE_UNKNOWN) if result else sc.JUDGE_UNKNOWN
                success_msg = f"任务 {task_id} 评测完成，结果：{result_name}"
            elif status == sc.TASK_ERROR:
                result_name = result.get('judge_result', sc.JUDGE_UNKNOWN) if result else sc.JUDGE_UNKNOWN
                success_msg = f"任务 {task_id} 评测出错，结果：{result_name}"
            else:
                success_msg = f"任务 {task_id} 状态更新为 {status}"
            
            return self._handle_response(response, success_msg, "更新任务状态失败")
                
        except Exception as e:
            log_error_with_context(self.logger, "更新任务状态", e, {
                "task_id": task_id,
                "task_status": status,
                "judge_result_data": result
            })
            return False
    
    @require_auth
    def add_compile_error(self, solution_id: int, ce_info: Dict[str, Any]) -> bool:
        """添加编译错误信息（结构化数据）"""
        try:
            # 预处理错误信息，清理异常字符
            sanitized_ce_info = sanitize_error_info(ce_info)
            
            data = {
                "sid": solution_id,
                "ceinfo": sanitized_ce_info  # 清理后的结构化数据
            }
            
            response = self._make_request('POST', 'addceinfo', json=data)
            return self._handle_response(response, f"解决方案 {solution_id} 编译错误信息已添加", "添加编译错误信息失败")
                
        except Exception as e:
            self.logger.error(f"添加编译错误信息时发生错误：{e}")
            return False
    
    @require_auth
    def add_runtime_error(self, solution_id: int, re_info: Dict[str, Any]) -> bool:
        """添加运行时错误信息（结构化数据）"""
        try:
            # 预处理错误信息，清理异常字符
            sanitized_re_info = sanitize_error_info(re_info)
            
            data = {
                "sid": solution_id,
                "reinfo": sanitized_re_info  # 清理后的结构化数据
            }
            
            response = self._make_request('POST', 'addreinfo', json=data)
            return self._handle_response(response, f"解决方案 {solution_id} 运行时错误信息已添加", "添加运行时错误信息失败")
                
        except Exception as e:
            self.logger.error(f"添加运行时错误信息时发生错误：{e}")
            return False
    
    @require_auth
    def update_user_stats(self, user_id: str) -> bool:
        """更新用户统计信息"""
        try:
            data = {"user_id": user_id}
            response = self._make_request('POST', 'updateuser', json=data)
            return self._handle_response(response, f"用户 {user_id} 统计信息已更新", "更新用户统计信息失败")
                
        except Exception as e:
            self.logger.error(f"更新用户统计信息时发生错误：{e}")
            return False
    
    @require_auth
    def update_problem_stats(self, problem_id: int) -> bool:
        """更新题目统计信息"""
        try:
            data = {"problem_id": problem_id}
            response = self._make_request('POST', 'updateproblem', json=data)
            return self._handle_response(response, f"题目 {problem_id} 统计信息已更新", "更新题目统计信息失败")
                
        except Exception as e:
            self.logger.error(f"更新题目统计信息时发生错误：{e}")
            return False
