#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 默认评测类型模块 (spj=0)
基于testlib的默认比对评测系统
"""

import os
import sys
import time
import logging
import subprocess
from typing import Dict, Any, Optional, List

# 将当前目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)

from base_judge_type import BaseJudgeType, JudgeSysErrTestData, JudgeSysErrCompile, JudgeSysErrProgram
from tools import status_constants as sc
from tools.debug_manager import is_debug_enabled

class JudgeTypeDefault(BaseJudgeType):
    """默认评测类型 (spj=0) - 基于testlib的比对评测"""
    
    def __init__(self, config: Dict[str, Any], work_dir: str = None):
        """初始化默认评测器"""
        super().__init__(config, work_dir)
    
    def run_default_check(self, in_file: str, out_file: str, user_output: str) -> Dict[str, Any]:
        """运行默认比对程序（使用testlib）"""
        try:
            # default_check 编译后放在 core 目录
            core_dir = self.get_core_dir()
            default_check_path = os.path.join(core_dir, "default_check")
            default_check_cc_path = os.path.join(core_dir, "default_check.cc")
            
            # 检查 default_check 是否存在，不存在则编译
            if not os.path.exists(default_check_path):
                if os.path.exists(default_check_cc_path):
                    self.logger.info("编译 default_check 程序...")
                    # 添加 testlib.h 头文件路径
                    judge_lib_path = os.path.join(core_dir, "..", "judge_lib")
                    compile_cmd = ["g++", "-O2", "-std=c++17", f"-I{judge_lib_path}", "-o", "default_check", "default_check.cc"]
                    
                    if is_debug_enabled():
                        self.logger.debug(f"编译命令: {' '.join(compile_cmd)}")
                    
                    compile_process = subprocess.run(
                        compile_cmd,
                        cwd=core_dir,
                        capture_output=True,
                        text=True
                    )
                    
                    if compile_process.returncode != 0:
                        raise JudgeSysErrCompile(f"编译 default_check 失败: {compile_process.stderr}")
                    
                    # 编译成功后，添加执行权限（使用 chmod +x，保留原有权限）
                    subprocess.run(["chmod", "+x", default_check_path], check=True)
                    self.logger.info("default_check 编译成功")
                else:
                    raise JudgeSysErrCompile("default_check.cc 源文件不存在")
            else:
                # 如果 default_check 存在，检查是否可执行
                if not os.access(default_check_path, os.X_OK):
                    self.logger.info("为 default_check 添加执行权限...")
                    # 添加执行权限（使用 chmod +x，保留原有权限）
                    subprocess.run(["chmod", "+x", default_check_path], check=True)
            
            # 运行testlib比对程序（使用绝对路径）
            # testlib 标准参数顺序：in_file, user_output, out_file
            # testlib 内部：inf=in_file, ouf=user_output, ans=out_file
            cmd = [default_check_path, in_file, user_output, out_file]
            
            if is_debug_enabled():
                self.logger.debug(f"运行比对程序: {' '.join(cmd)}")
                self.logger.debug(f"参数说明:")
                self.logger.debug(f"  in_file (inf): {in_file}")
                self.logger.debug(f"  user_output (ouf): {user_output}")  
                self.logger.debug(f"  out_file (ans): {out_file}")
                self.logger.debug(f"  default_check_path: {default_check_path}")
                self.logger.debug(f"  工作目录: {core_dir}")
            
            # 注意：default_check 在 core 目录下运行，不使用 chroot，所以不需要路径转换
            process = subprocess.Popen(
                cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                cwd=core_dir  # 在 core 目录下运行
            )
            
            try:
                stdout, stderr = process.communicate(timeout=10)
                return_code = process.returncode
                
                if return_code == 0:
                    # _ok: 答案正确
                    return {"judge_result": sc.JUDGE_ACCEPTED, "message": "输出正确"}
                elif return_code == 1:
                    # _wa: 答案错误
                    return {
                        "judge_result": sc.JUDGE_WRONG_ANSWER, 
                        "message": "输出错误",
                        "judge_info": "答案错误：程序输出与期望输出不匹配"
                    }
                elif return_code == 2:
                    # _pe: 格式错误
                    return {
                        "judge_result": sc.JUDGE_PRESENTATION_ERROR, 
                        "message": "输出格式错误",
                        "judge_info": "格式错误：程序输出格式与期望格式不匹配"
                    }
                elif return_code == 3:
                    # _fail: 评测系统错误
                    error_msg = f"testlib 内部错误 (返回码: {return_code})"
                    if stderr:
                        error_msg += f"，错误信息: {stderr}"
                    if stdout:
                        error_msg += f"，输出: {stdout}"
                    
                    if is_debug_enabled():
                        self.logger.error(f"testlib 内部错误: {error_msg}")
                        self.logger.error(f"命令: {' '.join(cmd)}")
                        self.logger.error(f"工作目录: {core_dir}")
                        self.logger.error(f"输入文件: {in_file}")
                        self.logger.error(f"期望文件: {out_file}")
                        self.logger.error(f"实际文件: {user_output}")
                    
                    raise JudgeSysErrProgram(error_msg)
                else:
                    # 其他未知错误码
                    error_msg = f"testlib 未知错误 (返回码: {return_code})"
                    if stderr:
                        error_msg += f"，错误信息: {stderr}"
                    if stdout:
                        error_msg += f"，输出: {stdout}"
                    
                    if is_debug_enabled():
                        self.logger.error(f"testlib 未知错误: {error_msg}")
                        self.logger.error(f"命令: {' '.join(cmd)}")
                        self.logger.error(f"工作目录: {core_dir}")
                        self.logger.error(f"输入文件: {in_file}")
                        self.logger.error(f"期望文件: {out_file}")
                        self.logger.error(f"实际文件: {user_output}")
                    
                    raise JudgeSysErrProgram(error_msg)
                    
            except subprocess.TimeoutExpired:
                process.kill()
                error_msg = "比对程序超时"
                if is_debug_enabled():
                    self.logger.error(f"default_check 超时: {error_msg}")
                raise JudgeSysErrProgram(error_msg)
                
        except (JudgeSysErrCompile, JudgeSysErrProgram):
            # 重新抛出已知的评测系统错误
            raise
        except Exception as e:
            from tools.debug_manager import log_error_with_context
            log_error_with_context(self.logger, "运行默认比对程序", e, {
                "in_file": in_file,
                "out_file": out_file,
                "user_output": user_output
            })
            raise JudgeSysErrProgram(f"运行默认比对程序时发生错误：{e}")
    
    def run_single_test_case(self, in_file: str, out_file: str, case_name: str, 
                           executable: str, time_limit: float, memory_limit: int, 
                           language: str = "cpp") -> Dict[str, Any]:
        """运行单个测试用例 - 默认评测实现"""
        try:
            # 运行程序
            user_output = os.path.join(self.work_dir, f"{case_name}_user.out")
            result = self.run_solution(language, executable, in_file, out_file, user_output, time_limit, memory_limit, case_name)
            
            if result["program_status"] == sc.PROGRAM_COMPLETED:
                # 使用testlib比对程序
                check_result = self.run_default_check(in_file, out_file, user_output)
                
                # 合并结果
                result.update({
                    "judge_result": check_result["judge_result"],
                    "message": check_result["message"]
                })
                
                # 如果是系统错误，需要特殊处理
                if check_result["judge_result"] == sc.JUDGE_SYSTEM_ERROR:
                    result["program_status"] = sc.PROGRAM_SYSTEM_ERROR
                    self.logger.error(f"testlib 系统错误: {check_result['message']}")

            return result
            
        except Exception as e:
            self.logger.error(f"运行测试用例 {case_name} 时发生错误：{e}")
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR),
                "time": 0,
                "memory": 0,
                "message": str(e)
            }
    
    def run_default_judge(self, problem_id: int, executable: str, time_limit: float, memory_limit: int, language: str = "cpp") -> Dict[str, Any]:
        """运行默认评测（使用testlib比对）"""
        try:
            # 构建data_dir路径
            data_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
            
            # 使用基类的公共方法发现测试用例（会自动检查data_dir和测试用例）
            test_cases = self.discover_test_cases(problem_id, data_dir)
            
            # 使用基类的公共方法运行测试用例循环
            return self.run_test_cases_loop(test_cases, executable, time_limit, memory_limit, language)
                
        except JudgeSysErrTestData as e:
            # 测试数据错误，直接抛出让上层处理
            raise
        except Exception as e:
            # 其他异常转换为测试数据错误
            raise JudgeSysErrTestData(f"默认评测时发生错误：{e}")
