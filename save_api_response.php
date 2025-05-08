<?php
// 数据库连接配置
$servername = "localhost";
$username = "csgo_artisheng_v";
$password = "SZXAxtsF6HGYpcn6";
$dbname = "csgo_artisheng_v";

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

    // 准备插入skin_names表的语句
    $stmt = $conn->prepare("INSERT INTO skin_names (name, marketHashName) 
                           VALUES (:name, :marketHashName)
                           ON DUPLICATE KEY UPDATE 
                           marketHashName = :marketHashName");

    // 遍历数据并插入
    foreach ($data as $item) {
        $params = [
            ':name' => $item['name'],
            ':marketHashName' => $item['marketHashName']
        ];

        $stmt->execute($params);
    }

    // 提交事务
    $conn->commit();
    echo "API响应数据保存成功\n";

} catch (Exception $e) {
    // 如果出现错误，回滚事务
    $conn->rollBack();
    echo "错误: " . $e->getMessage() . "\n";
}

// 关闭数据库连接
$conn = null;
?> 