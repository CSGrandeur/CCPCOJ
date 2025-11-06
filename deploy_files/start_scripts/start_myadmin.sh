#!/bin/bash
# 启动 PHPMyAdmin 容器
# 用法: bash start_myadmin.sh [选项...]
# 使用 --help 查看所有可用选项

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/parse_args.sh"
parse_args "$@"

start_myadmin() {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  初始化 PHPMyAdmin"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    if [ -n "$(docker ps -aq -f name=^myadmin$)" ]; then
        echo "✅ PHPMyAdmin 容器已存在"
    else
        # 创建必要目录
        mkdir -p "$PATH_DATA/nginx/attach"
        mkdir -p "$PATH_DATA/nginx/nginx_conf.d"
        
        # 生成 HTTP Basic Auth 密码文件
        echo "admin:$(openssl passwd -apr1 "$PASS_MYADMIN_PAGE")" > "$PATH_DATA/nginx/attach/pass"
        
        # 生成 Nginx 配置
        cat > "$PATH_DATA/nginx/nginx_conf.d/myadmin.conf" <<EOF
server {
    listen $PORT_MYADMIN;
    location / {
        proxy_redirect          off;
        proxy_set_header        Host      \$http_host;
        proxy_set_header        X-Real-IP \$remote_addr;
        proxy_set_header        X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_pass              http://myadmin;
        auth_basic              "验证后访问";
        auth_basic_user_file    "/etc/nginx/attach/pass";
    }
    access_log /var/log/nginx/myadmin_access.log;
}
EOF
        
        # 启动 PHPMyAdmin 容器
        docker run -dit $LINK_LOCAL \
            -e PMA_HOST="$SQL_HOST" \
            --name myadmin \
            --restart=unless-stopped \
            phpmyadmin:5.2.1 >/dev/null 2>&1
        
        echo "✅ PHPMyAdmin 容器启动成功"
    fi
    echo ""
}

start_myadmin
