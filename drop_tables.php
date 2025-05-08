<?php
// 数据库连接配置
$servername = "localhost";
$username = "csgo_artisheng_v";
$password = "SZXAxtsF6HGYpcn6";
$dbname = "csgo_artisheng_v";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "数据库连接成功\n";

    // 删除表
    $conn->exec("DROP TABLE IF EXISTS price_history");
    $conn->exec("DROP TABLE IF EXISTS skins");
    $conn->exec("DROP TABLE IF EXISTS skin_names");
    
    echo "表删除成功\n";

} catch(PDOException $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

$conn = null;
?> 