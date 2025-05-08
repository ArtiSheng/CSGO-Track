<?php

require_once 'includes/config.php';
require_once 'includes/BaseInfoManager.php';

try {
    // 创建数据库连接
    $db = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass']
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