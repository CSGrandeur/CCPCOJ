#!/bin/bash
# CSGOJ 统一部署脚本
# 此脚本由 build_deploy.sh 自动生成，包含所有部署逻辑
# 生成时间: 2025-11-01 22:44:04
# 参数解析和配置管理脚本
# 职责：
#   1. 定义默认配置值
#   2. 解析命令行参数
#   3. 加载配置文件
# 注意：不包含副作用操作（如创建目录、创建网络），这些由调用方控制

# ==================== 默认值定义 ====================
PASSWORD_DEFAULT=987654321
CONFIG_LOG='./data/config_log'
DEFAULT_CONFIG='./data/csgoj_config.cfg'

# 基础配置
CONFIG_FILE=0
CSGOJ_VERSION=latest
PATH_DATA="${PATH_DATA:-$(pwd)/data/csgoj_data}"
NONINTERACTIVE=false
IGNORE_CONFIG=false

# 密码配置
# 注意：PASS_ADMIN 和 PASS_JUDGER 已移除，由 Web 端引导设置
PASS_SQL_ROOT=$PASSWORD_DEFAULT
PASS_SQL_USER=$PASSWORD_DEFAULT
PASS_MYADMIN_PAGE=$PASSWORD_DEFAULT

# 端口配置
PORT_OJ=20080
PORT_MYADMIN=20050
PORT_DB=20006
PORT_OJ_DB=3306

# 数据库配置
SQL_USER='csgcpc'
SQL_HOST='db'
WITH_MYSQL=1

# OJ 配置
OJ_NAME='ccpc'
OJ_CDN='local'
OJ_MODE='cpcsys'
OJ_STATUS='cpc'
OJ_OPEN_OI=0
OJ_UPDATE_STATIC=0
BELONG_TO=0

# 其他配置
NGINX_PORT_RANGES=''
SECRET_KEY='super_secret_oj'
DOCKER_PULL_NEW=1
DOCKER_NET_NAME="csgoj_net"
LINK_LOCAL="--network $DOCKER_NET_NAME"

# Judge 模式配置（默认值）
CSGOJ_SERVER_BASE_URL=''
CSGOJ_SERVER_USERNAME=''
CSGOJ_SERVER_PASSWORD=''
JUDGE_POD_COUNT=1
RESTART_ALL=false
REBUILD_ALL=false
REMOVE_ALL=false

# ==================== Help 函数 ====================
show_help() {
    cat << EOF
CSGOJ 部署参数说明

使用方法:
  bash <script> [选项...]

基础配置:
  --CONFIG_FILE=<文件>           指定配置文件路径（优先级高于默认配置文件）
  --CSGOJ_VERSION=<版本>         Docker 镜像版本标签（默认: latest）
  --PATH_DATA=<路径>             数据目录绝对路径（默认: \$(pwd)/data/csgoj_data）
  --noninteractive, --no-interactive  非交互模式（参数缺失时使用默认值或报错）
  --ignore-config, --ignore-cfg  忽略默认配置文件（./data/csgoj_config.cfg），仅使用命令行参数和默认值

数据库配置:
  --PASS_SQL_ROOT=<密码>         MySQL root 用户密码（默认: 987654321）
  --PASS_SQL_USER=<密码>         MySQL 业务用户密码（默认: 987654321）
  --SQL_USER=<用户名>            MySQL 业务用户名（默认: csgcpc）
  --SQL_HOST=<主机>              MySQL 主机地址（默认: db）
  --WITH_MYSQL=<0|1>             是否部署 MySQL 容器（默认: 1）
  --PORT_DB=<端口>               MySQL 外部映射端口（默认: 20006）
  --PORT_OJ_DB=<端口>             OJ Web 连接 MySQL 的端口（默认: 3306）

OJ 配置:
  --OJ_NAME=<名称>               OJ 名称（默认: ccpc）
  --OJ_CDN=<local|jsdelivr>      静态资源 CDN 类型（默认: local）
  --OJ_MODE=<online|cpcsys>      OJ 运行模式（默认: cpcsys）
  --OJ_STATUS=<状态>             OJ 状态标识（默认: cpc）
  --OJ_OPEN_OI=<0|1>             [已废弃] OI 模式开关（默认: 0）
  --OJ_UPDATE_STATIC=<0|1>       部署时是否更新静态资源（默认: 0）
  --BELONG_TO=<名称>             所属 OJ 名称，用于多实例共享（默认: 等于 OJ_NAME）

端口配置:
  --PORT_OJ=<端口>               OJ Web 服务端口（默认: 20080）
  --PORT_MYADMIN=<端口>          PHPMyAdmin Web 端口（默认: 20050）
  --NGINX_PORT_RANGES=<映射>      额外的 Nginx 端口映射，如 "-p 80-89:80-89"

其他配置:
  --PASS_MYADMIN_PAGE=<密码>     PHPMyAdmin 页面访问密码（默认: 987654321）
  --SECRET_KEY=<密钥>            系统加密密钥（默认: super_secret_oj）
  --DOCKER_PULL_NEW=<0|1>        是否拉取最新镜像（默认: 1，断网时设为 0）
  --DOCKER_NET_NAME=<名称>       Docker 网络名称（默认: csgoj_net）
  --LINK_LOCAL=<参数>            Docker 网络连接参数（默认: --network csgoj_net）

注意:
  • 管理员账号密码：首次访问 Web 页面时通过引导界面设置
  • 评测机账号密码：首次访问 Web 页面时通过引导界面设置
  • 参数优先级：命令行参数 > 配置文件 > 默认值
  • 使用 --CONFIG_FILE 可以批量设置参数，避免命令行过长
  • 默认交互模式：必要参数缺失时进入交互式补全
  • 非交互模式：使用 --noninteractive 时，参数缺失直接报错或使用默认值

示例:
  # OJ Web Server 部署（使用默认配置）
  bash deploy_dev.sh

  # OJ Web Server 部署（使用配置文件）
  bash deploy_dev.sh --CONFIG_FILE=my_config.cfg

  # OJ Web Server 部署（自定义参数）
  bash deploy_dev.sh --OJ_NAME=test --PATH_DATA=/data/csgoj --PORT_OJ=8080

  # 评测机节点部署
  bash deploy_dev.sh --CSGOJ_SERVER_BASE_URL=http://192.168.1.100:20080 --CSGOJ_SERVER_PASSWORD=123456

  提示: deploy_dev.sh 会根据参数自动判断部署内容（OJ Web Server 或 Judge Node）

EOF
}

