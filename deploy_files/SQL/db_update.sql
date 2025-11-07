-- 数据库更新SQL语句
-- 使用存储过程来安全地添加字段，如果字段不存在则创建
-- 注意：此文件同时支持 phpMyAdmin 和 PHP multi_query() 执行

-- 设置分隔符为 $$，以便在存储过程中使用分号（phpMyAdmin 需要）
DELIMITER $$

-- 删除已存在的存储过程（如果存在）
DROP PROCEDURE IF EXISTS AddColumnIfNotExists$$

-- 创建存储过程来安全添加字段
CREATE PROCEDURE AddColumnIfNotExists(
    IN tableName VARCHAR(128),
    IN columnName VARCHAR(128),
    IN columnDefinition TEXT
)
BEGIN
    DECLARE columnExists INT DEFAULT 0;
    
    -- 检查字段是否存在
    SELECT COUNT(*) INTO columnExists
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = tableName
    AND COLUMN_NAME = columnName;
    
    -- 如果字段不存在，则添加字段
    IF columnExists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` ADD COLUMN `', columnName, '` ', columnDefinition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

-- 恢复默认分隔符
DELIMITER ;

-- 设置分隔符为 $$，以便在存储过程中使用分号
DELIMITER $$

-- 使用存储过程添加字段
-- 更新 cpc_team 表
CALL AddColumnIfNotExists('cpc_team', 'region', 'varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT "队伍所属国家或地区"')$$
CALL AddColumnIfNotExists('cpc_team', 'addition', 'json DEFAULT NULL COMMENT "附加信息"')$$
CALL AddColumnIfNotExists('cpc_team', 'name_en', 'varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL')$$

-- 更新 contest 表
CALL AddColumnIfNotExists('contest', 'addition', 'json DEFAULT NULL COMMENT "附加信息"')$$
CALL AddColumnIfNotExists('contest', 'flg_archive', 'tinyint NOT NULL DEFAULT 0 COMMENT "是否归档"')$$

CALL AddColumnIfNotExists('loginlog', 'success', 'TINYINT NOT NULL AFTER `password`')$$

-- 修改 attach 字段长度（从 varchar(32) 改为 varchar(64)）
-- 更新 contest 表的 attach 字段
ALTER TABLE `contest` MODIFY COLUMN `attach` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT ''$$

-- 更新 news 表的 attach 字段
ALTER TABLE `news` MODIFY COLUMN `attach` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT ''$$

-- 更新 problem 表的 attach 字段
ALTER TABLE `problem` MODIFY COLUMN `attach` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT ''$$

-- 删除临时存储过程
DROP PROCEDURE IF EXISTS AddColumnIfNotExists$$

-- 恢复默认分隔符
DELIMITER ;

-- 创建 outrank 表（如果不存在）
-- 直接使用 CREATE TABLE IF NOT EXISTS，避免复杂的动态SQL
CREATE TABLE IF NOT EXISTS `outrank` (
    `outrank_id` int NOT NULL AUTO_INCREMENT,
    `outrank_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    `in_date` datetime DEFAULT NULL,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
    `ckind` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
    `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
    `start_time` datetime DEFAULT NULL,
    `end_time` datetime DEFAULT NULL,
    `addition` json DEFAULT NULL COMMENT '附加信息',
    `flg_allow` tinyint NOT NULL DEFAULT '0' COMMENT '是否允许推送',
    `defunct` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
    PRIMARY KEY (`outrank_id`),
    UNIQUE KEY `outrank_uuid` (`outrank_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 修改 users 表字段长度
ALTER TABLE `users` 
    MODIFY COLUMN `user_id` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT 'user_id',
    MODIFY COLUMN `email` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
    MODIFY COLUMN `ip` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    MODIFY COLUMN `nick` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    MODIFY COLUMN `school` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL;

-- 创建 cpc_client 表（如果不存在）
CREATE TABLE IF NOT EXISTS `cpc_client` (
    `client_id` int NOT NULL AUTO_INCREMENT,
    `contest_id` int NOT NULL,
    `team_id_bind` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '绑定队伍号',
    `ip_bind` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '绑定IP',
    `ssh_config` json DEFAULT NULL COMMENT 'SSH配置，支持rsa和user/pass两种方式，以及ssh port存储',
    PRIMARY KEY (`client_id`),
    KEY `contest_id` (`contest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;