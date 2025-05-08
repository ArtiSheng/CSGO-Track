<?php
// 数据库连接配置
$servername = "localhost";
$username = "csgo_artisheng_v";      // 数据库用户名
$password = "SZXAxtsF6HGYpcn6";      // 数据库密码
$dbname = "csgo_artisheng_v";        // 数据库名称

// 创建数据库连接
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "数据库连接成功\n";
} catch(PDOException $e) {
    echo "连接失败: " . $e->getMessage() . "\n";
    exit;
}

// 读取JSON文件
$jsonFile = 'a.json';
$jsonContent = file_get_contents($jsonFile);
$jsonData = json_decode($jsonContent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON解析错误: " . json_last_error_msg() . "\n";
    exit;
}

// 检查JSON结构
if (!isset($jsonData['success']) || !isset($jsonData['data'])) {
    echo "JSON数据结构不正确\n";
    exit;
}

$data = $jsonData['data'];

// 准备插入语句
try {
    // 开始事务
    $conn->beginTransaction();

    // 准备插入skins表的语句
    $stmt = $conn->prepare("INSERT INTO skins (name, market_hash_name, last_updated) 
                           VALUES (:name, :market_hash_name, NOW())
                           ON DUPLICATE KEY UPDATE 
                           name = :name,
                           last_updated = NOW()");

    // 遍历数据并插入
    foreach ($data as $item) {
        // 插入或更新skins表
        $params = [
            ':name' => $item['name'] ?? '',
            ':market_hash_name' => $item['marketHashName'] ?? ''
        ];

        $stmt->execute($params);
        
        // 获取skin_id（如果是新插入的记录）
        $skin_id = $conn->lastInsertId();
        
        // 如果是更新操作，需要获取现有的skin_id
        if (!$skin_id) {
            $getIdStmt = $conn->prepare("SELECT id FROM skins WHERE market_hash_name = :market_hash_name");
            $getIdStmt->execute([':market_hash_name' => $params[':market_hash_name']]);
            $skin_id = $getIdStmt->fetchColumn();
        }

        echo "已导入: " . $params[':name'] . "\n";
    }

    // 提交事务
    $conn->commit();
    echo "数据导入成功\n";

} catch (Exception $e) {
    // 如果出现错误，回滚事务
    $conn->rollBack();
    echo "错误: " . $e->getMessage() . "\n";
}

// 关闭数据库连接
$conn = null;
?> 