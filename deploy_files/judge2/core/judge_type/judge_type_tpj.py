#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 TPJ评测类型模块 (spj=1)
基于testlib的tpj.cc特判评测系统
"""

import os
import sys
import time
import logging
import subprocess
import shutil
from typing import Dict, Any, Optional, List

# 将当前目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)

from base_judge_type import BaseJudgeType, JudgeSysErrTestData, JudgeSysErrCompile, JudgeSysErrProgram
from tools import status_constants as sc
from tools.debug_manager import is_debug_enabled

class JudgeTypeTpj(BaseJudgeType):
    """TPJ评测类型 (spj=1) - 基于testlib的tpj.cc特判评测"""
    
    def __init__(self, config: Dict[str, Any], work_dir: str = None):
        """初始化TPJ评测器"""
        super().__init__(config, work_dir)
    
    def compile_tpj(self, source_file: str, executable: str) -> Dict[str, Any]:
        """编译tpj程序"""
        try:
            # 编译tpj程序
            compile_cmd = [
                "g++", "-o", executable, source_file,
                "-O2", "-std=c++17", "-Wall", "-Wextra"
            ]
            
            result = subprocess.run(
                compile_cmd,
                capture_output=True,
                text=True,
                timeout=30
            )
            
            if result.returncode == 0:
                return {"compile_status": "success", "message": "编译成功"}
            else:
                return {"program_status": sc.PROGRAM_SYSTEM_ERROR, "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR), "message": f"编译失败：{result.stderr}"}
                
        except subprocess.TimeoutExpired:
            return {"program_status": sc.PROGRAM_SYSTEM_ERROR, "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR), "message": "编译超时"}
        except Exception as e:
            return {"program_status": sc.PROGRAM_SYSTEM_ERROR, "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR), "message": str(e)}
    
    def run_tpj_judge(self, tpj_executable: str, in_file: str, out_file: str, user_output: str) -> Dict[str, Any]:
        """运行tpj程序进行评测"""
        chroot_input_file = None
        chroot_output_file = None
        
        try:
            # 复制测试数据文件到工作目录（chroot 环境）
            chroot_input_file = os.path.join(self.work_dir, "input.in")
            chroot_output_file = os.path.join(self.work_dir, "output.out")
            
            # 复制输入和输出文件
            shutil.copy2(in_file, chroot_input_file)
            shutil.copy2(out_file, chroot_output_file)
            
            if is_debug_enabled():
                self.logger.debug(f"复制测试数据文件:")
                self.logger.debug(f"  {in_file} -> {chroot_input_file}")
                self.logger.debug(f"  {out_file} -> {chroot_output_file}")
            
            # 使用运行监控器运行 TPJ 程序
            from monitor.run_monitor import create_run_monitor
            
            # 创建 TPJ 运行监控器（使用 tpj_run 任务类型）
            tpj_monitor = create_run_monitor(
                logger=self.logger,
                memory_limit_mb=1024,  # TPJ 程序内存限制
                time_limit=10.0,      # TPJ 程序时间限制
                work_dir=self.work_dir,
                test_case_name="tpj_judge",
                language="cpp",
                task_type=sc.TASK_TYPE_TPJ_RUN   # 明确指定为 TPJ 运行任务
            )
            
            # 构建命令（使用原始路径，与其他评测模式保持一致）
            # testlib 标准参数顺序：in_file, user_output, out_file
            cmd = [tpj_executable, chroot_input_file, user_output, chroot_output_file]
            # 统一使用 convert_cmd_for_chroot 转换命令路径
            chroot_cmd = self.convert_cmd_for_chroot(cmd)
            
            if is_debug_enabled():
                self.logger.debug(f"运行 TPJ 程序: {' '.join(chroot_cmd)}")
            
            # 运行 TPJ 程序，tpj_result_analyzer 已经处理了所有结果分析
            result = tpj_monitor.run_program_with_monitoring(
                cmd=chroot_cmd,
                stdout=subprocess.PIPE,  # 捕获 stdout
                stderr=subprocess.PIPE,  # 捕获 stderr
                cwd=self.work_dir
            )
            
            if 'judge_result' not in result:
                # tpj 未正常执行
                result['judge_result'] = sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR)
            
            return result
                
        except Exception as e:
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR, 
                "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR), 
                "message": f"TPJ评测异常：{str(e)}"
            }
        finally:
            # 清理复制的测试数据文件
            try:
                if chroot_input_file and os.path.exists(chroot_input_file):
                    os.remove(chroot_input_file)
                    if is_debug_enabled():
                        self.logger.debug(f"清理文件: {chroot_input_file}")
                
                if chroot_output_file and os.path.exists(chroot_output_file):
                    os.remove(chroot_output_file)
                    if is_debug_enabled():
                        self.logger.debug(f"清理文件: {chroot_output_file}")
            except Exception as cleanup_error:
                # 清理失败不应该影响评测结果，只记录警告
                self.logger.warning(f"清理测试数据文件失败: {cleanup_error}")
    
    def run_single_test_case(self, in_file: str, out_file: str, case_name: str, 
                           executable: str, time_limit: float, memory_limit: int, 
                           language: str = "cpp") -> Dict[str, Any]:
        """运行单个测试用例 - TPJ评测实现"""
        try:
            # 运行程序
            user_output = os.path.join(self.work_dir, f"{case_name}_user.out")
            result = self.run_solution(language, executable, in_file, out_file, user_output, time_limit, memory_limit, case_name)
            
            if result["program_status"] == sc.PROGRAM_COMPLETED:
                # 运行tpj程序
                tpj_result = self.run_tpj_judge(self.tpj_executable, in_file, out_file, user_output)

                # 合并结果
                del tpj_result['time']
                del tpj_result['memory']
                result.update(tpj_result)
            
            return result
            
        except Exception as e:
            self.logger.error(f"运行TPJ测试用例 {case_name} 时发生错误：{e}")
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR,
                "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR),
                "time": 0,
                "memory": 0,
                "message": str(e)
            }
    
    def run_special_judge_mode(self, problem_id: int, executable: str, time_limit: float, memory_limit: int, language: str = "cpp") -> Dict[str, Any]:
        """运行特判评测（使用SPJ程序）"""
        try:
            # 构建data_dir路径
            data_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
            
            # 使用基类的公共方法发现测试用例（会自动检查data_dir和测试用例）
            test_cases = self.discover_test_cases(problem_id, data_dir)
            
            # 查找 TPJ 程序
            data_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
            tpj_program = os.path.join(data_dir, "tpj.cc")
            if not os.path.exists(tpj_program):
                raise JudgeSysErrTestData("tpj.cc 不存在")
            
            # 编译 TPJ 程序
            self.tpj_executable = os.path.join(self.work_dir, "tpj")
            # 将tpj程序复制到工作目录
            tpj_work_path = os.path.join(self.work_dir, "tpj.cc")
            shutil.copy2(tpj_program, tpj_work_path)
            if not self.compile_solution("cpp", "tpj.cc", "tpj", sc.TASK_TYPE_TPJ_COMPILE):
                raise JudgeSysErrCompile("tpj.cc 编译失败")
            
            # 编译完成后立即删除 tpj.cc 源代码，防止选手程序访问
            try:
                os.remove(tpj_work_path)
                self.logger.debug(f"已删除 TPJ 源代码文件: {tpj_work_path}")
            except Exception as e:
                self.logger.warning(f"删除 TPJ 源代码文件失败: {e}")
                        
            # 使用基类的公共方法运行测试用例循环
            return self.run_test_cases_loop(test_cases, executable, time_limit, memory_limit, language)
                
        except (JudgeSysErrTestData, JudgeSysErrCompile):
            # 重新抛出已知的评测系统错误
            raise
        except Exception as e:
            # 其他异常转换为测试数据错误
            raise JudgeSysErrTestData(f"特判评测时发生错误：{e}")
