#!/bin/bash
# CSGOJ judge2 评测机 Conda 环境初始化脚本
# 用于在 conda 环境中初始化一个可以测试评测机的 Python 环境

set -e

echo "🚀 开始初始化 CSGOJ judge2 评测机 Conda 环境..."

# 检查是否存在 judge2 环境
if ! conda env list | grep -q "judge2"; then
    echo "📦 创建 judge2 虚拟环境..."
    conda create -n judge2 python=3.12 -y
    echo "✅ judge2 环境创建完成"
else
    echo "✅ judge2 环境已存在"
fi

# 激活 judge2 环境
echo "🔄 激活 judge2 环境..."
source $(conda info --base)/etc/profile.d/conda.sh
conda activate judge2

# 检查当前环境
if [[ "$CONDA_DEFAULT_ENV" != "judge2" ]]; then
    echo "❌ 错误：无法激活 judge2 环境"
    exit 1
fi

echo "✅ 当前 conda 环境：$CONDA_DEFAULT_ENV"

# 检查 Python 版本
PYTHON_VERSION=$(python -c "import sys; print(f'{sys.version_info.major}.{sys.version_info.minor}')")
echo "✅ Python 版本：$PYTHON_VERSION"

# 检查 Python 版本是否正确（需要 3.12）
if [[ "$PYTHON_VERSION" != "3.12" ]]; then
    echo "⚠️  Python 版本不正确（当前：$PYTHON_VERSION，需要：3.12）"
    echo "🔄 重新创建 judge2 环境..."
    conda remove -n judge2 --all -y
    conda create -n judge2 python=3.12 -y
    conda activate judge2
    PYTHON_VERSION=$(python -c "import sys; print(f'{sys.version_info.major}.{sys.version_info.minor}')")
    echo "✅ 环境重新创建完成，Python 版本：$PYTHON_VERSION"
fi

# 检查是否已安装 python3-seccomp
if ! dpkg -l | grep -q python3-seccomp; then
    echo "📦 安装 python3-seccomp 系统包..."
    sudo apt update
    sudo apt install -y python3-seccomp
    echo "✅ python3-seccomp 安装完成"
else
    echo "✅ python3-seccomp 已安装"
fi

# 获取 conda 环境的 site-packages 路径
CONDA_SITE_PACKAGES=$(python -c "import site; print(site.getsitepackages()[0])")
echo "✅ Conda site-packages 路径：$CONDA_SITE_PACKAGES"

# 检查 seccomp 模块是否已链接
if [[ -L "$CONDA_SITE_PACKAGES/seccomp.so" ]]; then
    echo "✅ seccomp 模块已链接"
else
    echo "🔗 创建 seccomp 模块链接..."
    ln -sf /usr/lib/python3/dist-packages/seccomp.cpython-312-x86_64-linux-gnu.so \
           "$CONDA_SITE_PACKAGES/seccomp.so"
    echo "✅ seccomp 模块链接创建完成"
fi

# 安装 Python 依赖
echo "📦 安装 Python 依赖..."
pip install -r ../requirements.txt

# 测试 seccomp 模块
echo "🧪 测试 seccomp 模块..."
python -c "
import seccomp
print('✅ seccomp 导入成功！')
print('seccomp 文件:', seccomp.__file__)

# 测试 API
filter = seccomp.SyscallFilter(defaction=seccomp.KILL)
filter.add_rule(seccomp.ALLOW, 'read')
print('✅ seccomp API 测试成功！')
"

# 测试其他依赖
echo "🧪 测试其他依赖..."
python -c "
import requests
print('✅ requests 版本:', requests.__version__)
"

# 测试评测机核心模块
echo "🧪 测试评测机核心模块..."
python -c "
import sys
import os
# 添加 core 目录到 Python 路径
core_dir = os.path.join(os.getcwd(), 'core')
sys.path.insert(0, core_dir)

try:
    from config_loader import ConfigLoader
    print('✅ ConfigLoader 导入成功')
except ImportError as e:
    print('❌ ConfigLoader 导入失败:', e)

try:
    from web_client import WebClient
    print('✅ WebClient 导入成功')
except ImportError as e:
    print('❌ WebClient 导入失败:', e)

try:
    from judge_base import JudgeBase
    print('✅ JudgeBase 导入成功')
except ImportError as e:
    print('❌ JudgeBase 导入失败:', e)
"

echo ""
echo "🎉 CSGOJ judge2 评测机 Conda 环境初始化完成！"
echo ""
echo "📋 环境信息："
echo "  - Conda 环境：$CONDA_DEFAULT_ENV"
echo "  - Python 版本：$PYTHON_VERSION"
echo "  - seccomp 模块：已链接"
echo "  - Python 依赖：已安装"
echo ""
echo "🚀 现在可以运行评测机了："
echo "  conda activate judge2"
echo "  python core/judge_host.py"
echo "  python core/data_sync.py"
echo "  python core/judge_client.py <solution_id>"
echo ""
echo "💡 提示："
echo "  - 每次使用前请先运行：conda activate judge2"
echo "  - 如需重新初始化环境，请删除后重新运行此脚本"
