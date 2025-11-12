#!/bin/bash
# 构建CSGOJ judge2评测机基础镜像

echo "构建CSGOJ judge2评测机基础镜像..."

# 构建基础镜像
docker build --network=host -f Dockerfile_base -t csgrandeur/judge2_base:latest .

if [ $? -eq 0 ]; then
    echo "基础镜像构建成功：csgrandeur/judge2_base:latest"
else
    echo "基础镜像构建失败"
    exit 1
fi
