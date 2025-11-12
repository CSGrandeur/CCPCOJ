#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CSGOJ judge2 TPJ评测类型模块 (spj=1)
基于testlib的tpj.cc特判评测系统
"""

import os
import sys
import subprocess
import shutil
from typing import Dict, Any

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
        self.tpj_work_dir = None  # TPJ 工作目录（用于编译和运行）
    
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
        """运行tpj程序进行评测
        
        注意：tpj_executable 参数保留以保持接口兼容，但实际不使用
        TPJ 可执行文件已经在 self.tpj_work_dir 中（编译时已准备好）
        """
        try:
            # 直接使用 self.tpj_work_dir（已经在 run_special_judge_mode 中设置）
            # 只复制测试数据文件，TPJ 可执行文件已经在工作目录中
            files_to_copy = {
                "input.in": in_file,
                "output.out": out_file,
                "user_output.out": user_output
            }
            chroot_files = self._copy_files_to_tpj_work_dir(self.tpj_work_dir, files_to_copy)
            
            # 构建命令（TPJ 可执行文件已经在工作目录中，使用相对路径）
            # testlib 标准参数顺序：in_file, user_output, out_file
            cmd = ["./tpj", chroot_files["input.in"], 
                   chroot_files["user_output.out"], chroot_files["output.out"]]
            
            if is_debug_enabled():
                self.logger.debug(f"运行 TPJ 程序: {' '.join(cmd)}")
            
            # 复用基类的运行逻辑（使用实例变量 self.tpj_work_dir）
            result = self.run_process_with_atomic_monitoring(
                cmd=cmd,
                time_limit=10.0,
                memory_limit=1024,
                stdout_file=subprocess.PIPE,  # 捕获 stdout
                stderr_file=subprocess.PIPE,  # 捕获 stderr
                test_case_name="tpj_judge",
                task_type=sc.TASK_TYPE_TPJ_RUN,
                language="cpp",
                work_dir=self.tpj_work_dir  # 使用实例变量
            )
            
            return result
                
        except Exception as e:
            return {
                "program_status": sc.PROGRAM_SYSTEM_ERROR, 
                "judge_result": sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR), 
                "time": 0,
                "memory": 0,
                "message": f"TPJ评测异常：{str(e)}"
            }
        # 注意：不需要 finally 清理，因为工作目录在 run_special_judge_mode 结束时统一清理
    
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

                # 合并结果（安全删除 time 和 memory，因为 TPJ 结果不应该覆盖选手程序的时间和内存）
                tpj_result.pop('time', None)
                tpj_result.pop('memory', None)
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
            
            # 准备 TPJ 工作目录（用于编译和运行，保存为实例变量）
            self.tpj_work_dir, _ = self._prepare_tpj_work_dir()
            
            # 检查是否有缓存的 TPJ 可执行文件
            cached_tpj = self._get_cached_tpj_executable(problem_id, tpj_program)
            
            if cached_tpj:
                # 使用缓存的 TPJ 可执行文件，复制到 TPJ 工作目录
                self.logger.info(f"使用缓存的 TPJ 可执行文件: {cached_tpj}")
                self.tpj_executable = os.path.join(self.tpj_work_dir, "tpj")
                shutil.copy2(cached_tpj, self.tpj_executable)
                os.chmod(self.tpj_executable, 0o755)
            else:
                # 需要编译 TPJ 程序（在 TPJ 工作目录编译）
                self.logger.info(f"编译 TPJ 程序: {tpj_program}")
                # 将tpj程序复制到 TPJ 工作目录
                tpj_source_path = os.path.join(self.tpj_work_dir, "tpj.cc")
                shutil.copy2(tpj_program, tpj_source_path)
                
                # 在 TPJ 工作目录编译
                if not self.compile_solution("cpp", "tpj.cc", "tpj", sc.TASK_TYPE_TPJ_COMPILE, work_dir=self.tpj_work_dir):
                    raise JudgeSysErrCompile("tpj.cc 编译失败")
                
                # 编译好的可执行文件在 TPJ 工作目录
                self.tpj_executable = os.path.join(self.tpj_work_dir, "tpj")
                
                # 编译完成后立即删除 tpj.cc 源代码
                try:
                    os.remove(tpj_source_path)
                    self.logger.debug(f"已删除 TPJ 源代码文件: {tpj_source_path}")
                except Exception as e:
                    self.logger.warning(f"删除 TPJ 源代码文件失败: {e}")
                
                # 缓存编译好的 TPJ 可执行文件
                self._cache_tpj_executable(problem_id, self.tpj_executable)
                        
            # 使用基类的公共方法运行测试用例循环
            return self.run_test_cases_loop(test_cases, executable, time_limit, memory_limit, language)
                
        except (JudgeSysErrTestData, JudgeSysErrCompile):
            # 重新抛出已知的评测系统错误
            raise
        except Exception as e:
            # 其他异常转换为测试数据错误
            raise JudgeSysErrTestData(f"特判评测时发生错误：{e}")
        finally:
            # 清理 TPJ 工作目录（使用基类方法）
            if self.tpj_work_dir:
                self._cleanup_tpj_work_dir(self.tpj_work_dir)
