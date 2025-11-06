#!/bin/bash
# 构建CSGOJ judge2评测机镜像

echo "构建CSGOJ judge2评测机镜像..."

# 构建评测机镜像
docker build -t csgrandeur/ccpcoj-judge2:latest .

if [ $? -eq 0 ]; then
    echo "评测机镜像构建成功：csgrandeur/ccpcoj-judge2:latest"
else
    echo "评测机镜像构建失败"
    exit 1
fi
