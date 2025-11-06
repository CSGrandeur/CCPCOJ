#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
seccomp日志系统调用解析器
从dmesg日志中解析所有syscall的名称，去重排序输出列表
"""

import re
import sys
import os
import argparse
from typing import Set, List, Dict, Any

# 添加项目路径
current_dir = os.path.dirname(os.path.abspath(__file__))
project_root = os.path.dirname(os.path.dirname(os.path.dirname(current_dir)))
sys.path.insert(0, project_root)

try:
    import seccomp
    SECCOMP_AVAILABLE = True
except ImportError:
    SECCOMP_AVAILABLE = False
    print("警告: seccomp库不可用，将使用系统调用编号")

# 常见系统调用编号映射（x86_64架构）
SYSCALL_MAP = {
    0: "read",
    1: "write", 
    2: "open",
    3: "close",
    4: "stat",
    5: "fstat",
    6: "lstat",
    7: "poll",
    8: "lseek",
    9: "mmap",
    10: "mprotect",
    11: "munmap",
    12: "brk",
    13: "rt_sigaction",
    14: "rt_sigprocmask",
    15: "rt_sigreturn",
    16: "ioctl",
    17: "pread64",
    18: "pwrite64",
    19: "readv",
    20: "writev",
    21: "access",
    22: "pipe",
    23: "select",
    24: "sched_yield",
    25: "mremap",
    26: "msync",
    27: "mincore",
    28: "madvise",
    29: "shmget",
    30: "shmat",
    31: "shmctl",
    32: "dup",
    33: "dup2",
    34: "pause",
    35: "nanosleep",
    36: "getitimer",
    37: "alarm",
    38: "setitimer",
    39: "getpid",
    40: "sendfile",
    41: "socket",
    42: "connect",
    43: "accept",
    44: "sendto",
    45: "recvfrom",
    46: "sendmsg",
    47: "recvmsg",
    48: "shutdown",
    49: "bind",
    50: "listen",
    51: "getsockname",
    52: "getpeername",
    53: "socketpair",
    54: "setsockopt",
    55: "getsockopt",
    56: "clone",
    57: "fork",
    58: "vfork",
    59: "execve",
    60: "exit",
    61: "wait4",
    62: "kill",
    63: "uname",
    64: "semget",
    65: "semop",
    66: "semctl",
    67: "shmdt",
    68: "msgget",
    69: "msgsnd",
    70: "msgrcv",
    71: "msgctl",
    72: "fcntl",
    73: "flock",
    74: "fsync",
    75: "fdatasync",
    76: "truncate",
    77: "ftruncate",
    78: "getdents",
    79: "getcwd",
    80: "chdir",
    81: "fchdir",
    82: "rename",
    83: "mkdir",
    84: "rmdir",
    85: "creat",
    86: "link",
    87: "unlink",
    88: "symlink",
    89: "readlink",
    90: "chmod",
    91: "fchmod",
    92: "chown",
    93: "fchown",
    94: "lchown",
    95: "umask",
    96: "gettimeofday",
    97: "getrlimit",
    98: "getrusage",
    99: "sysinfo",
    100: "times",
    101: "ptrace",
    102: "getuid",
    103: "syslog",
    104: "getgid",
    105: "setuid",
    106: "setgid",
    107: "geteuid",
    108: "getegid",
    109: "setpgid",
    110: "getppid",
    111: "getpgrp",
    112: "setsid",
    113: "setreuid",
    114: "setregid",
    115: "getgroups",
    116: "setgroups",
    117: "setresuid",
    118: "getresuid",
    119: "setresgid",
    120: "getresgid",
    121: "getpgid",
    122: "setfsuid",
    123: "setfsgid",
    124: "getsid",
    125: "capget",
    126: "capset",
    127: "rt_sigpending",
    128: "rt_sigtimedwait",
    129: "rt_sigqueueinfo",
    130: "rt_sigsuspend",
    131: "sigaltstack",
    132: "utime",
    133: "mknod",
    134: "uselib",
    135: "personality",
    136: "ustat",
    137: "statfs",
    138: "fstatfs",
    139: "sysfs",
    140: "getpriority",
    141: "setpriority",
    142: "sched_setparam",
    143: "sched_getparam",
    144: "sched_setscheduler",
    145: "sched_getscheduler",
    146: "sched_get_priority_max",
    147: "sched_get_priority_min",
    148: "sched_rr_get_interval",
    149: "mlock",
    150: "munlock",
    151: "mlockall",
    152: "munlockall",
    153: "vhangup",
    154: "modify_ldt",
    155: "pivot_root",
    156: "_sysctl",
    157: "prctl",
    158: "arch_prctl",
    159: "adjtimex",
    160: "setrlimit",
    161: "chroot",
    162: "sync",
    163: "acct",
    164: "settimeofday",
    165: "mount",
    166: "umount2",
    167: "swapon",
    168: "swapoff",
    169: "reboot",
    170: "sethostname",
    171: "setdomainname",
    172: "iopl",
    173: "ioperm",
    174: "create_module",
    175: "init_module",
    176: "delete_module",
    177: "get_kernel_syms",
    178: "query_module",
    179: "quotactl",
    180: "nfsservctl",
    181: "getpmsg",
    182: "putpmsg",
    183: "afs_syscall",
    184: "tuxcall",
    185: "security",
    186: "gettid",
    187: "readahead",
    188: "setxattr",
    189: "lsetxattr",
    190: "fsetxattr",
    191: "getxattr",
    192: "lgetxattr",
    193: "fgetxattr",
    194: "listxattr",
    195: "llistxattr",
    196: "flistxattr",
    197: "removexattr",
    198: "lremovexattr",
    199: "fremovexattr",
    200: "tkill",
    201: "time",
    202: "futex",
    203: "sched_setaffinity",
    204: "sched_getaffinity",
    205: "set_thread_area",
    206: "io_setup",
    207: "io_destroy",
    208: "io_getevents",
    209: "io_submit",
    210: "io_cancel",
    211: "get_thread_area",
    212: "lookup_dcookie",
    213: "epoll_create",
    214: "epoll_ctl_old",
    215: "epoll_wait_old",
    216: "remap_file_pages",
    217: "getdents64",
    218: "set_tid_address",
    219: "restart_syscall",
    220: "semtimedop",
    221: "fadvise64",
    222: "timer_create",
    223: "timer_settime",
    224: "timer_gettime",
    225: "timer_getoverrun",
    226: "timer_delete",
    227: "clock_settime",
    228: "clock_gettime",
    229: "clock_getres",
    230: "clock_nanosleep",
    231: "exit_group",
    232: "epoll_wait",
    233: "epoll_ctl",
    234: "tgkill",
    235: "utimes",
    236: "vserver",
    237: "mbind",
    238: "set_mempolicy",
    239: "get_mempolicy",
    240: "mq_open",
    241: "mq_unlink",
    242: "mq_timedsend",
    243: "mq_timedreceive",
    244: "mq_notify",
    245: "mq_getsetattr",
    246: "kexec_load",
    247: "waitid",
    248: "add_key",
    249: "request_key",
    250: "keyctl",
    251: "ioprio_set",
    252: "ioprio_get",
    253: "inotify_init",
    254: "inotify_add_watch",
    255: "inotify_rm_watch",
    256: "migrate_pages",
    257: "openat",
    258: "mkdirat",
    259: "mknodat",
    260: "fchownat",
    261: "futimesat",
    262: "newfstatat",
    263: "unlinkat",
    264: "renameat",
    265: "linkat",
    266: "symlinkat",
    267: "readlinkat",
    268: "fchmodat",
    269: "faccessat",
    270: "pselect6",
    271: "ppoll",
    272: "unshare",
    273: "set_robust_list",
    274: "get_robust_list",
    275: "splice",
    276: "tee",
    277: "sync_file_range",
    278: "vmsplice",
    279: "move_pages",
    280: "utimensat",
    281: "epoll_pwait",
    282: "signalfd",
    283: "timerfd_create",
    284: "eventfd",
    285: "fallocate",
    286: "timerfd_settime",
    287: "timerfd_gettime",
    288: "accept4",
    289: "signalfd4",
    290: "eventfd2",
    291: "epoll_create1",
    292: "dup3",
    293: "pipe2",
    294: "inotify_init1",
    295: "preadv",
    296: "pwritev",
    297: "rt_tgsigqueueinfo",
    298: "perf_event_open",
    299: "recvmmsg",
    300: "fanotify_init",
    301: "fanotify_mark",
    302: "prlimit64",
    303: "name_to_handle_at",
    304: "open_by_handle_at",
    305: "clock_adjtime",
    306: "syncfs",
    307: "sendmmsg",
    308: "setns",
    309: "getcpu",
    310: "process_vm_readv",
    311: "process_vm_writev",
    312: "kcmp",
    313: "finit_module",
    314: "sched_setattr",
    315: "sched_getattr",
    316: "renameat2",
    317: "seccomp",
    318: "getrandom",
    319: "memfd_create",
    320: "kexec_file_load",
    321: "bpf",
    322: "execveat",
    323: "userfaultfd",
    324: "membarrier",
    325: "mlock2",
    326: "copy_file_range",
    327: "preadv2",
    328: "pwritev2",
    329: "pkey_mprotect",
    330: "pkey_alloc",
    331: "pkey_free",
    332: "statx",
    333: "io_pgetevents",
    334: "rseq",
    335: "pidfd_send_signal",
    336: "io_uring_setup",
    337: "io_uring_enter",
    338: "io_uring_register",
    339: "open_tree",
    340: "move_mount",
    341: "fsopen",
    342: "fsconfig",
    343: "fsmount",
    344: "fspick",
    345: "pidfd_open",
    346: "clone3",
    347: "close_range",
    348: "openat2",
    349: "pidfd_getfd",
    350: "faccessat2",
    351: "process_madvise",
    352: "epoll_pwait2",
    353: "mount_setattr",
    354: "quotactl_fd",
    355: "landlock_create_ruleset",
    356: "landlock_add_rule",
    357: "landlock_restrict_self",
    358: "memfd_secret",
    359: "process_mrelease",
    360: "futex_waitv",
    361: "set_mempolicy_home_node",
    436: "getrandom",  # 这个在您的日志中出现
}


def resolve_syscall_name(syscall_num: int) -> str:
    """解析系统调用编号为名称"""
    if SECCOMP_AVAILABLE:
        try:
            syscall_name = seccomp.resolve_syscall(seccomp.Arch.X86_64, syscall_num)
            if isinstance(syscall_name, bytes):
                syscall_name = syscall_name.decode('utf-8')
            return syscall_name
        except Exception:
            pass
    
    # 回退到预定义映射
    return SYSCALL_MAP.get(syscall_num, f"syscall_{syscall_num}")


def parse_dmesg_logs(log_content: str) -> Set[str]:
    """从dmesg日志内容中解析所有系统调用"""
    syscalls = set()
    
    # 匹配audit日志中的syscall=数字
    pattern = r'syscall=(\d+)'
    
    for line in log_content.split('\n'):
        matches = re.findall(pattern, line)
        for match in matches:
            syscall_num = int(match)
            syscall_name = resolve_syscall_name(syscall_num)
            syscalls.add(syscall_name)
    
    return syscalls


def parse_log_file(file_path: str) -> Set[str]:
    """从日志文件中解析系统调用"""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
        return parse_dmesg_logs(content)
    except Exception as e:
        print(f"读取文件 {file_path} 失败: {e}")
        return set()


def get_dmesg_content() -> str:
    """获取当前dmesg内容"""
    import subprocess
    try:
        result = subprocess.run(['dmesg'], capture_output=True, text=True, timeout=10)
        return result.stdout
    except Exception as e:
        print(f"获取dmesg失败: {e}")
        return ""


def main():
    parser = argparse.ArgumentParser(description='从dmesg日志中解析系统调用名称')
    parser.add_argument('--file', '-f', help='指定日志文件路径')
    parser.add_argument('--dmesg', '-d', action='store_true', help='从当前dmesg获取日志')
    parser.add_argument('--output', '-o', help='输出到文件')
    parser.add_argument('--verbose', '-v', action='store_true', help='详细输出')
    
    args = parser.parse_args()
    
    syscalls = set()
    
    if args.file:
        if args.verbose:
            print(f"解析文件: {args.file}")
        syscalls.update(parse_log_file(args.file))
    
    if args.dmesg:
        if args.verbose:
            print("解析当前dmesg日志")
        dmesg_content = get_dmesg_content()
        if dmesg_content:
            syscalls.update(parse_dmesg_logs(dmesg_content))
    
    if not args.file and not args.dmesg:
        # 默认解析当前dmesg
        if args.verbose:
            print("默认解析当前dmesg日志")
        dmesg_content = get_dmesg_content()
        if dmesg_content:
            syscalls.update(parse_dmesg_logs(dmesg_content))
    
    # 排序输出
    sorted_syscalls = sorted(syscalls)
    
    if args.verbose:
        print(f"\n发现 {len(sorted_syscalls)} 个不同的系统调用:")
    
    output_lines = []
    for syscall in sorted_syscalls:
        output_lines.append(syscall)
        if args.verbose:
            print(f"  {syscall}")
    
    # 输出结果
    if args.output:
        with open(args.output, 'w', encoding='utf-8') as f:
            f.write('\n'.join(output_lines))
        if args.verbose:
            print(f"\n结果已保存到: {args.output}")
    else:
        print('\n'.join(output_lines))


if __name__ == "__main__":
    # python3 monitor/tool_syscall/check_secccomp_log_call.py --file=run0/dmesg_logs/seccomp_logs_player_compile_compile_Main.cpp.txt 
    main()
