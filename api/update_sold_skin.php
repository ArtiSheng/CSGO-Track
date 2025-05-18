<?php
require_once '../config.php';
require_once '../includes/Database.php';

// 初始化数据库连接
$db = Database::getInstance()->getConnection();

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

// 确保请求是POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => '只允许POST请求']);
    exit;
}

// 记录接收到的数据用于调试
if (defined('DEBUG') && DEBUG) {
    error_log("编辑已售出饰品 - 接收到的POST数据: " . print_r($_POST, true));
}

// 验证必要字段
if (!isset($_POST['skin_id'])) {
    echo json_encode(['success' => false, 'message' => '缺少必要参数: skin_id']);
    exit;
}

$skinId = intval($_POST['skin_id']);
$purchasePrice = isset($_POST['purchase_price']) ? floatval($_POST['purchase_price']) : null;
$purchaseDate = isset($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
$soldPrice = isset($_POST['sold_price']) ? floatval($_POST['sold_price']) : null;
$fee = isset($_POST['fee']) ? floatval($_POST['fee']) : 0;
$soldDate = isset($_POST['sold_date']) ? $_POST['sold_date'] : null;

try {
    // 开始事务
    $db->beginTransaction();
    
    // 获取当前饰品信息
    $stmt = $db->prepare("SELECT * FROM skins WHERE id = ?");
    $stmt->execute([$skinId]);
    $skin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$skin) {
        throw new Exception('找不到该饰品');
    }
    
    if ($skin['is_sold'] != 1) {
        throw new Exception('该饰品不是已售出状态');
    }
    
    // 构建更新语句
    $updateFields = [];
    $params = [];
    
    if ($purchasePrice !== null) {
        $updateFields[] = "purchase_price = ?";
        $params[] = $purchasePrice;
    }
    
    if ($purchaseDate !== null) {
        $updateFields[] = "purchase_date = ?";
        $params[] = $purchaseDate;
    }
    
    if ($soldPrice !== null) {
        $updateFields[] = "sold_price = ?";
        $params[] = $soldPrice;
    }
    
    if ($fee !== null) {
        $updateFields[] = "fee = ?";
        $params[] = $fee;
    }
    
    if ($soldDate !== null) {
        $updateFields[] = "sold_date = ?";
        $params[] = $soldDate;
    }
    
    if (empty($updateFields)) {
        throw new Exception('未提供任何更新字段');
    }
    
    // 添加ID参数
    $params[] = $skinId;
    
    // 执行更新
    $sql = "UPDATE skins SET " . implode(", ", $updateFields) . " WHERE id = ?";
    
    if (defined('DEBUG') && DEBUG) {
        error_log("执行SQL: " . $sql);
        error_log("参数: " . print_r($params, true));
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('未做任何更改');
    }
    
    $db->commit();
    
    // 计算新的收益数据
    $newPurchasePrice = $purchasePrice !== null ? $purchasePrice : $skin['purchase_price'];
    $newSoldPrice = $soldPrice !== null ? $soldPrice : $skin['sold_price'];
    $newFee = $fee !== null ? $fee : $skin['fee'];
    $newSoldDate = $soldDate !== null ? $soldDate : $skin['sold_date'];
    $newPurchaseDate = $purchaseDate !== null ? $purchaseDate : $skin['purchase_date'];
    
    // 计算净收入和收益
    $netIncome = $newSoldPrice - $newFee;
    $profit = $netIncome - $newPurchasePrice;
    $profitPercent = ($newPurchasePrice > 0) ? ($profit / $newPurchasePrice * 100) : 0;
    
    // 添加日期格式检查
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newPurchaseDate)) {
        throw new Exception('购入日期格式无效，应为YYYY-MM-DD');
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newSoldDate)) {
        throw new Exception('售出日期格式无效，应为YYYY-MM-DD');
    }
    
    // 计算持有天数
    try {
        $purchaseDateObj = new DateTime($newPurchaseDate);
        $soldDateObj = new DateTime($newSoldDate);
        $daysHeld = $purchaseDateObj->diff($soldDateObj)->days;
        
        // 计算年化收益率
        $annualizedReturn = 0;
        if ($daysHeld > 0 && $newPurchasePrice > 0) {
            $totalReturn = $profit / $newPurchasePrice;
            $annualizedReturn = (pow(1 + $totalReturn, 365 / $daysHeld) - 1) * 100;
        }
    } catch (Exception $dateEx) {
        // 捕获日期处理错误
        error_log("日期处理错误: " . $dateEx->getMessage());
        error_log("购入日期: $newPurchaseDate, 售出日期: $newSoldDate");
        
        // 使用简单的日期差计算作为备用
        $daysHeld = 0;
        $annualizedReturn = 0;
    }
    
    echo json_encode([
        'success' => true,
        'message' => '已售出饰品更新成功',
        'data' => [
            'skin_id' => $skinId,
            'purchase_price' => $newPurchasePrice,
            'sold_price' => $newSoldPrice,
            'fee' => $newFee,
            'net_income' => $netIncome,
            'profit' => $profit,
            'profit_percent' => $profitPercent,
            'days_held' => $daysHeld,
            'annualized_return' => $annualizedReturn
        ]
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    // 记录错误到日志
    error_log("update_sold_skin.php错误: " . $e->getMessage());
    error_log("POST数据: " . print_r($_POST, true));
    
    // 确保返回有效的JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => '更新失败: ' . $e->getMessage(),
        'error_type' => 'database_error'
    ]);
} finally {
    // 确保脚本正常结束
    exit;
} 