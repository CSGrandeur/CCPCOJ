#!/bin/bash
# CSGOJ 评测机启动脚本
# 自动资源管理，用户只需提供基本连接信息
# 用法: bash start_judge2.sh [选项...]
# 使用 --help 查看所有可用选项

set -e

# ==================== Help 函数 ====================
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

# 默认值
CSGOJ_SERVER_BASE_URL=''
CSGOJ_SERVER_USERNAME=''
CSGOJ_SERVER_PASSWORD=''
JUDGE_POD_COUNT=1
RESTART_ALL=false
REBUILD_ALL=false
REMOVE_ALL=false
NONINTERACTIVE=false
PATH_DATA="${PATH_DATA:-$(pwd)/data/csgoj_data}"
OJ_NAME="${OJ_NAME:-ccpc}"
CSGOJ_VERSION="${CSGOJ_VERSION:-latest}"
DOCKER_NET_NAME="${DOCKER_NET_NAME:-csgoj_net}"
DOCKER_PULL_NEW="${DOCKER_PULL_NEW:-1}"
LINK_LOCAL="--network $DOCKER_NET_NAME"

# ==================== 参数解析 ====================
# 检查 help
for arg in "$@"; do
    if [ "$arg" = "--help" ] || [ "$arg" = "-h" ] || [ "$arg" = "-H" ]; then
        show_help
        exit 0
    fi
done

# 解析参数
for arg in "$@"; do
    case "$arg" in
        --CSGOJ_SERVER_BASE_URL=*) CSGOJ_SERVER_BASE_URL="${arg#*=}";;
        --CSGOJ_SERVER_USERNAME=*) CSGOJ_SERVER_USERNAME="${arg#*=}";;
        --CSGOJ_SERVER_PASSWORD=*) CSGOJ_SERVER_PASSWORD="${arg#*=}";;
        --JUDGE_POD_COUNT=*) JUDGE_POD_COUNT="${arg#*=}";;
        --restart-all) RESTART_ALL=true;;
        --rebuild-all) REBUILD_ALL=true;;
        --remove-all|--delete-all) REMOVE_ALL=true;;
        --noninteractive|--no-interactive) NONINTERACTIVE=true;;
        --PATH_DATA=*) PATH_DATA="${arg#*=}";;
        --OJ_NAME=*) OJ_NAME="${arg#*=}";;
        --CSGOJ_VERSION=*) CSGOJ_VERSION="${arg#*=}";;
        --DOCKER_NET_NAME=*) DOCKER_NET_NAME="${arg#*=}"; LINK_LOCAL="--network $DOCKER_NET_NAME";;
        --DOCKER_PULL_NEW=*) DOCKER_PULL_NEW="${arg#*=}";;
        --LINK_LOCAL=*) LINK_LOCAL="${arg#*=}";;
        *)
            echo "❌ 未知参数: '$arg'，使用 --help 查看帮助信息" >&2
            exit 1
            ;;
    esac
done

# ==================== 交互式补全函数 ====================

# Judge 模式必要参数交互补全（复用 configure.sh 的函数）
interactive_complete_judge_params() {
    # 加载 configure.sh 中的函数（如果未加载）
    if ! type interactive_configure_judge >/dev/null 2>&1; then
        SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
        source "${SCRIPT_DIR}/configure.sh"
    fi
    
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  缺少必要参数，进入交互式配置"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # 调用 configure.sh 中的函数，传递 "skip" 参数以跳过已提供的参数
    interactive_configure_judge "skip"
}

