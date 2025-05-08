-- 添加记录日期字段到skins表 (兼容旧版MySQL语法)
-- 首先检查字段是否存在
SET @s = (SELECT IF(
    EXISTS(
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'skins' 
        AND COLUMN_NAME = 'recorded_at'
    ),
    'SELECT "字段已存在，无需添加" AS message',
    'ALTER TABLE skins ADD COLUMN recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
));

PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 创建每日统计表
CREATE TABLE IF NOT EXISTS daily_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    total_investment DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_profit DECIMAL(10,2) NOT NULL DEFAULT 0,
    roi DECIMAL(10,2) NOT NULL DEFAULT 0,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (date)
);

-- 创建触发器，每天自动记录统计数据
DELIMITER //

-- 首先检查并删除已存在的触发器
DROP TRIGGER IF EXISTS update_daily_stats //

CREATE TRIGGER update_daily_stats AFTER UPDATE ON skins
FOR EACH ROW
BEGIN
    DECLARE today DATE;
    DECLARE investment DECIMAL(10,2);
    DECLARE current_value DECIMAL(10,2);
    DECLARE profit DECIMAL(10,2);
    DECLARE roi_value DECIMAL(10,2);
    
    SET today = CURDATE();
    
    -- 计算当日统计数据
    SELECT COALESCE(SUM(purchase_price), 0), COALESCE(SUM(market_price), 0) 
    INTO investment, current_value
    FROM skins;
    
    SET profit = current_value - investment;
    SET roi_value = IF(investment > 0, (profit / investment * 100), 0);
    
    -- 插入或更新每日统计
    INSERT INTO daily_stats (date, total_investment, total_value, total_profit, roi)
    VALUES (today, investment, current_value, profit, roi_value)
    ON DUPLICATE KEY UPDATE
        total_investment = investment,
        total_value = current_value,
        total_profit = profit,
        roi = roi_value,
        recorded_at = CURRENT_TIMESTAMP;
END//

-- 首先检查并删除已存在的存储过程
DROP PROCEDURE IF EXISTS record_daily_stats //

-- 创建每日统计记录的存储过程
CREATE PROCEDURE record_daily_stats()
BEGIN
    DECLARE investment DECIMAL(10,2);
    DECLARE current_value DECIMAL(10,2);
    DECLARE profit DECIMAL(10,2);
    DECLARE roi_value DECIMAL(10,2);
    
    -- 计算当日统计数据
    SELECT COALESCE(SUM(purchase_price), 0), COALESCE(SUM(market_price), 0) 
    INTO investment, current_value
    FROM skins;
    
    SET profit = current_value - investment;
    SET roi_value = IF(investment > 0, (profit / investment * 100), 0);
    
    -- 插入或更新每日统计
    INSERT INTO daily_stats (date, total_investment, total_value, total_profit, roi)
    VALUES (CURDATE(), investment, current_value, profit, roi_value)
    ON DUPLICATE KEY UPDATE
        total_investment = investment,
        total_value = current_value,
        total_profit = profit,
        roi = roi_value,
        recorded_at = CURRENT_TIMESTAMP;
END//

DELIMITER ; 