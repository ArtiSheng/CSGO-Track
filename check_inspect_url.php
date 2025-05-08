<?php
/**
 * 检视链接有效性检查工具
 * 用于验证检视链接是否有效，以及测试其是否能被API正常识别
 */

// 设置显示所有错误
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 引入必要文件
require_once 'config.php';
require_once 'includes/SteamDTAPI.php';

// 设置输出格式
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>检视链接检查工具</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2 { color: #333; }
        .valid { color: green; }
        .invalid { color: red; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .form-container { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .result-container { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        input[type="text"] { width: 100%; padding: 8px; margin-bottom: 10px; }
        button { padding: 8px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background: #45a049; }
    </style>
</head>
<body>
    <h1>CSGO检视链接检查工具</h1>
    
    <div class="form-container">
        <h2>输入检视链接</h2>
        <form method="post" action="">
            <input type="text" name="inspect_url" placeholder="输入Steam检视链接，例如：steam://rungame/730/..." 
                value="<?php echo isset($_POST['inspect_url']) ? htmlspecialchars($_POST['inspect_url']) : ''; ?>" required>
            <button type="submit">检查链接</button>
        </form>
    </div>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['inspect_url'])) {
        $inspectUrl = $_POST['inspect_url'];
        echo "<div class='result-container'>";
        echo "<h2>检查结果</h2>";
        
        // 基本格式检查
        echo "<h3>1. 基本格式检查</h3>";
        if (strpos($inspectUrl, 'steam://') === 0) {
            echo "<p class='valid'>✓ 检视链接格式正确，以 steam:// 开头</p>";
            $formatValid = true;
        } else {
            echo "<p class='invalid'>✗ 检视链接格式错误，必须以 steam:// 开头</p>";
            $formatValid = false;
        }
        
        // 检查CSGO特定格式
        echo "<h3>2. CSGO特定格式检查</h3>";
        if (strpos($inspectUrl, 'steam://rungame/730/') === 0 && strpos($inspectUrl, '+csgo_econ_action_preview') !== false) {
            echo "<p class='valid'>✓ 检视链接符合CSGO预览格式</p>";
            $csgoFormatValid = true;
        } else {
            echo "<p class='invalid'>✗ 检视链接不符合CSGO预览格式</p>";
            echo "<p>正确格式示例：steam://rungame/730/76561202255233023/+csgo_econ_action_preview%20S...</p>";
            $csgoFormatValid = false;
        }
        
        // 如果基本格式正确，继续API测试
        if ($formatValid && $csgoFormatValid) {
            echo "<h3>3. API测试</h3>";
            
            // 初始化API
            $api = new SteamDTAPI();
            
            // 不使用回调，直接请求
            $url = API_BASE_URL . '/open/cs2/v1/wear';
            $data = [
                'inspectUrl' => $inspectUrl
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . STEAMDT_API_KEY
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($response === false) {
                echo "<p class='invalid'>✗ API请求失败: " . curl_error($ch) . "</p>";
                $apiValid = false;
            } else if ($httpCode !== 200) {
                echo "<p class='invalid'>✗ API请求返回错误状态码: " . $httpCode . "</p>";
                $apiValid = false;
            } else {
                $result = json_decode($response, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo "<p class='invalid'>✗ API响应JSON解析失败: " . json_last_error_msg() . "</p>";
                    $apiValid = false;
                } else if (!isset($result['success']) || !$result['success']) {
                    $errorMsg = isset($result['errorMsg']) ? $result['errorMsg'] : (isset($result['message']) ? $result['message'] : '未知错误');
                    echo "<p class='invalid'>✗ API返回错误: " . htmlspecialchars($errorMsg) . "</p>";
                    
                    // 检查是否是过期链接
                    if (strpos($errorMsg, '过期') !== false || strpos($errorMsg, '无效') !== false) {
                        echo "<p>此检视链接可能已过期或无效，请从Steam中获取新的检视链接</p>";
                    }
                    
                    $apiValid = false;
                } else if (isset($result['data']['sync']) && $result['data']['sync'] === false) {
                    echo "<p class='valid'>✓ API响应正常，需要异步处理</p>";
                    echo "<p>任务ID: " . htmlspecialchars($result['data']['taskId'] ?? '未知') . "</p>";
                    $apiValid = true;
                } else {
                    // 检查是否有磨损度信息
                    $hasFloatData = false;
                    $floatValue = null;
                    
                    // 检查常见的响应格式路径
                    $possiblePaths = [
                        ['data', 'itemPreviewData', 'floatWear'],
                        ['data', 'floatWear'],
                        ['data', 'float_value'],
                        ['data', 'float']
                    ];
                    
                    foreach ($possiblePaths as $path) {
                        $value = $result;
                        foreach ($path as $key) {
                            if (isset($value[$key])) {
                                $value = $value[$key];
                            } else {
                                $value = null;
                                break;
                            }
                        }
                        
                        if (is_numeric($value)) {
                            $floatValue = $value;
                            $hasFloatData = true;
                            break;
                        }
                    }
                    
                    if ($hasFloatData) {
                        echo "<p class='valid'>✓ API响应正常，磨损度: " . $floatValue . "</p>";
                        $apiValid = true;
                    } else {
                        echo "<p class='invalid'>✗ API响应中未找到磨损度数据</p>";
                        $apiValid = false;
                    }
                }
                
                echo "<h4>API响应详情：</h4>";
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
            }
            
            curl_close($ch);
        }
        
        // 总结
        echo "<h3>4. 检查结果总结</h3>";
        if (isset($formatValid) && $formatValid && isset($csgoFormatValid) && $csgoFormatValid && isset($apiValid) && $apiValid) {
            echo "<p class='valid'>✓ 检视链接有效且可以正常使用</p>";
        } else {
            echo "<p class='invalid'>✗ 检视链接存在问题，请检查上述详细信息</p>";
            
            // 提供一些常见问题的解决方案
            echo "<h4>常见问题解决方案：</h4>";
            echo "<ul>";
            echo "<li>确保检视链接是从最新的CS2游戏中获取的</li>";
            echo "<li>检视链接可能已过期，请重新从Steam库存或市场获取</li>";
            echo "<li>如果使用的是CS2，确保检视链接包含正确的格式</li>";
            echo "<li>如果API返回系统异常，可能是服务器暂时不可用，请稍后再试</li>";
            echo "</ul>";
        }
        
        echo "</div>";
    }
    ?>
    
    <div class="form-container">
        <h2>如何获取正确的检视链接</h2>
        <ol>
            <li>打开Steam客户端，进入库存页面</li>
            <li>找到您想要查看的CS2饰品</li>
            <li>右键点击该饰品，选择"复制检视链接"</li>
            <li>粘贴到上方输入框中进行检查</li>
        </ol>
        <p>注意：检视链接通常会在一段时间后过期，如果链接无效，请重新获取最新的检视链接。</p>
    </div>
</body>
</html> 