#!/bin/bash
# 获取脚本所在目录的绝对路径
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# 获取项目根目录（脚本的上一级目录）
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

IMAGE_NAME="csgoj/judge2:latest"

echo "脚本目录: $SCRIPT_DIR"
echo "项目目录: $PROJECT_DIR"
echo ""
echo "正在启动调试容器..."

# 校验镜像是否存在
if ! docker image inspect "$IMAGE_NAME" >/dev/null 2>&1; then
  echo "❌ 镜像未找到：$IMAGE_NAME"
  exit 1
fi

docker run -dit \
  --name testjudge \
  --privileged \
  --security-opt seccomp=unconfined \
  --cgroupns=host \
  --pid=host \
  --memory=4g \
  --cpus=4 \
  --shm-size 1g \
  --workdir /core \
  -v "$PROJECT_DIR"/core:/core \
  -v "$PROJECT_DIR"/core/judge:/judge \
  -v /etc/localtime:/etc/localtime:ro \
  "$IMAGE_NAME" \
  /bin/bash

if [ $? -ne 0 ]; then
  echo ""
  echo "❌ 容器启动失败！"
  echo "请确认镜像 $IMAGE_NAME 存在"
  exit 1
fi

echo ""
echo "✅ 容器启动成功！"
echo ""
echo "📋 调试命令："
echo "  进入容器: docker exec -it testjudge /bin/bash"
echo "  查看日志: docker logs testjudge"
echo "  停止容器: docker stop testjudge"
echo "  删除容器: docker rm testjudge"
echo ""
echo "💡 提示："
echo "  - 容器工作目录已设置为 /core"
echo "  - SHM 已启用 (1GB)"
echo "  - 如需在系统安装库，使用: pip3 install --break-system-packages <库名>"
echo "  - 进入容器后，可以直接运行 python3 judge_client.py <题号> debug 进行调试"
echo ""
