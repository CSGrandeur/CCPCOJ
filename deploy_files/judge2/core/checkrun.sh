#!/bin/bash

# checkrun.sh - 执行命令并捕获结构化输出
# 用法: bash checkrun.sh "any cmd"

if [ $# -eq 0 ]; then
    echo "用法: bash checkrun.sh \"command\""
    exit 1
fi

# 获取要执行的命令
CMD="$1"

# 创建临时文件
STDOUT_FILE=$(mktemp)
STDERR_FILE=$(mktemp)

# 执行命令并捕获输出
eval "$CMD" > "$STDOUT_FILE" 2> "$STDERR_FILE"
RETURN_CODE=$?

# 读取输出内容
STDOUT_CONTENT=$(cat "$STDOUT_FILE")
STDERR_CONTENT=$(cat "$STDERR_FILE")

# 清理临时文件
rm -f "$STDOUT_FILE" "$STDERR_FILE"

# 结构化输出
echo "=== COMMAND EXECUTION RESULT ==="
echo "Command: $CMD"
echo "Return Code: $RETURN_CODE"
echo "--- STDOUT ---"
echo "$STDOUT_CONTENT"
echo "--- STDERR ---"
echo "$STDERR_CONTENT"
echo "=== END ==="
