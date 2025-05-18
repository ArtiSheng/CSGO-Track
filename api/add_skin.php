<?php
// 确保在输出任何内容之前设置错误处理
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/SteamDTAPI.php';
require_once '../includes/SteamItemManager.php';

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
    
    $manager = SteamItemManager::getInstance();

    // 验证必需的POST数据
    $requiredFields = ['name', 'purchase_date'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("缺少必需的字段: {$field}");
        }
    }
    
    // 特殊处理购入价格字段，允许值为0
    if (!isset($_POST['purchase_price']) || $_POST['purchase_price'] === '') {
        throw new Exception("缺少必需的字段: purchase_price");
    }

    // 获取并清理POST数据
    $name = trim($_POST['name']);
    $inspectUrl = isset($_POST['inspect_url']) ? trim($_POST['inspect_url']) : '';
    $purchasePrice = floatval($_POST['purchase_price']);
    $purchaseDate = trim($_POST['purchase_date']);
    $quantity = isset($_POST['quantity']) && is_numeric($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    // 检查是否为已售出饰品
    $isSold = isset($_POST['is_sold']) && $_POST['is_sold'] == 1;
    $soldPrice = null;
    $soldDate = null;
    $fee = null;
    
    // 如果是已售出饰品，获取相关信息
    if ($isSold) {
        if (!isset($_POST['sold_price']) || $_POST['sold_price'] === '') {
            throw new Exception("缺少必需的字段: sold_price");
        }
        if (!isset($_POST['sold_date']) || $_POST['sold_date'] === '') {
            throw new Exception("缺少必需的字段: sold_date");
        }
        
        $soldPrice = floatval($_POST['sold_price']);
        $soldDate = trim($_POST['sold_date']);
        $fee = isset($_POST['fee']) ? floatval($_POST['fee']) : 0;
        
        // 验证售出价格和日期
        if ($soldPrice < 0) {
            throw new Exception("卖出价格不能为负数");
        }
        
        if (!strtotime($soldDate)) {
            throw new Exception("无效的卖出日期格式");
        }
    }

    // 验证数据
    if ($purchasePrice < 0) {
        throw new Exception("购入价格不能为负数");
    }

    if (!strtotime($purchaseDate)) {
        throw new Exception("无效的购入日期格式");
    }
    
    // 确保卖出日期不早于购入日期
    if ($isSold && strtotime($soldDate) < strtotime($purchaseDate)) {
        throw new Exception("卖出日期不能早于购入日期");
    }

    // 添加饰品
    $skinIds = $manager->addSkin($name, $purchasePrice, $purchaseDate, $quantity);
    
    // 如果是已售出饰品，添加售出信息
    if ($isSold && !empty($skinIds)) {
        foreach ($skinIds as $skinId) {
            $manager->sellSkin($skinId, $soldPrice, $soldDate, $fee);
        }
    }

    // 清除之前的所有输出
    ob_clean();

    echo json_encode([
        'success' => true,
        'message' => ($quantity > 1 ? 
            ($isSold ? "已成功添加 {$quantity} 个已售出饰品" : "已成功添加 {$quantity} 个饰品") : 
            ($isSold ? "已售出饰品添加成功" : "饰品添加成功")),
        'data' => [
            'skin_ids' => $skinIds,
            'quantity' => $quantity,
            'is_sold' => $isSold
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