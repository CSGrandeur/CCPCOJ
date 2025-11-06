#!/bin/bash
# CSGOJ 交互式配置向导
# 用法: bash configure.sh
# 说明：独立工具，开发者可以单独使用，复用 parse_args.sh 的默认值和函数

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="${SCRIPT_DIR}/data/csgoj_config.cfg"

# 加载 parse_args.sh 获取默认值和函数
source "${SCRIPT_DIR}/parse_args.sh"

# ==================== 交互式配置函数 ====================

# Web 模式交互式配置（支持跳过已提供的参数）
interactive_configure_web() {
    local skip_if_provided="$1"  # 如果为 "skip"，则跳过已提供的参数
    
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  OJ Web Server 配置"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # 检查是否有已提供的参数
    local has_provided_params=false
    if [ "$skip_if_provided" = "skip" ]; then
        if [ -n "$PATH_DATA" ] || [ -n "$OJ_NAME" ] || [ -n "$CSGOJ_VERSION" ] || \
           [ -n "$WITH_MYSQL" ] || [ -n "$PASS_SQL_ROOT" ] || [ -n "$SQL_USER" ] || \
           [ -n "$PASS_SQL_USER" ] || [ -n "$PORT_OJ" ] || [ -n "$PORT_MYADMIN" ]; then
            has_provided_params=true
            echo ""
            echo "✅ 已提供的参数（将跳过询问）:"
            if [ -n "$PATH_DATA" ]; then
                echo "   - 数据目录: $PATH_DATA"
            fi
            if [ -n "$OJ_NAME" ]; then
                echo "   - OJ 名称: $OJ_NAME"
            fi
            if [ -n "$CSGOJ_VERSION" ]; then
                echo "   - Docker 镜像版本: $CSGOJ_VERSION"
            fi
            if [ -n "$WITH_MYSQL" ]; then
                echo "   - 部署 MySQL 容器: $([ "$WITH_MYSQL" = "1" ] && echo "是" || echo "否")"
            fi
            if [ -n "$SQL_HOST" ] && [ "$SQL_HOST" != "db" ]; then
                echo "   - MySQL 主机: $SQL_HOST"
            fi
            if [ -n "$PORT_OJ" ]; then
                echo "   - OJ Web 端口: $PORT_OJ"
            fi
            if [ -n "$PORT_MYADMIN" ]; then
                echo "   - PHPMyAdmin 端口: $PORT_MYADMIN"
            fi
            echo ""
        fi
    fi
    
    if [ "$has_provided_params" = true ]; then
        echo "⚠️  以下参数仍需配置:"
    else
        echo ""
        echo "💡 以下为所有配置参数，直接回车使用当前值（配置文件或默认值）"
    fi
    echo ""
    
    # 基础配置
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  基础配置"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    if [ -z "$PATH_DATA" ] || [ "$skip_if_provided" != "skip" ]; then
        read -p "数据目录 (当前: ${PATH_DATA}): " input_path_data
        PATH_DATA="${input_path_data:-$PATH_DATA}"
    fi
    
    if [ -z "$OJ_NAME" ] || [ "$skip_if_provided" != "skip" ]; then
        read -p "OJ 名称 (当前: ${OJ_NAME}): " input_oj_name
        OJ_NAME="${input_oj_name:-$OJ_NAME}"
    fi
    
    if [ -z "$CSGOJ_VERSION" ] || [ "$skip_if_provided" != "skip" ]; then
        read -p "Docker 镜像版本 (当前: ${CSGOJ_VERSION}): " input_version
        CSGOJ_VERSION="${input_version:-$CSGOJ_VERSION}"
    fi
    
    # MySQL 配置
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  MySQL 配置"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    if [ -z "$WITH_MYSQL" ] || [ "$skip_if_provided" != "skip" ]; then
        read -p "部署 MySQL 容器？(y/n, 当前: $([ "$WITH_MYSQL" = "1" ] && echo "y" || echo "n")): " input_with_mysql
        if [ -z "$input_with_mysql" ]; then
            # 保持当前值
            :
        elif [ "$input_with_mysql" = "y" ] || [ "$input_with_mysql" = "Y" ]; then
            WITH_MYSQL=1
        else
            WITH_MYSQL=0
        fi
    fi
    
    if [ "$WITH_MYSQL" = "1" ]; then
        # ===== 部署 MySQL 容器模式 =====
        echo "💡 本地 MySQL 容器配置"
        echo ""
        
        if [ -z "$PASS_SQL_ROOT" ] || [ "$skip_if_provided" != "skip" ]; then
            read -p "MySQL Root 密码 (当前: ${PASS_SQL_ROOT}): " input_pass_sql_root
            PASS_SQL_ROOT="${input_pass_sql_root:-$PASS_SQL_ROOT}"
        fi
        
        if [ -z "$SQL_USER" ] || [ "$skip_if_provided" != "skip" ]; then
            read -p "MySQL 业务用户 (当前: ${SQL_USER}): " input_sql_user
            SQL_USER="${input_sql_user:-$SQL_USER}"
        fi
        
        if [ -z "$PASS_SQL_USER" ] || [ "$skip_if_provided" != "skip" ]; then
            read -p "MySQL 业务用户密码 (当前: ${PASS_SQL_USER}): " input_pass_sql_user
            PASS_SQL_USER="${input_pass_sql_user:-$PASS_SQL_USER}"
        fi
        
        # 本地容器，SQL_HOST 固定为容器名
        SQL_HOST="db"
        
        if [ -z "$PORT_DB" ] || [ "$skip_if_provided" != "skip" ]; then
            read -p "MySQL 外部映射端口 (当前: ${PORT_DB:-20006}): " input_port_db
            PORT_DB="${input_port_db:-${PORT_DB:-20006}}"
        fi
        
        if [ -z "$PORT_OJ_DB" ] || [ "$skip_if_provided" != "skip" ]; then
            read -p "OJ Web 连接 MySQL 端口 (当前: ${PORT_OJ_DB:-3306}): " input_port_oj_db
            PORT_OJ_DB="${input_port_oj_db:-${PORT_OJ_DB:-3306}}"
        fi
    else
        # ===== 外连 MySQL 模式 =====
        echo "💡 外连 MySQL 配置"
        echo ""
        
        if [ -z "$SQL_HOST" ] || [ "$SQL_HOST" = "db" ] || [ "$skip_if_provided" != "skip" ]; then
            echo "⚠️  需要提供外部 MySQL 服务器的连接信息"
            echo ""
            
            while true; do
                read -p "MySQL 服务器地址（IP 或域名，必填）: " input_sql_host
                if [ -n "$input_sql_host" ]; then
                    SQL_HOST="$input_sql_host"
                    break
                fi
                echo "❌ MySQL 服务器地址不能为空，请重新输入"
            done
            echo ""
        fi
        
        if [ -z "$SQL_USER" ] || [ "$skip_if_provided" != "skip" ]; then
            read -p "MySQL 业务用户 (当前: ${SQL_USER}): " input_sql_user
            SQL_USER="${input_sql_user:-$SQL_USER}"
        fi
        
        if [ -z "$PASS_SQL_USER" ] || [ "$skip_if_provided" != "skip" ]; then
            read -sp "MySQL 业务用户密码 (必填): " input_pass_sql_user
            echo ""
            while [ -z "$input_pass_sql_user" ]; do
                echo "❌ MySQL 业务用户密码不能为空"
                read -sp "MySQL 业务用户密码 (必填): " input_pass_sql_user
                echo ""
            done
            PASS_SQL_USER="$input_pass_sql_user"
        fi
        
        # 外连模式不需要 PORT_DB（外部 MySQL 不需要端口映射）
        PORT_DB=""
        
        if [ -z "$PORT_OJ_DB" ] || [ "$skip_if_provided" != "skip" ]; then
            read -p "OJ Web 连接 MySQL 端口 (当前: ${PORT_OJ_DB:-3306}): " input_port_oj_db
            PORT_OJ_DB="${input_port_oj_db:-${PORT_OJ_DB:-3306}}"
        fi
    fi
    
    # OJ Web 配置
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  OJ Web 配置"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    if [ -z "$PORT_OJ" ] || [ "$skip_if_provided" != "skip" ]; then
        read -p "OJ Web 端口 (当前: ${PORT_OJ}): " input_port_oj
        PORT_OJ="${input_port_oj:-$PORT_OJ}"
    fi
    
    if [ -z "$PORT_MYADMIN" ] || [ "$skip_if_provided" != "skip" ]; then
        read -p "PHPMyAdmin 端口 (当前: ${PORT_MYADMIN}): " input_port_myadmin
        PORT_MYADMIN="${input_port_myadmin:-$PORT_MYADMIN}"
    fi
    
    if [ -z "$PASS_MYADMIN_PAGE" ] || [ "$skip_if_provided" != "skip" ]; then
        read -p "PHPMyAdmin 页面访问密码 (当前: ${PASS_MYADMIN_PAGE}): " input_pass_myadmin
        PASS_MYADMIN_PAGE="${input_pass_myadmin:-$PASS_MYADMIN_PAGE}"
    fi
    
    # OJ_CDN 使用默认值 local，不需要交互配置
    OJ_CDN="${OJ_CDN:-local}"
    
    # OJ_MODE 使用默认值 cpcsys，不需要交互配置
    OJ_MODE="${OJ_MODE:-cpcsys}"
    
    # OJ_STATUS 使用默认值 cpc，不需要交互配置
    OJ_STATUS="${OJ_STATUS:-cpc}"
    
    # SECRET_KEY 使用默认值 super_secret_oj，不需要交互配置
    SECRET_KEY="${SECRET_KEY:-super_secret_oj}"
    
    # NGINX_PORT_RANGS 使用默认值（空），不需要交互配置
    NGINX_PORT_RANGS="${NGINX_PORT_RANGS:-}"
    
    echo ""
}

