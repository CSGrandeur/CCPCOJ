<?php
$DB_HOSTNAME    = getenv('DB_HOSTNAME');
$DB_DATABASE    = getenv('DB_DATABASE');
$DB_USERNAME    = getenv('DB_USERNAME');
$DB_PASSWORD    = getenv('DB_PASSWORD');
$DB_HOSTPORT    = getenv('DB_HOSTPORT');
$PASS_ADMIN     = getenv('PASS_ADMIN');
$PASS_JUDGER    = getenv('PASS_JUDGER');

/**
 * 处理 SQL 文件中的 DELIMITER 指令，使其能在 PHP mysqli 中执行
 * 保持原文件兼容 phpMyAdmin，同时支持 PHP 自动处理
 * 
 * 策略：
 * 1. 移除所有 DELIMITER 指令
 * 2. 将所有自定义分隔符（如 $$）替换为分号
 * 3. 保持语句完整性，正确处理多行语句
 */
function processDelimiter($sql) {
    $currentDelimiter = ';';
    $statements = [];
    $currentStatement = '';
    
    // 按行处理
    $lines = explode("\n", $sql);
    
    foreach ($lines as $lineNum => $line) {
        $trimmedLine = trim($line);
        
        // 检查是否是 DELIMITER 指令（必须单独一行，前后可能有空白）
        if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $trimmedLine, $matches)) {
            // 在切换分隔符前，先完成当前未完成的语句
            if (!empty(trim($currentStatement))) {
                $stmt = finishStatement($currentStatement, $currentDelimiter);
                if ($stmt !== null) {
                    $statements[] = $stmt;
                }
                $currentStatement = '';
            }
            // 更新当前分隔符
            $currentDelimiter = $matches[1];
            // 跳过 DELIMITER 行，不添加到语句中
            continue;
        }
        
        // 将当前行添加到正在构建的语句中（先添加，再检查）
        $currentStatement .= $line . "\n";
        
        // 检查当前行是否以分隔符结束
        if (isStatementComplete($line, $currentDelimiter)) {
            // 语句完成，处理并保存
            $stmt = finishStatement($currentStatement, $currentDelimiter);
            if ($stmt !== null) {
                $statements[] = $stmt;
            }
            // 清空当前语句，准备下一个
            $currentStatement = '';
        }
    }
    
    // 处理文件末尾可能剩余的语句
    if (!empty(trim($currentStatement))) {
        $stmt = finishStatement($currentStatement, $currentDelimiter);
        if ($stmt !== null) {
            $statements[] = $stmt;
        }
    }
    
    // 用换行符连接所有语句，每个语句以分号结尾
    return implode("\n", $statements);
}

/**
 * 检查一行是否表示语句结束
 */
function isStatementComplete($line, $delimiter) {
    // 移除行尾注释（-- 开头的注释，但保留字符串中的 --）
    // 注意：简单处理，不处理字符串中的注释符号
    $lineWithoutComment = preg_replace('/\s*--.*$/', '', $line);
    // 移除行尾的空白字符和换行符
    $trimmed = rtrim($lineWithoutComment, " \t\r\n");
    
    if (empty($trimmed)) {
        return false;
    }
    
    if ($delimiter === ';') {
        // 默认分隔符：检查是否以分号结尾
        return substr($trimmed, -1) === ';';
    } else {
        // 自定义分隔符：检查行尾是否包含分隔符
        $delimiterLen = strlen($delimiter);
        if (strlen($trimmed) < $delimiterLen) {
            return false;
        }
        // 检查行尾是否完全匹配分隔符
        $lineEnd = substr($trimmed, -$delimiterLen);
        return $lineEnd === $delimiter;
    }
}

/**
 * 完成语句处理：移除分隔符，添加分号，过滤注释
 */
function finishStatement($statement, $delimiter) {
    $stmt = rtrim($statement);
    
    // 移除末尾的分隔符
    if ($delimiter !== ';') {
        // 移除行尾的分隔符（只匹配行尾，使用 $ 锚点）
        // 使用多行模式，但只匹配最后一行的分隔符
        $stmt = preg_replace('/' . preg_quote($delimiter, '/') . '\s*$/m', '', $stmt);
        $stmt = rtrim($stmt);
    } else {
        // 移除末尾的分号（如果有）
        $stmt = rtrim($stmt, ';');
    }
    
    // 过滤空语句和纯注释语句
    $trimmed = trim($stmt);
    if (empty($trimmed)) {
        return null;
    }
    
    // 检查是否是纯注释（以 -- 或 /* 开头，且不包含其他SQL内容）
    // 注意：不要过滤包含SQL关键字的语句，即使它们以注释开头
    // 检查是否包含SQL关键字（包括存储过程相关的）
    $hasSqlContent = preg_match('/\b(CREATE|DROP|ALTER|INSERT|UPDATE|DELETE|SELECT|CALL|BEGIN|END|DECLARE|SET|IF|THEN|ELSE|PROCEDURE|FUNCTION|TABLE|DATABASE|SCHEMA)\b/i', $trimmed);
    
    // 如果包含SQL内容，即使以注释开头也要保留（可能是注释+SQL的组合）
    if ($hasSqlContent) {
        // 移除开头的注释行，保留SQL部分
        $lines = explode("\n", $trimmed);
        $sqlLines = [];
        foreach ($lines as $line) {
            $lineTrimmed = trim($line);
            // 跳过纯注释行，但保留包含SQL的行
            if (!empty($lineTrimmed) && !preg_match('/^\s*(--|\/\*)/', $lineTrimmed)) {
                $sqlLines[] = $line;
            } elseif (!empty($lineTrimmed) && preg_match('/\b(CREATE|DROP|ALTER|INSERT|UPDATE|DELETE|SELECT|CALL|BEGIN|END|DECLARE|SET|IF|THEN|ELSE|PROCEDURE|FUNCTION|TABLE|DATABASE|SCHEMA)\b/i', $lineTrimmed)) {
                // 即使以注释开头，但包含SQL关键字，也要保留
                $sqlLines[] = $line;
            }
        }
        $trimmed = implode("\n", $sqlLines);
        $trimmed = trim($trimmed);
        
        if (empty($trimmed)) {
            return null;
        }
    } else {
        // 没有SQL内容，且是纯注释，过滤掉
        if (preg_match('/^\s*(--|\/\*)/', $trimmed)) {
            return null;
        }
    }
    
    // 返回处理后的语句，添加分号
    return $trimmed . ';';
}

