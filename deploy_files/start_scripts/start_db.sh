#!/bin/bash
# 启动 MySQL 数据库容器
# 用法: bash start_db.sh [选项...]
# 使用 --help 查看所有可用选项

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/parse_args.sh"
parse_args "$@"

start_db() {
    # 检查是否启用 MySQL
    if [ "$WITH_MYSQL" != "1" ]; then
        echo "⚠️  WITH_MYSQL=0，跳过 MySQL 容器启动"
        return 0
    fi
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  初始化 MySQL 数据库"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    if [ -n "$(docker ps -aq -f name=^db$)" ]; then
        echo "✅ MySQL 容器已存在"
    else
        # 创建必要目录
        mkdir -p "$PATH_DATA/var/mysql/mysql_config"
        mkdir -p "$PATH_DATA/var/mysql/mysql_init"
        
        # MySQL 配置文件
        cat > "$PATH_DATA/var/mysql/mysql_config/oj_my.cnf" <<EOF
[mysqld]
max_connections=2000
mysqlx_max_connections=800
default-time-zone='+8:00'
EOF
        chmod 644 "$PATH_DATA/var/mysql/mysql_config/oj_my.cnf"
        
        # 初始化 SQL
        echo "GRANT ALL PRIVILEGES ON \`${SQL_USER}_%\`.* TO '${SQL_USER}'@'%';" > "$PATH_DATA/var/mysql/mysql_init/init.sql"
        
        # 启动 MySQL 容器
        docker run -dit $LINK_LOCAL \
            --name db \
            -p "$PORT_DB:3306" \
            -v "$PATH_DATA/var/mysql/mysql_data:/var/lib/mysql" \
            -v "$PATH_DATA/var/mysql/mysql_config:/etc/mysql/conf.d" \
            -v "$PATH_DATA/var/mysql/mysql_init:/docker-entrypoint-initdb.d" \
            -e MYSQL_ROOT_PASSWORD="$PASS_SQL_ROOT" \
            -e MYSQL_USER="$SQL_USER" \
            -e MYSQL_PASSWORD="$PASS_SQL_USER" \
            --restart=unless-stopped \
            mysql:8.0.32 \
            --default-authentication-plugin=mysql_native_password >/dev/null 2>&1
        
        echo "✅ MySQL 容器启动成功"
    fi
    echo ""
}

start_db