# 验证必需参数（重启模式和删除模式不需要启动参数）
if [ "$RESTART_ALL" = false ] && [ "$REBUILD_ALL" = false ] && [ "$REMOVE_ALL" = false ]; then
    if [ -z "$CSGOJ_SERVER_BASE_URL" ] || [ -z "$CSGOJ_SERVER_USERNAME" ] || [ -z "$CSGOJ_SERVER_PASSWORD" ] || [ -z "$PATH_DATA" ]; then
        if [ "$NONINTERACTIVE" = true ]; then
            # 非交互模式：直接报错
            echo "❌ 错误: 必须提供 CSGOJ_SERVER_BASE_URL、CSGOJ_SERVER_USERNAME、CSGOJ_SERVER_PASSWORD 和 PATH_DATA" >&2
            echo ""
            echo "使用方式："
            echo "  bash start_judge2.sh --CSGOJ_SERVER_BASE_URL=... --CSGOJ_SERVER_USERNAME=... --CSGOJ_SERVER_PASSWORD=... --PATH_DATA=..."
            echo ""
            echo "或使用交互式（不使用 --noninteractive）："
            echo "  bash start_judge2.sh"
            echo ""
            exit 1
        else
            # 交互模式：进入交互补全
            interactive_complete_judge_params
        fi
    fi
elif [ "$REBUILD_ALL" = true ]; then
    # 重开模式需要启动参数
    if [ -z "$CSGOJ_SERVER_BASE_URL" ] || [ -z "$CSGOJ_SERVER_USERNAME" ] || [ -z "$CSGOJ_SERVER_PASSWORD" ] || [ -z "$PATH_DATA" ]; then
        if [ "$NONINTERACTIVE" = true ]; then
            # 非交互模式：直接报错
            echo "❌ 错误: 重开模式（--rebuild-all）需要提供启动参数：CSGOJ_SERVER_BASE_URL、CSGOJ_SERVER_USERNAME、CSGOJ_SERVER_PASSWORD 和 PATH_DATA" >&2
            echo ""
            echo "使用方式："
            echo "  bash start_judge2.sh --CSGOJ_SERVER_BASE_URL=... --CSGOJ_SERVER_USERNAME=... --CSGOJ_SERVER_PASSWORD=... --PATH_DATA=... --rebuild-all"
            echo ""
            exit 1
        else
            # 交互模式：进入交互补全
            interactive_complete_judge_params
        fi
    fi
fi

# 确保 Docker 网络存在
if command -v docker &> /dev/null; then
    if [ -z "$(docker network ls | grep $DOCKER_NET_NAME)" ]; then
        docker network create $DOCKER_NET_NAME >/dev/null 2>&1
    fi
fi