# ==================== 参数解析函数 ====================
parse_args() {
    # 检查 --help 参数
    for arg in "$@"; do
        if [ "$arg" = "--help" ] || [ "$arg" = "-h" ] || [ "$arg" = "-H" ]; then
            show_help
            exit 0
        fi
    done
    
    # 先预解析 --CONFIG_FILE 和 --ignore-config 参数（这些影响配置文件的加载）
    # 因为需要知道是否忽略配置文件，以及使用哪个配置文件
    local pre_config_file=""
    local pre_ignore_config=false
    for arg in "$@"; do
        if [[ "$arg" == --CONFIG_FILE=* ]]; then
            pre_config_file="${arg#*=}"
        elif [[ "$arg" == --CONFIG_FILE ]]; then
            # 如果是 --CONFIG_FILE value 格式，会在后面完整解析时处理
            :
        elif [ "$arg" = "--ignore-config" ] || [ "$arg" = "--ignore-cfg" ]; then
            pre_ignore_config=true
        fi
    done
    
    # 如果指定了 CONFIG_FILE，先设置它
    if [ -n "$pre_config_file" ]; then
        CONFIG_FILE="$pre_config_file"
    fi
    if [ "$pre_ignore_config" = true ]; then
        IGNORE_CONFIG=true
    fi
    
    # 1. 先加载配置文件（低优先级）
    # 这样后续的命令行参数可以覆盖配置文件中的值
    load_config_files
    
    # 2. 再解析命令行参数（高优先级，覆盖配置文件）
    local shortopts="h"
    local longopts="
        help,
        CONFIG_FILE:,
        CSGOJ_VERSION:,
        PATH_DATA:,
        PASS_SQL_ROOT:,
        PASS_SQL_USER:,
        PASS_MYADMIN_PAGE:,
        SQL_USER:,
        SQL_HOST:,
        WITH_MYSQL:,
        OJ_NAME:,
        OJ_CDN:,
        OJ_MODE:,
        OJ_STATUS:,
        OJ_OPEN_OI:,
        OJ_UPDATE_STATIC:,
        PORT_OJ:,
        PORT_OJ_DB:,
        PORT_MYADMIN:,
        PORT_DB:,
        BELONG_TO:,
        NGINX_PORT_RANGES:,
        SECRET_KEY:,
        DOCKER_PULL_NEW:,
        DOCKER_NET_NAME:,
        LINK_LOCAL:,
        CSGOJ_SERVER_BASE_URL:,
        CSGOJ_SERVER_USERNAME:,
        CSGOJ_SERVER_PASSWORD:,
        JUDGE_POD_COUNT:,
        restart-all,
        rebuild-all,
        remove-all,
        delete-all,
        noninteractive,
        no-interactive,
        ignore-config,
        ignore-cfg
    "
    
    local opts
    opts=$(getopt -o "$shortopts" --long "$longopts" -n "$0" -- "$@") || {
        echo "❌ 参数解析失败，使用 --help 查看帮助信息" >&2
        exit 1
    }
    
    eval set -- "$opts"
    
    while true; do
        case "$1" in
            -h|--help)
                show_help
                exit 0
                ;;
            --CONFIG_FILE)                  # 指定配置文件路径
                CONFIG_FILE="$2"
                shift 2
                ;;
            --CSGOJ_VERSION)                # Docker 镜像版本标签
                CSGOJ_VERSION="$2"
                shift 2
                ;;
            --PATH_DATA)                    # 数据目录绝对路径
                PATH_DATA="$2"
                shift 2
                ;;
            --PASS_SQL_ROOT)                # MySQL root 用户密码
                PASS_SQL_ROOT="$2"
                shift 2
                ;;
            --PASS_SQL_USER)                # MySQL 业务用户密码
                PASS_SQL_USER="$2"
                shift 2
                ;;
            --PASS_MYADMIN_PAGE)            # PHPMyAdmin 页面访问密码
                PASS_MYADMIN_PAGE="$2"
                shift 2
                ;;
            --SQL_USER)                     # MySQL 业务用户名
                SQL_USER="$2"
                shift 2
                ;;
            --SQL_HOST)                     # MySQL 主机地址
                SQL_HOST="$2"
                shift 2
                ;;
            --WITH_MYSQL)                   # 是否部署 MySQL 容器
                WITH_MYSQL="$2"
                shift 2
                ;;
            --OJ_NAME)                      # OJ 名称
                OJ_NAME="$2"
                shift 2
                ;;
            --OJ_CDN)                       # 静态资源 CDN 类型
                OJ_CDN="$2"
                shift 2
                ;;
            --OJ_MODE)                      # OJ 运行模式
                OJ_MODE="$2"
                shift 2
                ;;
            --OJ_STATUS)                    # OJ 状态标识
                OJ_STATUS="$2"
                shift 2
                ;;
            --OJ_OPEN_OI)                   # [已废弃] OI 模式开关
                OJ_OPEN_OI="$2"
                shift 2
                ;;
            --OJ_UPDATE_STATIC)             # 部署时是否更新静态资源
                OJ_UPDATE_STATIC="$2"
                shift 2
                ;;
            --PORT_OJ)                      # OJ Web 服务端口
                PORT_OJ="$2"
                shift 2
                ;;
            --PORT_OJ_DB)                   # OJ Web 连接 MySQL 的端口
                PORT_OJ_DB="$2"
                shift 2
                ;;
            --PORT_MYADMIN)                 # PHPMyAdmin Web 端口
                PORT_MYADMIN="$2"
                shift 2
                ;;
            --PORT_DB)                      # MySQL 外部映射端口
                PORT_DB="$2"
                shift 2
                ;;
            --BELONG_TO)                    # 所属 OJ 名称，用于多实例共享
                BELONG_TO="$2"
                shift 2
                ;;
            --NGINX_PORT_RANGES)             # 额外的 Nginx 端口映射
                NGINX_PORT_RANGES="$2"
                shift 2
                ;;
            --SECRET_KEY)                   # 系统加密密钥
                SECRET_KEY="$2"
                shift 2
                ;;
            --DOCKER_PULL_NEW)              # 是否拉取最新镜像
                DOCKER_PULL_NEW="$2"
                shift 2
                ;;
            --DOCKER_NET_NAME)              # Docker 网络名称
                DOCKER_NET_NAME="$2"
                LINK_LOCAL="--network $DOCKER_NET_NAME"
                shift 2
                ;;
            --LINK_LOCAL)                   # Docker 网络连接参数
                LINK_LOCAL="$2"
                shift 2
                ;;
            --CSGOJ_SERVER_BASE_URL)        # Judge 模式：OJ Web 服务器地址
                # getopt 会将 --KEY=VALUE 拆分为 --KEY 和 VALUE 两个参数
                # 所以这里 $1 是 --CSGOJ_SERVER_BASE_URL，$2 是值
                if [ -n "$2" ] && [[ "$2" != --* ]]; then
                    CSGOJ_SERVER_BASE_URL="$2"
                    shift 2
                else
                    echo "❌ 错误: --CSGOJ_SERVER_BASE_URL 需要提供值" >&2
                    exit 1
                fi
                ;;
            --CSGOJ_SERVER_USERNAME)        # Judge 模式：评测机用户名
                if [ -n "$2" ] && [[ "$2" != --* ]]; then
                    CSGOJ_SERVER_USERNAME="$2"
                    shift 2
                else
                    echo "❌ 错误: --CSGOJ_SERVER_USERNAME 需要提供值" >&2
                    exit 1
                fi
                ;;
            --CSGOJ_SERVER_PASSWORD)        # Judge 模式：评测机密码
                if [ -n "$2" ] && [[ "$2" != --* ]]; then
                    CSGOJ_SERVER_PASSWORD="$2"
                    shift 2
                else
                    echo "❌ 错误: --CSGOJ_SERVER_PASSWORD 需要提供值" >&2
                    exit 1
                fi
                ;;
            --JUDGE_POD_COUNT)              # Judge 模式：启动的 pod 数量
                if [ -n "$2" ] && [[ "$2" != --* ]]; then
                    JUDGE_POD_COUNT="$2"
                    shift 2
                else
                    JUDGE_POD_COUNT=1  # 默认值
                    shift
                fi
                ;;
            --restart-all)                  # Judge 模式：重启所有 pod
                # 这个参数在 deploy_judge 中处理，这里先记录
                RESTART_ALL=true
                shift
                ;;
            --rebuild-all)                  # Judge 模式：重开所有 pod
                # 这个参数在 deploy_judge 中处理，这里先记录
                REBUILD_ALL=true
                shift
                ;;
            --remove-all|--delete-all)      # Judge 模式：删除所有 pod
                # 这个参数在 deploy_judge 中处理，这里先记录
                REMOVE_ALL=true
                shift
                ;;
            --noninteractive|--no-interactive)  # 非交互模式
                NONINTERACTIVE=true
                shift
                ;;
            --ignore-config|--ignore-cfg)  # 忽略配置文件
                IGNORE_CONFIG=true
                shift
                ;;
            --)
                shift
                break
                ;;
            *)
                echo "❌ 未知参数: '$1'，使用 --help 查看帮助信息" >&2
                exit 1
                ;;
        esac
    done
    
    # 3. 设置派生配置
    if [ "$BELONG_TO" = "0" ]; then
        BELONG_TO=$OJ_NAME
    fi
}

