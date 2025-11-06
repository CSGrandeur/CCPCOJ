#!/bin/bash
# 安装 Docker CE
# 用法: bash install_docker.sh

set -e

install_docker() {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  检查 Docker 安装"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    if command -v docker &> /dev/null; then
        echo "✅ Docker 已安装"
        docker --version
        return 0
    fi
    
    echo "📦 开始安装 Docker CE..."
    
    # 移除旧版本
    for pkg in docker.io docker-doc docker-compose podman-docker containerd runc; do
        sudo apt-get remove -y "$pkg" 2>/dev/null || true
    done
    
    # 安装依赖
    sudo apt-get update
    sudo apt-get install -y ca-certificates curl gnupg
    
    # 添加 Docker 官方 GPG 密钥
    sudo install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://mirrors.aliyun.com/docker-ce/linux/ubuntu/gpg | \
        sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    sudo chmod a+r /etc/apt/keyrings/docker.gpg
    
    # 添加 Docker 仓库
    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
      https://mirrors.aliyun.com/docker-ce/linux/ubuntu \
      $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
      sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
    
    # 安装 Docker
    sudo apt-get update
    sudo apt-get install -y \
        docker-ce \
        docker-ce-cli \
        containerd.io \
        docker-buildx-plugin \
        docker-compose-plugin
    
    # 配置用户组
    sudo groupadd docker 2>/dev/null || true
    sudo gpasswd -a "$USER" docker
    
    # 配置镜像加速
    sudo mkdir -p /etc/docker
    cat <<EOF | sudo tee /etc/docker/daemon.json > /dev/null
{
    "registry-mirrors": [
        "https://docker.1ms.run",
        "https://dytt.online",
        "https://docker-0.unsee.tech",
        "https://lispy.org",
        "https://docker.xiaogenban1993.com",
        "https://666860.xyz",
        "https://hub.rat.dev",
        "https://docker.m.daocloud.io",
        "https://demo.52013120.xyz",
        "https://proxy.vvvv.ee",
        "https://registry.cyou",
        "https://dockerpull.cn"
    ]
}
EOF
    
    # 重启 Docker 服务
    sudo service docker restart
    
    echo ""
    echo "✅ Docker 安装完成"
    echo ""
    echo "⚠️  注意: 当前 shell 会话需要重新登录或执行 'newgrp docker' 才能使用 Docker"
    echo "   或者重新打开终端窗口"
    echo ""
}

install_docker
