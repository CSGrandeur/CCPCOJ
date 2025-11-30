#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
本地交互题测试脚本
用于在本地测试交互题的 tpj.cc 是否正确
无监控措施，简化版本，方便出题人本地调试

独立文件，不依赖项目其他模块
"""

import os
import sys
import time
import subprocess
import argparse
import logging
from typing import Dict, Any, List, Tuple, Optional


class LocalInteractiveTester:
    """本地交互题测试器"""
    
    def __init__(self, work_dir: str = None):
        self.work_dir = work_dir or os.getcwd()
        self.logger = self._setup_logger()
        
    def _setup_logger(self) -> logging.Logger:
        """设置日志记录器"""
        logger = logging.getLogger("LocalInteractiveTester")
        logger.setLevel(logging.DEBUG)
        
        # 清除已有的处理器
        logger.handlers.clear()
        
        # 控制台处理器（输出到stdout）
        console_handler = logging.StreamHandler(sys.stdout)
        console_handler.setLevel(logging.INFO)
        console_formatter = logging.Formatter(
            '%(asctime)s [%(levelname)s] %(message)s',
            datefmt='%Y-%m-%d %H:%M:%S'
        )
        console_handler.setFormatter(console_formatter)
        logger.addHandler(console_handler)
        
        # 文件处理器（输出到文件）
        log_file = os.path.join(self.work_dir, "interactive_test.log")
        file_handler = logging.FileHandler(log_file, mode='w', encoding='utf-8')
        file_handler.setLevel(logging.DEBUG)
        file_formatter = logging.Formatter(
            '%(asctime)s [%(levelname)s] %(name)s: %(message)s',
            datefmt='%Y-%m-%d %H:%M:%S'
        )
        file_handler.setFormatter(file_formatter)
        logger.addHandler(file_handler)
        
        logger.info(f"日志文件: {log_file}")
        return logger
    
    def is_executable(self, file_path: str) -> bool:
        """判断文件是否为可执行程序"""
        if not os.path.exists(file_path):
            return False
        
        # 检查文件是否可执行
        if os.access(file_path, os.X_OK):
            # 进一步检查是否是二进制文件（ELF格式）
            try:
                with open(file_path, 'rb') as f:
                    header = f.read(4)
                    # ELF文件头：0x7f 'ELF'
                    if header == b'\x7fELF':
                        return True
                    # 检查是否是脚本文件（shebang）
                    if header.startswith(b'#!'):
                        return True
            except:
                pass
        
        return False
    
    def detect_language_from_file(self, file_path: str) -> Optional[str]:
        """根据文件扩展名检测语言"""
        ext = os.path.splitext(file_path)[1].lower()
        
        language_map = {
            '.cpp': 'cpp',
            '.cc': 'cpp',
            '.cxx': 'cpp',
            '.c++': 'cpp',
            '.c': 'c',
            '.py': 'python',
            '.java': 'java',
            '.go': 'go',
        }
        
        language = language_map.get(ext)
        if language:
            self.logger.info(f"检测到语言: {language} (文件扩展名: {ext})")
            return language
        
        self.logger.error(f"不支持的文件扩展名: {ext}")
        return None
    
    def find_test_cases(self) -> List[Tuple[str, str, str]]:
        """查找当前目录下的测试用例"""
        test_cases = []
        
        for file in sorted(os.listdir(self.work_dir)):
            if file.endswith('.in'):
                base_name = file[:-3]
                in_file = os.path.join(self.work_dir, file)
                out_file = os.path.join(self.work_dir, f"{base_name}.out")
                
                if os.path.exists(out_file):
                    test_cases.append((in_file, out_file, base_name))
                    self.logger.info(f"找到测试用例: {base_name}")
                else:
                    self.logger.warning(f"找到输入文件 {file}，但未找到对应的输出文件 {base_name}.out")
        
        if not test_cases:
            self.logger.error("未找到任何测试用例（.in 和 .out 文件对）")
        
        return test_cases
    
    def compile_tpj(self, tpj_source: str) -> Optional[str]:
        """编译TPJ程序"""
        tpj_executable = os.path.join(self.work_dir, "tpj")
        
        # 检查是否已编译且是最新的
        if os.path.exists(tpj_executable):
            if os.path.getmtime(tpj_executable) >= os.path.getmtime(tpj_source):
                self.logger.info(f"TPJ可执行文件已存在且是最新的: {tpj_executable}")
                return tpj_executable
        
        self.logger.info(f"编译TPJ程序: {tpj_source}")
        
        # 查找judge_lib目录（用于testlib.h）
        # 尝试多个可能的路径
        possible_judge_lib_paths = [
            os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(__file__))), "judge_lib"),
            os.path.join(os.path.dirname(os.path.dirname(__file__)), "judge_lib"),
            "/usr/local/include",
            "/usr/include",
        ]
        
        judge_lib_path = None
        for path in possible_judge_lib_paths:
            if os.path.exists(os.path.join(path, "testlib.h")):
                judge_lib_path = path
                break
        
        compile_cmd = ["g++", "-O2", "-std=c++17", "-o", tpj_executable, tpj_source]
        if judge_lib_path:
            compile_cmd.insert(-2, f"-I{judge_lib_path}")
            self.logger.debug(f"使用 testlib.h 路径: {judge_lib_path}")
        else:
            self.logger.warning("未找到 testlib.h，可能编译失败")
        
        self.logger.debug(f"编译命令: {' '.join(compile_cmd)}")
        
        try:
            result = subprocess.run(
                compile_cmd,
                capture_output=True,
                text=True,
                timeout=30
            )
            
            if result.returncode == 0:
                os.chmod(tpj_executable, 0o755)
                self.logger.info(f"TPJ编译成功: {tpj_executable}")
                return tpj_executable
            else:
                self.logger.error(f"TPJ编译失败:")
                self.logger.error(f"stdout: {result.stdout}")
                self.logger.error(f"stderr: {result.stderr}")
                return None
                
        except subprocess.TimeoutExpired:
            self.logger.error("TPJ编译超时")
            return None
        except Exception as e:
            self.logger.error(f"TPJ编译异常: {e}")
            return None
    
    def compile_user_program(self, source_file: str, language: str) -> Optional[str]:
        """编译用户程序（如果需要）"""
        # Python等解释型语言不需要编译
        if language == 'python':
            self.logger.info(f"{language} 是解释型语言，无需编译")
            return source_file
        
        # 需要编译的语言
        base_name = os.path.splitext(os.path.basename(source_file))[0]
        executable = os.path.join(self.work_dir, base_name)
        
        # 检查是否已编译且是最新的
        if os.path.exists(executable):
            if os.path.getmtime(executable) >= os.path.getmtime(source_file):
                self.logger.info(f"可执行文件已存在且是最新的: {executable}")
                return executable
        
        self.logger.info(f"编译用户程序: {source_file}")
        
        # 根据语言选择编译命令
        if language == 'cpp':
            compile_cmd = ["g++", "-O2", "-std=c++17", "-o", executable, source_file]
        elif language == 'c':
            compile_cmd = ["gcc", "-O2", "-std=c17", "-o", executable, source_file]
        elif language == 'java':
            compile_cmd = ["javac", source_file]
            executable = os.path.join(self.work_dir, f"{base_name}.class")
        elif language == 'go':
            compile_cmd = ["go", "build", "-o", executable, source_file]
        else:
            self.logger.error(f"不支持编译语言: {language}")
            return None
        
        self.logger.debug(f"编译命令: {' '.join(compile_cmd)}")
        
        try:
            result = subprocess.run(
                compile_cmd,
                cwd=self.work_dir,
                capture_output=True,
                text=True,
                timeout=30
            )
            
            if result.returncode == 0:
                if os.path.exists(executable):
                    if language != 'java':  # Java不需要chmod
                        os.chmod(executable, 0o755)
                    self.logger.info(f"用户程序编译成功: {executable}")
                    return executable
                else:
                    self.logger.error(f"编译成功但可执行文件不存在: {executable}")
                    return None
            else:
                self.logger.error(f"用户程序编译失败:")
                self.logger.error(f"stdout: {result.stdout}")
                self.logger.error(f"stderr: {result.stderr}")
                return None
                
        except subprocess.TimeoutExpired:
            self.logger.error("用户程序编译超时")
            return None
        except Exception as e:
            self.logger.error(f"用户程序编译异常: {e}")
            return None
    
    def get_user_run_command(self, executable: str, language: str) -> List[str]:
        """获取用户程序运行命令"""
        if language == 'python':
            # Python直接运行源文件
            return ["python3", executable]
        elif language == 'java':
            # Java运行类名（不含.class）
            class_name = os.path.splitext(os.path.basename(executable))[0]
            return ["java", "-Dfile.encoding=UTF-8", "-cp", ".", class_name]
        elif language == 'go':
            # Go运行可执行文件
            return [executable]
        else:
            # C/C++运行可执行文件
            return [executable]
    
    def run_interactive_test(self, user_executable: str, user_cmd: List[str],
                            tpj_executable: str, in_file: str, out_file: str,
                            case_name: str) -> Dict[str, Any]:
        """运行单个交互题测试用例"""
        self.logger.info(f"\n{'='*60}")
        self.logger.info(f"开始测试用例: {case_name}")
        self.logger.info(f"{'='*60}")
        
        # 创建临时文件用于TPJ的tout输出
        tpj_tout_file = os.path.join(self.work_dir, f"{case_name}_tpj_tout.tmp")
        
        # 构建TPJ命令
        tpj_cmd = [tpj_executable, in_file, tpj_tout_file, out_file]
        
        self.logger.debug(f"用户程序命令: {' '.join(user_cmd)}")
        self.logger.debug(f"TPJ程序命令: {' '.join(tpj_cmd)}")
        self.logger.debug(f"输入文件: {in_file}")
        self.logger.debug(f"输出文件: {out_file}")
        self.logger.debug(f"TPJ临时输出文件: {tpj_tout_file}")
        
        user_process = None
        tpj_process = None
        
        try:
            start_time = time.time()
            
            # 启动用户程序
            self.logger.debug("启动用户程序...")
            user_process = subprocess.Popen(
                user_cmd,
                stdin=subprocess.PIPE,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                cwd=self.work_dir
            )
            self.logger.debug(f"用户程序PID: {user_process.pid}")
            
            # 启动TPJ程序
            self.logger.debug("启动TPJ程序...")
            tpj_process = subprocess.Popen(
                tpj_cmd,
                stdin=user_process.stdout,
                stdout=user_process.stdin,
                stderr=subprocess.PIPE,
                text=True,
                cwd=self.work_dir
            )
            self.logger.debug(f"TPJ程序PID: {tpj_process.pid}")
            
            # 关闭用户程序的stdout和stdin（已连接到TPJ）
            user_process.stdout.close()
            user_process.stdin.close()
            
            # 等待TPJ进程完成
            self.logger.debug("等待TPJ进程完成...")
            try:
                tpj_process.wait(timeout=30)
            except subprocess.TimeoutExpired:
                self.logger.error("TPJ进程超时")
                tpj_process.kill()
                user_process.kill()
                return {
                    "status": "TIMEOUT",
                    "message": "TPJ进程超时",
                    "tpj_return_code": None,
                    "user_return_code": None
                }
            
            tpj_return_code = tpj_process.returncode
            
            # 等待用户进程完成
            self.logger.debug("等待用户进程完成...")
            try:
                user_process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                self.logger.warning("用户进程超时，强制终止")
                user_process.kill()
            
            user_return_code = user_process.returncode
            
            # 读取TPJ的stderr
            tpj_stderr = ""
            if tpj_process.stderr:
                tpj_stderr = tpj_process.stderr.read()
                tpj_process.stderr.close()
            
            # 读取用户程序的stderr
            user_stderr = ""
            if user_process.stderr:
                user_stderr = user_process.stderr.read()
                user_process.stderr.close()
            
            end_time = time.time()
            run_time = end_time - start_time
            
            # 分析结果
            result = self._analyze_result(
                tpj_return_code, user_return_code,
                tpj_stderr, user_stderr, run_time, case_name
            )
            
            return result
            
        except Exception as e:
            self.logger.error(f"运行交互题测试时发生异常: {e}", exc_info=True)
            return {
                "status": "ERROR",
                "message": f"运行异常: {e}",
                "tpj_return_code": None,
                "user_return_code": None
            }
        finally:
            # 清理进程
            if user_process:
                try:
                    if user_process.poll() is None:
                        user_process.kill()
                except:
                    pass
            if tpj_process:
                try:
                    if tpj_process.poll() is None:
                        tpj_process.kill()
                except:
                    pass
    
    def _analyze_result(self, tpj_return_code: int, user_return_code: int,
                       tpj_stderr: str, user_stderr: str, run_time: float,
                       case_name: str) -> Dict[str, Any]:
        """分析测试结果"""
        self.logger.info(f"\n测试用例 {case_name} 结果:")
        self.logger.info(f"  运行时间: {run_time:.3f}秒")
        self.logger.info(f"  TPJ返回码: {tpj_return_code}")
        self.logger.info(f"  用户程序返回码: {user_return_code}")
        
        if tpj_stderr:
            self.logger.debug(f"TPJ stderr:\n{tpj_stderr}")
        if user_stderr:
            self.logger.debug(f"用户程序 stderr:\n{user_stderr}")
        
        # 根据testlib的返回码判断结果
        # 0: _ok (答案正确)
        # 1: _wa (答案错误)
        # 2: _pe (格式错误)
        # 3: _fail (评测系统错误)
        
        if tpj_return_code == 0:
            status = "ACCEPTED"
            message = "答案正确"
            self.logger.info(f"  ✓ {message}")
        elif tpj_return_code == 1:
            status = "WRONG_ANSWER"
            message = "答案错误"
            self.logger.error(f"  ✗ {message}")
        elif tpj_return_code == 2:
            status = "PRESENTATION_ERROR"
            message = "格式错误"
            self.logger.error(f"  ✗ {message}")
        elif tpj_return_code == 3:
            status = "SYSTEM_ERROR"
            message = "TPJ程序内部错误"
            self.logger.error(f"  ✗ {message}")
        else:
            status = "UNKNOWN"
            message = f"未知返回码: {tpj_return_code}"
            self.logger.warning(f"  ? {message}")
        
        if user_return_code != 0 and user_return_code is not None:
            self.logger.warning(f"  用户程序异常退出 (返回码: {user_return_code})")
        
        return {
            "status": status,
            "message": message,
            "tpj_return_code": tpj_return_code,
            "user_return_code": user_return_code,
            "run_time": run_time,
            "tpj_stderr": tpj_stderr,
            "user_stderr": user_stderr
        }
    
    def run_all_tests(self, user_file: str) -> bool:
        """运行所有测试用例"""
        # 检查是否是可执行文件
        if self.is_executable(user_file):
            self.logger.info(f"检测到可执行文件: {user_file}")
            user_executable = os.path.abspath(user_file)
            user_cmd = [user_executable]
            language = "executable"
        else:
            # 检测语言
            language = self.detect_language_from_file(user_file)
            if not language:
                return False
            
            # 编译用户程序（如果需要）
            user_executable = self.compile_user_program(os.path.abspath(user_file), language)
            if not user_executable:
                return False
            
            # 获取用户程序运行命令
            user_cmd = self.get_user_run_command(user_executable, language)
            if not user_cmd:
                self.logger.error("无法获取用户程序运行命令")
                return False
        
        # 查找测试用例
        test_cases = self.find_test_cases()
        if not test_cases:
            return False
        
        # 查找并编译TPJ
        tpj_source = os.path.join(self.work_dir, "tpj.cc")
        if not os.path.exists(tpj_source):
            self.logger.error(f"未找到TPJ源文件: {tpj_source}")
            return False
        
        tpj_executable = self.compile_tpj(tpj_source)
        if not tpj_executable:
            return False
        
        # 运行所有测试用例
        results = []
        passed = 0
        
        for in_file, out_file, case_name in test_cases:
            result = self.run_interactive_test(
                user_executable, user_cmd,
                tpj_executable, in_file, out_file, case_name
            )
            results.append((case_name, result))
            
            if result["status"] == "ACCEPTED":
                passed += 1
        
        # 汇总结果
        self.logger.info(f"\n{'='*60}")
        self.logger.info(f"测试汇总")
        self.logger.info(f"{'='*60}")
        self.logger.info(f"总测试用例数: {len(test_cases)}")
        self.logger.info(f"通过数: {passed}")
        self.logger.info(f"失败数: {len(test_cases) - passed}")
        
        for case_name, result in results:
            status_icon = "✓" if result["status"] == "ACCEPTED" else "✗"
            self.logger.info(f"  {status_icon} {case_name}: {result['status']} - {result['message']}")
        
        return passed == len(test_cases)


def main():
    """主函数"""
    parser = argparse.ArgumentParser(
        description="本地交互题测试脚本",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
示例:
  python local_test_interactive.py main.cpp
  python local_test_interactive.py main.py
  python local_test_interactive.py Main.java
  python local_test_interactive.py ./main  (可执行文件)

要求:
  - 当前目录下需要有 tpj.cc 文件
  - 当前目录下需要有 .in 和 .out 测试用例文件对
  - 日志会输出到 interactive_test.log 文件
        """
    )
    
    parser.add_argument(
        "source_file",
        help="选手程序源文件路径或可执行文件路径 (main.cpp, main.py, Main.java, ./main 等)"
    )
    
    parser.add_argument(
        "-w", "--work-dir",
        default=None,
        help="工作目录（默认：源文件所在目录或当前目录）"
    )
    
    args = parser.parse_args()
    
    # 检查源文件是否存在
    if not os.path.exists(args.source_file):
        print(f"错误: 文件不存在: {args.source_file}")
        return 1
    
    # 创建工作目录
    if args.work_dir:
        work_dir = os.path.abspath(args.work_dir)
    else:
        source_dir = os.path.dirname(os.path.abspath(args.source_file))
        work_dir = source_dir if source_dir else os.getcwd()
    
    # 创建测试器并运行
    tester = LocalInteractiveTester(work_dir)
    
    try:
        success = tester.run_all_tests(os.path.abspath(args.source_file))
        return 0 if success else 1
    except KeyboardInterrupt:
        tester.logger.info("\n用户中断测试")
        return 130
    except Exception as e:
        tester.logger.error(f"测试过程中发生异常: {e}", exc_info=True)
        return 1


if __name__ == "__main__":
    sys.exit(main())
