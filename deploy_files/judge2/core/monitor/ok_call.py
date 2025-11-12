#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
系统调用白名单配置文件
每个语言独立配置，结构清晰直观
"""

# =============================================================================
# 全局基础白名单（所有语言共有）
# =============================================================================

# 所有语言共有的系统调用，按字母顺序排序
GLOBAL_BASE_WHITELIST = {
    # 文件访问检查
    "access",
    
    # 系统架构和内存管理
    "arch_prctl",
    "brk",
    "madvise",
    "mmap",
    "mprotect",
    "munmap",
    
    # 进程退出
    "exit_group",
    
    # 文件描述符基础操作
    "close",
    "close_range",
    "fcntl",
    "fstat",
    "lseek",
    "newfstatat",
    
    # 目录操作
    "getcwd",
    "getdents64",
    
    # 用户/组标识
    "getegid",
    "geteuid",
    "getgid",
    "getpid",
    "getppid",
    "getuid",
    
    # 随机数和系统信息
    "getrandom",
    "sysinfo",
    
    # 进程限制和资源
    "prlimit64",
    
    # 信号处理
    "rt_sigaction",
    "rt_sigprocmask",
    "rt_sigreturn",
    
    # 线程和同步基础
    "rseq",
    "set_robust_list",
    "set_tid_address",
    
    # 时间相关（基础）
    "clock_gettime",
}

# =============================================================================
# C/C++ 语言配置（统一配置）
# =============================================================================

# 1. C/C++ 选手程序特定增补（超出全局基础的部分，按字母顺序排序）
C_CPP_PLAYER_EXTEND = {
    "execve",   # 需要进一步确认
    "futex",
    "gettid",   # C++ 标准库可能需要（异常处理、TLS等）
    "pread64",
    "tgkill",   # C++ 标准库可能需要（线程间信号通信）
    "writev",  # C++ 需要，C 也兼容
}

# 2. C/C++ 选手程序编译增补（按字母顺序排序）
C_CPP_PLAYER_COMPILE = {
    "chmod",
    "clone3",  # 编译阶段需要（并行编译、链接器等）
    "dup",
    "execve",
    "faccessat2",
    "getrusage",
    "ioctl",
    "mremap",  # 静态链接时链接器可能需要
    "newfstatat",
    "openat",
    "pipe2",
    "read",
    "readlink",
    "umask",
    "unlink",
    "vfork",
    "wait4",
    "write",
}

# 3. C/C++ TPJ编译增补（按字母顺序排序）
C_CPP_TPJ_COMPILE = {
    "clock_getres",
    "clock_gettime",  # 已在全局基础中，但TPJ需要
    "fstatat",
    "gettimeofday",
    "mkdir",
    "readlinkat",
    "rename",
    "renameat",
    "rmdir",
    "statx",
    "unlinkat",
}

# 4. C/C++ TPJ运行增补（按字母顺序排序，仅包含超出选手run的额外系统调用）
C_CPP_TPJ_RUN_EXTEND = {
    "gettimeofday",  # TPJ 特有的时间获取
    "ioctl",  # TPJ 可能需要
    "pread64",  # TPJ 可能需要
}

# C/C++ 基础白名单（全局基础 + C/C++特定增补）
C_CPP_PLAYER_BASE = GLOBAL_BASE_WHITELIST | C_CPP_PLAYER_EXTEND

# C/C++ 最终白名单组合
C_CPP_COMPILE_WHITELIST = C_CPP_PLAYER_BASE | C_CPP_PLAYER_COMPILE
C_CPP_COMPILE_TPJ_WHITELIST = C_CPP_PLAYER_BASE | C_CPP_PLAYER_COMPILE | C_CPP_TPJ_COMPILE
# 运行白名单需要包含一些编译白名单中的关键系统调用
C_CPP_RUN_WHITELIST = C_CPP_PLAYER_BASE | {"read", "write", "openat", "close", "fstat", "lseek", "getrusage", "readlinkat"}
# TPJ run 基于选手 run，再加上 TPJ 特有的系统调用
C_CPP_RUN_TPJ_WHITELIST = C_CPP_RUN_WHITELIST | C_CPP_TPJ_RUN_EXTEND

# =============================================================================
# C++ 语言配置（使用统一的 C/C++ 配置）
# =============================================================================

# C++ 配置变量名（指向统一的 C/C++ 配置）
CPP_PLAYER_EXTEND = C_CPP_PLAYER_EXTEND
CPP_PLAYER_COMPILE = C_CPP_PLAYER_COMPILE
CPP_TPJ_COMPILE = C_CPP_TPJ_COMPILE
CPP_TPJ_RUN_EXTEND = C_CPP_TPJ_RUN_EXTEND
CPP_PLAYER_BASE = C_CPP_PLAYER_BASE
CPP_COMPILE_WHITELIST = C_CPP_COMPILE_WHITELIST
CPP_COMPILE_TPJ_WHITELIST = C_CPP_COMPILE_TPJ_WHITELIST
CPP_RUN_WHITELIST = C_CPP_RUN_WHITELIST
CPP_RUN_TPJ_WHITELIST = C_CPP_RUN_TPJ_WHITELIST

# =============================================================================
# C 语言配置（使用统一的 C/C++ 配置）
# =============================================================================

# C 配置变量名（指向统一的 C/C++ 配置）
C_PLAYER_EXTEND = C_CPP_PLAYER_EXTEND
C_PLAYER_COMPILE = C_CPP_PLAYER_COMPILE
C_TPJ_COMPILE = C_CPP_TPJ_COMPILE
C_TPJ_RUN_EXTEND = C_CPP_TPJ_RUN_EXTEND
C_PLAYER_BASE = C_CPP_PLAYER_BASE
C_COMPILE_WHITELIST = C_CPP_COMPILE_WHITELIST
C_COMPILE_TPJ_WHITELIST = C_CPP_COMPILE_TPJ_WHITELIST
C_RUN_WHITELIST = C_CPP_RUN_WHITELIST
C_RUN_TPJ_WHITELIST = C_CPP_RUN_TPJ_WHITELIST

# =============================================================================
# Python 语言配置
# =============================================================================

# 1. Python 选手程序特定增补（超出全局基础的部分）
PYTHON_PLAYER_EXTEND = {
    "execve",
    "futex",
    "gettid",
    "ioctl",
    "mremap",
    "newfstatat",
    "pread64",
    "readlink",
}

# 2. Python 选手程序编译增补（Python通常不需要编译）
PYTHON_PLAYER_COMPILE = set()

# Python 基础白名单（全局基础 + Python特定增补）
PYTHON_PLAYER_BASE = GLOBAL_BASE_WHITELIST | PYTHON_PLAYER_EXTEND

# Python 最终白名单组合
PYTHON_COMPILE_WHITELIST = PYTHON_PLAYER_BASE | PYTHON_PLAYER_COMPILE
# 运行白名单需要包含一些编译白名单中的关键系统调用
# 注意：openat 需要保留，因为 Python 解释器需要打开文件（如 /dev/urandom）
# 虽然允许 openat，但通过 chroot 和文件系统权限，选手程序无法访问工作目录外的文件
# 评测机应确保工作目录内没有 *.in 或 *.out 文件（这些文件应在 chroot 外）
PYTHON_RUN_WHITELIST = PYTHON_PLAYER_BASE | {"read", "write", "openat", "close", "fstat", "lseek", "getrusage"}

# =============================================================================
# Java 语言配置
# =============================================================================

# 1. Java 选手程序特定增补（超出全局基础的部分，按字母顺序排序）
JAVA_PLAYER_EXTEND = {
    "clock_getres",
    "clock_nanosleep",
    "clone3",
    "connect",
    "execve",
    "exit",
    "faccessat2",
    "fchdir",
    "flock",
    "ftruncate",
    "futex",
    "gettid",
    "ioctl",
    "mkdir",
    "newfstatat",
    "prctl",
    "pread64",
    "readlink",
    "readlinkat",
    "sched_getaffinity",
    "sched_yield",
    "socket",
    "uname",
    "unlink",
}

# 2. Java 选手程序编译增补（按字母顺序排序）
JAVA_PLAYER_COMPILE = {
    "chmod",
    "clock_getres",
    "dup",
    "execve",
    "faccessat2",
    "futex",
    "getrusage",
    "getsockname",
    "ioctl",
    "kill",
    "newfstatat",
    "openat",
    "pipe2",
    "pread64",
    "read",
    "readlink",
    "setsockopt",
    "socketpair",
    "statx",
    "umask",
    "unlink",
    "vfork",
    "wait4",
    "write",
}

# Java 基础白名单（全局基础 + Java特定增补）
JAVA_PLAYER_BASE = GLOBAL_BASE_WHITELIST | JAVA_PLAYER_EXTEND

# Java 最终白名单组合
JAVA_COMPILE_WHITELIST = JAVA_PLAYER_BASE | JAVA_PLAYER_COMPILE
# 运行白名单需要包含一些编译白名单中的关键系统调用
JAVA_RUN_WHITELIST = JAVA_PLAYER_BASE | {"read", "write", "openat", "close", "fstat", "lseek", "getrusage"}

# =============================================================================
# Go 语言配置
# =============================================================================

# 1. Go 选手程序特定增补（超出全局基础的部分）
GO_PLAYER_EXTEND = set()  # Go语言没有超出全局基础的特定系统调用

# 2. Go 选手程序编译增补（按字母顺序排序）
GO_PLAYER_COMPILE = {
    "chmod",
    "execve",
    "faccessat2",
    "futex",
    "getrusage",
    "ioctl",
    "newfstatat",
    "openat",
    "pipe2",
    "pread64",
    "read",
    "readlink",
    "umask",
    "unlink",
    "vfork",
    "wait4",
    "write",
}

# Go 基础白名单（全局基础 + Go特定增补）
GO_PLAYER_BASE = GLOBAL_BASE_WHITELIST | GO_PLAYER_EXTEND

# Go 最终白名单组合
GO_COMPILE_WHITELIST = GO_PLAYER_BASE | GO_PLAYER_COMPILE
# 运行白名单需要包含一些编译白名单中的关键系统调用
GO_RUN_WHITELIST = GO_PLAYER_BASE | {"read", "write", "openat", "close", "fstat", "lseek", "getrusage"}

# =============================================================================
# 统一接口函数
# =============================================================================

def get_whitelist(language, scenario, spj_type=0):
    """
    获取指定语言、场景和SPJ类型的组合白名单
    
    Args:
        language: 编程语言 ("cpp", "c", "python", "java", "go")
        scenario: 场景 ("compile", "run")
        spj_type: Special Judge 类型 (0=无SPJ, 1=有SPJ)
        
    Returns:
        set: 组合后的系统调用白名单
    """
    if language == "cpp":
        if scenario == "compile":
            return CPP_COMPILE_TPJ_WHITELIST if spj_type > 0 else CPP_COMPILE_WHITELIST
        elif scenario == "run":
            return CPP_RUN_TPJ_WHITELIST if spj_type > 0 else CPP_RUN_WHITELIST
    
    elif language == "c":
        if scenario == "compile":
            return C_COMPILE_WHITELIST  # C 不支持 TPJ
        elif scenario == "run":
            return C_RUN_WHITELIST  # C 不支持 TPJ
    
    elif language == "python":
        if scenario == "compile":
            return PYTHON_COMPILE_WHITELIST  # Python 不支持 TPJ
        elif scenario == "run":
            return PYTHON_RUN_WHITELIST  # Python 不支持 TPJ
    
    elif language == "java":
        if scenario == "compile":
            return JAVA_COMPILE_WHITELIST  # Java 不支持 TPJ
        elif scenario == "run":
            return JAVA_RUN_WHITELIST  # Java 不支持 TPJ
    
    elif language == "go":
        if scenario == "compile":
            return GO_COMPILE_WHITELIST  # Go 不支持 TPJ
        elif scenario == "run":
            return GO_RUN_WHITELIST  # Go 不支持 TPJ
    
    return set()

if __name__ == "__main__":
    # 测试
    import logging
    
    logger = logging.getLogger("OkCall")
    logging.basicConfig(level=logging.INFO)
    logger.info("=== 系统调用白名单测试 ===")
    
    logger.info(f"全局基础白名单: {len(GLOBAL_BASE_WHITELIST)} 个系统调用")
    logger.info(f"C++ 编译 (无TPJ): {len(get_whitelist('cpp', 'compile', 0))} 个系统调用")
    logger.info(f"C++ 编译 (有TPJ): {len(get_whitelist('cpp', 'compile', 1))} 个系统调用")
    logger.info(f"C++ 运行 (无TPJ): {len(get_whitelist('cpp', 'run', 0))} 个系统调用")
    logger.info(f"C++ 运行 (有TPJ): {len(get_whitelist('cpp', 'run', 1))} 个系统调用")
    
    logger.info(f"C 编译: {len(get_whitelist('c', 'compile', 0))} 个系统调用")
    logger.info(f"C 运行: {len(get_whitelist('c', 'run', 0))} 个系统调用")
    
    logger.info(f"Python 编译: {len(get_whitelist('python', 'compile', 0))} 个系统调用")
    logger.info(f"Python 运行: {len(get_whitelist('python', 'run', 0))} 个系统调用")
    
    logger.info(f"Java 编译: {len(get_whitelist('java', 'compile', 0))} 个系统调用")
    logger.info(f"Java 运行: {len(get_whitelist('java', 'run', 0))} 个系统调用")
    
    logger.info(f"Go 编译: {len(get_whitelist('go', 'compile', 0))} 个系统调用")
    logger.info(f"Go 运行: {len(get_whitelist('go', 'run', 0))} 个系统调用")
