<?php

require_once 'config.php';
require_once 'includes/BaseInfoManager.php';

try {
    // 创建数据库连接
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 初始化管理器并执行获取
    $manager = new BaseInfoManager($db);
    $result = $manager->fetchAndSaveBaseInfo();

    // 输出结果
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 