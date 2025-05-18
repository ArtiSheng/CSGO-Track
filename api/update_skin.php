<?php
// 确保在输出任何内容之前设置错误处理
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config.php';
require_once '../includes/Database.php';

// 设置错误处理函数
function handleError($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error',
        'debug' => DEBUG ? [
            'error' => $errstr,
            'file' => $errfile,
            'line' => $errline
        ] : null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 设置异常处理函数
function handleException($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => DEBUG ? [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ] : null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

set_error_handler('handleError');
set_exception_handler('handleException');

header('Content-Type: application/json; charset=utf-8');

try {
    // 开启输出缓冲
    ob_start();
    
    // 获取数据库连接
    $db = Database::getInstance()->getConnection();

    // 获取并清理POST数据
    $skinId = isset($_POST['skin_id']) ? intval($_POST['skin_id']) : null;
    $name = isset($_POST['name']) ? trim($_POST['name']) : null;
    $purchasePrice = isset($_POST['purchase_price']) ? floatval($_POST['purchase_price']) : null;
    $purchaseDate = isset($_POST['purchase_date']) ? trim($_POST['purchase_date']) : null;
    $marketHashName = isset($_POST['marketHashName']) ? trim($_POST['marketHashName']) : null;
    
    // 调试信息
    if (DEBUG) {
        error_log("提交的数据：" . print_r($_POST, true));
    }

    // 构建更新SQL语句
    $updateFields = [];
    $params = [];
    
    if ($name !== null) {
        $updateFields[] = "name = ?";
        $params[] = $name;
    }
    
    if ($purchasePrice !== null) {
        $updateFields[] = "purchase_price = ?";
        $params[] = $purchasePrice;
        
        // 如果价格变化，添加新的价格历史记录
        $stmt = $db->prepare("SELECT purchase_price FROM skins WHERE id = ?");
        $stmt->execute([$skinId]);
        $oldPrice = $stmt->fetchColumn();
        
        if ($oldPrice != $purchasePrice) {
            $historyStmt = $db->prepare("INSERT INTO price_history (skin_id, price) VALUES (?, ?)");
            $historyStmt->execute([$skinId, $purchasePrice]);
        }
    }
    
    if ($purchaseDate !== null) {
        $updateFields[] = "purchase_date = ?";
        $params[] = $purchaseDate;
    }
    
    if ($marketHashName !== null) {
        $updateFields[] = "marketHashName = ?";
        $params[] = $marketHashName;
    }
    
    // 如果没有更新字段，则返回错误
    if (empty($updateFields)) {
        throw new Exception("未提供任何更新字段");
    }

    // 验证必需的POST数据
    if (!isset($_POST['skin_id']) || empty($_POST['skin_id'])) {
        throw new Exception("缺少必需的饰品ID");
    }

    // 执行单个饰品编辑
    $params[] = $skinId;
    $sql = "UPDATE skins SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("未找到该饰品或未做任何更改");
    }
    
    $affectedRows = 1;

    // 清除之前的所有输出
    ob_clean();

    echo json_encode([
        'success' => true,
        'message' => '饰品信息更新成功',
        'data' => [
            'affected_rows' => $affectedRows
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // 清除之前的所有输出
    ob_clean();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// 确保输出缓冲区被刷新
ob_end_flush();
?> 