# ==================== 配置加载函数 ====================
load_config_files() {
    # 如果设置了忽略配置文件标志，跳过配置文件加载
    if [ "$IGNORE_CONFIG" = true ]; then
        return 0
    fi
    
    # 1. 先加载默认配置文件（如果存在）
    if [ -f "$DEFAULT_CONFIG" ]; then
        # 临时保存 Judge 模式参数（如果通过命令行预设置）
        # 注意：在 load_config_files 调用时，命令行参数还未解析，所以这里保存的是默认值或之前的值
        # 这个机制主要用于防止配置文件中的空字符串覆盖命令行参数（在 parse_args 的后续步骤中处理）
        # shellcheck source=/dev/null
        source "$DEFAULT_CONFIG"
    fi
    
    # 2. 再加载用户指定的配置文件（优先级更高）
    if [ -n "$CONFIG_FILE" ] && [ "$CONFIG_FILE" != "0" ] && [ -f "$CONFIG_FILE" ]; then
        # shellcheck source=/dev/null
        source "$CONFIG_FILE"
    fi
}

# ==================== 初始化辅助函数 ====================
# 这些函数包含副作用，由调用方显式调用

# 确保配置日志目录存在
init_config_log() {
    mkdir -p "$CONFIG_LOG"
}

# 确保 Docker 网络存在
init_docker_network() {
    if command -v docker &> /dev/null; then
        if [ -z "$(docker network ls | grep "$DOCKER_NET_NAME")" ]; then
            docker network create "$DOCKER_NET_NAME" >/dev/null 2>&1 || true
        fi
    fi
}

# 确保数据目录存在并设置权限
init_data_directory() {
    if [ ! -d "$PATH_DATA" ]; then
        mkdir -p "$PATH_DATA"
        if [ "$EUID" -eq 0 ]; then
            # 如果以 root 运行，设置所有权
            chown -R "$USER" "$PATH_DATA" 2>/dev/null || true
        fi
        chmod 755 "$PATH_DATA" 2>/dev/null || true
    fi
}

# ==================== 配置写入函数 ====================
# 将当前所有配置参数写入配置文件（确保完整参数）
write_full_config_file() {
    local target_config_file="${1:-$DEFAULT_CONFIG}"
    local deploy_mode="${2:-web}"  # web 或 judge
    
    # 确保目录存在
    local config_dir=$(dirname "$target_config_file")
    if [ -n "$config_dir" ] && [ "$config_dir" != "." ]; then
        mkdir -p "$config_dir"
    fi
    
    # 设置派生配置
    local belong_to_value="$BELONG_TO"
    if [ "$belong_to_value" = "0" ] || [ -z "$belong_to_value" ]; then
        belong_to_value="$OJ_NAME"
    fi
    
    # 检查配置文件是否存在，如果存在，读取另一套配置
    local existing_web_config=""
    local existing_judge_config=""
    if [ -f "$target_config_file" ]; then
        # 提取已有的 web 配置（如果存在）
        if grep -q "# ==================== OJ Web Server 配置 ====================" "$target_config_file" 2>/dev/null; then
            existing_web_config=$(sed -n '/^# ==================== OJ Web Server 配置 ====================$/,$p' "$target_config_file" 2>/dev/null | sed '/^# ==================== Judge Node 配置 ====================$/,$d')
        fi
        # 提取已有的 judge 配置（如果存在）
        if grep -q "# ==================== Judge Node 配置 ====================" "$target_config_file" 2>/dev/null; then
            existing_judge_config=$(sed -n '/^# ==================== Judge Node 配置 ====================$/,$p' "$target_config_file" 2>/dev/null)
        fi
    fi
    
    # 写入完整配置文件（覆盖基础配置，但保留另一套配置）
    cat > "$target_config_file" <<EOF
# CSGOJ 部署配置文件
# 生成时间: $(date '+%Y-%m-%d %H:%M:%S')
# 部署内容: OJ Web Server + Judge Node（支持同时配置）
# 
# 说明：
#   - 此文件包含所有配置参数（包括默认值）
#   - 下次运行时不提供参数，将自动使用此配置文件
#   - 参数优先级：命令行参数 > 此配置文件 > 默认值
#   - 配置文件支持同时包含 Web 和 Judge 两套配置

# ==================== 基础配置 ====================
PATH_DATA="$PATH_DATA"
OJ_NAME="$OJ_NAME"
CSGOJ_VERSION="$CSGOJ_VERSION"
DOCKER_NET_NAME="$DOCKER_NET_NAME"
DOCKER_PULL_NEW=$DOCKER_PULL_NEW
LINK_LOCAL="--network $DOCKER_NET_NAME"
EOF

    # 写入 Web 配置（如果当前是 web 模式，写入新配置；否则保留已有配置）
    if [ "$deploy_mode" = "web" ]; then
        cat >> "$target_config_file" <<EOF

# ==================== OJ Web Server 配置 ====================
# MySQL 配置
WITH_MYSQL=${WITH_MYSQL:-1}
SQL_HOST="${SQL_HOST}"
SQL_USER="${SQL_USER:-csgcpc}"
PASS_SQL_ROOT="${PASS_SQL_ROOT}"
PASS_SQL_USER="${PASS_SQL_USER:-987654321}"
PORT_DB=${PORT_DB}
PORT_OJ_DB=${PORT_OJ_DB:-3306}

# OJ Web 配置
PORT_OJ=${PORT_OJ:-20080}
PORT_MYADMIN=${PORT_MYADMIN:-20050}
OJ_CDN="${OJ_CDN:-local}"
OJ_MODE="${OJ_MODE:-cpcsys}"
OJ_STATUS="${OJ_STATUS:-cpc}"
OJ_OPEN_OI=${OJ_OPEN_OI:-0}
OJ_UPDATE_STATIC=${OJ_UPDATE_STATIC:-0}
BELONG_TO="${belong_to_value}"

# 其他配置
PASS_MYADMIN_PAGE="${PASS_MYADMIN_PAGE:-987654321}"
SECRET_KEY="${SECRET_KEY:-super_secret_oj}"
NGINX_PORT_RANGES="${NGINX_PORT_RANGES}"
EOF
    elif [ -n "$existing_web_config" ]; then
        # 保留已有的 web 配置
        echo "" >> "$target_config_file"
        echo "$existing_web_config" >> "$target_config_file"
    fi
    
    # 写入 Judge 配置（如果当前是 judge 模式，写入新配置；否则保留已有配置）
    if [ "$deploy_mode" = "judge" ]; then
        cat >> "$target_config_file" <<EOF

# ==================== Judge Node 配置 ====================
CSGOJ_SERVER_BASE_URL="$CSGOJ_SERVER_BASE_URL"
CSGOJ_SERVER_USERNAME="$CSGOJ_SERVER_USERNAME"
CSGOJ_SERVER_PASSWORD="$CSGOJ_SERVER_PASSWORD"
JUDGE_POD_COUNT=${JUDGE_POD_COUNT:-1}
EOF
    elif [ -n "$existing_judge_config" ]; then
        # 保留已有的 judge 配置
        echo "" >> "$target_config_file"
        echo "$existing_judge_config" >> "$target_config_file"
    fi
    
    echo "$target_config_file"
}

# ==================== 配置记录函数 ====================
# 可选功能：记录配置变更历史（仅在需要时调用）
write_config_if_changed() {
    init_config_log
    
    local latest_config_file=""
    if [ -d "${CONFIG_LOG}" ] && [ "$(ls -A ${CONFIG_LOG}/csgoj_config_*.cfg 2>/dev/null)" ]; then
        latest_config_file=$(ls -t ${CONFIG_LOG}/csgoj_config_*.cfg 2>/dev/null | head -n 1)
    fi
    
    local temp_file
    temp_file=$(mktemp)
    
    for arg in "$@"; do
        if [[ $arg == --* ]] && [[ ${arg%%=*} != "--CONFIG_FILE" ]]; then
            echo "${arg:2}" >> "$temp_file"
        fi
    done
    
    if [[ -s $temp_file ]] && { [ -z "$latest_config_file" ] || ! diff -q "$temp_file" "$latest_config_file" >/dev/null 2>&1; }; then
        local timestamp
        timestamp=$(date +%s)
        local new_config_file="${CONFIG_LOG}/csgoj_config_${timestamp}.cfg"
        mv "$temp_file" "$new_config_file"
        echo "📝 新建配置记录: $new_config_file"
    else
        rm -f "$temp_file"
    fi
}

