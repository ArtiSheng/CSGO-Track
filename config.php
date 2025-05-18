<?php
define('DEBUG', false);  // 添加调试模式配置
define('STEAMDT_API_KEY', '###');
define('API_BASE_URL', 'https://open.steamdt.com');
define('DB_HOST', 'localhost');
define('DB_NAME', '###');
define('DB_USER', '###');
define('DB_PASS', '###');

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch (PDOException $e) {
    die("数据库连接失败：" . $e->getMessage() . "\n");
}
?> 