# Judge 模式交互式配置（支持跳过已提供的参数）
interactive_configure_judge() {
    local skip_if_provided="$1"  # 如果为 "skip"，则跳过已提供的参数
    
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  评测机节点配置"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # 检查是否有已提供的参数
    # 注意：只检测关键参数（没有默认值的），不检测有默认值的参数（如 PATH_DATA）
    # 关键参数：CSGOJ_SERVER_BASE_URL, CSGOJ_SERVER_USERNAME, CSGOJ_SERVER_PASSWORD
    local has_provided_params=false
    if [ "$skip_if_provided" = "skip" ]; then
        if [ -n "$CSGOJ_SERVER_BASE_URL" ] || [ -n "$CSGOJ_SERVER_USERNAME" ] || \
           [ -n "$CSGOJ_SERVER_PASSWORD" ]; then
            has_provided_params=true
            echo ""
            echo "✅ 已提供的参数（将跳过询问）:"
            if [ -n "$CSGOJ_SERVER_BASE_URL" ]; then
                echo "   - OJ Web 服务器地址: $CSGOJ_SERVER_BASE_URL"
            fi
            if [ -n "$CSGOJ_SERVER_USERNAME" ]; then
                echo "   - 评测机用户名: $CSGOJ_SERVER_USERNAME"
            fi
            if [ -n "$CSGOJ_SERVER_PASSWORD" ]; then
                echo "   - 评测机密码: [已提供]"
            fi
            # PATH_DATA 有默认值，不显示为"已提供"
            echo ""
        fi
    fi
    
    if [ "$has_provided_params" = true ]; then
        echo "⚠️  以下参数仍需填写:"
    else
        echo "⚠️  注意：请使用评测机容器能访问到的 OJ Web 服务器地址"
        echo "   不要使用 localhost 或 127.0.0.1（评测机容器内无法访问宿主机 localhost）"
        echo "   如果评测机和 Web 在同一台机器，使用服务器的内网或外网 IP"
    fi
    echo ""
    
    if [ -z "$CSGOJ_SERVER_BASE_URL" ] || [ "$skip_if_provided" != "skip" ]; then
        while true; do
            read -p "OJ Web 服务器地址 (必需，例如: http://192.168.1.100:20080): " input_base_url
            if [ -n "$input_base_url" ]; then
                CSGOJ_SERVER_BASE_URL="$input_base_url"
                break
            fi
            echo "❌ 错误: 服务器地址不能为空，请重新输入"
        done
        echo ""
    fi
    
    if [ -z "$CSGOJ_SERVER_USERNAME" ] || [ "$skip_if_provided" != "skip" ]; then
        read -p "评测机用户名 (默认: judger): " input_username
        CSGOJ_SERVER_USERNAME="${input_username:-judger}"
        echo ""
    fi
    
    if [ -z "$CSGOJ_SERVER_PASSWORD" ] || [ "$skip_if_provided" != "skip" ]; then
        while true; do
            read -sp "评测机密码 (必需): " input_password
            echo ""
            if [ -n "$input_password" ]; then
                CSGOJ_SERVER_PASSWORD="$input_password"
                break
            fi
            echo "❌ 错误: 密码不能为空，请重新输入"
        done
        echo ""
    fi
    
    if [ -z "$PATH_DATA" ] || [ "$skip_if_provided" != "skip" ]; then
        read -p "数据目录 (默认: $PATH_DATA): " input_path_data
        PATH_DATA="${input_path_data:-$PATH_DATA}"
        echo ""
    fi
    
    if [ -z "$JUDGE_POD_COUNT" ] || [ "$JUDGE_POD_COUNT" = "1" ] || [ "$skip_if_provided" != "skip" ]; then
        read -p "启动 pod 数量 (默认: 1): " input_pod_count
        JUDGE_POD_COUNT="${input_pod_count:-1}"
        echo ""
    fi
    
    echo "💡 资源将自动分配（CPU 和内存会根据系统资源智能计算）"
    echo ""
}

