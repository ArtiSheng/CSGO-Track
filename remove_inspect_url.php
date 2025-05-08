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
    
    // 检查inspect_url字段是否存在
    $stmt = $db->query("SHOW COLUMNS FROM skins LIKE 'inspect_url'");
    if ($stmt->rowCount() > 0) {
        // 删除inspect_url字段
        $db->exec("ALTER TABLE skins DROP COLUMN inspect_url");
        echo "成功删除inspect_url字段\n";
    } else {
        echo "inspect_url字段不存在，无需删除\n";
    }

    echo "数据库更新完成\n";

} catch (PDOException $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?> 