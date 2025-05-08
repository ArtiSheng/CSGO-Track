<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/SteamDTAPI.php';
require_once 'includes/SteamItemManager.php';

$manager = SteamItemManager::getInstance();
$db = Database::getInstance()->getConnection();

// 获取统计类型
$type = isset($_GET['type']) ? $_GET['type'] : 'investment';
$title = '';

switch ($type) {
    case 'investment':
        $title = '投资总额';
        break;
    case 'value':
        $title = '当前总值';
        break;
    case 'profit':
        $title = '总盈亏';
        break;
    case 'roi':
        $title = '投资回报率';
        break;
    default:
        $title = '统计数据';
        break;
}

// 获取数据
$data = [];
$labels = [];

// 检查daily_stats表是否存在
$tableExists = false;
try {
    $checkTable = $db->query("SHOW TABLES LIKE 'daily_stats'");
    $tableExists = ($checkTable->rowCount() > 0);
} catch (PDOException $e) {
    // 表不存在或查询出错
}

if (!$tableExists) {
    // 表不存在，使用临时数据
    $labels = [date('Y-m-d')];
    $currentTotal = 0;
    $currentValue = 0;
    $profit = 0;
    $roiValue = 0;
    
    // 尝试从skins表获取基本统计数据
    try {
        $stmt = $db->query("SELECT SUM(purchase_price) as total_investment, SUM(market_price) as total_value FROM skins");
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $currentTotal = floatval($row['total_investment'] ?? 0);
            $currentValue = floatval($row['total_value'] ?? 0);
            $profit = $currentValue - $currentTotal;
            $roiValue = $currentTotal > 0 ? ($profit / $currentTotal * 100) : 0;
        }
    } catch (PDOException $e) {
        // 如果查询失败，使用默认值
    }
    
    switch ($type) {
        case 'investment':
            $data = [$currentTotal];
            break;
        case 'value':
            $data = [$currentValue];
            break;
        case 'profit':
            $data = [$profit];
            break;
        case 'roi':
            $data = [$roiValue];
            break;
    }
    
    // 设置通知信息
    $notification = '统计数据表尚未创建，请先运行 <a href="update_stats_db.php">数据库更新</a>';
} else {
    // 从数据库中获取每日统计数据
    try {
        $query = "SELECT date, total_investment, total_value, total_profit, roi 
              FROM daily_stats 
              ORDER BY date ASC";

        $stmt = $db->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            $labels[] = $row['date'];
            
            switch ($type) {
                case 'investment':
                    $data[] = floatval($row['total_investment']);
                    break;
                case 'value':
                    $data[] = floatval($row['total_value']);
                    break;
                case 'profit':
                    $data[] = floatval($row['total_profit']);
                    break;
                case 'roi':
                    $data[] = floatval($row['roi']);
                    break;
            }
        }
    } catch (PDOException $e) {
        // 处理查询错误
        $labels = [date('Y-m-d')];
        $data = [0];
        $notification = '获取统计数据失败: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - CSGO饰品价格追踪</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">CSGO饰品价格追踪</a>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">返回主页</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($notification)): ?>
        <div class="alert alert-warning">
            <?php echo $notification; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $title; ?>趋势图</h5>
            </div>
            <div class="card-body">
                <div style="height: 400px;">
                    <canvas id="statsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 准备数据
        const chartData = {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: '<?php echo $title; ?>',
                data: <?php echo json_encode($data); ?>,
                fill: false,
                borderColor: '<?php echo $type === "profit" ? "rgba(40, 167, 69, 1)" : ($type === "roi" ? "rgba(23, 162, 184, 1)" : "rgba(0, 123, 255, 1)"); ?>',
                tension: 0.1
            }]
        };

        // 配置
        const chartConfig = {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                <?php if ($type === 'roi'): ?>
                                return value + '%';
                                <?php else: ?>
                                return '¥' + value.toFixed(2);
                                <?php endif; ?>
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                <?php if ($type === 'roi'): ?>
                                label += context.parsed.y.toFixed(2) + '%';
                                <?php else: ?>
                                label += '¥' + context.parsed.y.toFixed(2);
                                <?php endif; ?>
                                return label;
                            }
                        }
                    }
                }
            }
        };

        // 初始化图表
        window.onload = function() {
            const ctx = document.getElementById('statsChart').getContext('2d');
            new Chart(ctx, chartConfig);
        };
    </script>
</body>
</html> 