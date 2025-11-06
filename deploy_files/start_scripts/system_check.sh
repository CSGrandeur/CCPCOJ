#!/bin/bash
# 系统环境检查脚本（专用于评测机部署）
# 用法: bash system_check.sh
# 说明：检查系统是否满足评测机部署要求，包括 Ubuntu 版本和系统资源监控功能支持
# 注意：此检查仅在部署评测机（judge）时执行，Web 服务器部署不需要这些检查

set -e

# 默认值（如果未从外部传入）
NONINTERACTIVE="${NONINTERACTIVE:-false}"

# ==================== 系统检查函数 ====================

# 检查 Ubuntu 版本是否为 22.04 以上
check_ubuntu_version() {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  检查系统版本"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    if [ ! -f /etc/os-release ]; then
        echo "❌ 错误: 无法检测系统版本（/etc/os-release 不存在）"
        echo "   此脚本仅支持 Ubuntu 系统"
        exit 1
    fi
    
    # 读取系统信息
    # shellcheck source=/dev/null
    source /etc/os-release
    
    if [ "$ID" != "ubuntu" ]; then
        echo "❌ 错误: 此脚本仅支持 Ubuntu 系统"
        echo "   当前系统: $ID"
        exit 1
    fi
    
    echo "  检测到系统: $PRETTY_NAME"
    echo "  版本号: $VERSION_ID"
    
    # 比较版本号（22.04 及以上）
    local major_version
    local minor_version
    major_version=$(echo "$VERSION_ID" | cut -d. -f1)
    minor_version=$(echo "$VERSION_ID" | cut -d. -f2)
    
    if [ -z "$minor_version" ]; then
        minor_version=0
    fi
    
    # 版本比较：需要 >= 22.04（评测机要求）
    if [ "$major_version" -lt 22 ] || \
       ([ "$major_version" -eq 22 ] && [ "$minor_version" -lt 4 ]); then
        echo ""
        echo "❌ 错误: Ubuntu 版本太低，不支持评测机部署"
        echo "   当前版本: Ubuntu $VERSION_ID"
        echo "   最低要求: Ubuntu 22.04 或更高版本（评测机需要）"
        echo ""
        echo "💡 建议: 请升级系统到 Ubuntu 22.04 或更高版本"
        echo ""
        exit 1
    fi
    
    echo "✅ Ubuntu 版本检查通过（${VERSION_ID} >= 22.04）"
    echo ""
}

# 检查 cgroup v2 是否启用
check_cgroup_v2_enabled() {
    # 检查 /proc/mounts 中是否有 cgroup2
    if grep -q "cgroup2" /proc/mounts 2>/dev/null; then
        return 0
    fi
    
    # 检查 /sys/fs/cgroup 是否为 cgroup v2（存在 memory.max 表示 v2）
    if [ -f /sys/fs/cgroup/memory.max ]; then
        return 0
    fi
    
    return 1
}

# 检查内核版本是否 >= 5.19
check_kernel_version_ge_5_19() {
    local kernel_version
    kernel_version=$(uname -r | cut -d. -f1,2)
    local kernel_major
    local kernel_minor
    kernel_major=$(echo "$kernel_version" | cut -d. -f1)
    kernel_minor=$(echo "$kernel_version" | cut -d. -f2)
    
    if [ -z "$kernel_minor" ]; then
        kernel_minor=0
    fi
    
    # 内核 >= 5.19 支持 memory.peak
    if [ "$kernel_major" -gt 5 ] || \
       ([ "$kernel_major" -eq 5 ] && [ "$kernel_minor" -ge 19 ]); then
        return 0
    fi
    
    return 1
}

