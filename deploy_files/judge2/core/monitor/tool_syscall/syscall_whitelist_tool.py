#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
系统调用白名单测试工具
从 monitor_syscall.py 中提取的白名单测试相关功能
"""

import os
import sys
import logging
import subprocess
import time
import seccomp
from typing import Dict, Any, Optional, List, Set

# 将当前目录和core目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
# tool_syscall 在 core/monitor/tool_syscall/ 下，需要访问 core 目录
core_dir = os.path.dirname(os.path.dirname(os.path.dirname(current_dir)))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)
if core_dir not in sys.path:
    sys.path.insert(0, core_dir)

# 导入seccomp相关模块 - 如果失败则直接报错
from monitor.monitor_syscall import MonitorSyscall


def _save_seccomp_logs_to_temp(program_cmd: str, logger: logging.Logger):
    """保存seccomp日志到temp目录"""
    try:
        # 创建temp目录
        temp_dir = os.path.join(current_dir, "temp")
        os.makedirs(temp_dir, exist_ok=True)
        
        # 获取完整的dmesg日志
        result = subprocess.run(["dmesg"], capture_output=True, text=True, timeout=10)
        if result.returncode == 0:
            log_content = result.stdout
            
            # 生成文件名
            timestamp = time.strftime("%Y%m%d_%H%M%S")
            cmd_safe = program_cmd.replace(" ", "_").replace("/", "_").replace("\\", "_")[:50]
            log_file = os.path.join(temp_dir, f"seccomp_logs_TRAP_{cmd_safe}_{timestamp}.txt")
            
            # 保存完整的dmesg日志到文件
            with open(log_file, 'w', encoding='utf-8') as f:
                f.write(f"# Complete dmesg logs for whitelist test\n")
                f.write(f"# Generated at: {time.strftime('%Y-%m-%d %H:%M:%S')}\n")
                f.write(f"# Program command: {program_cmd}\n")
                f.write(f"# Seccomp mode: TRAP\n")
                f.write(f"# Total lines: {len(log_content.splitlines())}\n\n")
                f.write(log_content)
            
            logger.info(f"白名单测试：已保存完整 dmesg 日志到 {log_file}")
        else:
            logger.warning("白名单测试：无法获取 dmesg 日志")
            
    except Exception as e:
        logger.warning(f"白名单测试：保存 seccomp 日志失败: {e}")


def test_whitelist_with_seccomp(program_cmd: str, whitelist: set, logger: logging.Logger, work_dir: str = None) -> bool:
    """
    使用seccomp测试白名单 - 使用TRAP模式
    
    Args:
        program_cmd: 要测试的程序命令
        whitelist: 系统调用白名单
        logger: 日志记录器
        work_dir: 工作目录，如果提供则在此目录中执行程序
    
    Returns:
        bool: 测试是否成功
    """
    try:
        # 创建preexec_fn函数 - 使用TRAP模式
        def preexec_fn():
            """在子进程中创建和应用seccomp过滤器 - 使用TRAP模式"""
            try:
                # TRAP模式：发送SIGSYS信号并记录到audit日志（最佳实践）
                filter_obj = seccomp.SyscallFilter(seccomp.TRAP)
                
                # 添加允许的系统调用
                for syscall in whitelist:
                    try:
                        filter_obj.add_rule(seccomp.ALLOW, syscall)
                    except Exception as e:
                        logger.debug(f"添加系统调用规则失败 {syscall}: {e}")
                        continue
                
                # 在子进程中加载过滤器
                filter_obj.load()
                logger.debug(f"seccomp TRAP过滤器已应用到子进程 {os.getpid()}")
            except Exception as e:
                logger.error(f"应用seccomp过滤器失败: {e}")
                raise RuntimeError(f"应用seccomp过滤器失败: {e}")
        
        # # 清空dmesg日志
        # subprocess.run(["dmesg", "-C"], capture_output=True)
        
        if work_dir:
            # 在指定工作目录中执行程序
            process = subprocess.Popen(
                ['sh', '-c', program_cmd],
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                cwd=work_dir,
                preexec_fn=preexec_fn
            )
        else:
            # 在当前目录中执行程序
            process = subprocess.Popen(
                ['sh', '-c', program_cmd],
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                preexec_fn=preexec_fn
            )
        
        # 等待进程完成，设置超时
        try:
            stdout, stderr = process.communicate(timeout=30)
            return_code = process.returncode
        except subprocess.TimeoutExpired:
            process.kill()
            stdout, stderr = process.communicate()
            return_code = process.returncode
            logger.warning(f"白名单测试超时，返回码: {return_code}")
        
        # 保存seccomp日志到temp目录
        _save_seccomp_logs_to_temp(program_cmd, logger)
        
        # 分析返回码 - 参考demo的最佳实践
        if return_code < 0:
            signal_num = -return_code
            if signal_num == 31:  # SIGSYS
                logger.info(f"🎯 确认: 进程被 SIGSYS 信号终止，表示 seccomp 阻止了系统调用")
            else:
                logger.warning(f"进程被其他信号终止: {signal_num}")
            return False
        elif return_code != 0:
            logger.warning(f"白名单测试失败，返回码: {return_code}")
            logger.warning(f"stderr: {stderr}")
            logger.warning(f"stdout: {stdout}")
            return False
        else:
            logger.info(f"✓ 白名单测试成功，程序正常执行")
            return True
    except Exception as e:
        logger.warning(f"测试白名单失败: {e}")
        return False


def auto_generate_whitelist(program_cmd: str, max_iterations: int = 10, logger: logging.Logger = None, base_whitelist: set = None, work_dir: str = None):
    """
    自动生成程序的白名单
    
    Args:
        program_cmd: 要测试的程序命令（如 "g++ -o test test.cpp"）
        max_iterations: 最大迭代次数
        logger: 日志记录器
        base_whitelist: 基础白名单，如果提供则从此开始生成增补白名单
        work_dir: 工作目录，如果提供则在此目录中执行程序
    
    Returns:
        Set[str]: 生成的系统调用白名单
    """
    if logger is None:
        logger = logging.getLogger("AutoWhitelist")
    
    # 初始白名单
    if base_whitelist is not None:
        current_whitelist = base_whitelist.copy()
        logger.info(f"从基础白名单开始生成增补白名单，基础白名单包含 {len(base_whitelist)} 个系统调用")
    else:
        current_whitelist = set()
        logger.info("从空白名单开始生成白名单")
    
    logger.info(f"开始自动生成白名单，测试程序: {program_cmd}")
    logger.info(f"初始白名单包含 {len(current_whitelist)} 个系统调用")
    
    for iteration in range(max_iterations):
        logger.info(f"\n=== 第 {iteration + 1} 次迭代 ===")
        
        try:
            # 每次迭代执行多次程序，以覆盖可能的执行分支
            all_blocked_syscalls = set()
            logger.info(f"使用seccomp日志模式测试程序: {program_cmd}")
            logger.info(f"当前白名单包含 {len(current_whitelist)} 个系统调用: {sorted(current_whitelist)}")
            
            # 多次运行程序，以触发不同的代码路径
            for attempt in range(3):
                logger.info(f"  第 {attempt + 1} 次尝试...")
                
                # 只在第一次尝试前清空日志
                if attempt == 0:
                    subprocess.run(["dmesg", "-C"], capture_output=True)
                
                # 创建preexec_fn函数 - 使用TRAP模式的最佳实践
                def preexec_fn():
                    """在子进程中创建和应用seccomp过滤器 - 使用TRAP模式"""
                    try:
                        # 使用TRAP模式 - 最佳实践：发送SIGSYS信号并记录到audit日志
                        filter_obj = seccomp.SyscallFilter(seccomp.TRAP)
                        
                        # 添加允许的系统调用
                        for syscall in current_whitelist:
                            try:
                                filter_obj.add_rule(seccomp.ALLOW, syscall)
                            except Exception as e:
                                logger.debug(f"添加系统调用规则失败 {syscall}: {e}")
                                continue
                        
                        # 在子进程中加载过滤器
                        filter_obj.load()
                        logger.debug(f"seccomp TRAP过滤器已应用到子进程 {os.getpid()}")
                    except Exception as e:
                        logger.error(f"应用seccomp过滤器失败: {e}")
                        raise RuntimeError(f"应用seccomp过滤器失败: {e}")
                
                # 执行程序 - 使用Popen方式
                if work_dir:
                    # 在指定工作目录中执行程序
                    process = subprocess.Popen(
                        ['sh', '-c', program_cmd],
                        stdout=subprocess.PIPE,
                        stderr=subprocess.PIPE,
                        text=True,
                        cwd=work_dir,
                        preexec_fn=preexec_fn
                    )
                else:
                    # 在当前目录中执行程序
                    process = subprocess.Popen(
                        ['sh', '-c', program_cmd],
                        stdout=subprocess.PIPE,
                        stderr=subprocess.PIPE,
                        text=True,
                        preexec_fn=preexec_fn
                    )
                
                # 等待进程完成，设置超时
                try:
                    stdout, stderr = process.communicate(timeout=30)
                    return_code = process.returncode
                except subprocess.TimeoutExpired:
                    process.kill()
                    stdout, stderr = process.communicate()
                    return_code = process.returncode
                    logger.warning(f"    程序执行超时，返回码: {return_code}")
                
                logger.info(f"    程序执行完成，返回码: {return_code}")
                
                # 分析返回码 - 参考demo的最佳实践
                if return_code < 0:
                    signal_num = -return_code
                    if signal_num == 31:  # SIGSYS
                        logger.info(f"    🎯 确认: 进程被 SIGSYS 信号终止，表示 seccomp 阻止了系统调用")
                    else:
                        logger.warning(f"    进程被其他信号终止: {signal_num}")
                elif return_code != 0:
                    logger.warning(f"    程序执行失败，返回码: {return_code}")
                    logger.warning(f"    stderr: {stderr}")
                    logger.warning(f"    stdout: {stdout}")
                else:
                    logger.info(f"    ✓ 程序成功执行，白名单可能完整")
                
                # 保存seccomp日志到temp目录
                _save_seccomp_logs_to_temp(program_cmd, logger)
                
                # 解析seccomp日志，获取所有系统调用
                # 创建临时监控器来解析日志
                temp_monitor = MonitorSyscall(logger, task_type='custom')
                all_logged_syscalls = temp_monitor.parse_seccomp_logs("dmesg")
                if all_logged_syscalls:
                    # 计算新的系统调用（不在当前白名单中的）
                    new_syscalls = all_logged_syscalls - current_whitelist
                    if new_syscalls:
                        all_blocked_syscalls.update(new_syscalls)
                        logger.info(f"    发现新的系统调用: {sorted(new_syscalls)}")
                        logger.info(f"    日志中所有系统调用: {sorted(all_logged_syscalls)}")
                        logger.info(f"    当前白名单: {sorted(current_whitelist)}")
                    else:
                        logger.info(f"    日志中的系统调用都在当前白名单中: {sorted(all_logged_syscalls)}")
                
                # 短暂等待，让系统稳定
                time.sleep(0.5)
            
            # 处理本轮迭代发现的所有被阻止的系统调用
            if all_blocked_syscalls:
                logger.info(f"本轮迭代总共发现被seccomp阻止的系统调用: {sorted(all_blocked_syscalls)}")
                # 将被阻止的系统调用添加到白名单
                current_whitelist.update(all_blocked_syscalls)
                logger.info(f"添加被阻止的系统调用后，白名单包含 {len(current_whitelist)} 个系统调用")
                
                # 测试更新后的白名单（使用生产模式）
                logger.info("测试更新后的白名单（生产模式）...")
                if test_whitelist_with_seccomp(program_cmd, current_whitelist, logger, flg_allow_errno=False, work_dir=work_dir):
                    logger.info("✓ 添加被阻止的系统调用后测试成功！")
                    break
                else:
                    logger.info("添加被阻止的系统调用后仍然失败，继续迭代...")
            else:
                # 没有发现被阻止的系统调用，但需要验证白名单是否完整
                logger.info("没有发现被阻止的系统调用，验证白名单是否完整...")
                
                # 使用生产模式测试当前白名单
                if test_whitelist_with_seccomp(program_cmd, current_whitelist, logger, flg_allow_errno=False, work_dir=work_dir):
                    logger.info("✓ 白名单验证成功，程序可以正常运行！")
                    break
                else:
                    logger.info("白名单验证失败，可能还有遗漏的系统调用，继续迭代...")
                    
                # 如果白名单验证失败，尝试强制发现更多系统调用
                logger.info("尝试强制发现更多被阻止的系统调用...")
                temp_monitor = MonitorSyscall(logger, task_type='custom')
                additional_syscalls = temp_monitor._force_discover_blocked_syscalls(program_cmd, current_whitelist)
                if additional_syscalls:
                    logger.info(f"强制发现额外系统调用: {sorted(additional_syscalls)}")
                    current_whitelist.update(additional_syscalls)
                    logger.info(f"更新后白名单包含 {len(current_whitelist)} 个系统调用")
                else:
                    logger.warning("无法发现更多被阻止的系统调用，可能需要手动分析")
                    break
                
        except subprocess.TimeoutExpired:
            logger.warning("程序执行超时")
            break
        except Exception as e:
            logger.error(f"程序执行失败: {e}")
            break
        
        time.sleep(1)  # 短暂等待
    
    logger.info(f"\n=== 白名单生成完成 ===")
    logger.info(f"最终白名单包含 {len(current_whitelist)} 个系统调用")
    logger.info(f"系统调用列表: {sorted(current_whitelist)}")
    
    # 如果提供了基础白名单，返回增补的系统调用
    if base_whitelist is not None:
        additional_syscalls = current_whitelist - base_whitelist
        logger.info(f"增补的系统调用: {len(additional_syscalls)} 个")
        logger.info(f"增补系统调用列表: {sorted(additional_syscalls)}")
        return additional_syscalls
    else:
        return current_whitelist


def test_program_with_whitelist(program_cmd: str, whitelist: Set[str], logger: logging.Logger = None):
    """
    使用指定的白名单测试程序 - 使用TRAP模式
    
    Args:
        program_cmd: 要测试的程序命令
        whitelist: 系统调用白名单
        logger: 日志记录器
    
    Returns:
        bool: 程序是否成功运行
    """
    import signal
    
    if logger is None:
        logger = logging.getLogger("TestWhitelist")
    
    try:
        # 创建preexec_fn函数 - 使用TRAP模式
        def preexec_fn():
            """在子进程中创建和应用seccomp过滤器 - 使用TRAP模式"""
            try:
                # TRAP模式：发送SIGSYS信号并记录到audit日志（最佳实践）
                filter_obj = seccomp.SyscallFilter(seccomp.TRAP)
                
                # 添加允许的系统调用
                for syscall in whitelist:
                    try:
                        filter_obj.add_rule(seccomp.ALLOW, syscall)
                    except Exception as e:
                        logger.debug(f"添加系统调用规则失败 {syscall}: {e}")
                        continue
                
                # 在子进程中加载过滤器
                filter_obj.load()
                logger.debug(f"seccomp TRAP过滤器已应用到子进程 {os.getpid()}")
            except Exception as e:
                logger.error(f"应用seccomp过滤器失败: {e}")
                raise RuntimeError(f"应用seccomp过滤器失败: {e}")
        
        logger.info(f"使用白名单测试程序: {program_cmd}")
        logger.info(f"白名单包含 {len(whitelist)} 个系统调用")
        
        # 执行程序 - 使用Popen方式
        process = subprocess.Popen(
            ["sh", "-c", program_cmd],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            preexec_fn=preexec_fn
        )
        
        # 等待进程完成，设置超时
        try:
            stdout, stderr = process.communicate(timeout=30)
            return_code = process.returncode
        except subprocess.TimeoutExpired:
            process.kill()
            stdout, stderr = process.communicate()
            return_code = process.returncode
            logger.warning(f"程序执行超时，返回码: {return_code}")
        
        # 保存seccomp日志到temp目录
        _save_seccomp_logs_to_temp(program_cmd, logger)
        
        # 分析返回码 - 参考demo的最佳实践
        if return_code < 0:
            signal_num = -return_code
            if signal_num == 31:  # SIGSYS
                logger.info(f"🎯 确认: 进程被 SIGSYS 信号终止，表示 seccomp 阻止了系统调用")
            else:
                logger.warning(f"进程被其他信号终止: {signal_num}")
            return False
        elif return_code == 0:
            logger.info("✓ 程序成功运行")
            return True
        else:
            logger.warning(f"✗ 程序运行失败，返回码: {return_code}")
            if stderr:
                logger.warning(f"错误输出: {stderr}")
            return False
            
    except subprocess.TimeoutExpired:
        logger.error("程序执行超时")
        return False
    except Exception as e:
        logger.error(f"程序执行失败: {e}")
        return False


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="系统调用白名单测试工具")
    parser.add_argument("program_cmd", help="要测试的程序命令")
    parser.add_argument("--max-iterations", type=int, default=10, help="最大迭代次数")
    parser.add_argument("--test-whitelist", help="测试指定的白名单文件")
    
    args = parser.parse_args()
    
    # 设置日志
    logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
    logger = logging.getLogger("SyscallWhitelistTool")
    
    if args.test_whitelist:
        # 测试指定的白名单
        with open(args.test_whitelist, 'r') as f:
            whitelist = set(line.strip().strip('"').strip(',') for line in f if line.strip())
        
        success = test_program_with_whitelist(args.program_cmd, whitelist, logger)
        if success:
            print("✓ 白名单测试成功")
        else:
            print("✗ 白名单测试失败")
    else:
        # 自动生成白名单
        whitelist = auto_generate_whitelist(args.program_cmd, args.max_iterations, logger)
        print(f"\n生成的白名单 ({len(whitelist)} 个系统调用):")
        for syscall in sorted(whitelist):
            print(f"  \"{syscall}\",")