# ==================== 自动初始化 ====================
# 脚本加载时自动执行必要的初始化
init_config_log
init_docker_network
init_data_directory


# ==================== 函数定义区域 ====================

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
        if docker pull "$target_image" >/dev/null 2>&1; then
            web_image="$target_image"
            echo "✅ 使用镜像: $target_image (已拉取)"
        elif docker image inspect "$target_image" >/dev/null 2>&1 && \
             docker run --rm --entrypoint /bin/sh "$target_image" -c "echo test" >/dev/null 2>&1; then
            web_image="$target_image"
            echo "✅ 使用镜像: $target_image (本地存在)"
        else
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
        if [ -z "$NGINX_PORT_RANGES" ]; then
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
        local docker_run_output
        docker_run_output=$(docker run --name nginx-server $LINK_LOCAL \
            $NGINX_PORT_RANGES \
            -v "$PATH_DATA/var/www:/var/www" \
            $PUBLIC_MOUNT \
            -v "$PATH_DATA/dataspace:$PATH_DATA/dataspace" \
            -v "$PATH_DATA/var/log/nginx:/var/log/nginx" \
            -v "$PATH_DATA/nginx/nginx_conf.d:/etc/nginx/conf.d" \
            -v "$PATH_DATA/nginx/attach:/etc/nginx/attach" \
            --restart=unless-stopped \
            -d "$NGINX_IMAGE" 2>&1)
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

show_help() {
    cat << EOF
CSGOJ 评测机启动脚本

使用方法:
  bash start_judge2.sh [选项...]

必需参数:
  --CSGOJ_SERVER_BASE_URL=<地址>     OJ Web 服务器地址
  --CSGOJ_SERVER_USERNAME=<用户名>   评测机用户名
  --CSGOJ_SERVER_PASSWORD=<密码>     评测机密码
  --PATH_DATA=<路径>                 数据目录绝对路径

可选参数:
  --JUDGE_POD_COUNT=<数量>           启动的 pod 数量（默认: 1）
  --restart-all                      重启所有 pod（docker restart，保持容器不变）
  --rebuild-all                      重开所有 pod（删除并重新创建，需要提供启动参数）
  --remove-all, --delete-all         删除所有 pod（仅删除容器，不重新创建）
  --noninteractive, --no-interactive  非交互模式（参数缺失时直接报错，不进入交互补全）
  --OJ_NAME=<名称>                  OJ 名称（默认: ccpc）
  --CSGOJ_VERSION=<版本>            Docker 镜像版本（默认: latest）
  --DOCKER_NET_NAME=<名称>           Docker 网络名称（默认: csgoj_net）
  --DOCKER_PULL_NEW=<0|1>           是否拉取最新镜像（默认: 1）

示例:
  # 启动单个评测机 pod
  bash start_judge2.sh \\
      --CSGOJ_SERVER_BASE_URL=http://192.168.1.100:20080 \\
      --CSGOJ_SERVER_USERNAME=judger \\
      --CSGOJ_SERVER_PASSWORD=123456

  # 启动多个评测机 pod
  bash start_judge2.sh \\
      --CSGOJ_SERVER_BASE_URL=http://oj.example.com:20080 \\
      --CSGOJ_SERVER_USERNAME=judger \\
      --CSGOJ_SERVER_PASSWORD=123456 \\
      --JUDGE_POD_COUNT=4

  # 重启所有评测机 pod（docker restart）
  bash start_judge2.sh \\
      --restart-all

  # 重开所有评测机 pod（删除并重新创建）
  bash start_judge2.sh \\
      --CSGOJ_SERVER_BASE_URL=http://192.168.1.100:20080 \\
      --CSGOJ_SERVER_USERNAME=judger \\
      --CSGOJ_SERVER_PASSWORD=123456 \\
      --rebuild-all

  # 删除所有评测机 pod（仅删除，不重新创建）
  bash start_judge2.sh \\
      --remove-all

注意:
  • 资源（CPU、内存）将根据系统资源自动计算和分配
  • 评测机密码需要在 Web 管理界面设置后获取
  • CSGOJ_SERVER_BASE_URL 必须使用评测机容器能访问的 IP 或域名
  • 不要使用 localhost 或 127.0.0.1（评测机容器内无法访问宿主机 localhost）
  • 如果评测机和 Web 在同一台机器，使用服务器的内网或外网 IP
  • --restart-all: 仅重启现有容器（docker restart），不需要启动参数
  • --rebuild-all: 删除所有容器并重新创建，需要提供启动参数
  • --remove-all: 仅删除所有容器，不重新创建，不需要启动参数
  • 默认交互模式：必要参数缺失时进入交互式补全
  • 非交互模式：使用 --noninteractive 时，参数缺失直接报错

EOF
}


# Judge 模式必要参数交互补全（复用 configure.sh 的函数）

# 验证必需参数（重启模式和删除模式不需要启动参数）


# 转换内存单位到 GB（整数）
convert_memory_to_gb() {
    local mem_str="$1"
    local mem_value=$(echo "$mem_str" | sed 's/[^0-9.]//g')
    local mem_unit=$(echo "$mem_str" | sed 's/[0-9.]//g' | tr '[:upper:]' '[:lower:]')
    
    case "$mem_unit" in
        g|gb|gigabyte|gigabytes)
            echo "${mem_value%.*}"
            ;;
        m|mb|megabyte|megabytes)
            echo "$((mem_value / 1024))"
            ;;
        *)
            # 默认当作 GB
            echo "${mem_value%.*}"
            ;;
    esac
}

# 计算每个 pod 的 CPU 数量
calculate_cpu_per_pod() {
    local target_pod_count=$1
    local total_cpus=$(nproc)
    
    # 单 pod 且总核心数少于 8
    if [ $target_pod_count -eq 1 ] && [ $total_cpus -lt 8 ]; then
        local available_after_offset=$((total_cpus - 2))
        # 如果偏移 2 之后还有 4 个以上核心，使用偏移后的所有核心
        if [ $available_after_offset -ge 4 ]; then
            echo $available_after_offset
        else
            # 否则至少保证用后 4 个逻辑核心
            echo 4
        fi
    else
        # 其它情况（多 pod 或总核心 >= 8）：固定每个 pod 用 4 个逻辑核心
        echo 4
    fi
}

# 计算每个 pod 的内存（GB）
calculate_memory_per_pod() {
    # 每个 pod 固定 4GB 内存（通过 --memory 配置）
    # 注意：每个 pod 还需要 1GB SHM（通过 --shm-size 配置）
    # 所以每个 pod 总内存需求 = 4GB + 1GB = 5GB
    echo 4
}

# 计算 CPU 偏移量
calculate_cpu_offset() {
    local target_pod_count=$1
    local cpus_per_pod=$2
    local total_cpus=$(nproc)
    
    # 单 pod 且总核心数少于 8
    if [ $target_pod_count -eq 1 ] && [ $total_cpus -lt 8 ]; then
        local available_after_offset=$((total_cpus - 2))
        # 如果偏移 2 之后还有 4 个以上核心，使用偏移 2
        if [ $available_after_offset -ge 4 ]; then
            echo 2
        else
            # 否则，至少保证用后 4 个逻辑核心，偏移 = 总核心 - 4
            # 但要确保偏移量不为负数（至少是 0）
            local offset=$((total_cpus - 4))
            if [ $offset -lt 0 ]; then
                echo 0
            else
                echo $offset
            fi
        fi
    else
        # 其它情况（多 pod 或总核心 >= 8）：固定偏移量 2
        echo 2
    fi
}