# 检查内核是否支持 memory.peak
check_memory_peak_support() {
    # 方法1：检查根 cgroup 是否有 memory.peak（最简单，无需 root 权限，只需可读）
    # 在 cgroup v2 中，根 cgroup 位于 /sys/fs/cgroup
    if [ -f "/sys/fs/cgroup/memory.peak" ]; then
        if cat "/sys/fs/cgroup/memory.peak" >/dev/null 2>&1; then
            return 0
        fi
    fi
    
    # 方法2：检查当前进程的 cgroup 是否有 memory.peak（无需 root 权限）
    # cgroup v2 格式: 0::/path/to/cgroup
    local current_cgroup
    current_cgroup=$(cat /proc/self/cgroup 2>/dev/null | grep "^0::" | head -1 | cut -d: -f3)
    
    if [ -n "$current_cgroup" ] && [ "$current_cgroup" != "/" ]; then
        # 检查 /sys/fs/cgroup 下是否有 memory.peak 文件
        local memory_peak_path="/sys/fs/cgroup$current_cgroup/memory.peak"
        if [ -f "$memory_peak_path" ]; then
            # 尝试读取 memory.peak（如果内核支持，文件存在且可读）
            if cat "$memory_peak_path" >/dev/null 2>&1; then
                return 0
            fi
        fi
    fi
    
    # 方法3：检查是否有任何 cgroup 目录包含 memory.peak（作为备用检查）
    # 使用 find 命令查找（无需 root 权限，只需可读）
    if find /sys/fs/cgroup -maxdepth 2 -name "memory.peak" -readable 2>/dev/null | grep -q .; then
        return 0
    fi
    
    # 所有检查都失败，不支持
    return 1
}

# 升级内核
upgrade_kernel() {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  升级内核"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "⚠️  注意: 内核升级完成后需要重启系统才能生效"
    echo ""
    
    # 更新软件包列表
    echo "📦 更新软件包列表..."
    sudo apt-get update
    
    # 安装最新内核（包含 HWE - Hardware Enablement）
    echo ""
    echo "📦 安装最新内核..."
    sudo apt-get install -y linux-generic-hwe-22.04
    
    echo ""
    echo "✅ 内核升级完成"
    echo ""
    echo "⚠️  重要提示:"
    echo "   内核升级完成后，需要重启系统才能使用新内核"
    echo "   重启命令: sudo reboot"
    echo ""
    
    # 询问用户是否立即重启
    read -p "是否立即重启系统？(y/n, 默认: n): " reboot_now
    reboot_now=${reboot_now:-n}
    
    if [ "$reboot_now" = "y" ] || [ "$reboot_now" = "Y" ]; then
        echo ""
        echo "🔄 正在重启系统..."
        sudo reboot
    else
        echo ""
        echo "💡 请稍后手动重启系统以使用新内核:"
        echo "   sudo reboot"
        echo ""
    fi
}

