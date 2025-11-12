#!/bin/bash
# 启动 OJ Web 容器
# 用法: bash start_ojweb.sh [选项...]
# 使用 --help 查看所有可用选项
# 注意: 管理员账号和评测机账号密码由 Web 端引导设置

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/parse_args.sh"
parse_args "$@"

start_ojweb() {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  初始化 OJ Web 服务"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # 创建必要目录
    if [ ! -d "$PATH_DATA/var/www/$OJ_NAME" ]; then
        mkdir -p "$PATH_DATA/var/www/$OJ_NAME"
    fi
    
    if [ -n "$(docker ps -aq -f name=^php-$OJ_NAME$)" ]; then
        echo "✅ OJ Web 容器已存在"
    else
        # 确定 Web 镜像版本（先尝试 latest，失败后 fallback 到本地镜像）
        local web_image=""
        local target_image="csgrandeur/ccpcoj-web2:$CSGOJ_VERSION"
        
        # 首先尝试拉取指定版本
        echo "📦 尝试使用镜像: $target_image"
        # 先检查本地是否已存在
        if docker image inspect "$target_image" >/dev/null 2>&1 && \
           docker run --rm --entrypoint /bin/sh "$target_image" -c "echo test" >/dev/null 2>&1; then
            web_image="$target_image"
            echo "✅ 使用镜像: $target_image (本地存在)"
        else
            # 本地不存在，尝试拉取（显示进度）
            echo "📥 正在拉取镜像: $target_image"
            echo "   请稍候，这可能需要一些时间..."
            echo ""
            if docker pull "$target_image"; then
                web_image="$target_image"
                echo ""
                echo "✅ 镜像拉取成功: $target_image"
            else
                echo ""
                echo "⚠️  镜像 $target_image 拉取失败，尝试使用本地镜像"
                # Fallback: 查找本地有效镜像
                local found_local=false
                for version in "1.4.3" "1.4.2" "1.4.1"; do
                    local check_image="csgrandeur/ccpcoj-web2:$version"
                    if docker image inspect "$check_image" >/dev/null 2>&1; then
                        # 尝试运行一个简单命令来验证镜像是否可用
                        if docker run --rm --entrypoint /bin/sh "$check_image" -c "echo test" >/dev/null 2>&1; then
                            web_image="$check_image"
                            found_local=true
                            echo "⚠️  使用本地镜像: $web_image (fallback from $target_image)"
                            break
                        fi
                    fi
                done
                
                if [ "$found_local" = false ]; then
                    echo "❌ 无法找到有效的 OJ Web 镜像"
                    echo "   请检查网络连接或手动拉取: docker pull csgrandeur/ccpcoj-web2:$CSGOJ_VERSION"
                    return 1
                fi
            fi
        fi
        
        # 开发模式挂载
        WEB_MOUNT_ARGS=()
        if [ "$CSGOJ_DEV" = "1" ]; then
            # 计算项目根目录（兼容独立脚本和合并脚本，开发者测试和用户部署两种场景）
            local project_dir=""
            
            # 方法1: 如果 SCRIPT_DIR 已定义，使用它（独立脚本场景：start_scripts -> deploy_files -> project_root）
            if [ -n "$SCRIPT_DIR" ]; then
                local temp_dir=$(cd "$SCRIPT_DIR/../.." 2>/dev/null && pwd || echo "")
                if [ -n "$temp_dir" ] && [ -d "$temp_dir/ojweb" ]; then
                    project_dir="$temp_dir"
                fi
            fi
            
            # 方法2: 从脚本位置计算（兼容两种场景：脚本在 start_scripts 或项目根目录）
            if [ -z "$project_dir" ] || [ ! -d "$project_dir/ojweb" ]; then
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
                    if [ -d "$script_dir/ojweb" ]; then
                        project_dir="$script_dir"
                    # 场景2: 从脚本目录向上两级（开发者测试场景：start_scripts -> deploy_files -> project_root）
                    else
                        local temp_dir=$(cd "$script_dir/../.." 2>/dev/null && pwd || echo "")
                        if [ -n "$temp_dir" ] && [ -d "$temp_dir/ojweb" ]; then
                            project_dir="$temp_dir"
                        fi
                    fi
                fi
            fi
            
            # 方法3: 从当前工作目录向上查找（最后的后备方案）
            if [ -z "$project_dir" ] || [ ! -d "$project_dir/ojweb" ]; then
                local current_dir=$(pwd)
                local search_dir="$current_dir"
                # 向上查找最多5级，直到找到包含 ojweb 的目录
                for i in {1..5}; do
                    if [ -d "$search_dir/ojweb" ]; then
                        project_dir="$search_dir"
                        break
                    fi
                    search_dir=$(cd "$search_dir/.." 2>/dev/null && pwd || echo "")
                    if [ "$search_dir" = "/" ] || [ -z "$search_dir" ]; then
                        break
                    fi
                done
            fi
            
            if [ -n "$project_dir" ] && [ -d "$project_dir/ojweb" ]; then
                WEB_MOUNT_ARGS=(
                    -v "$project_dir/ojweb/application:/ojweb/application"
                    -v "$project_dir/ojweb/public:/ojweb/public"
                    -v "$project_dir/ojweb/extend:/ojweb/extend"
                    -v "$project_dir/ojweb/vendor:/ojweb/vendor"
                    -v "$project_dir/ojweb/thinkphp:/ojweb/thinkphp"
                    -v "$project_dir/ojweb/entrypoint.sh:/ojweb/entrypoint.sh"
                    -v "$project_dir/ojweb/dbinit.php:/ojweb/dbinit.php"
                    -v "$project_dir/deploy_files/SQL/:/SQL/"
                )
                echo "🔧 开发模式：已启用源码目录挂载"
            fi
        fi
        
        # 确保必要目录存在
        mkdir -p "$PATH_DATA/nginx/nginx_conf.d"
        mkdir -p "$PATH_DATA/var/www/$OJ_NAME"
        mkdir -p "$PATH_DATA/var/data/judge-$BELONG_TO"
        
        # 启动 OJ Web 容器
        local docker_run_output
        docker_run_output=$(docker run --pull=never -dit $LINK_LOCAL \
            --name "php-$OJ_NAME" \
            -e DB_HOSTNAME="$SQL_HOST" \
            -e DB_DATABASE="${SQL_USER}_${BELONG_TO}" \
            -e DB_USERNAME="$SQL_USER" \
            -e DB_PASSWORD="$PASS_SQL_USER" \
            -e DB_HOSTPORT="$PORT_OJ_DB" \
            -e PORT_OJ="$PORT_OJ" \
            -e OJ_SESSION="$OJ_NAME" \
            -e OJ_NAME="$OJ_NAME" \
            -e OJ_CDN="$OJ_CDN" \
            -e OJ_MODE="$OJ_MODE" \
            -e OJ_STATUS="$OJ_STATUS" \
            -e OJ_STATIC="/var/www/public/$BELONG_TO" \
            -e OJ_UPDATE_STATIC="$OJ_UPDATE_STATIC" \
            -e BELONG_TO="$BELONG_TO" \
            -v "$PATH_DATA/var/www:/var/www" \
            "${WEB_MOUNT_ARGS[@]}" \
            -v "$PATH_DATA/var/data/judge-$BELONG_TO:/home/judge" \
            -v "$PATH_DATA/nginx/nginx_conf.d:/etc/nginx/conf.d" \
            --restart=unless-stopped \
            "$web_image" 2>&1)
        local docker_run_exit_code=$?
        # 等待一下，然后检查容器是否真的在运行
        sleep 1
        if ! docker ps --format "{{.Names}}" | grep -q "^php-$OJ_NAME$"; then
            echo "❌ OJ Web 容器启动失败"
            echo "   docker run 输出："
            echo "$docker_run_output" | head -10
            echo ""
            # 检查容器是否存在但已停止
            if docker ps -a --format "{{.Names}}" | grep -q "^php-$OJ_NAME$"; then
                echo "   容器已创建但未运行，查看日志："
                docker logs "php-$OJ_NAME" 2>&1 | tail -15
                echo ""
                echo "   尝试清理失败的容器..."
                docker rm -f "php-$OJ_NAME" >/dev/null 2>&1 || true
            fi
            echo "   提示："
            echo "   - 检查镜像是否存在: docker images | grep ccpcoj-web"
            echo "   - 检查参数是否正确: SQL_HOST=$SQL_HOST, PORT_OJ_DB=$PORT_OJ_DB"
            echo "   - 检查目录权限: $PATH_DATA"
            return 1
        fi
        
        # 重启 Nginx 以加载新配置（如果存在）
        docker restart nginx-server >/dev/null 2>&1 || true
        
        echo "✅ OJ Web 容器启动成功"
    fi
    echo ""
}

start_ojweb
