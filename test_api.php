<?php
/**
 * SteamDT API 测试脚本
 * 用于直接测试API连接和响应
 */

// 设置显示所有错误
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 引入配置文件
require_once 'config.php';

// 设置输出格式
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>API测试</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2 { color: #333; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
        .section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>SteamDT API 测试</h1>
    
    <div class="section">
        <h2>1. API 基本信息</h2>
        <p>API 基础URL: <?php echo API_BASE_URL; ?></p>
        <p>API KEY: <?php echo substr(STEAMDT_API_KEY, 0, 5) . '...' . substr(STEAMDT_API_KEY, -5); ?></p>
        <p>回调地址: https://你的域名/wear_callback.php</p>
    </div>
    
    <?php
    // 测试API连接
    $testUrl = API_BASE_URL . '/open/cs2/v1/ping';
    
    echo "<div class='section'>";
    echo "<h2>2. API 连接测试</h2>";
    echo "<p>测试URL: {$testUrl}</p>";
    
    // 发起请求
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . STEAMDT_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false) {
        echo "<p class='error'>连接失败: " . curl_error($ch) . "</p>";
    } else {
        echo "<p>HTTP状态码: {$httpCode}</p>";
        echo "<p>原始响应:</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
        $result = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($result['success']) && $result['success']) {
                echo "<p class='success'>API连接成功!</p>";
            } else {
                echo "<p class='error'>API连接失败: " . ($result['message'] ?? $result['errorMsg'] ?? '未知错误') . "</p>";
            }
        } else {
            echo "<p class='error'>JSON解析错误: " . json_last_error_msg() . "</p>";
        }
    }
    curl_close($ch);
    echo "</div>";
    
    // 测试磨损度API
    if (isset($_POST['inspect_url'])) {
        $inspectUrl = $_POST['inspect_url'];
        $callbackUrl = isset($_POST['callback_url']) ? $_POST['callback_url'] : 'https://你的域名/wear_callback.php';
        
        echo "<div class='section'>";
        echo "<h2>3. 磨损度API测试</h2>";
        echo "<p>检视链接: " . htmlspecialchars($inspectUrl) . "</p>";
        echo "<p>回调URL: " . htmlspecialchars($callbackUrl) . "</p>";
        
        $wearUrl = API_BASE_URL . '/open/cs2/v1/wear';
        $data = [
            'inspectUrl' => $inspectUrl,
            'callbackUrl' => $callbackUrl
        ];
        
        echo "<p>请求数据:</p>";
        echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        
        // 发起请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $wearUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . STEAMDT_API_KEY
        ]);
        
        // 启用详细输出，显示请求头和响应头
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // 获取详细信息
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        echo "<p>CURL详细日志:</p>";
        echo "<pre>" . htmlspecialchars($verboseLog) . "</pre>";
        
        if ($response === false) {
            echo "<p class='error'>请求失败: " . curl_error($ch) . "</p>";
        } else {
            echo "<p>HTTP状态码: {$httpCode}</p>";
            echo "<p>原始响应:</p>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
            
            $result = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (isset($result['success']) && $result['success']) {
                    echo "<p class='success'>API请求成功!</p>";
                    
                    // 显示磨损度信息
                    if (isset($result['data']['floatWear']) || isset($result['data']['itemPreviewData']['floatWear'])) {
                        $floatValue = isset($result['data']['floatWear']) ? 
                            $result['data']['floatWear'] : 
                            $result['data']['itemPreviewData']['floatWear'];
                        echo "<p class='success'>磨损度: " . $floatValue . "</p>";
                    } else {
                        echo "<p>未找到磨损度信息，完整响应结构:</p>";
                        echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                    }
                } else if (isset($result['data']['sync']) && $result['data']['sync'] === false) {
                    echo "<p class='success'>异步处理中，任务ID: " . ($result['data']['taskId'] ?? '未知') . "</p>";
                    echo "<p>请稍后检查回调日志</p>";
                } else {
                    echo "<p class='error'>API返回错误: " . ($result['message'] ?? $result['errorMsg'] ?? '未知错误') . "</p>";
                }
            } else {
                echo "<p class='error'>JSON解析错误: " . json_last_error_msg() . "</p>";
            }
        }
        curl_close($ch);
        echo "</div>";
    }
    ?>
    
    <div class="section">
        <h2>测试磨损度API</h2>
        <form method="post" action="">
            <div>
                <label for="inspect_url">检视链接:</label><br>
                <input type="text" id="inspect_url" name="inspect_url" style="width: 100%;" value="<?php echo isset($_POST['inspect_url']) ? htmlspecialchars($_POST['inspect_url']) : ''; ?>" required>
            </div>
            <div style="margin-top: 10px;">
                <label for="callback_url">回调URL (可选):</label><br>
                <input type="text" id="callback_url" name="callback_url" style="width: 100%;" value="<?php echo isset($_POST['callback_url']) ? htmlspecialchars($_POST['callback_url']) : 'https://你的域名/wear_callback.php'; ?>">
            </div>
            <div style="margin-top: 10px;">
                <button type="submit">测试API</button>
            </div>
        </form>
    </div>
    
    <div class="section">
        <h2>回调日志检查</h2>
        <?php
        $logFile = 'logs/wear_callback_' . date('Y-m-d') . '.log';
        if (file_exists($logFile)) {
            echo "<p>今日回调日志:</p>";
            echo "<pre>" . htmlspecialchars(file_get_contents($logFile)) . "</pre>";
        } else {
            echo "<p>未找到今日回调日志文件，请先进行API测试或检查日志文件路径。</p>";
        }
        ?>
    </div>
</body>
</html> 