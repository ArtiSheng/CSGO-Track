<?php
require_once 'config.php';

try {
    // 创建数据库连接
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
    
    echo "数据库连接成功\n";
    
    // 检查sort_order字段是否存在
    $stmt = $db->query("SHOW COLUMNS FROM skins LIKE 'sort_order'");
    if ($stmt->rowCount() > 0) {
        echo "sort_order字段已存在，无需添加\n";
    } else {
        // 添加sort_order字段
        $db->exec("ALTER TABLE skins ADD COLUMN sort_order INT DEFAULT 0 COMMENT '排序顺序'");
        echo "成功添加sort_order字段\n";
        
        // 初始化现有数据的排序值（使用ID作为初始排序）
        $db->exec("UPDATE skins SET sort_order = id");
        echo "初始化排序值完成\n";
    }

    echo "数据库更新完成\n";

} catch (PDOException $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?> 