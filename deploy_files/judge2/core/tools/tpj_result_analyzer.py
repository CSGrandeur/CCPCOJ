#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
TPJ 结果分析器
分析 TPJ 程序的运行结果，将 testlib 返回值映射为评测结果
"""

import sys
import os
from typing import Dict, Any

# 添加路径以导入状态常量
current_dir = os.path.dirname(os.path.abspath(__file__))
parent_dir = os.path.dirname(current_dir)
if parent_dir not in sys.path:
    sys.path.insert(0, parent_dir)
from tools import status_constants as sc


def analyze_tpj_result(return_code: int, stderr_data: str = "", cpu_time_used: float = 0, memory_used: int = 0) -> Dict[str, Any]:
    """
    分析 TPJ 程序的返回码，返回完整的评测结果
    
    Args:
        return_code: TPJ 程序返回码
        stderr_data: 标准错误输出
        cpu_time_used: CPU时间使用量（毫秒）
        memory_used: 内存使用量（KB）
        
    Returns:
        包含完整评测结果的字典
    """
    # testlib 返回值映射
    testlib_codes = {
        0: "ACCEPTED",
        1: "WRONG_ANSWER", 
        2: "PRESENTATION_ERROR",
        3: "FAIL",
        4: "DIRT",
        5: "POINTS",
        7: "POINTS",
        8: "UNEXPECTED_EOF"
    }
    
    # 基础结果
    result = {
        "program_status": sc.PROGRAM_COMPLETED,
        "judge_result": sc.JUDGE_UNKNOWN,
        "time": int(cpu_time_used),
        "memory": memory_used,
        "message": "",
        "testlib_result": None,
        "testlib_message": stderr_data.strip(),
        "judge_info": None
    }
    
    # 检查是否为 testlib 返回值
    if return_code in testlib_codes:
        testlib_result = testlib_codes[return_code]
        result["testlib_result"] = testlib_result
        
        if testlib_result == "ACCEPTED":
            result["judge_result"] = sc.JUDGE_ACCEPTED
            result["message"] = "TPJ评测通过"
        elif testlib_result == "WRONG_ANSWER":
            result["judge_result"] = sc.JUDGE_WRONG_ANSWER
            result["message"] = "TPJ评测未通过：答案错误"
            result["judge_info"] = stderr_data.strip()
        elif testlib_result == "PRESENTATION_ERROR":
            result["judge_result"] = sc.JUDGE_PRESENTATION_ERROR
            result["message"] = "TPJ评测未通过：格式错误"
            result["judge_info"] = stderr_data.strip()
        elif testlib_result == "POINTS":
            result["judge_result"] = sc.JUDGE_WRONG_ANSWER
            result["message"] = "TPJ评测：部分正确"
            result["judge_info"] = stderr_data.strip()
        elif testlib_result == "FAIL":
            result["program_status"] = sc.PROGRAM_SYSTEM_ERROR
            result["judge_result"] = sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR)
            result["message"] = "TPJ程序报告：出题人错误" # 例：quitif(pans != jans, _fail, "participant has found a solution, but jury hasn't");
        else:
            result["judge_result"] = sc.JUDGE_WRONG_ANSWER
            result["message"] = f"TPJ评测结果：{testlib_result}"
            result["judge_info"] = stderr_data.strip()
    else:
        # 非 testlib 返回值，视为系统错误
        result["program_status"] = sc.PROGRAM_SYSTEM_ERROR
        result["judge_result"] = sc.get_judge_result_from_program_status(sc.PROGRAM_SYSTEM_ERROR)
        result["message"] = f"TPJ程序异常退出，返回码: {return_code}"
    
    return result


if __name__ == "__main__":
    # 测试
    print("=== TPJ 结果分析器测试 ===")
    
    test_cases = [
        (0, "ok 12 numbers"),
        (1, "wrong answer Expected 5, but found 3"),
        (2, "presentation error Invalid format"),
        (3, "fail Internal error"),
        (5, "points 0.5"),
        (123, "unknown error")
    ]
    
    for return_code, stderr in test_cases:
        result = analyze_tpj_result(return_code, stderr, 100, 1024)
        print(f"返回码 {return_code}: {result['message']} (judge_result: {result['judge_result']})")
        if result['judge_info']:
            print(f"  错误信息: {result['judge_info']}")
        print()
