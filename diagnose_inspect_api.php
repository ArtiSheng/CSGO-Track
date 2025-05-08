<?php
/**
 * API诊断脚本，检查api/get_inspect_images.php的问题
 * 使用方法：在浏览器中访问 diagnose_inspect_api.php?skin_id=饰品ID
 */

// 设置显示所有错误
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 引入必要的文件
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/SteamDTAPI.php';
require_once 'includes/Database.php';

// 设置输出为HTML
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>API诊断工具</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2, h3 { color: #333; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        .section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>API诊断工具</h1>
    
    <?php
    // 获取skin_id参数
    $skinId = isset($_GET['skin_id']) ? (int)$_GET['skin_id'] : null;
    
    if (!$skinId) {
        ?>
        <div class="section error">
            <h2>错误：缺少skin_id参数</h2>
            <p>请提供一个有效的skin_id参数：<a href="?skin_id=1">例如：?skin_id=1</a></p>
            
            <h3>当前skin_id列表：</h3>
            <ul>
            <?php
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->query("SELECT id, name FROM skins ORDER BY id");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<li><a href=\"?skin_id={$row['id']}\">{$row['id']}: {$row['name']}</a></li>";
                }
            } catch (Exception $e) {
                echo "<li class='error'>获取skin_id列表失败: " . $e->getMessage() . "</li>";
            }
            ?>
            </ul>
        </div>
        <?php
        exit;
    }
    
    // 显示诊断信息
    echo "<div class='section'>";
    echo "<h2>诊断skin_id: {$skinId}</h2>";
    
    // 获取饰品信息
    try {
        $stmt = $pdo->prepare("SELECT * FROM skins WHERE id = ?");
        $stmt->execute([$skinId]);
        $skin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$skin) {
            echo "<p class='error'>饰品未找到!</p>";
            exit;
        }
        
        echo "<h3>饰品基本信息</h3>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> {$skin['id']}</li>";
        echo "<li><strong>名称:</strong> {$skin['name']}</li>";
        echo "<li><strong>检视链接:</strong> <span style='word-break: break-all;'>{$skin['inspect_url']}</span></li>";
        echo "</ul>";
        
        // 检查检视链接是否有效
        if (empty($skin['inspect_url'])) {
            echo "<p class='error'>检视链接为空!</p>";
        } else if (strpos($skin['inspect_url'], 'steam://') !== 0) {
            echo "<p class='warning'>检视链接格式可能不正确 (应以 'steam://' 开头)</p>";
        }
        
        // 显示API诊断信息
        echo "<div class='api-test'>";
        echo "<h3>API 功能测试</h3>";

        // 添加检视图测试
        if ($skin && !empty($skin['inspect_url'])) {
            echo "<div class='test-section'>";
            echo "<h4>检视图测试</h4>";
            
            try {
                $api = new SteamDTAPI();
                $inspectResult = $api->getInspectImages($skin['inspect_url']);
                
                echo "<div class='api-response'>";
                echo "<strong>API 响应:</strong> <pre>" . htmlspecialchars(json_encode($inspectResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
                
                if ($inspectResult['success']) {
                    echo "<div class='success'>检视图API调用成功!</div>";
                    
                    if (isset($inspectResult['data']['screenshot']['screenshots']) && is_array($inspectResult['data']['screenshot']['screenshots'])) {
                        echo "<div class='screenshots'>";
                        echo "<h5>检视图</h5>";
                        foreach ($inspectResult['data']['screenshot']['screenshots'] as $screenshot) {
                            echo "<img src='{$screenshot}' class='img-fluid mb-2' style='max-width: 400px;'>";
                        }
                        echo "</div>";
                    } else {
                        echo "<div class='warning'>API返回成功，但未找到截图数据</div>";
                    }
                } else {
                    echo "<div class='error'>检视图API调用失败: " . ($inspectResult['errorMsg'] ?? $inspectResult['message'] ?? '未知错误') . "</div>";
                }
                
                echo "</div>";
            } catch (Exception $e) {
                echo "<div class='error'>检视图API调用异常: " . $e->getMessage() . "</div>";
            }
            
            echo "</div>"; // End test-section
        }
        
        // 模拟api/get_inspect_images.php的调用
        echo "<h3>模拟api/get_inspect_images.php的调用</h3>";
        echo "<p>这个测试将模拟API调用过程，检查可能的问题...</p>";
        
        // 检查db.php和Database.php的一致性
        echo "<h4>数据库连接检查</h4>";
        
        if (isset($pdo) && $pdo instanceof PDO) {
            echo "<p class='success'>db.php 中的 \$pdo 有效</p>";
        } else {
            echo "<p class='error'>db.php 中的 \$pdo 无效!</p>";
        }
        
        try {
            $dbInstance = Database::getInstance();
            $dbConnection = $dbInstance->getConnection();
            if ($dbConnection instanceof PDO) {
                echo "<p class='success'>Database 类连接有效</p>";
            } else {
                echo "<p class='error'>Database 类连接无效!</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>Database 类出错: " . $e->getMessage() . "</p>";
        }
        
        // 检查API方法是否存在
        echo "<h4>API方法检查</h4>";
        
        if (method_exists($api, 'getInspectImages')) {
            echo "<p class='success'>getInspectImages 方法存在</p>";
        } else {
            echo "<p class='error'>getInspectImages 方法不存在!</p>";
        }
        
        // 检查API URL和密钥
        echo "<h4>API配置检查</h4>";
        
        if (defined('API_BASE_URL') && !empty(API_BASE_URL)) {
            echo "<p class='success'>API_BASE_URL 已定义: " . API_BASE_URL . "</p>";
        } else {
            echo "<p class='error'>API_BASE_URL 未定义或为空!</p>";
        }
        
        if (defined('STEAMDT_API_KEY') && !empty(STEAMDT_API_KEY)) {
            echo "<p class='success'>STEAMDT_API_KEY 已定义</p>";
        } else {
            echo "<p class='error'>STEAMDT_API_KEY 未定义或为空!</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>诊断过程中发生错误: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
    ?>
    
    <div class="section">
        <h2>其他诊断工具</h2>
        <ul>
            <li><a href="test_inspect_cli.php" target="_blank">命令行检视图测试工具 (test_inspect_cli.php)</a></li>
        </ul>
        <p>命令行工具使用示例：</p>
        <pre>php test_inspect_cli.php "steam://rungame/730/76561202255233023/+csgo_econ_action_preview%20S76561198082398031A29193125493D5227599390355187286"</pre>
    </div>
</body>
</html> 