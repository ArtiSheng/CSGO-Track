<?php
require_once 'config.php';

try {
    // 连接数据库
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
    
    echo "数据库连接成功\n";
    
    // 检查price_platform字段是否存在
    $stmt = $db->query("SHOW COLUMNS FROM skins LIKE 'price_platform'");
    if ($stmt->rowCount() == 0) {
        // 添加price_platform字段
        $db->exec("ALTER TABLE skins ADD COLUMN price_platform VARCHAR(50) DEFAULT NULL AFTER market_price");
        echo "成功添加price_platform字段\n";
    } else {
        echo "price_platform字段已存在\n";
    }
    
    // 更新现有数据的平台信息
    $stmt = $db->prepare("UPDATE skins SET price_platform = ? WHERE market_price > 0 AND price_platform IS NULL");
    $stmt->execute(['BUFF']);  // 默认使用BUFF作为平台
    $count = $stmt->rowCount();
    echo "更新了 {$count} 条记录的平台信息\n";
    
    // 检查quantity字段是否存在
    $stmt = $db->query("SHOW COLUMNS FROM skins LIKE 'quantity'");
    if ($stmt->rowCount() == 0) {
        // 添加quantity字段
        $db->exec("ALTER TABLE skins ADD COLUMN quantity INT DEFAULT 1 AFTER name");
        echo "成功添加quantity字段\n";
    } else {
        echo "quantity字段已存在\n";
    }
    
    // 更新现有数据的数量信息
    $stmt = $db->prepare("UPDATE skins SET quantity = 1 WHERE quantity IS NULL");
    $stmt->execute();
    $count = $stmt->rowCount();
    echo "更新了 {$count} 条记录的数量信息\n";
    
    echo "数据库更新成功完成\n";
    
} catch (PDOException $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?> 