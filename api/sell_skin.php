<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/SteamItemManager.php';

// 设置响应头
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
    // 获取请求参数
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $soldPrice = isset($_POST['sold_price']) ? floatval($_POST['sold_price']) : 0;
    $soldDate = isset($_POST['sold_date']) ? $_POST['sold_date'] : null;
    $fee = isset($_POST['fee']) ? floatval($_POST['fee']) : 0;
    
    // 记录接收到的参数
    error_log("卖出饰品 - 接收到的参数: " . json_encode([
        'id' => $id,
        'sold_price' => $soldPrice,
        'sold_date' => $soldDate,
        'fee' => $fee
    ]));
    
    // 验证必填参数
    if ($id <= 0) {
        throw new Exception('无效的饰品ID');
    }
    
    if ($soldPrice <= 0) {
        throw new Exception('卖出价格必须大于0');
    }
    
    if (empty($soldDate)) {
        throw new Exception('卖出日期不能为空');
    }
    
    // 获取数据库连接
    $db = Database::getInstance()->getConnection();
    
    // 开始事务
    $db->beginTransaction();
    
    // 检查饰品是否存在且未售出
    $stmt = $db->prepare("SELECT * FROM skins WHERE id = ? AND (is_sold = 0 OR is_sold IS NULL)");
    $stmt->execute([$id]);
    $skin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$skin) {
        $stmt = $db->prepare("SELECT is_sold FROM skins WHERE id = ?");
        $stmt->execute([$id]);
        $checkSkin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$checkSkin) {
            throw new Exception('找不到该饰品');
        } elseif ($checkSkin['is_sold'] == 1) {
            throw new Exception('该饰品已经被标记为售出状态');
        } else {
            throw new Exception('无法确认饰品状态');
        }
    }
    
    // 获取管理器实例
    $manager = SteamItemManager::getInstance();
    
    // 标记饰品为已售出状态
    $result = $manager->sellSkin($id, $soldPrice, $soldDate, $fee);
    
    if (!$result) {
        throw new Exception('标记饰品为已售出状态失败');
    }
    
    // 计算利润和收益率
    $purchasePrice = floatval($skin['purchase_price']);
    $netIncome = $soldPrice - $fee;
    $profit = $netIncome - $purchasePrice;
    $profitPercent = ($purchasePrice > 0) ? (($netIncome - $purchasePrice) / $purchasePrice * 100) : 0;
    
    // 计算持有天数
    $purchaseDate = new DateTime($skin['purchase_date']);
    $sellDate = new DateTime($soldDate);
    $daysHeld = $purchaseDate->diff($sellDate)->days;
    
    // 计算年化收益率
    $annualizedReturn = 0;
    if ($daysHeld > 0 && $purchasePrice > 0) {
        $totalReturn = ($netIncome - $purchasePrice) / $purchasePrice;
        $annualizedReturn = (pow(1 + $totalReturn, 365 / $daysHeld) - 1) * 100;
    }
    
    // 提交事务
    $db->commit();
    
    // 返回成功响应
    echo json_encode([
        'success' => true,
        'message' => '饰品已成功标记为已售出',
        'data' => [
            'id' => $id,
            'name' => $skin['name'],
            'purchase_price' => $purchasePrice,
            'sold_price' => $soldPrice,
            'fee' => $fee,
            'net_income' => $netIncome,
            'profit' => $profit,
            'profit_percent' => $profitPercent,
            'days_held' => $daysHeld,
            'annualized_return' => $annualizedReturn
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => '卖出失败: ' . $e->getMessage()]);
} 