#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
白名单测试工具
围绕评测机逻辑的白名单测试工具，支持指定评测流程的具体步骤进行白名单测试
"""

import os
import sys
import json
import logging
import argparse
import tempfile
import shutil
from typing import Dict, Any, Set, Optional, List

# 将当前目录和core目录添加到Python路径
current_dir = os.path.dirname(os.path.abspath(__file__))
# tool_syscall 在 core/monitor/tool_syscall/ 下，需要访问 core 目录
core_dir = os.path.dirname(os.path.dirname(current_dir))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)
if core_dir not in sys.path:
    sys.path.insert(0, core_dir)

from tools.config_loader import ConfigLoader
from tools.debug_manager import DebugManager, setup_unified_logging, is_debug_enabled
from tools.web_client import WebClient

# 导入seccomp相关模块 - 如果失败则直接报错
from monitor.monitor_syscall import MonitorSyscall
from monitor.ok_call import get_whitelist
from syscall_whitelist_tool import auto_generate_whitelist, test_program_with_whitelist, _save_seccomp_logs_to_temp


class WhitelistChecker:
    """白名单测试工具"""
    
    def __init__(self, config_path: str):
        """初始化白名单测试工具"""
        self.config = ConfigLoader.load_config(config_path)
        
        # 初始化调试管理器
        DebugManager.init_debug_manager(debug_mode=False)
        
        # 设置日志系统
        self.logger = setup_unified_logging("WhitelistChecker", self.config)
        
        # 初始化Web客户端
        self.web_client = WebClient(self.config)
        
        # 加载基础白名单
        self.base_whitelist = self._load_base_whitelist()
        
        self.logger.info("白名单测试工具启动")
        self.logger.info(f"基础白名单包含 {len(self.base_whitelist)} 个系统调用")
    
    def _load_base_whitelist(self) -> Set[str]:
        """加载基础白名单 - 使用 ok_call.py 中的配置"""
        # 使用 ok_call.py 中的基础白名单作为默认基础
        # 这里使用 C++ 编译场景的基础白名单作为通用基础
        base_whitelist = get_whitelist("cpp", "compile", 0)
        self.logger.info(f"使用 ok_call.py 中的 C++ 编译白名单作为基础白名单: {len(base_whitelist)} 个系统调用")
        
        # 如果存在 prepared_whitelist.txt，则使用它作为基础（向后兼容）
        whitelist_file = os.path.join(current_dir, "prepared_whitelist.txt")
        if os.path.exists(whitelist_file):
            try:
                with open(whitelist_file, 'r', encoding='utf-8') as f:
                    file_whitelist = set()
                    for line in f:
                        line = line.strip()
                        if line and line.startswith('"') and line.endswith(','):
                            # 移除引号和逗号
                            syscall = line[1:-2].strip()
                            if syscall:
                                file_whitelist.add(syscall)
                    if file_whitelist:
                        base_whitelist = file_whitelist
                        self.logger.info(f"使用 prepared_whitelist.txt 作为基础白名单: {len(base_whitelist)} 个系统调用")
            except Exception as e:
                self.logger.warning(f"读取 prepared_whitelist.txt 失败，使用默认基础白名单: {e}")
        
        return base_whitelist
    
    def _get_language_base_whitelist(self, language: str, scenario: str, spj_type: int = 0) -> Set[str]:
        """根据语言和场景获取基础白名单"""
        try:
            whitelist = get_whitelist(language, scenario, spj_type)
            self.logger.info(f"获取 {language}_{scenario}_spj{spj_type} 基础白名单: {len(whitelist)} 个系统调用")
            return whitelist
        except Exception as e:
            self.logger.warning(f"获取 {language}_{scenario} 基础白名单失败，使用默认基础白名单: {e}")
            return self.base_whitelist
    
    def _create_test_environment(self, problem_id: int) -> tuple[bool, str, Dict[str, Any]]:
        """创建测试环境"""
        try:
            # 创建临时工作目录
            work_dir = tempfile.mkdtemp(prefix=f"whitelist_test_{problem_id}_")
            self.logger.info(f"创建测试工作目录: {work_dir}")
            
            # 同步题目数据
            from data_sync import DataSync
            data_sync = DataSync(self.config)
            if not data_sync.sync_problem_data(problem_id):
                return False, "", {"error": "同步题目数据失败"}
            
            return True, work_dir, {}
            
        except Exception as e:
            self.logger.error(f"创建测试环境失败: {e}")
            return False, "", {"error": str(e)}
    
    def _cleanup_test_environment(self, work_dir: str):
        """清理测试环境"""
        try:
            if work_dir and os.path.exists(work_dir):
                shutil.rmtree(work_dir)
                self.logger.info(f"清理测试工作目录: {work_dir}")
        except Exception as e:
            self.logger.warning(f"清理测试环境失败: {e}")
    
    def _get_problem_info(self, problem_id: int) -> Optional[Dict[str, Any]]:
        """获取题目信息"""
        try:
            return self.web_client.get_problem_info(problem_id)
        except Exception as e:
            self.logger.error(f"获取题目信息失败: {e}")
            return None
    
    def _prepare_real_solution(self, solution_id: int, work_dir: str) -> tuple[bool, str, str, str]:
        """准备真实的解决方案"""
        try:
            # 获取解决方案信息
            solution_info = self.web_client.get_solution_info(solution_id)
            if not solution_info:
                self.logger.error(f"无法获取解决方案 {solution_id} 的信息")
                return False, "", "", ""
            
            language = solution_info["language"]
            problem_id = solution_info["problem_id"]
            
            # 根据语言确定源文件名
            source_file_map = {
                "python": "Main.py",
                "java": "Main.java", 
                "c": "Main.c",
                "cpp": "Main.cpp",
                "go": "Main.go"
            }
            source_file = source_file_map.get(language, f"Main.{language}")
            executable = "Main"
            
            # 获取源代码
            source_code = self.web_client.get_solution_code(solution_id)
            if not source_code:
                self.logger.error(f"无法获取解决方案 {solution_id} 的源代码")
                return False, "", "", ""
            
            # 保存源代码
            source_path = os.path.join(work_dir, source_file)
            with open(source_path, 'w', encoding='utf-8') as f:
                f.write(source_code)
            
            self.logger.info(f"准备真实解决方案: {source_path}")
            self.logger.info(f"语言: {language}, 题目ID: {problem_id}")
            return True, source_file, executable, language
            
        except Exception as e:
            self.logger.error(f"准备真实解决方案失败: {e}")
            return False, "", "", ""
    
    def test_compile_step(self, problem_id: int, language: str, work_dir: str, 
                         source_file: str, executable: str) -> tuple[bool, Set[str]]:
        """测试编译步骤的白名单"""
        try:
            self.logger.info(f"=== 测试编译步骤白名单 ===")
            self.logger.info(f"语言: {language}, 源文件: {source_file}")
            
            # 构建编译命令
            from lang.lang_factory import LanguageFactory
            lang_handler = LanguageFactory.create_language_handler(language, self.config, work_dir)
            if not lang_handler:
                self.logger.error(f"不支持的语言: {language}")
                return False, set()
            
            # 获取编译命令
            compile_cmd = lang_handler.get_compile_command(source_file, executable)
            if not compile_cmd:
                self.logger.error("无法获取编译命令")
                return False, set()
            
            # 获取语言特定的基础白名单
            language_base_whitelist = self._get_language_base_whitelist(language, "compile", 0)
            
            # 使用语言特定的基础白名单进行白名单生成测试
            self.logger.info(f"使用 {language} 编译基础白名单进行编译步骤白名单测试...")
            monitor = MonitorSyscall(self.logger, task_type='custom')
            monitor.allowed_syscalls = language_base_whitelist.copy()
            
            # 生成编译步骤的增补白名单
            additional_whitelist = auto_generate_whitelist(
                ' '.join(compile_cmd), 
                max_iterations=5, 
                logger=self.logger,
                base_whitelist=language_base_whitelist,
                work_dir=work_dir
            )
            
            # 合并基础白名单和增补白名单
            final_whitelist = language_base_whitelist.union(additional_whitelist)
            
            self.logger.info(f"编译步骤白名单测试完成")
            self.logger.info(f"语言基础白名单: {len(language_base_whitelist)} 个系统调用")
            self.logger.info(f"增补白名单: {len(additional_whitelist)} 个系统调用")
            self.logger.info(f"最终白名单: {len(final_whitelist)} 个系统调用")
            
            # 重要：测试最终白名单在生产环境（TRAP模式）下是否有效
            self.logger.info("=== 生产环境测试（TRAP模式）===")
            from syscall_whitelist_tool import test_whitelist_with_seccomp
            production_test_success = test_whitelist_with_seccomp(
                ' '.join(compile_cmd), 
                final_whitelist, 
                self.logger, 
                work_dir=work_dir
            )
            
            # 保存生产环境测试的seccomp日志
            _save_seccomp_logs_to_temp(' '.join(compile_cmd), self.logger)
            
            if production_test_success:
                self.logger.info("✅ 生产环境测试通过！最终白名单有效")
            else:
                self.logger.error("❌ 生产环境测试失败！最终白名单无效")
                return False, set()
            
            return True, final_whitelist
            
        except Exception as e:
            self.logger.error(f"测试编译步骤白名单失败: {e}")
            return False, set()
    
    def test_run_step(self, problem_id: int, language: str, work_dir: str, 
                     executable: str, test_case: tuple) -> tuple[bool, Set[str]]:
        """测试运行步骤的白名单"""
        try:
            self.logger.info(f"=== 测试运行步骤白名单 ===")
            self.logger.info(f"语言: {language}, 可执行文件: {executable}")
            
            # 构建运行命令
            from lang.lang_factory import LanguageFactory
            lang_handler = LanguageFactory.create_language_handler(language, self.config, work_dir)
            if not lang_handler:
                self.logger.error(f"不支持的语言: {language}")
                return False, set()
            
            # 获取运行命令
            run_cmd = lang_handler.get_run_command(executable)
            if not run_cmd:
                self.logger.error("无法获取运行命令")
                return False, set()
            
            # 获取语言特定的基础白名单
            language_base_whitelist = self._get_language_base_whitelist(language, "run", 0)
            
            # 使用语言特定的基础白名单进行白名单生成测试
            self.logger.info(f"使用 {language} 运行基础白名单进行运行步骤白名单测试...")
            monitor = MonitorSyscall(self.logger, task_type='custom')
            monitor.allowed_syscalls = language_base_whitelist.copy()
            
            # 生成运行步骤的增补白名单
            additional_whitelist = auto_generate_whitelist(
                ' '.join(run_cmd), 
                max_iterations=5, 
                logger=self.logger,
                base_whitelist=language_base_whitelist,
                work_dir=work_dir
            )
            
            # 合并基础白名单和增补白名单
            final_whitelist = language_base_whitelist.union(additional_whitelist)
            
            self.logger.info(f"运行步骤白名单测试完成")
            self.logger.info(f"语言基础白名单: {len(language_base_whitelist)} 个系统调用")
            self.logger.info(f"增补白名单: {len(additional_whitelist)} 个系统调用")
            self.logger.info(f"最终白名单: {len(final_whitelist)} 个系统调用")
            
            # 重要：测试最终白名单在生产环境（TRAP模式）下是否有效
            self.logger.info("=== 生产环境测试（TRAP模式）===")
            from syscall_whitelist_tool import test_whitelist_with_seccomp
            production_test_success = test_whitelist_with_seccomp(
                ' '.join(run_cmd), 
                final_whitelist, 
                self.logger, 
                work_dir=work_dir
            )
            
            # 保存生产环境测试的seccomp日志
            _save_seccomp_logs_to_temp(' '.join(run_cmd), self.logger)
            
            if production_test_success:
                self.logger.info("✅ 生产环境测试通过！最终白名单有效")
            else:
                self.logger.error("❌ 生产环境测试失败！最终白名单无效")
                return False, set()
            
            return True, final_whitelist
            
        except Exception as e:
            self.logger.error(f"测试运行步骤白名单失败: {e}")
            return False, set()
    
    def test_tpj_compile_step(self, problem_id: int, work_dir: str) -> tuple[bool, Set[str]]:
        """测试TPJ编译步骤的白名单"""
        try:
            self.logger.info(f"=== 测试TPJ编译步骤白名单 ===")
            
            # 查找TPJ源文件
            tpj_files = []
            data_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
            if os.path.exists(data_dir):
                for file in os.listdir(data_dir):
                    if file.endswith('.cc') or file.endswith('.cpp'):
                        tpj_files.append(file)
            
            if not tpj_files:
                self.logger.warning("未找到TPJ源文件")
                return False, set()
            
            tpj_file = tpj_files[0]  # 使用第一个找到的TPJ文件
            tpj_path = os.path.join(data_dir, tpj_file)
            tpj_executable = "tpj"
            
            # 复制TPJ文件到工作目录
            work_tpj_path = os.path.join(work_dir, tpj_file)
            shutil.copy2(tpj_path, work_tpj_path)
            
            # 构建TPJ编译命令
            compile_cmd = f"g++ -o {tpj_executable} {tpj_file}"
            
            # 获取TPJ编译的基础白名单（使用C++编译+TPJ编译增补）
            tpj_base_whitelist = self._get_language_base_whitelist("cpp", "compile", 1)
            
            # 使用TPJ编译基础白名单进行白名单生成测试
            self.logger.info("使用TPJ编译基础白名单进行TPJ编译步骤白名单测试...")
            monitor = MonitorSyscall(self.logger, task_type='custom')
            monitor.allowed_syscalls = tpj_base_whitelist.copy()
            
            # 生成TPJ编译步骤的增补白名单
            additional_whitelist = auto_generate_whitelist(
                compile_cmd, 
                max_iterations=5, 
                logger=self.logger,
                base_whitelist=tpj_base_whitelist,
                work_dir=work_dir
            )
            
            # 合并基础白名单和增补白名单
            final_whitelist = tpj_base_whitelist.union(additional_whitelist)
            
            self.logger.info(f"TPJ编译步骤白名单测试完成")
            self.logger.info(f"TPJ基础白名单: {len(tpj_base_whitelist)} 个系统调用")
            self.logger.info(f"增补白名单: {len(additional_whitelist)} 个系统调用")
            self.logger.info(f"最终白名单: {len(final_whitelist)} 个系统调用")
            
            return True, final_whitelist
            
        except Exception as e:
            self.logger.error(f"测试TPJ编译步骤白名单失败: {e}")
            return False, set()
    
    def test_tpj_run_step(self, problem_id: int, work_dir: str, 
                         tpj_executable: str, test_case: tuple) -> tuple[bool, Set[str]]:
        """测试TPJ运行步骤的白名单"""
        try:
            self.logger.info(f"=== 测试TPJ运行步骤白名单 ===")
            
            # 构建TPJ运行命令
            in_file, out_file, case_name = test_case
            run_cmd = f"./{tpj_executable} {in_file} {out_file}"
            
            # 获取TPJ运行的基础白名单（使用C++运行+TPJ运行增补）
            tpj_base_whitelist = self._get_language_base_whitelist("cpp", "run", 1)
            
            # 使用TPJ运行基础白名单进行白名单生成测试
            self.logger.info("使用TPJ运行基础白名单进行TPJ运行步骤白名单测试...")
            monitor = MonitorSyscall(self.logger, task_type='custom')
            monitor.allowed_syscalls = tpj_base_whitelist.copy()
            
            # 生成TPJ运行步骤的增补白名单
            additional_whitelist = auto_generate_whitelist(
                run_cmd, 
                max_iterations=5, 
                logger=self.logger,
                base_whitelist=tpj_base_whitelist,
                work_dir=work_dir
            )
            
            # 合并基础白名单和增补白名单
            final_whitelist = tpj_base_whitelist.union(additional_whitelist)
            
            self.logger.info(f"TPJ运行步骤白名单测试完成")
            self.logger.info(f"TPJ基础白名单: {len(tpj_base_whitelist)} 个系统调用")
            self.logger.info(f"增补白名单: {len(additional_whitelist)} 个系统调用")
            self.logger.info(f"最终白名单: {len(final_whitelist)} 个系统调用")
            
            return True, final_whitelist
            
        except Exception as e:
            self.logger.error(f"测试TPJ运行步骤白名单失败: {e}")
            return False, set()
    
    def run_whitelist_test(self, solution_id: int, step: str) -> Dict[str, Any]:
        """运行白名单测试"""
        work_dir = None
        
        try:
            self.logger.info(f"开始白名单测试 - 解决方案ID: {solution_id}, 步骤: {step}")
            
            # 获取解决方案信息
            solution_info = self.web_client.get_solution_info(solution_id)
            if not solution_info:
                return {"success": False, "error": "无法获取解决方案信息"}
            
            problem_id = solution_info["problem_id"]
            language = solution_info["language"]
            
            self.logger.info(f"题目ID: {problem_id}, 语言: {language}")
            
            # 获取题目信息
            problem_info = self._get_problem_info(problem_id)
            if not problem_info:
                return {"success": False, "error": "无法获取题目信息"}
            
            # 创建测试环境
            success, work_dir, error_info = self._create_test_environment(problem_id)
            if not success:
                return {"success": False, "error": error_info.get("error", "创建测试环境失败")}
            
            # 根据步骤执行相应的测试
            if step == "compile":
                # 测试编译步骤
                success, source_file, executable, actual_language = self._prepare_real_solution(solution_id, work_dir)
                if not success:
                    return {"success": False, "error": "准备真实解决方案失败"}
                
                test_success, whitelist = self.test_compile_step(problem_id, actual_language, work_dir, source_file, executable)
                
            elif step == "run":
                # 测试运行步骤
                success, source_file, executable, actual_language = self._prepare_real_solution(solution_id, work_dir)
                if not success:
                    return {"success": False, "error": "准备真实解决方案失败"}
                
                # 先编译
                from lang.lang_factory import LanguageFactory
                lang_handler = LanguageFactory.create_language_handler(actual_language, self.config, work_dir)
                if lang_handler:
                    compile_result = lang_handler.compile_solution(source_file, executable)
                    if compile_result["compile_status"] != "success":
                        return {"success": False, "error": f"编译解决方案失败: {compile_result.get('message', '')}"}
                
                # 获取测试用例
                data_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
                test_cases = []
                if os.path.exists(data_dir):
                    for file in sorted(os.listdir(data_dir)):
                        if file.endswith('.in'):
                            base_name = file[:-3]
                            in_file = os.path.join(data_dir, file)
                            out_file = os.path.join(data_dir, f"{base_name}.out")
                            if os.path.exists(out_file):
                                test_cases.append((in_file, out_file, base_name))
                
                if not test_cases:
                    return {"success": False, "error": "未找到测试用例"}
                
                test_success, whitelist = self.test_run_step(problem_id, actual_language, work_dir, executable, test_cases[0])
                
            elif step == "tpj_compile":
                # 测试TPJ编译步骤
                test_success, whitelist = self.test_tpj_compile_step(problem_id, work_dir)
                
            elif step == "tpj_run":
                # 测试TPJ运行步骤
                # 先编译TPJ
                tpj_success, tpj_whitelist = self.test_tpj_compile_step(problem_id, work_dir)
                if not tpj_success:
                    return {"success": False, "error": "TPJ编译失败"}
                
                # 获取测试用例
                data_dir = os.path.join(self.config["judge"]["data_dir"], str(problem_id))
                test_cases = []
                if os.path.exists(data_dir):
                    for file in sorted(os.listdir(data_dir)):
                        if file.endswith('.in'):
                            base_name = file[:-3]
                            in_file = os.path.join(data_dir, file)
                            out_file = os.path.join(data_dir, f"{base_name}.out")
                            if os.path.exists(out_file):
                                test_cases.append((in_file, out_file, base_name))
                
                if not test_cases:
                    return {"success": False, "error": "未找到测试用例"}
                
                test_success, whitelist = self.test_tpj_run_step(problem_id, work_dir, "tpj", test_cases[0])
                
            else:
                return {"success": False, "error": f"不支持的步骤: {step}"}
            
            if test_success:
                # 根据步骤确定使用的基础白名单
                if step == "compile":
                    base_whitelist = self._get_language_base_whitelist(language, "compile", 0)
                elif step == "run":
                    base_whitelist = self._get_language_base_whitelist(language, "run", 0)
                elif step == "tpj_compile":
                    base_whitelist = self._get_language_base_whitelist("cpp", "compile", 1)
                elif step == "tpj_run":
                    base_whitelist = self._get_language_base_whitelist("cpp", "run", 1)
                else:
                    base_whitelist = self.base_whitelist
                
                # 计算增补白名单
                additional_whitelist = whitelist - base_whitelist
                
                return {
                    "success": True,
                    "solution_id": solution_id,
                    "problem_id": problem_id,
                    "step": step,
                    "language": language,
                    "base_whitelist_count": len(base_whitelist),
                    "additional_whitelist_count": len(additional_whitelist),
                    "final_whitelist_count": len(whitelist),
                    "base_whitelist": sorted(base_whitelist),
                    "additional_whitelist": sorted(additional_whitelist),
                    "final_whitelist": sorted(whitelist)
                }
            else:
                return {"success": False, "error": "白名单测试失败"}
                
        except Exception as e:
            self.logger.error(f"运行白名单测试失败: {e}")
            return {"success": False, "error": str(e)}
        finally:
            # 清理测试环境
            if work_dir:
                self._cleanup_test_environment(work_dir)


def main():
    """主函数"""
    parser = argparse.ArgumentParser(description="白名单测试工具")
    parser.add_argument("solution_id", type=int, help="解决方案ID")
    parser.add_argument("step", choices=["compile", "run", "tpj_compile", "tpj_run"], 
                       help="测试步骤: compile(编译选手程序), run(运行选手程序), tpj_compile(编译TPJ), tpj_run(运行TPJ)")
    parser.add_argument("--config", help="配置文件路径")
    parser.add_argument("--output", help="输出文件路径")
    
    args = parser.parse_args()
    
    # 确定配置文件路径
    if args.config:
        config_path = args.config
    else:
        # 使用默认配置文件路径
        config_path = os.path.join(core_dir, "config.json")
    
    # 创建白名单测试工具
    checker = WhitelistChecker(config_path)
    
    # 运行白名单测试
    result = checker.run_whitelist_test(args.solution_id, args.step)
    
    # 确定输出文件路径
    if args.output:
        output_file = args.output
    else:
        # 默认保存到当前目录的 white_test_res.json
        output_file = os.path.join(current_dir, "white_test_res.json")
    
    # 保存结果到文件
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(result, f, ensure_ascii=False, indent=2)
    print(f"结果已保存到: {output_file}")
    
    # 同时在控制台输出结果
    print("\n测试结果:")
    print(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