# ==================== 主执行逻辑 ====================

# 如果作为独立脚本执行
if [ "${BASH_SOURCE[0]}" = "${0}" ]; then
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  CSGOJ 部署配置向导"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    # 部署内容选择
    echo "请选择部署内容："
    echo "  1) OJ Web Server（Web 服务器，包含 MySQL、Nginx、PHP 等服务）"
    echo "  2) Judge Node（评测机节点，仅启动评测服务）"
    echo ""
    read -p "请输入选项 (1/2, 默认: 1): " deploy_mode
    deploy_mode=${deploy_mode:-1}
    
    if [ "$deploy_mode" != "1" ] && [ "$deploy_mode" != "2" ]; then
        echo "❌ 无效选项"
        exit 1
    fi
    
    # 调用相应的配置函数（独立执行模式，不跳过已提供的参数）
    if [ "$deploy_mode" = "1" ]; then
        interactive_configure_web ""
    else
        interactive_configure_judge ""
    fi
    
    # 写入配置文件
    if [ "$deploy_mode" = "1" ]; then
        write_full_config_file "$CONFIG_FILE" "web"
    else
        write_full_config_file "$CONFIG_FILE" "judge"
    fi
    
    # 显示完成信息
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  配置文件已生成"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "📁 配置文件路径: $CONFIG_FILE"
    echo ""
    echo "💡 使用配置文件启动："
    if [ "$deploy_mode" = "1" ]; then
        echo "  bash csgoj_deploy.sh web"
        echo "  或直接: bash csgoj_deploy.sh"
    else
        echo "  bash csgoj_deploy.sh judge"
    fi
    echo ""
    echo "提示: csgoj_deploy.sh 是统一的部署入口，支持 OJ Web Server 和评测机节点两种模式"
    echo ""
fi
