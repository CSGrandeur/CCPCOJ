# -*- coding: utf-8 -*-
"""
评测系统状态常量定义
统一管理所有状态字符串，确保程序一致性
"""

import os
import sys
import signal

# 将当前目录和父目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)

# =============================================================================
# 程序执行状态 (Program Execution Status)
# 描述程序运行过程中的状态，与最终评测结果区分
# =============================================================================

# 程序正常完成
PROGRAM_COMPLETED = "program_completed"           # 程序正常执行完成

# 程序异常状态
PROGRAM_TIME_LIMIT_EXCEEDED = "time_limit_exceeded" # 程序运行超时
PROGRAM_MEMORY_LIMIT_EXCEEDED = "memory_limit_exceeded" # 程序内存超限
PROGRAM_RUNTIME_ERROR = "runtime_error"             # 程序运行时错误
PROGRAM_COMPILE_ERROR = "compile_error"             # 程序编译错误
PROGRAM_SYSTEM_ERROR = "system_error"               # 系统级错误
PROGRAM_UNKNOWN = "unknown"                         # 未知状态
# =============================================================================
# 评测结果状态 (Judge Result Status)
# 描述最终评测结果，用于显示给用户
# =============================================================================

# 通过状态
JUDGE_ACCEPTED = "AC"                    # Accepted - 答案正确
JUDGE_ACCEPTED_ALT = "accepted"          # Accepted 的另一种表示

# 错误状态
JUDGE_WRONG_ANSWER = "WA"                 # Wrong Answer - 答案错误
JUDGE_TIME_LIMIT_EXCEEDED = "TLE"         # Time Limit Exceeded - 超时
JUDGE_MEMORY_LIMIT_EXCEEDED = "MLE"       # Memory Limit Exceeded - 内存超限
JUDGE_RUNTIME_ERROR = "RE"                # Runtime Error - 运行时错误
JUDGE_COMPILE_ERROR = "CE"                # Compile Error - 编译错误
JUDGE_PRESENTATION_ERROR = "PE"           # Presentation Error - 格式错误
JUDGE_OUTPUT_LIMIT_EXCEEDED = "OLE"       # Output Limit Exceeded - 输出超限

# 特殊状态
JUDGE_PENDING = "Pending"                 # 等待评测
JUDGE_RUNNING = "Running"                 # 正在评测
JUDGE_JUDGE_FAILED = "JF"                 # Judge Failed - 评测失败
JUDGE_SYSTEM_ERROR = "SE"                 # System Error - 系统错误
JUDGE_UNKNOWN = "Unknown"                 # Unknown - 未知状态

# =============================================================================
# 评测任务状态 (Judge Task Status)
# 描述评测任务的整体状态
# =============================================================================

# 任务状态
TASK_PENDING = "pending"                  # 任务等待中
TASK_RUNNING = "running"                  # 任务运行中
TASK_COMPLETED = "completed"              # 任务已完成
TASK_ERROR = "error"                      # 任务出错
TASK_CANCELLED = "cancelled"              # 任务已取消

# =============================================================================
# 任务类型 (Task Type)
# 描述具体的任务类型，用于区分不同的处理逻辑
# =============================================================================

# 选手程序相关任务
TASK_TYPE_PLAYER_COMPILE = "player_compile"    # 选手程序编译
TASK_TYPE_PLAYER_RUN = "player_run"            # 选手程序运行

# TPJ (Test Program Judge) 相关任务
TASK_TYPE_TPJ_COMPILE = "tpj_compile"          # TPJ 程序编译
TASK_TYPE_TPJ_RUN = "tpj_run"                  # TPJ 程序运行

# 交互题相关任务
TASK_TYPE_INTERACTIVE_COMPILE = "interactive_compile"  # 交互题编译
TASK_TYPE_INTERACTIVE_RUN = "interactive_run"          # 交互题运行

# 自定义任务
TASK_TYPE_CUSTOM = "custom"                  # 自定义任务类型

# =============================================================================
# 评测结果映射 (Judge Result Mapping)
# 用于状态转换和显示
# =============================================================================

# 程序执行状态到评测结果的映射
PROGRAM_TO_JUDGE_RESULT = {
    PROGRAM_COMPLETED: JUDGE_ACCEPTED,
    PROGRAM_TIME_LIMIT_EXCEEDED: JUDGE_TIME_LIMIT_EXCEEDED,
    PROGRAM_MEMORY_LIMIT_EXCEEDED: JUDGE_MEMORY_LIMIT_EXCEEDED,
    PROGRAM_RUNTIME_ERROR: JUDGE_RUNTIME_ERROR,
    PROGRAM_COMPILE_ERROR: JUDGE_COMPILE_ERROR,
    PROGRAM_SYSTEM_ERROR: JUDGE_SYSTEM_ERROR,
}

# 注意：显示名称映射由web端处理，评测机不涉及UI显示

