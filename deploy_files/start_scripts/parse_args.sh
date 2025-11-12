#!/bin/bash
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

# 参数提供标志（用于判断用户是否明确提供了某些参数）
PROVIDED_BELONG_TO=false

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
                PROVIDED_BELONG_TO=true
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
    # 如果用户没有明确提供 BELONG_TO（通过命令行参数），则设置为 OJ_NAME
    # 注意：PROVIDED_BELONG_TO 在命令行参数解析时设置（第 309 行）
    # 如果用户没有通过命令行提供 --BELONG_TO，PROVIDED_BELONG_TO 保持为 false
    # 此时无论 BELONG_TO 的值是什么（可能是配置文件中的旧值），都应该更新为 OJ_NAME
    if [ "$PROVIDED_BELONG_TO" != "true" ]; then
        BELONG_TO="$OJ_NAME"
    elif [ "$BELONG_TO" = "0" ] || [ -z "$BELONG_TO" ]; then
        # 如果用户明确提供了 BELONG_TO，但值为 0 或空，也设置为 OJ_NAME（防御性处理）
        BELONG_TO="$OJ_NAME"
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
EOF
        # 只有当用户提供了 BELONG_TO 参数时，才写入配置文件
        if [ "$PROVIDED_BELONG_TO" = "true" ]; then
            echo "BELONG_TO=\"${belong_to_value}\"" >> "$target_config_file"
        fi
        
        cat >> "$target_config_file" <<EOF

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
# 注意：这些初始化函数包含副作用操作（创建目录、创建网络等）
# 根据设计原则（第 7 行），parse_args.sh 不包含副作用操作，这些由调用方控制
# 
# 不在脚本加载时自动执行，原因：
#   1. 此时 PATH_DATA 等参数可能还是默认值，会在错误位置创建目录
#   2. 参数解析顺序：默认值 -> 配置文件 -> 命令行参数
#   3. 自动初始化会在参数解析之前执行，使用了错误的参数值
#
# 各脚本在需要时自己处理初始化：
#   - init_config_log: 仅在 write_config_if_changed 中调用（可选功能）
#   - init_docker_network: 各脚本在需要时自己创建网络（如 start_judge2.sh）
#   - init_data_directory: 各脚本在需要时自己创建目录（如 start_db.sh, start_nginx.sh）
#
# 如果需要显式调用，应在 parse_args 之后：
#   source parse_args.sh
#   parse_args "$@"
#   # 此时 PATH_DATA 等参数已正确解析，可以安全调用初始化函数
#   init_data_directory  # 如果需要

