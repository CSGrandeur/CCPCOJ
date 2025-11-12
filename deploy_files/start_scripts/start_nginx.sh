#!/bin/bash
# 启动 Nginx 容器
# 用法: bash start_nginx.sh [选项...]
# 使用 --help 查看所有可用选项

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/parse_args.sh"
parse_args "$@"

start_nginx() {
    # Nginx 镜像版本常量
    local NGINX_IMAGE_VERSION="1.29.3-alpine"
    local NGINX_IMAGE="nginx:${NGINX_IMAGE_VERSION}"
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  初始化 Nginx"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    if [ -n "$(docker ps -aq -f name=^nginx-server$)" ]; then
        echo "✅ Nginx 容器已存在"
    else
        # 端口映射配置
        # 如果 NGINX_PORT_RANGES 为空或未设置，使用默认端口映射
        if [ -z "${NGINX_PORT_RANGES:-}" ]; then
            NGINX_PORT_RANGES="-p $PORT_OJ:$PORT_OJ -p $PORT_MYADMIN:$PORT_MYADMIN"
        fi
        
        # 开发模式挂载
        PUBLIC_MOUNT=""
        if [ "$CSGOJ_DEV" = "1" ]; then
            # 计算项目根目录（兼容独立脚本和合并脚本，开发者测试和用户部署两种场景）
            local project_dir=""
            
            # 方法1: 如果 SCRIPT_DIR 已定义，使用它（独立脚本场景：start_scripts -> deploy_files -> project_root）
            if [ -n "$SCRIPT_DIR" ]; then
                local temp_dir=$(cd "$SCRIPT_DIR/../.." 2>/dev/null && pwd || echo "")
                if [ -n "$temp_dir" ] && [ -d "$temp_dir/ojweb/public" ]; then
                    project_dir="$temp_dir"
                fi
            fi
            
            # 方法2: 从脚本位置计算（兼容两种场景：脚本在 start_scripts 或项目根目录）
            if [ -z "$project_dir" ] || [ ! -d "$project_dir/ojweb/public" ]; then
                local script_dir=""
                # 尝试使用 BASH_SOURCE（更可靠）
                if [ -n "${BASH_SOURCE[0]}" ]; then
                    script_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" 2>/dev/null && pwd || echo "")
                fi
                # 如果 BASH_SOURCE 不可用，尝试使用 $0
                if [ -z "$script_dir" ] && [ -n "$0" ]; then
                    script_dir=$(cd "$(dirname "$0")" 2>/dev/null && pwd || echo "")
                fi
                
                if [ -n "$script_dir" ]; then
                    # 场景1: 检查脚本所在目录是否是项目根目录（用户部署场景）
                    if [ -d "$script_dir/ojweb/public" ]; then
                        project_dir="$script_dir"
                    # 场景2: 从脚本目录向上两级（开发者测试场景：start_scripts -> deploy_files -> project_root）
                    else
                        local temp_dir=$(cd "$script_dir/../.." 2>/dev/null && pwd || echo "")
                        if [ -n "$temp_dir" ] && [ -d "$temp_dir/ojweb/public" ]; then
                            project_dir="$temp_dir"
                        fi
                    fi
                fi
            fi
            
            # 方法3: 从当前工作目录向上查找（最后的后备方案）
            if [ -z "$project_dir" ] || [ ! -d "$project_dir/ojweb/public" ]; then
                local current_dir=$(pwd)
                local search_dir="$current_dir"
                # 向上查找最多5级，直到找到包含 ojweb/public 的目录
                for i in {1..5}; do
                    if [ -d "$search_dir/ojweb/public" ]; then
                        project_dir="$search_dir"
                        break
                    fi
                    search_dir=$(cd "$search_dir/.." 2>/dev/null && pwd || echo "")
                    if [ "$search_dir" = "/" ] || [ -z "$search_dir" ]; then
                        break
                    fi
                done
            fi
            
            if [ -n "$project_dir" ] && [ -d "$project_dir/ojweb/public" ]; then
                PUBLIC_MOUNT="-v $project_dir/ojweb/public:/var/www/baseoj/public"
                echo "🔧 开发模式：已启用源码目录挂载 ($project_dir/ojweb/public)"
            fi
        fi
        
        # 确保必要目录存在
        mkdir -p "$PATH_DATA/nginx/nginx_conf.d"
        mkdir -p "$PATH_DATA/nginx/attach"
        mkdir -p "$PATH_DATA/var/log/nginx"
        mkdir -p "$PATH_DATA/dataspace"
        mkdir -p "$PATH_DATA/var/www"
        
        # 启动 Nginx 容器（官方镜像，docker 会自动 pull）
        # 使用数组构建命令，确保参数正确传递
        local docker_run_args=(
            "run"
            "--name" "nginx-server"
        )
        echo $LINK_LOCAL
        # 添加 LINK_LOCAL 参数（可能包含多个参数，需要展开）
        if [ -n "${LINK_LOCAL:-}" ]; then
            # 将 LINK_LOCAL 按空格分割并添加到数组
            read -ra link_local_args <<< "$LINK_LOCAL"
            docker_run_args+=("${link_local_args[@]}")
        fi
        
        # 添加端口映射（如果 NGINX_PORT_RANGES 不为空）
        if [ -n "${NGINX_PORT_RANGES:-}" ]; then
            # 将 NGINX_PORT_RANGES 按空格分割并添加到数组
            read -ra port_range_args <<< "$NGINX_PORT_RANGES"
            docker_run_args+=("${port_range_args[@]}")
        fi
        
        # 添加卷挂载和其他参数
        docker_run_args+=(
            "-v" "$PATH_DATA/var/www:/var/www"
        )
        if [ -n "${PUBLIC_MOUNT:-}" ]; then
            read -ra public_mount_args <<< "$PUBLIC_MOUNT"
            docker_run_args+=("${public_mount_args[@]}")
        fi
        docker_run_args+=(
            "-v" "$PATH_DATA/dataspace:$PATH_DATA/dataspace"
            "-v" "$PATH_DATA/var/log/nginx:/var/log/nginx"
            "-v" "$PATH_DATA/nginx/nginx_conf.d:/etc/nginx/conf.d"
            "-v" "$PATH_DATA/nginx/attach:/etc/nginx/attach"
            "--restart=unless-stopped"
            "-d"
            "$NGINX_IMAGE"
        )
        
        # 执行命令并捕获输出
        local docker_run_output
        docker_run_output=$(docker "${docker_run_args[@]}" 2>&1)
        local docker_run_exit_code=$?
        
        # 等待一下，然后检查容器是否真的在运行
        sleep 1
        if ! docker ps --format "{{.Names}}" | grep -q "^nginx-server$"; then
            echo "❌ Nginx 容器启动失败"
            echo "   docker run 输出："
            echo "$docker_run_output" | head -10
            echo ""
            # 检查容器是否存在但已停止
            if docker ps -a --format "{{.Names}}" | grep -q "^nginx-server$"; then
                echo "   容器已创建但未运行，查看日志："
                docker logs "nginx-server" 2>&1 | tail -15
                echo ""
                echo "   尝试清理失败的容器..."
                docker rm -f "nginx-server" >/dev/null 2>&1 || true
            fi
            echo "   提示："
            echo "   - 检查镜像是否存在: docker images | grep nginx"
            echo "   - 检查端口是否被占用: netstat -tuln | grep -E ':$PORT_OJ|:$PORT_MYADMIN'"
            echo "   - 检查目录权限: $PATH_DATA"
            return 1
        fi
        
        echo "✅ Nginx 容器启动成功"
    fi
    echo ""
}

start_nginx
