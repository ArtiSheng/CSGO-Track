<?php
/**
 * 价格更新调试脚本
 * 用于检查饰品价格是否正确更新到数据库，并诊断前端显示问题
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置输出类型为HTML
header('Content-Type: text/html; charset=utf-8');

// 引入必要的类
require_once 'config.php';
require_once 'includes/Database.php';

echo "<h1>价格更新调试</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .error { color: red; font-weight: bold; }
    .success { color: green; font-weight: bold; }
    pre { background: #f8f8f8; padding: 10px; border: 1px solid #ddd; overflow: auto; }
</style>";

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConnection();
    
    // 1. 检查数据库连接
    echo "<h2>1. 数据库连接</h2>";
    echo "<p class='success'>数据库连接成功</p>";
    
    // 2. 检查饰品表结构
    echo "<h2>2. 饰品表结构检查</h2>";
    $tableInfo = $db->query("DESCRIBE skins")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table>";
    echo "<tr><th>字段</th><th>类型</th><th>Null</th><th>默认值</th></tr>";
    foreach ($tableInfo as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 检查market_price字段是否存在
    $market_price_exists = false;
    foreach ($tableInfo as $column) {
        if ($column['Field'] === 'market_price') {
            $market_price_exists = true;
            break;
        }
    }
    
    if (!$market_price_exists) {
        echo "<p class='error'>警告：market_price字段不存在！需要检查表结构或字段名称。</p>";
    } else {
        echo "<p class='success'>market_price字段存在</p>";
    }
    
    // 3. 检查饰品表中的价格数据 - 修改查询中的last_update为last_updated
    echo "<h2>3. 价格数据检查</h2>";
    
    // 先检查last_updated字段是否存在
    $last_updated_field = 'last_updated';
    $found = false;
    foreach ($tableInfo as $column) {
        if ($column['Field'] === 'last_updated') {
            $found = true;
            break;
        } elseif ($column['Field'] === 'last_update') {
            $last_updated_field = 'last_update';
            $found = true;
            break;
        }
    }
    
    if ($found) {
        $stmt = $db->query("
            SELECT id, name, market_price, $last_updated_field, marketHashName
            FROM skins 
            WHERE is_sold = 0 OR is_sold IS NULL
            ORDER BY $last_updated_field DESC
            LIMIT 10
        ");
        $skins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>ID</th><th>名称</th><th>市场价格</th><th>最后更新时间</th><th>Market Hash名称</th></tr>";
        foreach ($skins as $skin) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($skin['id']) . "</td>";
            echo "<td>" . htmlspecialchars($skin['name']) . "</td>";
            
            // 检查价格是否为0
            if (empty($skin['market_price']) || $skin['market_price'] == 0) {
                echo "<td class='error'>" . htmlspecialchars($skin['market_price']) . "</td>";
            } else {
                echo "<td class='success'>" . htmlspecialchars($skin['market_price']) . "</td>";
            }
            
            echo "<td>" . htmlspecialchars($skin[$last_updated_field] ?? '未更新') . "</td>";
            echo "<td>" . htmlspecialchars($skin['marketHashName'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>警告：未找到last_updated或last_update字段，无法显示最后更新时间。</p>";
        
        // 仍然显示价格数据，但不显示更新时间
        $stmt = $db->query("
            SELECT id, name, market_price, marketHashName
            FROM skins 
            WHERE is_sold = 0 OR is_sold IS NULL
            LIMIT 10
        ");
        $skins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>ID</th><th>名称</th><th>市场价格</th><th>Market Hash名称</th></tr>";
        foreach ($skins as $skin) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($skin['id']) . "</td>";
            echo "<td>" . htmlspecialchars($skin['name']) . "</td>";
            
            // 检查价格是否为0
            if (empty($skin['market_price']) || $skin['market_price'] == 0) {
                echo "<td class='error'>" . htmlspecialchars($skin['market_price']) . "</td>";
            } else {
                echo "<td class='success'>" . htmlspecialchars($skin['market_price']) . "</td>";
            }
            
            echo "<td>" . htmlspecialchars($skin['marketHashName'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 统计有多少饰品价格为0
    $zero_price_count = $db->query("
        SELECT COUNT(*) FROM skins 
        WHERE (is_sold = 0 OR is_sold IS NULL) 
        AND (market_price IS NULL OR market_price = 0)
    ")->fetchColumn();
    
    $total_count = $db->query("
        SELECT COUNT(*) FROM skins 
        WHERE is_sold = 0 OR is_sold IS NULL
    ")->fetchColumn();
    
    echo "<p>未售出饰品总数: <strong>$total_count</strong></p>";
    echo "<p>价格为0的饰品数量: <strong>$zero_price_count</strong></p>";
    
    if ($zero_price_count / $total_count > 0.5) {
        echo "<p class='error'>警告：超过50%的饰品价格为0，可能存在系统性问题！</p>";
    }
    
    // 4. 检查最近的价格更新历史
    echo "<h2>4. 价格历史记录检查</h2>";
    
    echo "<p>您的应用使用SteamDT网站来查看饰品价格历史走势，因此不需要在本地数据库存储历史记录。</p>";
    echo "<p>建议：可以考虑<a href='cleanup_price_history.php' style='color:blue;'>清理价格历史表</a>以节省数据库空间和提高系统性能。</p>";
    
    try {
        $stmt = $db->query("
            SELECT h.id, h.skin_id, s.name, h.price, h.date
            FROM price_history h
            JOIN skins s ON h.skin_id = s.id
            ORDER BY h.date DESC
            LIMIT 10
        ");
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($history)) {
            echo "<p class='success'>没有找到价格历史记录，这是正常的，因为价格历史应该在SteamDT上查看。</p>";
        } else {
            echo "<div class='warning' style='color:orange; font-weight:bold; margin: 10px 0;'>
                发现本地价格历史记录。这些记录占用数据库空间但可能不必要，因为历史价格可以从SteamDT查看。
            </div>";
            echo "<table>";
            echo "<tr><th>ID</th><th>饰品ID</th><th>饰品名称</th><th>记录价格</th><th>记录时间</th></tr>";
            foreach ($history as $record) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($record['id']) . "</td>";
                echo "<td>" . htmlspecialchars($record['skin_id']) . "</td>";
                echo "<td>" . htmlspecialchars($record['name']) . "</td>";
                echo "<td>" . htmlspecialchars($record['price']) . "</td>";
                echo "<td>" . htmlspecialchars($record['date']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $historyEx) {
        if (strpos($historyEx->getMessage(), "Table") !== false && strpos($historyEx->getMessage(), "doesn't exist") !== false) {
            echo "<p class='success'>price_history表不存在，这是正常的，因为价格历史应该在SteamDT上查看。</p>";
        } else {
            echo "<p class='error'>查询价格历史记录失败: " . htmlspecialchars($historyEx->getMessage()) . "</p>";
        }
    }
    
    // 5. 测试API
    echo "<h2>5. API测试</h2>";
    
    // 选取一个价格为0的饰品进行测试
    $test_skin = $db->query("
        SELECT id, name, marketHashName 
        FROM skins 
        WHERE (is_sold = 0 OR is_sold IS NULL) 
        AND (market_price IS NULL OR market_price = 0)
        AND marketHashName IS NOT NULL AND marketHashName != ''
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    if ($test_skin) {
        echo "<p>测试饰品: <strong>" . htmlspecialchars($test_skin['name']) . "</strong> (ID: " . htmlspecialchars($test_skin['id']) . ")</p>";
        echo "<p>Market Hash名称: <strong>" . htmlspecialchars($test_skin['marketHashName']) . "</strong></p>";
        
        // 调用API测试
        $apiUrl = 'https://open.steamdt.com/open/cs2/v1/price/batch';
        $apiToken = 'd721f86734a3405e8f094ff3df4e9de7';
        
        $requestData = [
            'marketHashNames' => [$test_skin['marketHashName']]
        ];
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiToken,
            'Content-Type: application/json',
            'Accept: */*'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response_data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            echo "<p class='error'>API调用失败: $error</p>";
        } else {
            echo "<p>API状态码: $httpCode</p>";
            
            $data = json_decode($response_data, true);
            echo "<p>API响应:</p>";
            echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
            
            if ($data && isset($data['success']) && $data['success'] === true && !empty($data['data'])) {
                echo "<p class='success'>API响应成功，正在分析价格数据...</p>";
                
                $priceData = $data['data'][0];
                $marketHashName = $priceData['marketHashName'];
                
                echo "<p>返回的Market Hash名称: <strong>" . htmlspecialchars($marketHashName) . "</strong></p>";
                
                echo "<table>";
                echo "<tr><th>平台</th><th>售价</th><th>数量</th></tr>";
                
                $lowestPrice = PHP_FLOAT_MAX;
                $lowestPlatform = '';
                
                foreach ($priceData['dataList'] as $platformData) {
                    $platform = $platformData['platform'];
                    $sellPrice = $platformData['sellPrice'];
                    $sellCount = $platformData['sellCount'];
                    
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($platform) . "</td>";
                    echo "<td>" . htmlspecialchars($sellPrice) . "</td>";
                    echo "<td>" . htmlspecialchars($sellCount) . "</td>";
                    echo "</tr>";
                    
                    if ($sellPrice > 0 && $sellPrice < $lowestPrice) {
                        $lowestPrice = $sellPrice;
                        $lowestPlatform = $platform;
                    }
                }
                echo "</table>";
                
                if ($lowestPrice != PHP_FLOAT_MAX) {
                    echo "<p>最低价格: <strong>¥{$lowestPrice}</strong> (平台: {$lowestPlatform})</p>";
                    
                    // 尝试更新测试饰品价格 - 使用正确的last_updated字段名
                    $updateStmt = null;
                    
                    if ($found && $last_updated_field === 'last_updated') {
                        $updateStmt = $db->prepare("UPDATE skins SET market_price = :price, last_updated = NOW() WHERE id = :id");
                    } elseif ($found && $last_updated_field === 'last_update') {
                        $updateStmt = $db->prepare("UPDATE skins SET market_price = :price, last_update = NOW() WHERE id = :id");
                    } else {
                        $updateStmt = $db->prepare("UPDATE skins SET market_price = :price WHERE id = :id");
                    }
                    
                    $result = $updateStmt->execute([
                        ':price' => $lowestPrice,
                        ':id' => $test_skin['id']
                    ]);
                    
                    if ($result) {
                        echo "<p class='success'>已更新测试饰品价格，请刷新页面查看。</p>";
                    } else {
                        echo "<p class='error'>更新测试饰品价格失败: " . implode(', ', $updateStmt->errorInfo()) . "</p>";
                    }
                } else {
                    echo "<p class='error'>未找到有效价格</p>";
                }
            } else {
                echo "<p class='error'>API响应错误或未包含预期的数据</p>";
            }
        }
    } else {
        echo "<p>未找到符合条件的测试饰品</p>";
    }
    
    // 6. 前端缓存检查
    echo "<h2>6. 前端缓存检查</h2>";
    echo "<p>检查是否存在前端缓存问题:</p>";
    echo "<ul>";
    echo "<li>请确认前端代码获取价格时使用正确的字段 (<code>market_price</code>)</li>";
    echo "<li>检查是否存在浏览器缓存问题，可以尝试清除浏览器缓存</li>";
    echo "<li>如果是PWA，可能需要重新安装应用</li>";
    echo "<li>检查是否有Service Worker或其他缓存机制影响数据刷新</li>";
    echo "</ul>";
    
    // 7. 解决方案建议
    echo "<h2>7. 解决方案建议</h2>";
    
    if ($zero_price_count > 0) {
        echo "<p>尝试立即更新所有价格为0的饰品:</p>";
        echo '<form method="post" action="update_price.php">
            <button type="submit" name="update_empty_prices" value="1" style="padding:10px; background:#4CAF50; color:white; border:none; cursor:pointer;">
                立即更新所有价格为0的饰品
            </button>
        </form>';
    }
    
    echo "<p>其他解决方案:</p>";
    echo "<ol>";
    echo "<li>检查前端代码中读取价格的字段名称是否与数据库一致</li>";
    echo "<li>确保数据库的`market_price`列类型为DECIMAL或FLOAT，而不是INT或其他类型</li>";
    echo "<li>手动更新API凭证，确保API仍然有效</li>";
    echo "<li>检查数据库表上是否有触发器或约束限制价格更新</li>";
    echo "<li>检查PHP错误日志中是否有其他相关错误</li>";
    echo "</ol>";
    
    // 8. 特别解决方案：更新update_price.php中的字段名
    echo "<h2>8. 修复价格更新脚本</h2>";
    
    echo "<p>根据数据库字段检查，您可能需要修复更新价格的脚本 <code>update_price.php</code> 中的字段名称。请检查该文件中是否使用了正确的字段名 <code>" . htmlspecialchars($last_updated_field) . "</code>。</p>";
    
    echo "<p>这里是修复建议：</p>";
    echo "<ol>";
    echo "<li>在 <code>update_price.php</code> 中搜索 <code>last_update</code> 和 <code>last_updated</code>，确保使用的是正确的字段名</li>";
    echo "<li>如果字段名错误，修改后重新运行价格更新脚本</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>错误</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>位置: " . htmlspecialchars($e->getFile()) . " 行 " . $e->getLine() . "</p>";
    echo "<p>堆栈跟踪:</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
?> 