# 验证资源并计算最大可启动 pod 数量
validate_and_calculate_resources() {
    local target_pod_count=$1
    
    # 自动计算每个 pod 的资源
    local auto_cpus=$(calculate_cpu_per_pod $target_pod_count)
    local auto_memory_gb=$(calculate_memory_per_pod $target_pod_count)
    
    # 保存到全局变量供后续使用
    JUDGE_DOCKER_CPUS=$auto_cpus
    JUDGE_DOCKER_MEMORY="${auto_memory_gb}g"
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  自动资源配置"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  系统资源:"
    echo "    CPU: $(nproc) 逻辑核心"
    echo "    内存: $(free -g | awk '/^Mem:/{print $2}')GB"
    echo ""
    echo "  目标 pod 数量: ${target_pod_count}"
    echo "  自动配置（每个 pod）:"
    echo "    CPU: ${auto_cpus} 核心"
    echo "    内存: ${auto_memory_gb}GB (+ 1GB SHM = $(($auto_memory_gb + 1))GB 总计)"
    echo ""
    
    # 检查资源是否充足
    local total_cpus=$(nproc)
    local total_mem_gb=$(free -g | awk '/^Mem:/{print $2}')
    local reserved_mem_gb=2
    local max_mem=$((total_mem_gb - reserved_mem_gb))
    
    local needed_cpus=$((target_pod_count * auto_cpus))
    # 每个 pod 需要的内存 = 配置的内存 + shm-size (1GB)
    local shm_size_gb=1
    local needed_mem=$((target_pod_count * (auto_memory_gb + shm_size_gb)))
    
    # 计算基于 CPU 的最大 pod 数
    local max_pods_by_cpu=0
    # 单 pod 且总核心 < 8 的情况，最多就是 1 个 pod
    if [ $target_pod_count -eq 1 ] && [ $total_cpus -lt 8 ]; then
        max_pods_by_cpu=1
    else
        # 其它情况：固定每个 pod 用 4 核心，固定偏移 2，最多能开的 pod 数 = (总核心 - 2) / 4
        local fixed_cpus_per_pod=4
        local fixed_offset=2
        local available_after_offset=$((total_cpus - fixed_offset))
        if [ $fixed_cpus_per_pod -gt 0 ]; then
            max_pods_by_cpu=$((available_after_offset / fixed_cpus_per_pod))
        fi
    fi
    
    # 计算基于内存的最大 pod 数
    local max_pods_by_mem=0
    if [ $((auto_memory_gb + shm_size_gb)) -gt 0 ]; then
        max_pods_by_mem=$((max_mem / (auto_memory_gb + shm_size_gb)))
    fi
    
    local max_pods=0
    if [ $max_pods_by_cpu -lt $max_pods_by_mem ]; then
        max_pods=$max_pods_by_cpu
    else
        max_pods=$max_pods_by_mem
    fi
    
    if [ $max_pods -lt 1 ]; then
        echo "❌ 错误: 系统资源严重不足，无法启动任何 pod"
        echo "   建议使用更大资源的服务器"
        exit 1
    elif [ $max_pods -lt $target_pod_count ]; then
        local shortage=$((target_pod_count - max_pods))
        echo "⚠️  警告: 资源不足，只能启动 ${max_pods} 个 pod（目标: ${target_pod_count}）"
        echo "   缺少 ${shortage} 个 pod 的资源"
        echo "   注意: 每个 pod 实际需要 $(($auto_memory_gb + 1))GB 内存（${auto_memory_gb}GB + 1GB SHM）"
        echo ""
        
        # 自动调整为目标数量
        local actual_count=$max_pods
        if [ $actual_count -lt 1 ]; then
            actual_count=1
        fi
        
        echo "💡 将自动调整为启动 ${actual_count} 个 pod"
        JUDGE_POD_COUNT=$actual_count
        
        read -p "是否继续？(y/n, 默认: y): " confirm
        confirm=${confirm:-y}
        if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
            echo "已取消启动"
            exit 0
        fi
    else
        echo "✅ 资源充足，可以启动 ${target_pod_count} 个 pod"
    fi
    
    echo ""
}


