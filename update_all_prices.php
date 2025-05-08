<?php
/**
 * 批量更新所有饰品的市场价格
 * 可通过浏览器访问或命令行运行
 * 命令行运行: php update_all_prices.php
 */

require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/SteamDTAPI.php';
require_once 'includes/SteamItemManager.php';

// 初始化
$isCliMode = (php_sapi_name() === 'cli');
$startTime = microtime(true);
$manager = SteamItemManager::getInstance();
$db = Database::getInstance()->getConnection();

// 设置超时时间（如果需要）
set_time_limit(300); // 5分钟超时

// 如果不是CLI模式，输出HTML头部
if (!$isCliMode) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批量更新价格</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>批量更新所有饰品价格</h1>
        <div class="progress mb-3">
            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" id="progress-bar"></div>
        </div>
        <div id="results">
            <p>开始更新价格...</p>
        </div>
';

    // 添加自动刷新日志的JavaScript
    echo '<script>
        let logDiv = document.getElementById("results");
        function scrollToBottom() {
            window.scrollTo(0, document.body.scrollHeight);
        }
        
        function updateProgress(percent) {
            let progressBar = document.getElementById("progress-bar");
            progressBar.style.width = percent + "%";
            progressBar.setAttribute("aria-valuenow", percent);
        }
        
        scrollToBottom();
    </script>';
    
    // 刷新输出缓冲区
    flush();
}

// 输出日志函数
function outputLog($message, $type = 'info') {
    global $isCliMode;
    
    // 添加日志到错误日志
    error_log("[价格更新] " . strip_tags($message));
    
    if ($isCliMode) {
        echo $message . PHP_EOL;
    } else {
        echo "<p class=\"{$type}\">" . $message . "</p>\n";
        flush();
    }
}

// 更新进度条
function updateProgressBar($current, $total) {
    global $isCliMode;
    
    $percent = round(($current / $total) * 100);
    
    if (!$isCliMode) {
        echo "<script>updateProgress({$percent});</script>\n";
        flush();
    } else {
        echo "进度: {$percent}% ({$current}/{$total})" . PHP_EOL;
    }
}

try {
    // 获取所有需要更新的饰品
    $stmt = $db->query("SELECT id, name, marketHashName FROM skins ORDER BY id");
    $skins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalSkins = count($skins);
    
    if ($totalSkins == 0) {
        outputLog("没有找到需要更新的饰品", "error");
    } else {
        outputLog("找到 {$totalSkins} 个饰品需要更新价格", "info");
        
        // 更新每个饰品的价格
        $successCount = 0;
        $failCount = 0;
        
        foreach ($skins as $index => $skin) {
            $skinId = $skin['id'];
            $name = $skin['name'];
            $marketHashName = $skin['marketHashName'];
            
            outputLog("正在更新 [{$skinId}] {$name}...");
            
            // 更新价格
            $result = $manager->updateMarketPrice($marketHashName);
            
            if ($result) {
                // 获取更新后的价格
                $priceStmt = $db->prepare("SELECT market_price, price_platform FROM skins WHERE id = ?");
                $priceStmt->execute([$skinId]);
                $priceData = $priceStmt->fetch(PDO::FETCH_ASSOC);
                
                $newPrice = $priceData['market_price'];
                $platform = $priceData['price_platform'];
                
                outputLog("✓ 价格更新成功: ¥" . number_format($newPrice, 2) . " ({$platform})", "success");
                $successCount++;
            } else {
                outputLog("✗ 价格更新失败", "error");
                $failCount++;
            }
            
            // 更新进度条
            updateProgressBar($index + 1, $totalSkins);
            
            // 添加短暂延迟，避免API请求过于频繁
            usleep(500000); // 500毫秒延迟
        }
        
        // 输出统计信息
        $timeUsed = round(microtime(true) - $startTime, 2);
        outputLog("价格更新完成! 成功: {$successCount}, 失败: {$failCount}, 用时: {$timeUsed}秒", "info");
    }
} catch (Exception $e) {
    outputLog("发生错误: " . $e->getMessage(), "error");
}

// 如果不是CLI模式，输出HTML尾部
if (!$isCliMode) {
    echo '
        <p>
            <a href="index.php" class="btn btn-primary">返回首页</a>
            <button onclick="window.location.reload()" class="btn btn-secondary">重新执行</button>
        </p>
    </div>
</body>
</html>';
}
?> 