<?php
define('DEBUG', false);  // 添加调试模式配置
define('STEAMDT_API_KEY', 'd721f86734a3405e8f094ff3df4e9de7');
define('API_BASE_URL', 'https://open.steamdt.com');
define('DB_HOST', 'localhost');
define('DB_NAME', 'csgo_artisheng_v');
define('DB_USER', 'csgo_artisheng_v');
define('DB_PASS', 'SZXAxtsF6HGYpcn6');

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