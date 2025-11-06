<?php
$DB_HOSTNAME    = getenv('DB_HOSTNAME');
$DB_DATABASE    = getenv('DB_DATABASE');
$DB_USERNAME    = getenv('DB_USERNAME');
$DB_PASSWORD    = getenv('DB_PASSWORD');
$DB_HOSTPORT    = getenv('DB_HOSTPORT');
$PASS_ADMIN     = getenv('PASS_ADMIN');
$PASS_JUDGER    = getenv('PASS_JUDGER');

/**
 * 处理 SQL 文件中的 DELIMITER 指令，使其能在 multi_query() 中执行
 * 将 DELIMITER 指令移除，并将所有自定义分隔符替换为分号
 */
function processDelimiter($sql) {
    $currentDelimiter = ';';
    $statements = [];
    $currentStatement = '';
    
    // 按行处理，但需要跟踪当前分隔符
    $lines = explode("\n", $sql);
    
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        
        // 检查是否是 DELIMITER 指令
        if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $trimmedLine, $matches)) {
            // 如果有未完成的语句，先完成它
            if (!empty(trim($currentStatement))) {
                // 移除语句末尾的分隔符（如果有）
                $stmt = rtrim($currentStatement);
                if ($currentDelimiter !== ';') {
                    // 移除自定义分隔符
                    $stmt = preg_replace('/' . preg_quote($currentDelimiter, '/') . '\s*$/', '', $stmt);
                }
                $stmt = rtrim($stmt);
                if (!empty($stmt)) {
                    $statements[] = $stmt . ';';
                }
                $currentStatement = '';
            }
            // 更新分隔符
            $currentDelimiter = $matches[1];
            continue; // 跳过 DELIMITER 行
        }
        
        // 添加当前行到语句中
        $currentStatement .= $line . "\n";
        
        // 检查当前行是否以分隔符结束（去除注释和空白后）
        // 对于自定义分隔符，检查行的末尾
        if ($currentDelimiter !== ';') {
            // 移除行尾注释（-- 或 /* */）
            $lineWithoutComment = $line;
            // 处理单行注释 --
            if (preg_match('/(.*?)(\s*--.*)$/', $lineWithoutComment, $commentMatch)) {
                $lineWithoutComment = $commentMatch[1];
            }
            // 检查是否以分隔符结尾
            $trimmed = rtrim($lineWithoutComment);
            if (substr($trimmed, -strlen($currentDelimiter)) === $currentDelimiter) {
                // 完成当前语句
                $stmt = rtrim($currentStatement);
                // 移除末尾的分隔符
                $stmt = preg_replace('/' . preg_quote($currentDelimiter, '/') . '\s*$/', '', $stmt);
                $stmt = rtrim($stmt);
                if (!empty($stmt)) {
                    $statements[] = $stmt . ';';
                }
                $currentStatement = '';
            }
        }
    }
    
    // 处理剩余的语句
    if (!empty(trim($currentStatement))) {
        $stmt = rtrim($currentStatement);
        if ($currentDelimiter !== ';') {
            $stmt = preg_replace('/' . preg_quote($currentDelimiter, '/') . '\s*$/', '', $stmt);
        }
        $stmt = rtrim($stmt);
        if (!empty($stmt)) {
            $statements[] = $stmt . ';';
        }
    }
    
    return implode("\n", $statements);
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
        $sql = processDelimiter($sql);
        if(!$mysqli->multi_query($sql)) {
            die("导入 db_update.sql 失败: " . $mysqli->error . " (错误码: " . $mysqli->errno . ")\n");
        } else {
            echo "导入 db_update.sql\n";
            // 处理所有查询结果
            do {
                if ($result = $mysqli->store_result()) {
                    $result->free();
                }
            } while ($mysqli->more_results() && $mysqli->next_result());
            // 检查执行过程中是否有错误
            if ($mysqli->errno) {
                die("导入 db_update.sql 执行失败: " . $mysqli->error . " (错误码: " . $mysqli->errno . ")\n");
            }
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
