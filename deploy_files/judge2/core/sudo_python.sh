#!/bin/bash
# 智能sudo包装脚本 - 自动检测Python环境并执行命令
# 用法: bash sudo_python.sh <python_script> [args...]
# 示例: bash sudo_python.sh judge_client.py 8215 debug

# 获取脚本所在目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# 检查参数
if [ $# -eq 0 ]; then
    echo "用法: bash sudo_python.sh <python_script> [args...]"
    echo "示例: bash sudo_python.sh judge_client.py 8215 debug"
    exit 1
fi

# 获取Python脚本名和参数
PYTHON_SCRIPT="$1"
shift

# 正确处理所有参数，保持引号和空格
SCRIPT_ARGS=()
while [ $# -gt 0 ]; do
    SCRIPT_ARGS+=("$1")
    shift
done

# 检测当前Python环境
PYTHON_PATH=$(which python3)
if [ -z "$PYTHON_PATH" ]; then
    echo "错误：未找到Python解释器"
    exit 1
fi

echo "检测到Python路径: $PYTHON_PATH"
echo "执行脚本: $PYTHON_SCRIPT"
echo "参数: ${SCRIPT_ARGS[*]}"
echo ""

# 设置C/C++编译环境（避免重复添加）
JUDGE_LIB_PATH="$SCRIPT_DIR/../judge_lib"
if [[ ":$C_INCLUDE_PATH:" != *":$JUDGE_LIB_PATH:"* ]]; then
    export C_INCLUDE_PATH="$JUDGE_LIB_PATH:$C_INCLUDE_PATH"
fi
if [[ ":$CPLUS_INCLUDE_PATH:" != *":$JUDGE_LIB_PATH:"* ]]; then
    export CPLUS_INCLUDE_PATH="$JUDGE_LIB_PATH:$CPLUS_INCLUDE_PATH"
fi
if [[ ":$CPATH:" != *":$JUDGE_LIB_PATH:"* ]]; then
    export CPATH="$JUDGE_LIB_PATH:$CPATH"
fi

# 检查是否需要sudo权限（通过尝试创建测试cgroup）
echo "检查cgroups权限..."
if mkdir -p "/sys/fs/cgroup/test_$(whoami)_$(date +%s)" 2>/dev/null; then
    echo "✅ 当前用户已有cgroups权限，无需sudo"
    # 直接运行，无需sudo
    exec python "$PYTHON_SCRIPT" "${SCRIPT_ARGS[@]}"
else
    echo "⚠️  需要sudo权限以使用cgroups监控"
    echo "正在使用sudo执行..."
    
    # 使用sudo运行，保持环境变量
    echo "请输入sudo密码以启用cgroups监控："
    sudo -E bash -c "
        # 设置环境变量
        export PATH='$PATH'
        export PYTHONPATH='$SCRIPT_DIR:$PYTHONPATH'
        export PYTHONIOENCODING=utf-8
        
        # 传递已设置好的C/C++编译环境
        export C_INCLUDE_PATH='$C_INCLUDE_PATH'
        export CPLUS_INCLUDE_PATH='$CPLUS_INCLUDE_PATH'
        export CPATH='$CPATH'
        
        # 切换到工作目录
        cd '$SCRIPT_DIR'
        
        # 执行Python脚本
        exec '$PYTHON_PATH' '$PYTHON_SCRIPT' \"\$@\"
    " -- "${SCRIPT_ARGS[@]}"
fi
