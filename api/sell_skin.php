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

// 获取请求数据
// 使用$_POST而不是php://input，因为前端使用FormData提交
if (empty($_POST)) {
    echo json_encode(['success' => false, 'message' => '未接收到POST数据']);
    exit;
}

// 记录接收到的数据用于调试
if (defined('DEBUG') && DEBUG) {
    error_log("卖出饰品 - 接收到的POST数据: " . print_r($_POST, true));
}

// 验证必要字段
if (!isset($_POST['id']) || !isset($_POST['sold_price']) || !isset($_POST['sold_date'])) {
    echo json_encode(['success' => false, 'message' => '缺少必要参数: ' . 
        (!isset($_POST['id']) ? 'id ' : '') .
        (!isset($_POST['sold_price']) ? 'sold_price ' : '') .
        (!isset($_POST['sold_date']) ? 'sold_date' : '')
    ]);
    exit;
}

$id = $_POST['id'];
$soldPrice = floatval($_POST['sold_price']);
$fee = isset($_POST['fee']) ? floatval($_POST['fee']) : 0;
$soldDate = $_POST['sold_date'];

try {
    // 开始事务
    $db->beginTransaction();
    
    // 获取饰品信息
    $stmt = $db->prepare("SELECT * FROM skins WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $skin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$skin) {
        throw new Exception('找不到该饰品');
    }
    
    // 更新饰品状态为已售出
    $stmt = $db->prepare("
        UPDATE skins 
        SET is_sold = 1, 
            sold_price = :sold_price, 
            fee = :fee,
            sold_date = :sold_date
        WHERE id = :id
    ");
    
    $stmt->execute([
        ':id' => $id,
        ':sold_price' => $soldPrice,
        ':fee' => $fee,
        ':sold_date' => $soldDate
    ]);
    
    // 计算净收入和收益
    $purchasePrice = floatval($skin['purchase_price']);
    $netIncome = $soldPrice - $fee; // 实际到手金额
    $profit = $netIncome - $purchasePrice; // 实际盈亏
    $profitPercent = ($purchasePrice > 0) ? ($profit / $purchasePrice * 100) : 0;
    
    // 计算持有天数
    $purchaseDate = new DateTime($skin['purchase_date']);
    $sellDate = new DateTime($soldDate);
    $daysHeld = $purchaseDate->diff($sellDate)->days;
    
    // 计算年化收益率（采用复利计算）
    $annualizedReturn = 0;
    if ($daysHeld > 0 && $purchasePrice > 0) {
        // 计算总收益率
        $totalReturn = $profit / $purchasePrice;
        
        // 使用复利公式计算年化收益率: (1 + r)^(365/days) - 1
        // 其中r是总收益率，days是持有天数
        $annualizedReturn = (pow(1 + $totalReturn, 365 / $daysHeld) - 1) * 100;
    }
    
    $db->commit();
    
    // 返回结果
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
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => '卖出失败: ' . $e->getMessage()]);
} 