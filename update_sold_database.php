<?php
require_once 'config.php';

try {
    // 检查数据库连接
    if (!isset($db) || $db === null) {
        die("错误：数据库连接失败。请检查config.php中的数据库配置是否正确。\n");
    }
    
    // 测试数据库连接
    $db->query("SELECT 1");
    echo "数据库连接成功\n";
    
    // 添加卖出相关字段
    $db->exec("ALTER TABLE skins ADD COLUMN is_sold TINYINT(1) DEFAULT 0 COMMENT '是否已卖出'");
    echo "成功添加is_sold字段\n";
    
    $db->exec("ALTER TABLE skins ADD COLUMN sold_price DECIMAL(10,2) DEFAULT 0 COMMENT '出售价格'");
    echo "成功添加sold_price字段\n";
    
    $db->exec("ALTER TABLE skins ADD COLUMN fee DECIMAL(10,2) DEFAULT 0 COMMENT '手续费'");
    echo "成功添加fee字段\n";
    
    $db->exec("ALTER TABLE skins ADD COLUMN sold_date DATE DEFAULT NULL COMMENT '卖出日期'");
    echo "成功添加sold_date字段\n";
    
    echo "数据库更新完成！\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "字段已存在，无需添加\n";
    } else {
        echo "错误：" . $e->getMessage() . "\n";
        echo "错误代码：" . $e->getCode() . "\n";
    }
} catch (Exception $e) {
    echo "发生错误：" . $e->getMessage() . "\n";
} 