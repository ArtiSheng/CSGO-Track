<?php
require_once '../config.php';
require_once '../includes/Database.php';

header('Content-Type: application/json; charset=utf-8');

// 设置错误处理
function handleError($errno, $errstr, $errfile, $errline) {
    $response = [
        'success' => false,
        'message' => "错误[$errno]: $errstr in $errfile on line $errline"
    ];
    echo json_encode($response);
    exit;
}
set_error_handler('handleError');

// 设置异常处理
function handleException($e) {
    $response = [
        'success' => false,
        'message' => '发生异常: ' . $e->getMessage()
    ];
    echo json_encode($response);
    exit;
}
set_exception_handler('handleException');

try {
    // 获取请求中的饰品ID
    if (!isset($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => '缺少必要参数: id']);
        exit;
    }

    $id = intval($_GET['id']);
    
    // 获取数据库连接
    $db = Database::getInstance()->getConnection();
    
    // 开始事务
    $db->beginTransaction();
    
    // 检查饰品是否存在
    $stmt = $db->prepare("SELECT * FROM skins WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $skin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$skin) {
        throw new Exception('找不到该饰品');
    }
    
    // 删除饰品
    $stmt = $db->prepare("DELETE FROM skins WHERE id = :id");
    $result = $stmt->execute([':id' => $id]);
    
    if (!$result) {
        throw new Exception('删除饰品失败');
    }
    
    $db->commit();
    
    // 返回成功响应
    echo json_encode([
        'success' => true,
        'message' => '饰品已成功删除'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 