# 注意：颜色映射由web端处理，评测机不涉及UI显示

# =============================================================================
# 状态验证函数
# =============================================================================

def is_valid_program_status(status):
    """验证程序执行状态是否有效"""
    valid_statuses = [
        PROGRAM_COMPLETED,
        PROGRAM_TIME_LIMIT_EXCEEDED,
        PROGRAM_MEMORY_LIMIT_EXCEEDED,
        PROGRAM_RUNTIME_ERROR,
        PROGRAM_COMPILE_ERROR,
        PROGRAM_SYSTEM_ERROR,
    ]
    return status in valid_statuses

def is_valid_judge_result(result):
    """验证评测结果是否有效"""
    valid_results = [
        JUDGE_ACCEPTED,
        JUDGE_WRONG_ANSWER,
        JUDGE_TIME_LIMIT_EXCEEDED,
        JUDGE_MEMORY_LIMIT_EXCEEDED,
        JUDGE_RUNTIME_ERROR,
        JUDGE_COMPILE_ERROR,
        JUDGE_PRESENTATION_ERROR,
        JUDGE_OUTPUT_LIMIT_EXCEEDED,
        JUDGE_JUDGE_FAILED,
        JUDGE_SYSTEM_ERROR,
        JUDGE_PENDING,
        JUDGE_RUNNING,
    ]
    return result in valid_results

def is_valid_task_status(status):
    """验证任务状态是否有效"""
    valid_statuses = [
        TASK_PENDING,
        TASK_RUNNING,
        TASK_COMPLETED,
        TASK_ERROR,
        TASK_CANCELLED,
    ]
    return status in valid_statuses

def is_valid_task_type(task_type):
    """验证任务类型是否有效"""
    valid_task_types = [
        TASK_TYPE_PLAYER_COMPILE,
        TASK_TYPE_PLAYER_RUN,
        TASK_TYPE_TPJ_COMPILE,
        TASK_TYPE_TPJ_RUN,
        TASK_TYPE_INTERACTIVE_COMPILE,
        TASK_TYPE_INTERACTIVE_RUN,
        TASK_TYPE_CUSTOM,
    ]
    return task_type in valid_task_types

def is_compile_task_type(task_type):
    """判断是否为编译任务类型"""
    compile_task_types = [
        TASK_TYPE_PLAYER_COMPILE,
        TASK_TYPE_TPJ_COMPILE,
        TASK_TYPE_INTERACTIVE_COMPILE,
    ]
    return task_type in compile_task_types

def is_run_task_type(task_type):
    """判断是否为运行任务类型"""
    run_task_types = [
        TASK_TYPE_PLAYER_RUN,
        TASK_TYPE_TPJ_RUN,
        TASK_TYPE_INTERACTIVE_RUN,
    ]
    return task_type in run_task_types

def is_tpj_task_type(task_type):
    """判断是否为 TPJ 任务类型"""
    tpj_task_types = [
        TASK_TYPE_TPJ_COMPILE,
        TASK_TYPE_TPJ_RUN,
    ]
    return task_type in tpj_task_types

def get_judge_result_from_program_status(program_status):
    """从程序执行状态获取评测结果"""
    return PROGRAM_TO_JUDGE_RESULT.get(program_status, JUDGE_SYSTEM_ERROR)

# =============================================================================
# 信号和返回码映射 (Signal and Exit Code Mapping)
# 用于精确判断进程终止原因
# =============================================================================

# 系统级错误返回码映射
SYSTEM_ERROR_CODES = {
    # 命令执行错误
    125: "timeout命令执行失败",
    126: "命令无法执行（权限问题）", 
    127: "命令未找到或可执行文件缺失",
    128: "信号相关错误",
    
    # 信号相关错误（Shell格式：128 + 信号编号）
    128 + signal.SIGINT: "进程被中断（Ctrl+C）",
    
    # 注意：SIGKILL、SIGTERM、SIGXCPU 被归类为资源超限信号，不在此处
}

# 资源超限相关信号
RESOURCE_LIMIT_SIGNALS = {
    # 内存超限信号
    signal.SIGKILL: "memory_or_system",  # 需要结合监控数据判断
    -signal.SIGKILL: "memory_or_system",
    128 + signal.SIGKILL: "memory_or_system",
    
    # CPU时间超限信号
    signal.SIGXCPU: "cpu_time",  # RLIMIT触发的CPU时间超限
    -signal.SIGXCPU: "cpu_time",
    128 + signal.SIGXCPU: "cpu_time",
    
    # 超时信号
    signal.SIGTERM: "timeout",  # 通常由超时触发
    -signal.SIGTERM: "timeout",
    128 + signal.SIGTERM: "timeout",
}