# 查找可用的 pod 索引
find_available_pod_indices() {
    local target_count=$1
    local existing_indices=()
    
    # 获取所有现有的 judge pod
    local existing_containers=$(docker ps -a --filter "name=judge-$OJ_NAME" --format "{{.Names}}" 2>/dev/null || true)
    
    # 提取索引号
    for container in $existing_containers; do
        if [[ $container =~ judge-$OJ_NAME-([0-9]+)$ ]]; then
            existing_indices+=("${BASH_REMATCH[1]}")
        elif [[ $container == judge-$OJ_NAME ]]; then
            existing_indices+=("0")
        fi
    done
    
    # 对索引排序
    IFS=$'\n' sorted_indices=($(sort -n <<<"${existing_indices[*]}"))
    unset IFS
    
    local available_indices=()
    local current_idx=0
    
    # 查找可用的索引
    for existing_idx in "${sorted_indices[@]}"; do
        while [ $current_idx -lt $existing_idx ] && [ ${#available_indices[@]} -lt $target_count ]; do
            available_indices+=($current_idx)
            ((current_idx++))
        done
        if [ ${#available_indices[@]} -ge $target_count ]; then
            break
        fi
        ((current_idx=$existing_idx+1))
    done
    
    # 如果还需要更多索引，继续添加
    while [ ${#available_indices[@]} -lt $target_count ]; do
        available_indices+=($current_idx)
        ((current_idx++))
    done
    
    echo "${available_indices[@]}"
}

# 重启所有现有的 judge pod（docker restart）
restart_all_judge_pods() {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  重启所有评测机 pod"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    local containers=$(docker ps -a --filter "name=judge-$OJ_NAME" --format "{{.Names}}" 2>/dev/null || true)
    
    if [ -z "$containers" ]; then
        echo "  没有找到现有的 pod"
        echo ""
        return
    fi
    
    for container in $containers; do
        echo "  重启: $container"
        docker restart "$container" >/dev/null 2>&1 || true
    done
    
    echo "  完成"
    echo ""
}

# 停止并删除所有现有的 judge pod（用于重开）
rebuild_all_judge_pods() {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  重开所有评测机 pod"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    local containers=$(docker ps -a --filter "name=judge-$OJ_NAME" --format "{{.Names}}" 2>/dev/null || true)
    
    if [ -z "$containers" ]; then
        echo "  没有找到现有的 pod"
        echo ""
        return
    fi
    
    for container in $containers; do
        echo "  停止并删除: $container"
        docker stop "$container" >/dev/null 2>&1 || true
        docker rm "$container" >/dev/null 2>&1 || true
    done
    
    echo "  完成"
    echo ""
}

# 删除所有现有的 judge pod（仅删除，不重新创建）
remove_all_judge_pods() {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  删除所有评测机 pod"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    local containers=$(docker ps -a --filter "name=judge-$OJ_NAME" --format "{{.Names}}" 2>/dev/null || true)
    
    if [ -z "$containers" ]; then
        echo "  没有找到现有的 pod"
        echo ""
        return
    fi
    
    for container in $containers; do
        echo "  删除: $container"
        docker stop "$container" >/dev/null 2>&1 || true
        docker rm "$container" >/dev/null 2>&1 || true
    done
    
    echo "  完成"
    echo ""
}

# 启动单个 pod
start_single_pod() {
    local pod_index=$1
    local cpus=$2
    local memory=$3
    local cpu_offset=$4
    
    # 确定容器名称
    if [ $pod_index -eq 0 ] && [ $JUDGE_POD_COUNT -eq 1 ]; then
        CONTAINER_NAME="judge-$OJ_NAME"
    else
        CONTAINER_NAME="judge-$OJ_NAME-$pod_index"
    fi
    
    # 检查容器是否已存在
    if [ -n "$(docker ps -a -q -f name=^${CONTAINER_NAME}$)" ]; then
        echo "  ⏭️  跳过: $CONTAINER_NAME 已存在"
        return
    fi
    
    # 创建必要目录
    mkdir -p "$PATH_DATA/var/data/judge-$OJ_NAME/data"
    
    # CPU 绑定配置
    CPUSET_CONFIG=""
    if [ $cpu_offset -gt 0 ]; then
        local cpu_start=$((cpu_offset + cpus * pod_index))
        local cpu_end=$((cpu_start + cpus - 1))
        CPUSET_CPUS=$(seq -s, $cpu_start $cpu_end)
        CPUSET_CONFIG="--cpuset-cpus=$CPUSET_CPUS"
    fi
    
    # 开发模式挂载（CSGOJ_DEV 环境变量）
    JUDGE_MOUNT=""
    if [ "$CSGOJ_DEV" = "1" ]; then
        # 计算项目根目录（兼容独立脚本和合并脚本，开发者测试和用户部署两种场景）
        local project_dir=""
        
        # 方法1: 如果 SCRIPT_DIR 已定义，使用它（独立脚本场景：start_scripts -> deploy_files -> project_root）
        if [ -n "$SCRIPT_DIR" ]; then
            local temp_dir=$(cd "$SCRIPT_DIR/../.." 2>/dev/null && pwd || echo "")
            if [ -n "$temp_dir" ] && [ -d "$temp_dir/deploy_files/judge2/core" ]; then
                project_dir="$temp_dir"
            fi
        fi
        
        # 方法2: 从脚本位置计算（兼容两种场景：脚本在 start_scripts 或项目根目录）
        if [ -z "$project_dir" ] || [ ! -d "$project_dir/deploy_files/judge2/core" ]; then
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
                if [ -d "$script_dir/deploy_files/judge2/core" ]; then
                    project_dir="$script_dir"
                # 场景2: 从脚本目录向上两级（开发者测试场景：start_scripts -> deploy_files -> project_root）
                else
                    local temp_dir=$(cd "$script_dir/../.." 2>/dev/null && pwd || echo "")
                    if [ -n "$temp_dir" ] && [ -d "$temp_dir/deploy_files/judge2/core" ]; then
                        project_dir="$temp_dir"
                    fi
                fi
            fi
        fi
        
        # 方法3: 从当前工作目录向上查找（最后的后备方案）
        if [ -z "$project_dir" ] || [ ! -d "$project_dir/deploy_files/judge2/core" ]; then
            local current_dir=$(pwd)
            local search_dir="$current_dir"
            # 向上查找最多5级，直到找到包含 deploy_files/judge2/core 的目录
            for i in {1..5}; do
                if [ -d "$search_dir/deploy_files/judge2/core" ]; then
                    project_dir="$search_dir"
                    break
                fi
                search_dir=$(cd "$search_dir/.." 2>/dev/null && pwd || echo "")
                if [ "$search_dir" = "/" ] || [ -z "$search_dir" ]; then
                    break
                fi
            done
        fi
        
        if [ -n "$project_dir" ]; then
            local judge2_core_dir="$project_dir/deploy_files/judge2/core"
            if [ -d "$judge2_core_dir" ]; then
                JUDGE_MOUNT="-v $judge2_core_dir:/core"
                echo "  🔧 开发模式：已启用源码目录挂载 ($judge2_core_dir)"
            fi
        fi
    fi
    
    # 确定镜像版本（先尝试 latest，失败后 fallback 到本地镜像）
    local judge_image=""
    local target_image="csgrandeur/ccpcoj-judge2:$CSGOJ_VERSION"
    
    echo "  尝试使用镜像: $target_image"
    if docker pull "$target_image" >/dev/null 2>&1; then
        judge_image="$target_image"
        echo "  ✅ 使用镜像: $target_image (已拉取)"
    elif docker image inspect "$target_image" >/dev/null 2>&1 && \
         docker run --rm --entrypoint /bin/sh "$target_image" -c "echo test" >/dev/null 2>&1; then
        judge_image="$target_image"
        echo "  ✅ 使用镜像: $target_image (本地存在)"
    else
        echo "  ⚠️  镜像 $target_image 拉取失败，尝试使用本地镜像"
        # Fallback: 查找本地有效镜像
        local found_local=false
        for version in "latest" "1.0.0" "0.1.0"; do
            local check_image="csgrandeur/ccpcoj-judge2:$version"
            if docker image inspect "$check_image" >/dev/null 2>&1; then
                # 尝试运行一个简单命令来验证镜像是否可用
                if docker run --rm --entrypoint /bin/sh "$check_image" -c "echo test" >/dev/null 2>&1; then
                    judge_image="$check_image"
                    found_local=true
                    echo "  ⚠️  使用本地镜像: $judge_image (fallback from $target_image)"
                    break
                fi
            fi
        done
        
        if [ "$found_local" = false ]; then
            echo "  ❌ 无法找到有效的评测机镜像"
            echo "     请检查网络连接或手动拉取: docker pull $target_image"
            return 1
        fi
    fi
    
    # 启动容器
    echo "  启动: $CONTAINER_NAME (CPU: ${cpus}, Memory: ${memory})"
    
    local docker_run_output
    docker_run_output=$(docker run --pull=never -dit $LINK_LOCAL \
        --name "$CONTAINER_NAME" \
        --privileged \
        --security-opt seccomp=unconfined \
        --cgroupns=host \
        --pid=host \
        --cpus="$cpus" \
        $CPUSET_CONFIG \
        --memory="${memory}" \
        --shm-size=1g \
        -e CSGOJ_SERVER_BASE_URL="$CSGOJ_SERVER_BASE_URL" \
        -e CSGOJ_SERVER_USERNAME="$CSGOJ_SERVER_USERNAME" \
        -e CSGOJ_SERVER_PASSWORD="$CSGOJ_SERVER_PASSWORD" \
        -e CSGOJ_SERVER_API_PATH="/ojtool/judge2" \
        -v "$PATH_DATA/var/data/judge-$OJ_NAME/data:/judge/data" \
        -v /etc/localtime:/etc/localtime:ro \
        $JUDGE_MOUNT \
        --restart=unless-stopped \
        "$judge_image" 2>&1)
    
    if [ $? -eq 0 ]; then
        echo "  ✅ $CONTAINER_NAME 启动成功"
    else
        echo "  ❌ $CONTAINER_NAME 启动失败"
        echo "     错误信息:"
        echo "$docker_run_output" | head -5
        return 1
    fi
}


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
    
    # NGINX_PORT_RANGES 使用默认值（空），不需要交互配置
    NGINX_PORT_RANGES="${NGINX_PORT_RANGES:-}"
    
    echo ""
}

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

# ==================== 交互式配置函数 ====================

interactive_configure() {
    local selected_mode
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  CSGOJ 部署内容选择"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "请选择部署内容："
    echo "  web   - OJ Web Server（Web 服务器，包含 MySQL、Nginx、PHP 等服务）"
    echo "  judge - Judge Node（评测机节点，仅启动评测服务）"
    echo ""
    read -p "请输入选项 (web/judge, 默认: web): " selected_mode
    selected_mode=${selected_mode:-web}
    selected_mode=$(echo "$selected_mode" | tr '[:upper:]' '[:lower:]')
    
    if [ "$selected_mode" != "web" ] && [ "$selected_mode" != "judge" ]; then
        echo "❌ 无效选项，请输入 'web' 或 'judge'"
        exit 1
    fi
    
    # 设置全局变量供外部使用（不使用 local）
    INTERACTIVE_SELECTED_MODE="$selected_mode"
    
    # 根据选择的模式，处理交互式配置
    if [ "$selected_mode" = "judge" ]; then
        interactive_configure_judge ""
        
        # 调用 Judge 模式（使用位置参数）
        deploy_judge "$CSGOJ_SERVER_BASE_URL" "$CSGOJ_SERVER_USERNAME" "$CSGOJ_SERVER_PASSWORD" "$JUDGE_POD_COUNT" ""
        # deploy_judge 会 exit，这里不会执行到
        exit $?
    else
        # Web 模式直接继续执行（使用默认参数）
        echo ""
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        echo "  OJ Web Server 部署"
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        echo ""
        echo "💡 将使用默认配置部署 Web 服务"
        echo "   如需自定义配置，请使用参数或配置文件"
        echo ""
        # Web 模式直接返回，不执行任何退出操作
        return 0
    fi
}


# ==================== 交互补全函数 ====================

# Judge 模式必要参数交互补全（复用 interactive_configure_judge 函数）
interactive_complete_judge_params() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  缺少必要参数，进入交互式配置"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # 调用 interactive_configure_judge，传递 "skip" 参数以跳过已提供的参数
    interactive_configure_judge "skip"
}

# Web 模式首次部署完整参数交互配置（复用 interactive_configure_web 函数）
interactive_configure_web_first_time() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  OJ Web Server 首次部署配置"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # 首次配置时，不传递 "skip" 参数，确保询问所有参数（即使有默认值）
    # 这样用户可以看到并确认所有配置项
    interactive_configure_web ""
}

# 显示配置文件内容和位置
display_config_info() {
    local config_file_path="$1"
    local deploy_mode="$2"
    
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  配置文件已保存"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "📁 配置文件路径: $config_file_path"
    echo ""
    echo "💡 下次运行说明："
    echo "   • 如果不提供参数，将自动使用此配置文件中的配置"
    echo "   • 使用方式: bash csgoj_deploy.sh"
    if [ "$deploy_mode" = "judge" ]; then
        echo "   • 或指定模式: bash csgoj_deploy.sh judge"
    fi
    echo ""
    echo "📋 当前配置摘要："
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    if [ "$deploy_mode" = "judge" ]; then
        echo "  部署内容: Judge Node"
        echo "  服务器地址: $CSGOJ_SERVER_BASE_URL"
        echo "  用户名: $CSGOJ_SERVER_USERNAME"
        echo "  Pod 数量: $JUDGE_POD_COUNT"
    else
        echo "  部署内容: OJ Web Server"
        echo "  OJ 名称: $OJ_NAME"
        echo "  数据目录: $PATH_DATA"
        echo "  Web 端口: $PORT_OJ"
        echo "  数据库端口: $PORT_DB"
        if [ "$WITH_MYSQL" = "1" ]; then
            echo "  MySQL: 容器部署"
        else
            echo "  MySQL: 外部连接 ($SQL_HOST)"
        fi
    fi
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
}


# ==================== 主入口逻辑 ====================

main() {
    local DEPLOY_MODE=''
    local REMAINING_ARGS=()
    
    # 定义默认配置文件路径（需要在 parse_args 之前，因为要在 main 中使用）
    local DEFAULT_CONFIG="${DEFAULT_CONFIG:-./data/csgoj_config.cfg}"
    
    # 检查第一个参数是否为命令模式
    if [ $# -gt 0 ]; then
        case "$1" in
            web|judge)
                DEPLOY_MODE="$1"
                # 移除第一个参数，剩余参数传递给 parse_args
                shift
                REMAINING_ARGS=("$@")
                ;;
            --help|-h|-H)
                # help 在 parse_args 中处理
                ;;
            *)
                # 不是命令模式，检查是否是隐式的 judge 模式参数
                for arg in "$@"; do
                    if [[ "$arg" == *"CSGOJ_SERVER_BASE_URL"* ]] || [[ "$arg" == *"CSGOJ_SERVER_PASSWORD"* ]]; then
                        DEPLOY_MODE="judge"
                        break
                    fi
                done
                # 如果不是隐式判断，也没有命令，进入交互式选择
                if [ -z "$DEPLOY_MODE" ]; then
                    INTERACTIVE_SELECTED_MODE=""
                    interactive_configure
                    # interactive_configure 中，如果选择 judge 会调用 deploy_judge 并 exit（不会返回）
                    # 如果选择 web，会设置 INTERACTIVE_SELECTED_MODE="web" 并 return 0
                    # 防御性检查：如果没有设置 INTERACTIVE_SELECTED_MODE（不应该发生），默认使用 web
                    if [ "$INTERACTIVE_SELECTED_MODE" = "web" ]; then
                        DEPLOY_MODE="web"
                    elif [ -z "$INTERACTIVE_SELECTED_MODE" ]; then
                        # 如果 INTERACTIVE_SELECTED_MODE 为空（异常情况），默认使用 web
                        DEPLOY_MODE="web"
                    fi
                fi
                REMAINING_ARGS=("$@")
                ;;
        esac
    else
        # 没有参数，先进入交互式选择 web/judge
        INTERACTIVE_SELECTED_MODE=""
        interactive_configure
        # interactive_configure 中，如果选择 judge 会调用 deploy_judge 并 exit（不会返回）
        # 如果选择 web，会设置 INTERACTIVE_SELECTED_MODE="web" 并 return 0
        # 防御性检查：如果没有设置 INTERACTIVE_SELECTED_MODE（不应该发生），默认使用 web
        if [ "$INTERACTIVE_SELECTED_MODE" = "web" ]; then
            DEPLOY_MODE="web"
        elif [ -z "$INTERACTIVE_SELECTED_MODE" ]; then
            # 如果 INTERACTIVE_SELECTED_MODE 为空（异常情况），默认使用 web
            DEPLOY_MODE="web"
        fi
    fi
    
    # 解析参数（如果有剩余参数）
    if [ ${#REMAINING_ARGS[@]} -gt 0 ]; then
        parse_args "${REMAINING_ARGS[@]}"
    else
        parse_args
    fi
    
    # 判断部署内容（命令模式和交互式选择优先，配置文件中的变量不能覆盖）
    local JUDGE_NODE_MODE=false
    # 优先检查显式设置的 DEPLOY_MODE（来自命令参数或交互式选择）
    if [ "$DEPLOY_MODE" = "judge" ]; then
        JUDGE_NODE_MODE=true
    elif [ "$DEPLOY_MODE" = "web" ]; then
        # Web 模式明确设置为 false（即使配置文件中有 judge 相关变量，也优先使用显式选择的模式）
        JUDGE_NODE_MODE=false
    elif [ -z "$DEPLOY_MODE" ]; then
        # DEPLOY_MODE 为空，使用隐式检测（仅检查命令行参数，不检查配置文件中的变量）
        # 注意：这里只检查 REMAINING_ARGS，不检查配置文件，因为配置文件可能包含旧的 judge 配置
        for arg in "${REMAINING_ARGS[@]}"; do
            if [[ "$arg" == *"CSGOJ_SERVER_BASE_URL"* ]] || [[ "$arg" == *"CSGOJ_SERVER_PASSWORD"* ]]; then
                JUDGE_NODE_MODE=true
                break
            fi
        done
        # 如果没有明确的 judge 参数，默认使用 web 模式
        if [ "$JUDGE_NODE_MODE" = false ]; then
            DEPLOY_MODE="web"
            JUDGE_NODE_MODE=false
        fi
    else
        # DEPLOY_MODE 有值但不是 "judge" 或 "web"（异常情况），默认使用 web
        DEPLOY_MODE="web"
        JUDGE_NODE_MODE=false
    fi
    
    # ==================== Judge 模式 ====================
    if [ "$JUDGE_NODE_MODE" = true ]; then
        deploy_judge "${REMAINING_ARGS[@]}"
        exit $?
    fi
    
    # ==================== Web 模式 ====================
    deploy_web
}

# ==================== 部署函数 ====================

deploy_judge() {
    # Judge 模式参数（参数已经在 parse_args 中解析，这里直接使用全局变量）
    # 注意：不再重新加载配置文件，因为 parse_args 已经处理了参数优先级：
    # 命令行参数 > 配置文件 > 默认值
    
    # 判断是否为交互式模式（位置参数，不是 --KEY=value 格式）
    # 交互式模式：直接传入的值（如 deploy_judge "http://..." "username" "password"）
    # 命令行模式：传入的是已解析的参数数组，但变量已经在 parse_args 中设置好了
    local is_interactive_mode=false
    if [ $# -ge 3 ] && [[ ! "$1" == --* ]] && [[ "$1" != *"="* ]]; then
        # 交互式模式：第一个参数不是 -- 开头，也不是 KEY=VALUE 格式
        is_interactive_mode=true
        CSGOJ_SERVER_BASE_URL="$1"
        CSGOJ_SERVER_USERNAME="$2"
        CSGOJ_SERVER_PASSWORD="$3"
        JUDGE_POD_COUNT="${4:-1}"
        RESTART_ALL="${5:-false}"
    fi
    
    # 命令行模式：参数已在 parse_args 中解析，直接使用全局变量
    # 验证必需参数（重启模式和删除模式不需要启动参数）
    if [ "$RESTART_ALL" = false ] && [ "$REBUILD_ALL" = false ] && [ "$REMOVE_ALL" = false ]; then
        # 检查必要参数是否缺失
        local missing_params=false
        if [ -z "$CSGOJ_SERVER_BASE_URL" ] || [ -z "$CSGOJ_SERVER_USERNAME" ] || [ -z "$CSGOJ_SERVER_PASSWORD" ]; then
            missing_params=true
        fi
        
        if [ "$missing_params" = true ]; then
            if [ "$NONINTERACTIVE" = true ]; then
                # 非交互模式：直接报错
                echo "❌ 错误: 必须提供 CSGOJ_SERVER_BASE_URL、CSGOJ_SERVER_USERNAME 和 CSGOJ_SERVER_PASSWORD" >&2
                echo ""
                echo "使用方式："
                echo "  bash csgoj_deploy.sh judge --CSGOJ_SERVER_BASE_URL=... --CSGOJ_SERVER_USERNAME=... --CSGOJ_SERVER_PASSWORD=..."
                echo ""
                echo "或使用交互式（不使用 --noninteractive）："
                echo "  bash csgoj_deploy.sh"
                echo ""
                exit 1
            else
                # 交互模式：进入交互补全
                interactive_complete_judge_params
            fi
        fi
    elif [ "$REBUILD_ALL" = true ]; then
        # 重开模式需要启动参数
        if [ -z "$CSGOJ_SERVER_BASE_URL" ] || [ -z "$CSGOJ_SERVER_USERNAME" ] || [ -z "$CSGOJ_SERVER_PASSWORD" ]; then
            if [ "$NONINTERACTIVE" = true ]; then
                echo "❌ 错误: 重开模式（--rebuild-all）需要提供启动参数：CSGOJ_SERVER_BASE_URL、CSGOJ_SERVER_USERNAME 和 CSGOJ_SERVER_PASSWORD" >&2
                echo ""
                echo "使用方式："
                echo "  bash csgoj_deploy.sh judge --CSGOJ_SERVER_BASE_URL=... --CSGOJ_SERVER_USERNAME=... --CSGOJ_SERVER_PASSWORD=... --rebuild-all"
                echo ""
                exit 1
            else
                # 交互模式：进入交互补全
                interactive_complete_judge_params
            fi
        fi
    fi
    
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  Judge Node 部署"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "  OJ 名称: $OJ_NAME"
    if [ "$RESTART_ALL" = false ]; then
        echo "  服务器地址: $CSGOJ_SERVER_BASE_URL"
        echo "  用户名: $CSGOJ_SERVER_USERNAME"
    fi
    echo ""
    
    # 确保 Docker 网络存在
    if [ -z "$(docker network ls | grep $DOCKER_NET_NAME)" ]; then
        docker network create $DOCKER_NET_NAME >/dev/null 2>&1
    fi
    
    # 处理重启所有 pod 的请求（仅重启，不重新创建）
    if [ "$RESTART_ALL" = true ]; then
        restart_all_judge_pods
        exit 0
    fi
    
    # 处理删除所有 pod 的请求（仅删除，不重新创建）
    if [ "$REMOVE_ALL" = true ]; then
        remove_all_judge_pods
        exit 0
    fi
    
    # 处理重开所有 pod 的请求（删除并重新创建）
    if [ "$REBUILD_ALL" = true ]; then
        rebuild_all_judge_pods
        # 继续执行后续的创建和启动流程
    fi
    
    # 验证资源并计算配置
    validate_and_calculate_resources $JUDGE_POD_COUNT
    
    # 计算 CPU 偏移量
    local CPU_OFFSET=$(calculate_cpu_offset $JUDGE_POD_COUNT $JUDGE_DOCKER_CPUS)
    
    # 查找可用的 pod 索引
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  查找可用 pod 索引"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    local POD_INDICES=($(find_available_pod_indices $JUDGE_POD_COUNT))
    echo "  将使用索引: ${POD_INDICES[*]}"
    echo ""
    
    # 启动所有 pod
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  启动评测机 pod"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # 将 JUDGE_POD_COUNT 设置为全局变量，供 start_single_pod 使用
    export JUDGE_POD_COUNT
    
    for idx in "${POD_INDICES[@]}"; do
        start_single_pod $idx $JUDGE_DOCKER_CPUS $JUDGE_DOCKER_MEMORY $CPU_OFFSET
    done
    
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  完成"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "已启动 ${#POD_INDICES[@]} 个评测机 pod"
    echo ""
    echo "查看日志:"
    echo "  docker logs -f judge-$OJ_NAME"
    if [ $JUDGE_POD_COUNT -gt 1 ]; then
        echo "  docker logs -f judge-$OJ_NAME-0"
        echo "  docker logs -f judge-$OJ_NAME-1"
    fi
    echo ""
    
    # 写入完整配置文件
    local config_file_path
    config_file_path=$(write_full_config_file "$DEFAULT_CONFIG" "judge")
    display_config_info "$config_file_path" "judge"
}

deploy_web() {
    # ==================== Web 模式 ====================
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  CSGOJ OJ Web Server 部署"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    # 确保 DEFAULT_CONFIG 已定义（从 parse_args.sh 加载，如果没有则使用默认值）
    DEFAULT_CONFIG="${DEFAULT_CONFIG:-./data/csgoj_config.cfg}"
    
    # 检查配置文件是否存在（用于判断是否需要进入交互式配置）
    # 注意：不再重新加载配置文件，因为 parse_args 已经处理了参数优先级：
    # 命令行参数 > 配置文件 > 默认值
    local config_exists=false
    if [ "$IGNORE_CONFIG" != true ] && [ -f "$DEFAULT_CONFIG" ]; then
        config_exists=true
    fi
    
    # 如果配置文件不存在，即使参数有默认值，也应该进入交互式配置以确认配置
    # 这样可以确保用户首次部署时能够看到并确认所有配置项
    if [ "$config_exists" = false ] && [ "$NONINTERACTIVE" != true ]; then
        interactive_configure_web_first_time
    fi
    
    # 安装 Docker（内部会检查是否已安装）
    install_docker
    
    # 创建 Docker 网络（如果不存在）
    if [ -z "$(docker network ls | grep "$DOCKER_NET_NAME")" ]; then
        docker network create "$DOCKER_NET_NAME" >/dev/null 2>&1
    fi
    
    # 启动所有服务（每个函数内部会检查容器是否存在，存在则跳过，不存在则启动）
    start_db          # 内部检查 WITH_MYSQL 和容器是否存在
    start_myadmin     # 内部检查容器是否存在
    start_ojweb       # 内部检查容器是否存在
    start_nginx       # 内部检查容器是否存在
    
    # 部署完成提示
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  部署完成"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "容器已启动，初始化需要一定时间，可用以下命令查看启动状态："
    echo "  docker logs php-$OJ_NAME"
    echo "  docker logs nginx-server"
    if [ "$WITH_MYSQL" = "1" ]; then
        echo "  docker logs db"
    fi
    echo ""
    echo "💡 首次访问 Web 页面时，系统将引导您设置管理员账号和评测机账号"
    echo ""
    
    # 写入完整配置文件
    local config_file_path
    config_file_path=$(write_full_config_file "$DEFAULT_CONFIG" "web")
    display_config_info "$config_file_path" "web"
    
    echo "💡 评测机部署："
    echo "   评测机需要在 Web 管理界面设置 judger 账号后，使用以下命令启动："
    echo ""
    echo "   bash csgoj_deploy.sh judge \\"
    echo "       --CSGOJ_SERVER_BASE_URL=http://<OJ服务器IP或域名>:${PORT_OJ} \\"
    echo "       --CSGOJ_SERVER_USERNAME=<评测机分配的账号> \\"
    echo "       --CSGOJ_SERVER_PASSWORD=<在Web界面设置的密码>"
    echo ""
    echo "   或使用交互式："
    echo "   bash csgoj_deploy.sh"
    echo ""
    echo "   ⚠️  注意：不要使用 localhost 或 127.0.0.1"
    echo ""
}

# 执行主函数
main "$@"