$maxTries = 10;
$tries = 0;
$mysqli = null;
while ($tries < $maxTries) {
    try{
        $mysqli = new mysqli($DB_HOSTNAME, $DB_USERNAME, $DB_PASSWORD, "", $DB_HOSTPORT);

        if ($mysqli->connect_error) {
            throw new Exception($mysqli->connect_error, $mysqli->connect_errno);
        }
        echo "连接数据库成功\n";
        break;
    } catch (Exception $e) {
        if ($tries === $maxTries - 1) {
            die("连接数据库失败: " . $e->getMessage() . "\n");
        } else {
            echo "等待数据库启动\n";
            sleep(10); // Wait for 10 seconds before retrying
            $tries++;
        }
    }
}
$result = $mysqli->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$DB_DATABASE'");
$dir = '/SQL';
if ($result->num_rows == 0) {
    if(!$mysqli->query("CREATE DATABASE IF NOT EXISTS `$DB_DATABASE` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;")) {
        die("建立数据库`$DB_DATABASE`失败\n");
    }
    $mysqli->select_db($DB_DATABASE);
    $files = scandir($dir); // 如果修改 /SQL 目录的文件名，则要检查这里逻辑
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
            $sql = file_get_contents($dir . '/' . $file);
            if(!$mysqli->multi_query($sql)) {
                die("导入" . $file . "失败: " . $mysqli->error . " (错误码: " . $mysqli->errno . ")\n");
            } else {
                echo "导入" . $file . "\n";
                // 处理所有查询结果
                do {
                    if ($result = $mysqli->store_result()) {
                        $result->free();
                    }
                } while ($mysqli->more_results() && $mysqli->next_result());
                // 检查执行过程中是否有错误
                if ($mysqli->errno) {
                    die("导入" . $file . "执行失败: " . $mysqli->error . " (错误码: " . $mysqli->errno . ")\n");
                }
            }
        }
    }
} else {
    $mysqli->select_db($DB_DATABASE);
        // 检查并执行 db_update.sql（无论数据库是否存在都要执行）
        $updateFile = $dir . '/db_update.sql';
        if (file_exists($updateFile)) {
            $sql = file_get_contents($updateFile);
            // 处理 DELIMITER 指令，使其能在 multi_query() 中执行
            $processedSql = processDelimiter($sql);
            
            // 检查处理后的 SQL 是否包含存储过程定义
            if (stripos($processedSql, 'CREATE PROCEDURE AddColumnIfNotExists') === false) {
                // 输出处理后的 SQL 的前2000个字符用于调试
                $preview = substr($processedSql, 0, 2000);
                // 统计处理后的语句数量
                $stmtCount = substr_count($processedSql, ';');
                die("错误：处理后的 SQL 中未找到存储过程定义，可能处理失败\n" .
                    "处理后的 SQL 包含约 {$stmtCount} 个语句\n" .
                    "处理后的 SQL 预览（前2000字符）:\n" . $preview . "\n");
            }
            
            // 使用 multi_query 执行处理后的 SQL（所有语句已用分号分隔）
            if (!$mysqli->multi_query($processedSql)) {
                die("导入 db_update.sql 失败: " . $mysqli->error . " (错误码: " . $mysqli->errno . ")\n");
            } else {
                echo "导入 db_update.sql\n";
                // 处理所有查询结果
                $statementCount = 0;
                do {
                    $statementCount++;
                    if ($result = $mysqli->store_result()) {
                        $result->free();
                    }
                    // 检查每个语句的执行结果
                    if ($mysqli->errno) {
                        $errorMsg = "导入 db_update.sql 执行失败（第 {$statementCount} 个语句）: " . $mysqli->error . " (错误码: " . $mysqli->errno . ")\n";
                        // 如果是存储过程不存在的错误，提供更详细的提示
                        if ($mysqli->errno == 1305 && stripos($mysqli->error, 'AddColumnIfNotExists') !== false) {
                            $errorMsg .= "提示：存储过程可能未正确创建，请检查前面的 CREATE PROCEDURE 语句是否执行成功\n";
                        }
                        die($errorMsg);
                    }
                } while ($mysqli->more_results() && $mysqli->next_result());
                // 最终检查是否有未处理的错误
                if ($mysqli->errno) {
                    die("导入 db_update.sql 执行失败: " . $mysqli->error . " (错误码: " . $mysqli->errno . ")\n");
                }
                echo "导入 db_update.sql 完成\n";
            }
        }
}
// 最终检查是否有未处理的错误
if ($mysqli->errno) {
    die("数据库操作失败：" . $mysqli->errno . " - " . $mysqli->error . "\n");
}

$mysqli->close();
echo "数据库初始化完毕\n";
?>