# 程序运行时错误信号 - 明确属于选手程序错误
RUNTIME_ERROR_SIGNALS = {
    signal.SIGSEGV: "段错误",      # 选手程序访问非法内存
    signal.SIGFPE: "浮点数错误",    # 选手程序除零或浮点运算错误
    signal.SIGABRT: "断言失败",    # 选手程序断言失败
    signal.SIGILL: "非法指令",     # 选手程序执行非法指令
    -signal.SIGSEGV: "段错误",
    -signal.SIGFPE: "浮点数错误", 
    -signal.SIGABRT: "断言失败",
    -signal.SIGILL: "非法指令",
    128 + signal.SIGSEGV: "段错误",
    128 + signal.SIGFPE: "浮点数错误",
    128 + signal.SIGABRT: "断言失败",
    128 + signal.SIGILL: "非法指令",
}

# Testlib 退出码定义（基于 testlib.h）
TESTLIB_EXIT_CODES = {
    0: "ACCEPTED",              # _ok
    1: "WRONG_ANSWER",          # _wa
    2: "PRESENTATION_ERROR",    # _pe
    3: "FAIL",                  # _fail (出题人错误)
    4: "DIRT",                  # _dirt
    5: "POINTS",                # _points (部分分)
    7: "POINTS",                # _points (POINTS_EXIT_CODE)
    8: "UNEXPECTED_EOF",        # _unexpected_eof
}

# TPJ 运行错误信号 - 属于出题人责任，应归类为系统错误
TPJ_RUNTIME_ERROR_SIGNALS = {
    signal.SIGSEGV: "TPJ程序段错误",
    signal.SIGFPE: "TPJ程序浮点数错误", 
    signal.SIGABRT: "TPJ程序断言失败",
    signal.SIGILL: "TPJ程序非法指令",
    -signal.SIGSEGV: "TPJ程序段错误",
    -signal.SIGFPE: "TPJ程序浮点数错误",
    -signal.SIGABRT: "TPJ程序断言失败", 
    -signal.SIGILL: "TPJ程序非法指令",
    128 + signal.SIGSEGV: "TPJ程序段错误",
    128 + signal.SIGFPE: "TPJ程序浮点数错误",
    128 + signal.SIGABRT: "TPJ程序断言失败",
    128 + signal.SIGILL: "TPJ程序非法指令",
}

# 系统级错误信号 - 可能是评测机或系统问题
SYSTEM_ERROR_SIGNALS = {
    signal.SIGBUS: "总线错误",     # 硬件或系统问题，不一定是选手程序错误
    -signal.SIGBUS: "总线错误",
    128 + signal.SIGBUS: "总线错误",
    signal.SIGSYS: "系统调用错误",  # seccomp阻止或无效系统调用
    -signal.SIGSYS: "系统调用错误",
    128 + signal.SIGSYS: "系统调用错误",
}

# 编译错误返回码
COMPILE_ERROR_CODES = {
    127: "可执行文件缺失或命令未找到",
}

def is_system_error(return_code):
    """判断是否为系统级错误"""
    return return_code in SYSTEM_ERROR_CODES

def is_compile_error(return_code):
    """判断是否为编译错误"""
    return return_code in COMPILE_ERROR_CODES

def is_resource_limit_signal(return_code):
    """判断是否为资源超限相关信号"""
    return return_code in RESOURCE_LIMIT_SIGNALS

def is_runtime_error_signal(return_code):
    """判断是否为程序运行时错误信号"""
    return return_code in RUNTIME_ERROR_SIGNALS


def is_testlib_exit_code(return_code):
    """判断是否为 testlib 退出码"""
    return return_code in TESTLIB_EXIT_CODES


def is_tpj_runtime_error_signal(return_code):
    """判断是否为 TPJ 运行时错误信号"""
    return return_code in TPJ_RUNTIME_ERROR_SIGNALS


def get_testlib_result(return_code):
    """获取 testlib 退出码对应的评测结果"""
    return TESTLIB_EXIT_CODES.get(return_code, "UNKNOWN")


def get_tpj_runtime_error_message(return_code):
    """获取 TPJ 运行时错误消息"""
    return TPJ_RUNTIME_ERROR_SIGNALS.get(return_code, f"TPJ程序运行时错误，返回码: {return_code}")

def is_system_error_signal(return_code):
    """判断是否为系统级错误信号"""
    return return_code in SYSTEM_ERROR_SIGNALS

def get_system_error_message(return_code):
    """获取系统错误消息"""
    return SYSTEM_ERROR_CODES.get(return_code, f"未知系统错误，返回码: {return_code}")

def get_resource_limit_type(return_code):
    """获取资源超限类型"""
    return RESOURCE_LIMIT_SIGNALS.get(return_code, None)

def get_runtime_error_message(return_code):
    """获取运行时错误消息"""
    return RUNTIME_ERROR_SIGNALS.get(return_code, f"程序运行时错误，返回码: {return_code}")

# 将系统级错误信号添加到系统错误代码中
SYSTEM_ERROR_CODES.update(SYSTEM_ERROR_SIGNALS)
