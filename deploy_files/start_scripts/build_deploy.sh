#!/bin/bash
# 构建合并部署脚本
# 将所有脚本合并为单一 csgoj_deploy.sh 文件
# 用法: bash build_deploy.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUTPUT_FILE="${SCRIPT_DIR}/csgoj_deploy.sh"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  构建合并部署脚本"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# 开始构建
cat > "$OUTPUT_FILE" <<'DEPLOY_HEADER'
#!/bin/bash
# CSGOJ 统一部署脚本
# 此脚本由 build_deploy.sh 自动生成，包含所有部署逻辑
# 生成时间: 
DEPLOY_HEADER

# 添加生成时间
sed -i "s/# 生成时间: /# 生成时间: $(date '+%Y-%m-%d %H:%M:%S')/" "$OUTPUT_FILE"

# 读取并嵌入 parse_args.sh（去除 shebang）
echo "  嵌入 parse_args.sh..."
sed -n '2,$p' "${SCRIPT_DIR}/parse_args.sh" >> "$OUTPUT_FILE"

# 添加分隔注释
cat >> "$OUTPUT_FILE" <<'SEPARATOR'

# ==================== 函数定义区域 ====================

SEPARATOR

# 提取 install_docker 函数
echo "  提取 install_docker 函数..."
{
    # 找到函数开始行号
    func_start=$(grep -n "^install_docker() {" "${SCRIPT_DIR}/install_docker.sh" | cut -d: -f1)
    # 找到函数结束行号（使用括号匹配）
    func_end=$(awk -v start="$func_start" '
        BEGIN { depth=0; found_start=0 }
        NR>=start {
            if (/^install_docker\(\) \{/) { found_start=1; depth=1; next }
            if (found_start) {
                line = $0
                gsub(/[^{]/, "", line)
                open_count = length(line)
                line = $0
                gsub(/[^}]/, "", line)
                close_count = length(line)
                depth += open_count - close_count
                line = $0
                gsub(/^[[:space:]]*/, "", line)
                gsub(/[[:space:]]*$/, "", line)
                if (depth == 0 && line == "}") {
                    print NR
                    exit
                }
            }
        }
    ' "${SCRIPT_DIR}/install_docker.sh" | head -1)
    # 验证 func_end 是否有效
    if [ -z "$func_end" ] || [ ! "$func_end" -ge "$func_start" ]; then
        echo "❌ 错误: 无法找到 install_docker 函数的结束位置" >&2
        exit 1
    fi
    # 提取函数体（包含开始和结束行）
    sed -n "${func_start},${func_end}p" "${SCRIPT_DIR}/install_docker.sh" >> "$OUTPUT_FILE"
}
echo "" >> "$OUTPUT_FILE"

# 提取 start_db 函数
echo "  提取 start_db 函数..."
{
    # 找到函数开始行号
    func_start=$(grep -n "^start_db() {" "${SCRIPT_DIR}/start_db.sh" | cut -d: -f1)
    # 找到函数结束行号（使用括号匹配）
    func_end=$(awk -v start="$func_start" '
        BEGIN { depth=0; found_start=0 }
        NR>=start {
            if (/^start_db\(\) \{/) { found_start=1; depth=1; next }
            if (found_start) {
                # 计算当前行的 { 和 } 数量
                line = $0
                gsub(/[^{]/, "", line)
                open_count = length(line)
                line = $0
                gsub(/[^}]/, "", line)
                close_count = length(line)
                depth += open_count - close_count
                # 检查是否是独立的 }（去掉前后空白后只有 }）
                line = $0
                gsub(/^[[:space:]]*/, "", line)
                gsub(/[[:space:]]*$/, "", line)
                if (depth == 0 && line == "}") {
                    print NR
                    exit
                }
            }
        }
    ' "${SCRIPT_DIR}/start_db.sh" | head -1)
    # 验证 func_end 是否有效
    if [ -z "$func_end" ] || [ ! "$func_end" -ge "$func_start" ]; then
        echo "❌ 错误: 无法找到 start_db 函数的结束位置" >&2
        exit 1
    fi
    # 提取函数体（包含开始和结束行）
    sed -n "${func_start},${func_end}p" "${SCRIPT_DIR}/start_db.sh" >> "$OUTPUT_FILE"
}
echo "" >> "$OUTPUT_FILE"

# 提取 start_myadmin 函数
echo "  提取 start_myadmin 函数..."
{
    # 找到函数开始行号
    func_start=$(grep -n "^start_myadmin() {" "${SCRIPT_DIR}/start_myadmin.sh" | cut -d: -f1)
    # 找到函数结束行号（使用括号匹配）
    func_end=$(awk -v start="$func_start" '
        BEGIN { depth=0; found_start=0 }
        NR>=start {
            if (/^start_myadmin\(\) \{/) { found_start=1; depth=1; next }
            if (found_start) {
                line = $0
                gsub(/[^{]/, "", line)
                open_count = length(line)
                line = $0
                gsub(/[^}]/, "", line)
                close_count = length(line)
                depth += open_count - close_count
                line = $0
                gsub(/^[[:space:]]*/, "", line)
                gsub(/[[:space:]]*$/, "", line)
                if (depth == 0 && line == "}") {
                    print NR
                    exit
                }
            }
        }
    ' "${SCRIPT_DIR}/start_myadmin.sh" | head -1)
    # 验证 func_end 是否有效
    if [ -z "$func_end" ] || [ ! "$func_end" -ge "$func_start" ]; then
        echo "❌ 错误: 无法找到 start_myadmin 函数的结束位置" >&2
        exit 1
    fi
    # 提取函数体（包含开始和结束行）
    sed -n "${func_start},${func_end}p" "${SCRIPT_DIR}/start_myadmin.sh" >> "$OUTPUT_FILE"
}
echo "" >> "$OUTPUT_FILE"

# 提取 start_ojweb 函数
echo "  提取 start_ojweb 函数..."
{
    # 找到函数开始行号
    func_start=$(grep -n "^start_ojweb() {" "${SCRIPT_DIR}/start_ojweb.sh" | cut -d: -f1)
    # 找到函数结束行号（使用括号匹配）
    func_end=$(awk -v start="$func_start" '
        BEGIN { depth=0; found_start=0 }
        NR>=start {
            if (/^start_ojweb\(\) \{/) { found_start=1; depth=1; next }
            if (found_start) {
                line = $0
                gsub(/[^{]/, "", line)
                open_count = length(line)
                line = $0
                gsub(/[^}]/, "", line)
                close_count = length(line)
                depth += open_count - close_count
                line = $0
                gsub(/^[[:space:]]*/, "", line)
                gsub(/[[:space:]]*$/, "", line)
                if (depth == 0 && line == "}") {
                    print NR
                    exit
                }
            }
        }
    ' "${SCRIPT_DIR}/start_ojweb.sh" | head -1)
    # 验证 func_end 是否有效
    if [ -z "$func_end" ] || [ ! "$func_end" -ge "$func_start" ]; then
        echo "❌ 错误: 无法找到 start_ojweb 函数的结束位置" >&2
        exit 1
    fi
    # 提取函数体（包含开始和结束行）
    sed -n "${func_start},${func_end}p" "${SCRIPT_DIR}/start_ojweb.sh" >> "$OUTPUT_FILE"
}
echo "" >> "$OUTPUT_FILE"

# 提取 start_nginx 函数
echo "  提取 start_nginx 函数..."
{
    # 找到函数开始行号
    func_start=$(grep -n "^start_nginx() {" "${SCRIPT_DIR}/start_nginx.sh" | cut -d: -f1)
    # 找到函数结束行号（使用括号匹配）
    func_end=$(awk -v start="$func_start" '
        BEGIN { depth=0; found_start=0 }
        NR>=start {
            if (/^start_nginx\(\) \{/) { found_start=1; depth=1; next }
            if (found_start) {
                line = $0
                gsub(/[^{]/, "", line)
                open_count = length(line)
                line = $0
                gsub(/[^}]/, "", line)
                close_count = length(line)
                depth += open_count - close_count
                line = $0
                gsub(/^[[:space:]]*/, "", line)
                gsub(/[[:space:]]*$/, "", line)
                if (depth == 0 && line == "}") {
                    print NR
                    exit
                }
            }
        }
    ' "${SCRIPT_DIR}/start_nginx.sh" | head -1)
    # 验证 func_end 是否有效
    if [ -z "$func_end" ] || [ ! "$func_end" -ge "$func_start" ]; then
        echo "❌ 错误: 无法找到 start_nginx 函数的结束位置" >&2
        exit 1
    fi
    # 提取函数体（包含开始和结束行）
    sed -n "${func_start},${func_end}p" "${SCRIPT_DIR}/start_nginx.sh" >> "$OUTPUT_FILE"
}
echo "" >> "$OUTPUT_FILE"

# 提取 start_judge2.sh 的所有函数（只提取函数定义，不包括变量定义、参数解析和主流程代码）
echo "  提取评测机相关函数..."
# 读取 start_judge2.sh，只提取函数定义部分
{
    # 找到第一个函数开始（show_help）
    func_start=$(grep -n "^show_help() {" "${SCRIPT_DIR}/start_judge2.sh" | cut -d: -f1)
    # 找到主流程开始（"# ==================== 主流程 ===================="）
    main_start=$(grep -n "^# ==================== 主流程 ====================" "${SCRIPT_DIR}/start_judge2.sh" | cut -d: -f1)
    
    if [ -n "$func_start" ] && [ -n "$main_start" ]; then
        # 提取从 show_help 到主流程之前的所有函数
        # 包括：show_help、资源计算函数、Pod 管理函数等，但不包括 interactive_complete_judge_params
        sed -n "${func_start},$((main_start-1))p" "${SCRIPT_DIR}/start_judge2.sh" | \
        # 先跳过 interactive_complete_judge_params 函数（会在后面重新定义）
        awk '
            BEGIN { skip_func=0; brace_depth=0 }
            /^interactive_complete_judge_params\(\) \{/ {
                skip_func=1
                brace_depth=1
                next
            }
            {
                if (skip_func) {
                    # 计算大括号深度
                    for (i=1; i<=length($0); i++) {
                        char = substr($0, i, 1)
                        if (char == "{") brace_depth++
                        if (char == "}") {
                            brace_depth--
                            if (brace_depth == 0) {
                                skip_func=0
                                next
                            }
                        }
                    }
                    next
                }
                print
            }
        ' | \
        # 过滤掉变量定义和参数解析代码（这些不是函数，会在脚本加载时执行）
        awk '
            BEGIN { in_function=0; skip_until_next_func=0 }
            # 跳过 "# 默认值" 到下一个 "# ====================" 之前的内容
            /^# 默认值/ { skip_until_next_func=1; next }
            /^# ==================== .*函数|^# ==================== 资源自动计算|^# ==================== Pod 管理/ { skip_until_next_func=0; in_function=0; next }
            # 跳过参数解析部分
            /^# ==================== 参数解析/ { skip_until_next_func=1; next }
            /^# 确保 Docker 网络存在/ { skip_until_next_func=1; next }
            # 跳过主流程代码（不在函数内的代码行，且不是注释或空行）
            { 
                if (!in_function && !skip_until_next_func && !/^#/ && !/^[[:space:]]*$/) {
                    # 如果不是函数定义，可能是主流程代码，跳过
                    if (!/^[a-zA-Z_][a-zA-Z0-9_]*\(\) \{/) {
                        next
                    }
                }
            }
            # 如果是函数定义行，开始输出
            /^[a-zA-Z_][a-zA-Z0-9_]*\(\) \{/ { skip_until_next_func=0; in_function=1; print; next }
            # 如果在函数内，输出
            { if (!skip_until_next_func) print }
        ' >> "$OUTPUT_FILE"
    fi
}
echo "" >> "$OUTPUT_FILE"

# 提取 system_check.sh 的所有函数
echo "  提取系统检查函数..."
# 提取 check_ubuntu_version 函数
echo "  提取 check_ubuntu_version 函数..."
{
    func_start=$(grep -n "^check_ubuntu_version() {" "${SCRIPT_DIR}/system_check.sh" | cut -d: -f1)
    if [ -n "$func_start" ]; then
        func_end=$(awk -v start="$func_start" '
            BEGIN { depth=0; found_start=0 }
            NR>=start {
                if (/^check_ubuntu_version\(\) \{/) { found_start=1; depth=1; next }
                if (found_start) {
                    line = $0
                    gsub(/[^{]/, "", line)
                    open_count = length(line)
                    line = $0
                    gsub(/[^}]/, "", line)
                    close_count = length(line)
                    depth += open_count - close_count
                    line = $0
                    gsub(/^[[:space:]]*/, "", line)
                    gsub(/[[:space:]]*$/, "", line)
                    if (depth == 0 && line == "}") {
                        print NR
                        exit
                    }
                }
            }
        ' "${SCRIPT_DIR}/system_check.sh" | head -1)
        if [ -n "$func_end" ] && [ "$func_end" -ge "$func_start" ]; then
            sed -n "${func_start},${func_end}p" "${SCRIPT_DIR}/system_check.sh" >> "$OUTPUT_FILE"
        fi
    fi
}
echo "" >> "$OUTPUT_FILE"

# 提取 check_cgroup_v2_enabled 函数
echo "  提取 check_cgroup_v2_enabled 函数..."
{
    func_start=$(grep -n "^check_cgroup_v2_enabled() {" "${SCRIPT_DIR}/system_check.sh" | cut -d: -f1)
    if [ -n "$func_start" ]; then
        func_end=$(awk -v start="$func_start" '
            BEGIN { depth=0; found_start=0 }
            NR>=start {
                if (/^check_cgroup_v2_enabled\(\) \{/) { found_start=1; depth=1; next }
                if (found_start) {
                    line = $0
                    gsub(/[^{]/, "", line)
                    open_count = length(line)
                    line = $0
                    gsub(/[^}]/, "", line)
                    close_count = length(line)
                    depth += open_count - close_count
                    line = $0
                    gsub(/^[[:space:]]*/, "", line)
                    gsub(/[[:space:]]*$/, "", line)
                    if (depth == 0 && line == "}") {
                        print NR
                        exit
                    }
                }
            }
        ' "${SCRIPT_DIR}/system_check.sh" | head -1)
        if [ -n "$func_end" ] && [ "$func_end" -ge "$func_start" ]; then
            sed -n "${func_start},${func_end}p" "${SCRIPT_DIR}/system_check.sh" >> "$OUTPUT_FILE"
        fi
    fi
}
echo "" >> "$OUTPUT_FILE"

# 提取 check_memory_peak_support 函数
echo "  提取 check_memory_peak_support 函数..."
{
    func_start=$(grep -n "^check_memory_peak_support() {" "${SCRIPT_DIR}/system_check.sh" | cut -d: -f1)
    if [ -n "$func_start" ]; then
        func_end=$(awk -v start="$func_start" '
            BEGIN { depth=0; found_start=0 }
            NR>=start {
                if (/^check_memory_peak_support\(\) \{/) { found_start=1; depth=1; next }
                if (found_start) {
                    line = $0
                    gsub(/[^{]/, "", line)
                    open_count = length(line)
                    line = $0
                    gsub(/[^}]/, "", line)
                    close_count = length(line)
                    depth += open_count - close_count
                    line = $0
                    gsub(/^[[:space:]]*/, "", line)
                    gsub(/[[:space:]]*$/, "", line)
                    if (depth == 0 && line == "}") {
                        print NR
                        exit
                    }
                }
            }
        ' "${SCRIPT_DIR}/system_check.sh" | head -1)
        if [ -n "$func_end" ] && [ "$func_end" -ge "$func_start" ]; then
            sed -n "${func_start},${func_end}p" "${SCRIPT_DIR}/system_check.sh" >> "$OUTPUT_FILE"
        fi
    fi
}
echo "" >> "$OUTPUT_FILE"

# 提取 upgrade_kernel 函数
echo "  提取 upgrade_kernel 函数..."
{
    func_start=$(grep -n "^upgrade_kernel() {" "${SCRIPT_DIR}/system_check.sh" | cut -d: -f1)
    if [ -n "$func_start" ]; then
        func_end=$(awk -v start="$func_start" '
            BEGIN { depth=0; found_start=0 }
            NR>=start {
                if (/^upgrade_kernel\(\) \{/) { found_start=1; depth=1; next }
                if (found_start) {
                    line = $0
                    gsub(/[^{]/, "", line)
                    open_count = length(line)
                    line = $0
                    gsub(/[^}]/, "", line)
                    close_count = length(line)
                    depth += open_count - close_count
                    line = $0
                    gsub(/^[[:space:]]*/, "", line)
                    gsub(/[[:space:]]*$/, "", line)
                    if (depth == 0 && line == "}") {
                        print NR
                        exit
                    }
                }
            }
        ' "${SCRIPT_DIR}/system_check.sh" | head -1)
        if [ -n "$func_end" ] && [ "$func_end" -ge "$func_start" ]; then
            sed -n "${func_start},${func_end}p" "${SCRIPT_DIR}/system_check.sh" >> "$OUTPUT_FILE"
        fi
    fi
}
echo "" >> "$OUTPUT_FILE"

# 提取 check_memory_peak 函数
echo "  提取 check_memory_peak 函数..."
{
    func_start=$(grep -n "^check_memory_peak() {" "${SCRIPT_DIR}/system_check.sh" | cut -d: -f1)
    if [ -n "$func_start" ]; then
        func_end=$(awk -v start="$func_start" '
            BEGIN { depth=0; found_start=0 }
            NR>=start {
                if (/^check_memory_peak\(\) \{/) { found_start=1; depth=1; next }
                if (found_start) {
                    line = $0
                    gsub(/[^{]/, "", line)
                    open_count = length(line)
                    line = $0
                    gsub(/[^}]/, "", line)
                    close_count = length(line)
                    depth += open_count - close_count
                    line = $0
                    gsub(/^[[:space:]]*/, "", line)
                    gsub(/[[:space:]]*$/, "", line)
                    if (depth == 0 && line == "}") {
                        print NR
                        exit
                    }
                }
            }
        ' "${SCRIPT_DIR}/system_check.sh" | head -1)
        if [ -n "$func_end" ] && [ "$func_end" -ge "$func_start" ]; then
            sed -n "${func_start},${func_end}p" "${SCRIPT_DIR}/system_check.sh" >> "$OUTPUT_FILE"
        fi
    fi
}
echo "" >> "$OUTPUT_FILE"

# 提取 system_check 主函数
echo "  提取 system_check 主函数..."
{
    func_start=$(grep -n "^system_check() {" "${SCRIPT_DIR}/system_check.sh" | cut -d: -f1)
    if [ -n "$func_start" ]; then
        func_end=$(awk -v start="$func_start" '
            BEGIN { depth=0; found_start=0 }
            NR>=start {
                if (/^system_check\(\) \{/) { found_start=1; depth=1; next }
                if (found_start) {
                    line = $0
                    gsub(/[^{]/, "", line)
                    open_count = length(line)
                    line = $0
                    gsub(/[^}]/, "", line)
                    close_count = length(line)
                    depth += open_count - close_count
                    line = $0
                    gsub(/^[[:space:]]*/, "", line)
                    gsub(/[[:space:]]*$/, "", line)
                    if (depth == 0 && line == "}") {
                        print NR
                        exit
                    }
                }
            }
        ' "${SCRIPT_DIR}/system_check.sh" | head -1)
        if [ -n "$func_end" ] && [ "$func_end" -ge "$func_start" ]; then
            sed -n "${func_start},${func_end}p" "${SCRIPT_DIR}/system_check.sh" >> "$OUTPUT_FILE"
        fi
    fi
}
echo "" >> "$OUTPUT_FILE"

# 提取交互式配置函数（从 configure.sh）
echo "  提取交互式配置函数..."

# 提取 interactive_configure_web 函数
echo "  提取 interactive_configure_web 函数..."
{
    func_start=$(grep -n "^interactive_configure_web() {" "${SCRIPT_DIR}/configure.sh" | cut -d: -f1)
    if [ -n "$func_start" ]; then
        func_end=$(awk -v start="$func_start" '
            BEGIN { depth=0; found_start=0 }
            NR>=start {
                if (/^interactive_configure_web\(\) \{/) { found_start=1; depth=1; next }
                if (found_start) {
                    line = $0
                    gsub(/[^{]/, "", line)
                    open_count = length(line)
                    line = $0
                    gsub(/[^}]/, "", line)
                    close_count = length(line)
                    depth += open_count - close_count
                    line = $0
                    gsub(/^[[:space:]]*/, "", line)
                    gsub(/[[:space:]]*$/, "", line)
                    if (depth == 0 && line == "}") {
                        print NR
                        exit
                    }
                }
            }
        ' "${SCRIPT_DIR}/configure.sh" | head -1)
        if [ -n "$func_end" ] && [ "$func_end" -ge "$func_start" ]; then
            sed -n "${func_start},${func_end}p" "${SCRIPT_DIR}/configure.sh" >> "$OUTPUT_FILE"
        fi
    fi
}
echo "" >> "$OUTPUT_FILE"

# 提取 interactive_configure_judge 函数
echo "  提取 interactive_configure_judge 函数..."
{
    func_start=$(grep -n "^interactive_configure_judge() {" "${SCRIPT_DIR}/configure.sh" | cut -d: -f1)
    if [ -n "$func_start" ]; then
        func_end=$(awk -v start="$func_start" '
            BEGIN { depth=0; found_start=0 }
            NR>=start {
                if (/^interactive_configure_judge\(\) \{/) { found_start=1; depth=1; next }
                if (found_start) {
                    line = $0
                    gsub(/[^{]/, "", line)
                    open_count = length(line)
                    line = $0
                    gsub(/[^}]/, "", line)
                    close_count = length(line)
                    depth += open_count - close_count
                    line = $0
                    gsub(/^[[:space:]]*/, "", line)
                    gsub(/[[:space:]]*$/, "", line)
                    if (depth == 0 && line == "}") {
                        print NR
                        exit
                    }
                }
            }
        ' "${SCRIPT_DIR}/configure.sh" | head -1)
        if [ -n "$func_end" ] && [ "$func_end" -ge "$func_start" ]; then
            sed -n "${func_start},${func_end}p" "${SCRIPT_DIR}/configure.sh" >> "$OUTPUT_FILE"
        fi
    fi
}
echo "" >> "$OUTPUT_FILE"

# 添加部署模式选择函数
cat >> "$OUTPUT_FILE" <<'INTERACTIVE_FUNCTION'
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

INTERACTIVE_FUNCTION

# 添加交互补全函数
cat >> "$OUTPUT_FILE" <<'INTERACTIVE_COMPLETE_FUNCTIONS'

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

INTERACTIVE_COMPLETE_FUNCTIONS

# 添加主入口逻辑
cat >> "$OUTPUT_FILE" <<'MAIN_LOGIC'

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
    
    # 系统环境检查（仅在需要启动新 pod 时执行，重启和删除不需要）
    if [ "$RESTART_ALL" = false ] && [ "$REMOVE_ALL" = false ]; then
        system_check
    fi
    
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
    
    # 注意：系统检查（Ubuntu版本和memory.peak）仅在部署评测机时执行
    # Web 服务器部署不需要这些检查
    
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
MAIN_LOGIC

# 添加执行权限
chmod +x "$OUTPUT_FILE"

echo ""
echo "✅ 合并脚本已生成: $OUTPUT_FILE"
echo ""
echo "💡 提示: 合并脚本支持所有部署内容"
echo "   使用方式："
echo "   • 命令模式:"
echo "     bash csgoj_deploy.sh web [参数...]   # Web Server 部署"
echo "     bash csgoj_deploy.sh judge [参数...] # Judge 节点部署"
echo "   • 交互模式:"
echo "     bash csgoj_deploy.sh                  # 进入交互式配置"
echo "   • 隐式模式:"
echo "     bash csgoj_deploy.sh [参数...]       # 自动判断模式"
echo ""
