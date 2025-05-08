<?php
// 数据库连接配置
$servername = "localhost";
$username = "csgo_artisheng_v";
$password = "SZXAxtsF6HGYpcn6";
$dbname = "csgo_artisheng_v";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 获取skins表结构
    $stmt = $conn->query("DESCRIBE skins");
    echo "skins表结构：\n";
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
} catch(PDOException $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

$conn = null;
?> 