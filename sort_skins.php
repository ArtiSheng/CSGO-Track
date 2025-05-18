<?php
require_once 'config.php';
require_once 'includes/db.php';

// 设置显示所有错误
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 设置输出格式
header('Content-Type: text/html; charset=utf-8');

// 处理表单提交
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sort_orders'])) {
    try {
        $sortOrders = $_POST['sort_orders'];
        
        // 开始事务
        $pdo->beginTransaction();
        
        // 更新每个饰品的排序
        $stmt = $pdo->prepare("UPDATE skins SET sort_order = ? WHERE id = ?");
        
        foreach ($sortOrders as $id => $order) {
            $stmt->execute([(int)$order, (int)$id]);
        }
        
        // 提交事务
        $pdo->commit();
        
        $message = '排序已成功保存';
        $messageType = 'success';
    } catch (Exception $e) {
        // 回滚事务
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $message = '保存排序时发生错误: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// 获取所有饰品，按排序值排序
try {
    $stmt = $pdo->query("SELECT id, name, purchase_price, market_price, price_platform, sort_order FROM skins ORDER BY sort_order ASC, id ASC");
    $skins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = '获取饰品列表失败: ' . $e->getMessage();
    $messageType = 'danger';
    $skins = [];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>饰品排序管理</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body { padding: 20px; }
        .container { max-width: 900px; }
        .drag-handle {
            cursor: grab;
            color: #888;
        }
        .sortable-item {
            background-color: #fff;
            border: 1px solid #dee2e6;
            margin-bottom: 8px;
            padding: 10px;
            border-radius: 4px;
        }
        .sortable-item:hover {
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>饰品排序管理</h1>
            <a href="index.php" class="btn btn-secondary">返回首页</a>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">拖拽排序列表</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">拖拽饰品调整显示顺序，完成后点击"保存排序"按钮。</p>
                
                <form method="post" id="sortForm">
                    <div class="sortable-list" id="sortable">
                        <?php foreach ($skins as $skin): ?>
                            <div class="sortable-item" data-id="<?php echo $skin['id']; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="drag-handle me-3">
                                        <i class="bi bi-grip-vertical fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?php echo htmlspecialchars($skin['name']); ?></div>
                                        <div class="text-muted small">
                                            购入价: ¥<?php echo number_format($skin['purchase_price'], 2); ?> | 
                                            市场价: ¥<?php echo number_format($skin['market_price'], 2); ?><?php echo !empty($skin['price_platform']) ? ' (' . $skin['price_platform'] . ')' : ''; ?>
                                        </div>
                                    </div>
                                    <div class="ms-2">
                                        <span class="badge bg-secondary">当前排序: <?php echo $skin['sort_order']; ?></span>
                                        <input type="hidden" name="sort_orders[<?php echo $skin['id']; ?>]" value="<?php echo $skin['sort_order']; ?>" class="sort-order-input">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">保存排序</button>
                        <button type="button" class="btn btn-outline-secondary ms-2" id="resetOrder">恢复原始排序</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化拖拽排序
            const sortableList = document.getElementById('sortable');
            const sortable = new Sortable(sortableList, {
                animation: 150,
                handle: '.drag-handle',
                onEnd: updateOrder
            });
            
            // 更新排序值
            function updateOrder() {
                const items = sortableList.querySelectorAll('.sortable-item');
                
                items.forEach((item, index) => {
                    const id = item.dataset.id;
                    const input = item.querySelector('.sort-order-input');
                    input.value = index + 1;
                    
                    // 更新显示的排序值
                    const badge = item.querySelector('.badge');
                    if (badge) {
                        badge.textContent = '当前排序: ' + (index + 1);
                    }
                });
            }
            
            // 重置排序
            document.getElementById('resetOrder').addEventListener('click', function() {
                if (confirm('确定要恢复原始排序吗？未保存的更改将丢失。')) {
                    window.location.reload();
                }
            });
        });
    </script>
</body>
</html> 