# 检查系统资源监控功能支持
check_memory_peak() {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  检查系统资源监控功能"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # 首先检查 cgroup v2 是否启用
    if ! check_cgroup_v2_enabled; then
        echo "❌ 错误: 系统资源管理功能未启用"
        echo ""
        echo "💡 此功能是评测机部署的必要条件"
        echo "   评测机需要启用新的资源管理功能才能正常监控程序运行"
        echo ""
        echo "   启用方法（Ubuntu 22.04+）："
        echo "   1. 编辑 /etc/default/grub"
        echo "   2. 添加或修改: GRUB_CMDLINE_LINUX=\"systemd.unified_cgroup_hierarchy=1\""
        echo "   3. 执行: sudo update-grub"
        echo "   4. 重启系统"
        echo ""
        exit 1
    fi
    
    echo "✅ 系统资源管理功能已启用"
    
    # 检查内核是否支持 memory.peak
    local kernel_version
    kernel_version=$(uname -r)
    echo "  当前内核版本: $kernel_version"
    
    if check_memory_peak_support; then
        echo "✅ 内存监控功能正常"
        echo ""
        return 0
    fi
    
    # 不支持 memory.peak，检查内核版本
    # 判断是版本问题还是配置问题
    if check_kernel_version_ge_5_19; then
        # 内核版本足够新，但功能不可用，可能是配置问题或环境限制（如 WSL2）
        echo "❌ 内存监控功能不可用（可能是配置问题或环境限制）"
        echo ""
        echo "💡 评测机需要监控程序内存使用情况"
        echo "   当前内核: $kernel_version（版本已满足要求 >= 5.19）"
        echo ""
        
        # 检查是否是 WSL2 环境
        if grep -q "microsoft" /proc/version 2>/dev/null || \
           grep -q "WSL2" <<< "$kernel_version"; then
            echo "⚠️  检测到 WSL2 环境"
            echo "   WSL2 可能不支持完整的 cgroup 功能，这是已知限制"
            echo "   建议在原生 Linux 环境中部署评测机"
            echo ""
        else
            echo "   可能的原因："
            echo "   1. cgroup 配置问题"
            echo "   2. 权限不足，无法创建测试 cgroup"
            echo "   3. 系统限制"
            echo ""
        fi
    else
        # 内核版本确实太旧
        echo "❌ 系统内核版本较旧，不支持完整的内存监控功能"
        echo ""
        echo "💡 评测机需要监控程序内存使用情况，需要较新的内核版本"
        echo "   当前内核: $kernel_version"
        echo "   需要版本: 5.19 或更高（评测机需要）"
        echo ""
        echo "   可以通过升级内核来解决："
        echo "   - Ubuntu 22.04: 自动安装最新内核"
        echo ""
    fi
    
    # 根据内核版本决定处理方式
    if check_kernel_version_ge_5_19; then
        # 内核版本足够新，但功能不可用，可能是环境限制（如 WSL2）
        echo "⚠️  警告: 内存监控功能不可用，评测机部署可能无法正常工作"
        echo ""
        
        if [ "$NONINTERACTIVE" != "true" ]; then
            read -p "是否继续部署？(y/n, 默认: n): " continue_deploy
            continue_deploy=${continue_deploy:-n}
            
            if [ "$continue_deploy" != "y" ] && [ "$continue_deploy" != "Y" ]; then
                echo ""
                echo "❌ 部署已取消"
                exit 1
            fi
        else
            # 非交互模式，直接报错
            exit 1
        fi
    else
        # 内核版本确实太旧，需要升级
        if [ "$NONINTERACTIVE" != "true" ]; then
            read -p "是否现在升级内核？(y/n, 默认: y): " upgrade_kernel_confirm
            upgrade_kernel_confirm=${upgrade_kernel_confirm:-y}
            
            if [ "$upgrade_kernel_confirm" = "y" ] || [ "$upgrade_kernel_confirm" = "Y" ]; then
                upgrade_kernel
                echo ""
                echo "⚠️  系统检查已暂停，请重启系统后重新运行部署脚本"
                exit 0
            else
                echo ""
                echo "⚠️  警告: 未升级内核，评测机部署可能无法正常工作"
                echo "   如需升级内核，请稍后运行："
                echo "   sudo apt-get update"
                echo "   sudo apt-get install -y linux-generic-hwe-22.04"
                echo "   sudo reboot"
                echo ""
                exit 1
            fi
        else
            # 非交互模式，直接报错
            echo "❌ 错误: 系统内核版本较旧，无法为评测机提供完整的内存监控功能"
            echo ""
            echo "   请手动升级内核："
            echo "   sudo apt-get update"
            echo "   sudo apt-get install -y linux-generic-hwe-22.04"
            echo "   sudo reboot"
            echo ""
            exit 1
        fi
    fi
}

# 主检查函数（评测机部署专用）
system_check() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  评测机系统环境检查"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    # 检查 Ubuntu 版本（评测机需要 22.04 以上）
    check_ubuntu_version
    
    # 检查系统资源监控功能支持（评测机需要监控程序内存使用情况）
    check_memory_peak
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  系统检查完成"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "✅ 所有检查通过，系统满足部署要求"
    echo ""
}

# ==================== 独立执行逻辑 ====================
# 如果作为独立脚本执行（不是被 source）
if [ "${BASH_SOURCE[0]}" = "${0}" ]; then
    system_check
fi

