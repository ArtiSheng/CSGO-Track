<?php
function getMarketHashName($skinName) {
    $servername = "localhost";
    $username = "csgo_artisheng_v";
    $password = "SZXAxtsF6HGYpcn6";
    $dbname = "csgo_artisheng_v";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 准备查询语句
        $stmt = $conn->prepare("SELECT marketHashName FROM skin_names WHERE name = :name");
        $stmt->execute([':name' => $skinName]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['marketHashName'];
        } else {
            return null;
        }
        
    } catch(PDOException $e) {
        error_log("数据库错误: " . $e->getMessage());
        return null;
    }
}

// 使用示例
if (isset($_GET['name'])) {
    $skinName = $_GET['name'];
    $marketHashName = getMarketHashName($skinName);
    
    if ($marketHashName) {
        echo json_encode([
            'success' => true,
            'marketHashName' => $marketHashName
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '未找到对应的市场Hash名称'
        ]);
    }
}
?> 