# ==================== 资源自动计算函数 ====================

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
    
    # 显示当前 CPU 绑核情况
    display_cpu_bindings
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
        # 其它情况：固定每个 pod 用 4 核心，固定偏移 2
        # 考虑所有评测机容器（不仅仅是当前 OJ_NAME）的绑核情况
        get_used_cpus
        local used_cpu_count=${#USED_CPUS[@]}
        local fixed_cpus_per_pod=4
        local fixed_offset=2
        # 可用 CPU = 总CPU - 固定偏移 - 已占用的CPU（所有评测机容器）
        local available_after_offset=$((total_cpus - fixed_offset - used_cpu_count))
        if [ $fixed_cpus_per_pod -gt 0 ] && [ $available_after_offset -gt 0 ]; then
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

# ==================== CPU 绑核查询和管理函数 ====================

# 查询所有评测机容器的 CPU 绑核情况
# 返回格式：每行一个容器，格式为 "容器名:核心列表"（如 "judge-ccpc-0:2,3,4,5"）
get_all_judge_cpu_bindings() {
    local all_judge_containers=$(docker ps -a --filter "name=judge-" --format "{{.Names}}" 2>/dev/null || true)
    local bindings=()
    
    for container in $all_judge_containers; do
        # 获取容器的 cpuset-cpus 配置
        # 方法1: 从 docker inspect 获取 HostConfig.CpusetCpus
        local cpuset=$(docker inspect "$container" --format='{{.HostConfig.CpusetCpus}}' 2>/dev/null || echo "")
        
        # 如果 cpuset 不为空，记录绑定信息
        if [ -n "$cpuset" ] && [ "$cpuset" != "<no value>" ]; then
            bindings+=("$container:$cpuset")
        fi
    done
    
    # 输出所有绑定信息（每行一个）
    for binding in "${bindings[@]}"; do
        echo "$binding"
    done
}

# 解析 CPU 列表字符串（如 "2,3,4,5" 或 "2-5"）为单个核心编号数组
# 参数: CPU 列表字符串
# 返回: 核心编号数组（通过 echo 输出，空格分隔）
parse_cpu_list() {
    local cpu_list="$1"
    local cpus=()
    
    # 处理逗号分隔的列表（如 "2,3,4,5"）
    if [[ "$cpu_list" == *","* ]]; then
        IFS=',' read -ra cpu_parts <<< "$cpu_list"
        for part in "${cpu_parts[@]}"; do
            # 处理范围（如 "2-5"）
            if [[ "$part" == *"-"* ]]; then
                local start=$(echo "$part" | cut -d'-' -f1)
                local end=$(echo "$part" | cut -d'-' -f2)
                for ((cpu=start; cpu<=end; cpu++)); do
                    cpus+=($cpu)
                done
            else
                cpus+=($part)
            fi
        done
    # 处理范围格式（如 "2-5"）
    elif [[ "$cpu_list" == *"-"* ]]; then
        local start=$(echo "$cpu_list" | cut -d'-' -f1)
        local end=$(echo "$cpu_list" | cut -d'-' -f2)
        for ((cpu=start; cpu<=end; cpu++)); do
            cpus+=($cpu)
        done
    # 单个核心
    else
        cpus+=($cpu_list)
    fi
    
    # 去重并排序，返回空格分隔的列表（去除末尾换行）
    printf '%s\n' "${cpus[@]}" | sort -n | uniq | tr '\n' ' ' | sed 's/[[:space:]]*$//'
}

# 获取所有已绑定的 CPU 核心（所有评测机容器）
# 返回: 已绑定的核心编号数组（通过全局变量 USED_CPUS 返回）
# 注意: 包括所有评测机容器（包括当前 OJ_NAME 的已存在容器），用于避免绑核冲突
get_used_cpus() {
    local bindings=$(get_all_judge_cpu_bindings)
    local all_used_cpus=()
    
    # 解析每个容器的绑核信息
    while IFS= read -r binding; do
        if [ -z "$binding" ]; then
            continue
        fi
        
        local container_name=$(echo "$binding" | cut -d':' -f1)
        local cpu_list=$(echo "$binding" | cut -d':' -f2)
        
        # 解析 CPU 列表（包括所有评测机容器，避免不同 OJ_NAME 之间的冲突）
        local parsed_cpus=($(parse_cpu_list "$cpu_list"))
        all_used_cpus+=("${parsed_cpus[@]}")
    done <<< "$bindings"
    
    # 去重并排序
    USED_CPUS=($(printf '%s\n' "${all_used_cpus[@]}" | sort -n | uniq))
}

# 查找可用的 CPU 核心范围
# 参数: $1=需要的核心数量, $2=CPU 偏移量（起始搜索位置）
# 返回: 可用的核心范围（通过 echo 输出，格式如 "2,3,4,5"）
find_available_cpu_range() {
    local needed_cpus=$1
    local cpu_offset=$2
    local total_cpus=$(nproc)
    
    # 获取所有已使用的核心
    get_used_cpus
    
    # 将已使用的核心转换为关联数组以便快速查找
    declare -A used_cpu_map
    for cpu in "${USED_CPUS[@]}"; do
        used_cpu_map[$cpu]=1
    done
    
    # 从偏移量开始查找连续可用的核心
    local found_start=-1
    local found_count=0
    
    for ((cpu=cpu_offset; cpu<total_cpus; cpu++)); do
        if [ -z "${used_cpu_map[$cpu]}" ]; then
            # 这个核心可用
            if [ $found_start -eq -1 ]; then
                found_start=$cpu
                found_count=1
            else
                ((found_count++))
            fi
            
            # 如果找到了足够的核心
            if [ $found_count -eq $needed_cpus ]; then
                # 生成核心列表
                if [ $needed_cpus -eq 1 ]; then
                    echo "$found_start"
                else
                    local cpu_end=$((found_start + needed_cpus - 1))
                    echo "$found_start-$cpu_end"
                fi
                return 0
            fi
        else
            # 这个核心已被使用，重置查找
            found_start=-1
            found_count=0
        fi
    done
    
    # 没有找到足够的连续核心，尝试查找非连续的核心
    local found_cpus=()
    for ((cpu=cpu_offset; cpu<total_cpus && ${#found_cpus[@]} -lt needed_cpus; cpu++)); do
        if [ -z "${used_cpu_map[$cpu]}" ]; then
            found_cpus+=($cpu)
        fi
    done
    
    if [ ${#found_cpus[@]} -eq $needed_cpus ]; then
        # 生成逗号分隔的列表
        local IFS=','
        echo "${found_cpus[*]}"
        unset IFS
        return 0
    fi
    
    # 没有找到足够的核心
    return 1
}

# 显示当前所有评测机的 CPU 绑核情况
display_cpu_bindings() {
    local bindings=$(get_all_judge_cpu_bindings)
    
    if [ -z "$bindings" ]; then
        echo "  当前没有评测机容器绑定 CPU"
        return
    fi
    
    echo "  当前评测机 CPU 绑核情况:"
    while IFS= read -r binding; do
        if [ -z "$binding" ]; then
            continue
        fi
        
        local container_name=$(echo "$binding" | cut -d':' -f1)
        local cpu_list=$(echo "$binding" | cut -d':' -f2)
        echo "    $container_name: $cpu_list"
    done <<< "$bindings"
}

# ==================== Pod 管理函数 ====================

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
    
    # CPU 绑定配置（使用新的智能绑核逻辑，避免与其他 OJ_NAME 冲突）
    CPUSET_CONFIG=""
    if [ $cpu_offset -gt 0 ]; then
        local total_cpus=$(nproc)
        
        # 使用新的绑核逻辑：查找可用的 CPU 核心范围（考虑所有评测机容器）
        local available_cpu_range=$(find_available_cpu_range $cpus $cpu_offset)
        
        if [ $? -ne 0 ] || [ -z "$available_cpu_range" ]; then
            echo "  ❌ 错误: 无法为 Pod $pod_index 找到足够的可用 CPU 核心"
            echo "     需要核心数: $cpus"
            echo "     系统CPU总数: $total_cpus (0-$((total_cpus - 1)))"
            echo "     起始搜索位置: $cpu_offset"
            echo ""
            
            # 显示当前绑核情况
            echo "  当前 CPU 绑核情况:"
            display_cpu_bindings
            echo ""
            
            echo "💡 建议:"
            echo "   1. 减少 pod 数量"
            echo "   2. 减少每个 pod 的 CPU 数量"
            echo "   3. 删除其他评测机容器释放 CPU 核心"
            echo "   4. 使用更大的系统"
            return 1
        fi
        
        # 解析可用核心范围，生成 cpuset-cpus 格式
        # available_cpu_range 可能是 "2-5" 或 "2,3,4,5" 格式
        CPUSET_CPUS="$available_cpu_range"
        CPUSET_CONFIG="--cpuset-cpus=$CPUSET_CPUS"
        
        # 显示绑定的核心信息
        local parsed_cpus=($(parse_cpu_list "$available_cpu_range"))
        local cpu_count=${#parsed_cpus[@]}
        if [ $cpu_count -eq 1 ]; then
            echo "  📌 CPU 绑定: 逻辑核心 ${parsed_cpus[0]} (共 1 个核心)"
        else
            local cpu_start=${parsed_cpus[0]}
            local cpu_end=${parsed_cpus[$((cpu_count - 1))]}
            if [ $cpu_end -eq $((cpu_start + cpu_count - 1)) ]; then
                # 连续范围
                echo "  📌 CPU 绑定: 逻辑核心 $cpu_start-$cpu_end (共 $cpu_count 个核心)"
            else
                # 非连续范围
                echo "  📌 CPU 绑定: 逻辑核心 $CPUSET_CPUS (共 $cpu_count 个核心，非连续)"
            fi
        fi
    else
        echo "  📌 CPU 绑定: 未指定（使用系统自动分配）"
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
    # 先检查本地是否已存在
    if docker image inspect "$target_image" >/dev/null 2>&1 && \
       docker run --rm --entrypoint /bin/sh "$target_image" -c "echo test" >/dev/null 2>&1; then
        judge_image="$target_image"
        echo "  ✅ 使用镜像: $target_image (本地存在)"
    else
        # 本地不存在，尝试拉取（显示进度）
        echo "  📥 正在拉取镜像: $target_image"
        echo "     请稍候，这可能需要一些时间..."
        echo ""
        if docker pull "$target_image"; then
            judge_image="$target_image"
            echo ""
            echo "  ✅ 镜像拉取成功: $target_image"
        else
            echo ""
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
    fi
    
    # 启动容器
    # 提取内存数值（去除单位，如 "4g" -> "4"）
    local memory_gb=$(echo "$memory" | sed 's/[^0-9]//g')
    local total_memory=$((memory_gb + 1))  # 加上 1GB SHM
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  启动 Pod: $CONTAINER_NAME"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    if [ -n "$CPUSET_CPUS" ]; then
        echo "  CPU: ${cpus} 核心 (绑定: $CPUSET_CPUS)"
    else
        echo "  CPU: ${cpus} 核心 (未绑定)"
    fi
    echo "  内存: ${memory} + 1GB SHM = ${total_memory}GB 总计"
    
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

# ==================== 主流程 ====================

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  评测机启动脚本 v2.0"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "  OJ 名称: $OJ_NAME"
if [ "$RESTART_ALL" = false ]; then
    echo "  服务器地址: $CSGOJ_SERVER_BASE_URL"
    echo "  用户名: $CSGOJ_SERVER_USERNAME"
fi
echo ""

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
CPU_OFFSET=$(calculate_cpu_offset $JUDGE_POD_COUNT $JUDGE_DOCKER_CPUS)

# 查找可用的 pod 索引
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  查找可用 pod 索引"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
POD_INDICES=($(find_available_pod_indices $JUDGE_POD_COUNT))
echo "  将使用索引: ${POD_INDICES[*]}"
echo ""

# 启动所有 pod
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  启动评测机 pod"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

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

# 写入完整配置文件（仅在非重启模式时写入）
if [ "$RESTART_ALL" = false ]; then
    local config_file_path="${SCRIPT_DIR:-$(pwd)}/data/csgoj_config.cfg"
    
    # 确保目录存在
    local config_dir=$(dirname "$config_file_path")
    if [ -n "$config_dir" ] && [ "$config_dir" != "." ]; then
        mkdir -p "$config_dir"
    fi
    
    # 写入完整配置文件
    cat > "$config_file_path" <<EOF
# CSGOJ 部署配置文件
# 生成时间: $(date '+%Y-%m-%d %H:%M:%S')
# 部署内容: Judge Node
# 
# 说明：
#   - 此文件包含所有配置参数（包括默认值）
#   - 下次运行时不提供参数，将自动使用此配置文件
#   - 参数优先级：命令行参数 > 此配置文件 > 默认值

# ==================== 基础配置 ====================
PATH_DATA="$PATH_DATA"
OJ_NAME="$OJ_NAME"
CSGOJ_VERSION="$CSGOJ_VERSION"
DOCKER_NET_NAME="$DOCKER_NET_NAME"
DOCKER_PULL_NEW=$DOCKER_PULL_NEW
LINK_LOCAL="--network $DOCKER_NET_NAME"

# ==================== Judge Node 配置 ====================
CSGOJ_SERVER_BASE_URL="$CSGOJ_SERVER_BASE_URL"
CSGOJ_SERVER_USERNAME="$CSGOJ_SERVER_USERNAME"
CSGOJ_SERVER_PASSWORD="$CSGOJ_SERVER_PASSWORD"
JUDGE_POD_COUNT=${JUDGE_POD_COUNT:-1}
EOF
    
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  配置文件已保存"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "📁 配置文件路径: $config_file_path"
    echo ""
    echo "💡 下次运行说明："
    echo "   • 如果不提供参数，将自动使用此配置文件中的配置"
    echo "   • 使用方式: bash start_judge2.sh"
    echo ""
    echo "📋 当前配置摘要："
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  部署内容: Judge Node"
    echo "  服务器地址: $CSGOJ_SERVER_BASE_URL"
    echo "  用户名: $CSGOJ_SERVER_USERNAME"
    echo "  Pod 数量: $JUDGE_POD_COUNT"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
fi

