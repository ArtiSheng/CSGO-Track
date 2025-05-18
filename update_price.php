<?php
/**
 * 饰品价格实时更新API
 * 提供给前端调用，用于实时更新饰品的价格数据
 * 支持批量更新
 * 返回JSON格式的结果
 */

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 启用调试模式
define('DEBUG', true);

// 记录开始时间
$startTime = microtime(true);

// 设置响应类型为JSON
header('Content-Type: application/json');

// 初始化响应
$response = [
    'success' => false,
    'message' => '',
    'updated_items' => [],
    'time' => date('Y-m-d H:i:s')
];

// 引入必要的类
require_once 'config.php';
require_once 'includes/Database.php';

// SteamDT API配置
$apiUrl = 'https://open.steamdt.com/open/cs2/v1/price/batch';
$apiToken = 'd721f86734a3405e8f094ff3df4e9de7'; // 将此替换为您的实际API令牌

// 记录日志的函数 - 改为仅使用error_log避免权限问题
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    error_log($logMessage);
    // 仅在调试模式下输出日志
    if (defined('DEBUG') && DEBUG) {
        echo "<!-- LOG: $logMessage -->\n";
    }
}

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConnection();
    
    // 获取所有未售出的饰品，按marketHashName分组以避免重复请求相同的饰品
    $stmt = $db->query("
        SELECT id, name, quantity, 
            CASE 
                WHEN marketHashName IS NULL OR marketHashName = '' THEN name 
                ELSE marketHashName 
            END as marketHashName 
        FROM skins 
        WHERE is_sold = 0 OR is_sold IS NULL
    ");
    $allSkins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 按marketHashName分组
    $uniqueSkins = [];
    $skinIdsByHash = [];
    
    foreach ($allSkins as $skin) {
        $hashName = $skin['marketHashName'];
        
        // 记录每个hashName对应的所有饰品ID
        if (!isset($skinIdsByHash[$hashName])) {
            $skinIdsByHash[$hashName] = [];
            // 只添加唯一的饰品到请求列表
            $uniqueSkins[] = $skin;
        }
        
        // 添加该hashName下的所有饰品ID
        $skinIdsByHash[$hashName][] = [
            'id' => $skin['id'],
            'name' => $skin['name'],
            'quantity' => $skin['quantity'] ?? 1
        ];
    }
    
    $totalCount = count($allSkins);
    $uniqueCount = count($uniqueSkins);
    logMessage("找到 $totalCount 个未售出饰品，去重后需要请求 $uniqueCount 个不同的饰品价格");
    
    if ($uniqueCount == 0) {
        logMessage("没有找到需要更新的饰品，操作终止");
        $response['message'] = '没有找到需要更新的饰品';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 准备批量更新请求
    $batchSize = 50; // 每批次请求的饰品数量
    $batches = array_chunk($uniqueSkins, $batchSize);
    
    $updatedCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    
    foreach ($batches as $batchIndex => $batchSkins) {
        logMessage("处理批次 " . ($batchIndex + 1) . " / " . count($batches) . " (包含 " . count($batchSkins) . " 个不同饰品)");
        
        // 准备请求体数据 - 保持marketHashName字段不变
        $marketHashNames = [];
        foreach ($batchSkins as $skin) {
            $marketHashNames[] = $skin['marketHashName'];
        }
        
        // API请求数据
        $requestData = [
            'marketHashNames' => $marketHashNames
        ];
        
        // 发送API请求
        $ch = curl_init($apiUrl);
        
        // 设置请求头和选项
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
        
        // 执行请求
        $response_data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        // 检查是否有CURL错误
        if ($error) {
            logMessage("CURL错误: $error");
            $errorCount += count($batchSkins);
            continue;
        }
        
        // 检查HTTP状态码
        if ($httpCode != 200) {
            logMessage("HTTP错误: 状态码 $httpCode");
            $errorCount += count($batchSkins);
            continue;
        }
        
        // 解析响应
        $data = json_decode($response_data, true);
        
        // 检查API响应是否成功
        if (!$data || !isset($data['success']) || $data['success'] !== true) {
            $errorMsg = isset($data['errorMsg']) ? $data['errorMsg'] : '未知错误';
            logMessage("API响应错误: $errorMsg");
            $errorCount += count($batchSkins);
            continue;
        }
        
        // 处理成功的响应
        if (empty($data['data'])) {
            logMessage("API返回的数据为空");
            $errorCount += count($batchSkins);
            continue;
        }
        
        // 创建一个哈希表，让我们可以通过marketHashName快速找到对应的饰品
        $skinsByHashName = [];
        foreach ($batchSkins as $skin) {
            $hashNameKey = strtolower($skin['marketHashName']);
            $skinsByHashName[$hashNameKey] = $skin;
        }
        
        // 处理API返回的价格数据
        foreach ($data['data'] as $priceData) {
            $marketHashName = $priceData['marketHashName'];
            $hashNameKey = strtolower($marketHashName);
            
            // 找到对应的饰品 - 使用哈希表快速查找
            if (isset($skinsByHashName[$hashNameKey])) {
                // 找出所有平台的最低价格
                $lowestPrice = PHP_FLOAT_MAX; // 初始化为一个很大的数
                $platformWithLowestPrice = '';
                $platformPrices = []; // 初始化平台价格数组
                
                // 初始化所有平台价格的日志
                $platformPricesLog = [];
                
                foreach ($priceData['dataList'] as $platformData) {
                    $platform = $platformData['platform'];
                    $sellPrice = $platformData['sellPrice'];
                    $platformPrices[$platform] = $sellPrice; // 记录每个平台的价格
                    $platformPricesLog[] = "$platform: $sellPrice";
                    
                    // 只考虑价格大于0的平台
                    if ($sellPrice > 0) {
                        if ($sellPrice < $lowestPrice) {
                            $lowestPrice = $sellPrice;
                            $platformWithLowestPrice = $platform;
                        }
                    }
                }
                
                $skinName = $skinsByHashName[$hashNameKey]['name'];
                logMessage("饰品 '{$skinName}' 各平台价格: " . implode(', ', $platformPricesLog));
                
                // 处理目标平台 (BUFF, C5, YOUPIN) 的价格
                $targetPlatforms = ['BUFF', 'C5', 'YOUPIN'];
                $validTargetPrices = [];
                
                // 收集目标平台的有效价格
                foreach ($targetPlatforms as $platform) {
                    if (isset($platformPrices[$platform]) && $platformPrices[$platform] > 0) {
                        $validTargetPrices[$platform] = $platformPrices[$platform];
                        logMessage("找到 $platform 价格: {$platformPrices[$platform]} 元");
                    } else {
                        logMessage("未找到 $platform 的有效价格");
                    }
                }
                
                // 找出最低价格
                $finalPrice = null;
                $finalPlatform = null;
                
                if (!empty($validTargetPrices)) {
                    // 从目标平台中选择最低价格
                    $finalPrice = min($validTargetPrices);
                    // 找出对应的平台
                    $finalPlatform = array_search($finalPrice, $validTargetPrices);
                    
                    if ($lowestPrice < $finalPrice) {
                        logMessage("注意: 目标平台最低价 {$finalPrice} ({$finalPlatform}) 不是所有平台中的最低价 {$lowestPrice} ({$platformWithLowestPrice})");
                    }
                    
                    logMessage("选择了 $finalPlatform 的最低价格: $finalPrice 元");
                } elseif ($lowestPrice < PHP_FLOAT_MAX) {
                    // 如果目标平台都没有价格，使用所有平台中的最低价
                    $finalPrice = $lowestPrice;
                    $finalPlatform = $platformWithLowestPrice;
                    logMessage("目标平台无有效价格，使用 $finalPlatform 的价格: $finalPrice 元");
                } else {
                    logMessage("警告: 所有平台均无有效价格，跳过此饰品");
                    $skippedCount++;
                    continue;
                }
                
                // 如果找到了有效价格，更新数据库
                if ($finalPrice !== null) {
                    // 获取该marketHashName下的所有饰品ID
                    $relatedSkins = $skinIdsByHash[$marketHashName] ?? [];
                    
                    if (empty($relatedSkins)) {
                        logMessage("错误: 找不到与 '$marketHashName' 匹配的饰品ID");
                        continue;
                    }
                    
                    $updatedThisHash = 0;
                    
                    // 更新所有相关饰品的价格
                    foreach ($relatedSkins as $relatedSkin) {
                        $skinId = $relatedSkin['id'];
                        $skinName = $relatedSkin['name'];
                        $quantity = intval($relatedSkin['quantity'] ?? 1);
                        
                        try {
                            // 获取旧价格用于计算变化
                            $oldPriceStmt = $db->prepare("SELECT market_price FROM skins WHERE id = :id");
                            $oldPriceStmt->execute([':id' => $skinId]);
                            $oldPrice = $oldPriceStmt->fetchColumn();
                            $oldPrice = floatval($oldPrice);
                            
                            // 计算价格变化百分比
                            $priceChange = $oldPrice > 0 ? (($finalPrice - $oldPrice) / $oldPrice * 100) : 0;
                            
                            // 更新饰品价格
                            $updateStmt = $db->prepare("UPDATE skins SET market_price = :price, last_updated = NOW() WHERE id = :id");
                            $updateStmt->execute([
                                ':price' => $finalPrice,
                                ':id' => $skinId
                            ]);
                            
                            // 添加到更新列表
                            $response['updated_items'][] = [
                                'id' => $skinId,
                                'name' => $skinName,
                                'price' => $finalPrice,
                                'old_price' => $oldPrice,
                                'change' => $priceChange,
                                'platform' => $finalPlatform,
                                'quantity' => $quantity
                            ];
                            
                            logMessage("成功更新饰品 ID:$skinId 名称:{$skinName}" . 
                                      ($quantity > 1 ? " (数量:$quantity)" : "") . 
                                      " 价格:$finalPrice ($finalPlatform)");
                            
                            $updatedCount++;
                            $updatedThisHash++;
                        } catch (Exception $e) {
                            logMessage("数据库更新错误: " . $e->getMessage());
                            $errorCount++;
                        }
                    }
                    
                    logMessage("已更新 '$marketHashName' 的 $updatedThisHash 个相关饰品");
                } else {
                    logMessage("无法找到饰品 '{$skinName}' 的有效价格数据");
                    $errorCount++;
                }
            } else {
                logMessage("无法匹配返回的物品 '$marketHashName' 到数据库中的饰品");
            }
        }
        
        // 防止请求过快，添加短暂延迟
        if (count($batches) > 1) {
            sleep(2);
        }
    }
    
    // 输出统计信息
    logMessage("价格更新完成：总计 $totalCount 个饰品，成功更新 $updatedCount 个，失败 $errorCount 个，跳过 $skippedCount 个");
    
    $response['success'] = true;
    $response['message'] = "成功更新了 $updatedCount 个物品的价格，共 $totalCount 个饰品";
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    logMessage("脚本执行错误: " . $e->getMessage());
}

// 计算执行时间
$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);
logMessage("=== 价格更新结束，用时 $executionTime 秒 ===");

// 返回JSON响应
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?> 