<?php
require_once 'config.php';
require_once 'includes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>数据库更新</title>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .success { color: green; }
            .error { color: red; }
            pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
        </style>
    </head>
    <body>";
    
    echo "<h2>执行数据库更新以支持统计图表功能</h2>";
    
    // 直接执行单独的SQL语句，避免复杂的解析
    
    // 1. 检查并添加recorded_at字段
    echo "<h3>1. 检查并添加recorded_at字段</h3>";
    try {
        $checkColumn = $db->query("SHOW COLUMNS FROM skins LIKE 'recorded_at'");
        if ($checkColumn->rowCount() == 0) {
            echo "<p>添加recorded_at字段...</p>";
            $db->exec("ALTER TABLE skins ADD COLUMN recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            echo "<p class='success'>字段添加成功</p>";
        } else {
            echo "<p class='success'>字段已存在，无需添加</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>添加字段失败: " . $e->getMessage() . "</p>";
    }
    
    // 2. 创建daily_stats表
    echo "<h3>2. 创建daily_stats表</h3>";
    try {
        $db->exec("
        CREATE TABLE IF NOT EXISTS daily_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            total_investment DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_value DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_profit DECIMAL(10,2) NOT NULL DEFAULT 0,
            roi DECIMAL(10,2) NOT NULL DEFAULT 0,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (date)
        )");
        echo "<p class='success'>表创建成功或已存在</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>创建表失败: " . $e->getMessage() . "</p>";
    }
    
    // 3. 创建触发器
    echo "<h3>3. 创建触发器</h3>";
    try {
        $db->exec("DROP TRIGGER IF EXISTS update_daily_stats");
        $triggerSql = "
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
        END";
        $db->exec($triggerSql);
        echo "<p class='success'>触发器创建成功</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>创建触发器失败: " . $e->getMessage() . "</p>";
    }
    
    // 4. 创建存储过程
    echo "<h3>4. 创建存储过程</h3>";
    try {
        $db->exec("DROP PROCEDURE IF EXISTS record_daily_stats");
        $procSql = "
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
        END";
        $db->exec($procSql);
        echo "<p class='success'>存储过程创建成功</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>创建存储过程失败: " . $e->getMessage() . "</p>";
    }
    
    // 5. 记录初始统计数据
    echo "<h3>5. 记录初始统计数据</h3>";
    try {
        // 首先尝试调用存储过程
        try {
            $db->exec("CALL record_daily_stats()");
            echo "<p class='success'>通过存储过程记录初始统计数据成功</p>";
        } catch (PDOException $e) {
            echo "<p class='error'>调用存储过程失败: " . $e->getMessage() . "</p>";
            
            // 如果调用存储过程失败，直接执行SQL
            echo "<p>尝试直接执行SQL记录初始数据...</p>";
            $db->exec("
                INSERT INTO daily_stats (date, total_investment, total_value, total_profit, roi)
                SELECT CURDATE(), 
                       SUM(purchase_price), 
                       SUM(market_price), 
                       SUM(market_price - purchase_price),
                       CASE WHEN SUM(purchase_price) > 0 
                            THEN (SUM(market_price - purchase_price) / SUM(purchase_price)) * 100
                            ELSE 0 END
                FROM skins
                ON DUPLICATE KEY UPDATE
                    total_investment = VALUES(total_investment),
                    total_value = VALUES(total_value),
                    total_profit = VALUES(total_profit),
                    roi = VALUES(roi)
            ");
            echo "<p class='success'>直接执行SQL记录初始数据成功</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>记录初始统计数据失败: " . $e->getMessage() . "</p>";
    }
    
    // 6. 填充历史数据（如果有）
    echo "<h3>6. 填充历史统计数据</h3>";
    try {
        $db->exec("
            INSERT IGNORE INTO daily_stats (date, total_investment, total_value, total_profit, roi)
            SELECT purchase_date, 
                   SUM(purchase_price), 
                   SUM(market_price), 
                   SUM(market_price - purchase_price),
                   CASE WHEN SUM(purchase_price) > 0 
                        THEN (SUM(market_price - purchase_price) / SUM(purchase_price)) * 100
                        ELSE 0 END
            FROM skins
            WHERE purchase_date IS NOT NULL
            GROUP BY purchase_date
        ");
        echo "<p class='success'>填充历史统计数据成功</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>填充历史统计数据失败: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3 class='success'>数据库更新完成！</h3>";
    echo "<p><a href='index.php'>返回首页</a></p>";
    echo "<p><a href='stats_chart.php'>查看统计图表</a></p>";
    
    echo "</body></html>";
    
} catch (PDOException $e) {
    die("数据库错误: " . $e->getMessage());
} catch (Exception $e) {
    die("错误: " . $e->getMessage());
} 