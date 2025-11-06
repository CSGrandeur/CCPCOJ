#!/bin/bash
# CSGOJ judge2 评测机构建脚本

set -e

echo "##################################################"
echo "构建CSGOJ judge2评测机"
echo "##################################################"

# 检查Docker是否运行
if ! command -v docker &> /dev/null; then
    echo "错误：Docker未安装或未运行"
    exit 1
fi

# 构建基础镜像
echo "构建基础镜像..."
cd "$(dirname "$0")"
docker build -f Dockerfile_base -t csgoj/judge2:base .

if [ $? -ne 0 ]; then
    echo "错误：构建基础镜像失败"
    exit 1
fi

echo "基础镜像构建成功：csgoj/judge2:base"

# 构建评测机镜像
echo "构建评测机镜像..."
docker build -t csgoj/judge2:latest .

if [ $? -ne 0 ]; then
    echo "错误：构建评测机镜像失败"
    exit 1
fi

echo "评测机镜像构建成功：csgoj/judge2:latest"

# 显示镜像信息
echo ""
echo "构建完成！"
echo "基础镜像：csgoj/judge2:base"
echo "评测机镜像：csgoj/judge2:latest"
echo ""
echo "可以使用以下命令启动评测机："
echo "docker run -d --name judge2 csgoj/judge2:latest"
