<?php
/**
 * 饰品价格实时更新API
 * 提供给前端调用，用于实时更新饰品的价格数据
 * 支持批量更新
 * 返回JSON格式的结果
 */

require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/SteamDTAPI.php';
require_once 'includes/SteamItemManager.php';

// 设置响应类型为JSON
header('Content-Type: application/json; charset=utf-8');

// 初始化响应数组
$response = [
    'success' => false,
    'message' => '',
    'updated_items' => [],
    'time' => date('Y-m-d H:i:s')
];

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConnection();
    
    // 获取价格API实例
    $api = new SteamDTAPI();
    
    // 批量模式 - 更新所有或指定数量的物品
    // 限制每次更新的物品数量
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 999) : 999;
    
    // 获取未售出的饰品列表，并按marketHashName分组
    // 先获取所有不同的marketHashName
    $stmt = $db->query("SELECT DISTINCT marketHashName FROM skins 
                       WHERE marketHashName IS NOT NULL AND 
                       (is_sold = 0 OR is_sold IS NULL) 
                       ORDER BY last_updated ASC LIMIT $limit");
    $uniqueHashNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($uniqueHashNames)) {
        throw new Exception('没有找到可更新的饰品');
    }
    
    // 过滤掉空值
    $uniqueHashNames = array_filter($uniqueHashNames);
    
    if (empty($uniqueHashNames)) {
        throw new Exception('没有有效的marketHashName');
    }
    
    error_log("[价格更新] 开始批量更新 " . count($uniqueHashNames) . " 个不同饰品的价格");
    
    // 批量获取价格
    $priceInfo = $api->getBatchPrices($uniqueHashNames);
    
    if (!$priceInfo['success']) {
        throw new Exception('批量获取价格失败：' . ($priceInfo['message'] ?? '未知错误'));
    }
    
    // 获取所有具有这些marketHashName的饰品
    $placeholders = implode(',', array_fill(0, count($uniqueHashNames), '?'));
    $stmt = $db->prepare("SELECT id, name, marketHashName, market_price 
                         FROM skins 
                         WHERE marketHashName IN ($placeholders)
                         AND (is_sold = 0 OR is_sold IS NULL)");
    $stmt->execute($uniqueHashNames);
    $skins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 准备更新语句
    $updateStmt = $db->prepare("UPDATE skins SET market_price = ?, price_platform = ?, last_updated = NOW() WHERE id = ?");
    
    // 按marketHashName分组处理饰品
    $groupedSkins = [];
    foreach ($skins as $skin) {
        if (empty($skin['marketHashName'])) continue;
        $hashName = $skin['marketHashName'];
        if (!isset($groupedSkins[$hashName])) {
            $groupedSkins[$hashName] = [];
        }
        $groupedSkins[$hashName][] = $skin;
    }
    
    // 逐个更新饰品价格
    foreach ($groupedSkins as $hashName => $skinGroup) {
        // 检查API返回中是否有该饰品的价格数据
        if (isset($priceInfo['data'][$hashName])) {
            $priceData = $priceInfo['data'][$hashName];
            $newPrice = $priceData['price'];
            $platform = $priceData['platform_display'] ?: $priceData['platform'];
            
            // 对具有相同marketHashName的所有饰品应用相同的价格
            foreach ($skinGroup as $skin) {
                // 执行更新
                $updateStmt->execute([$newPrice, $platform, $skin['id']]);
                
                // 计算价格变化
                $oldPrice = floatval($skin['market_price']);
                $priceChange = $oldPrice > 0 ? (($newPrice - $oldPrice) / $oldPrice * 100) : 0;
                
                // 添加到更新列表
                $response['updated_items'][] = [
                    'id' => $skin['id'],
                    'name' => $skin['name'],
                    'price' => $newPrice,
                    'old_price' => $oldPrice,
                    'change' => $priceChange,
                    'platform' => $platform
                ];
            }
            
            // 每组只记录一次日志
            error_log("[批量价格更新] 饰品组 {$hashName} 价格已更新：新价格 ¥{$newPrice} ({$platform})，影响 " . count($skinGroup) . " 个物品");
        } else {
            error_log("[批量价格更新] 警告：API返回中没有找到饰品 {$hashName} 的价格数据");
        }
    }
    
    if (empty($response['updated_items'])) {
        throw new Exception('没有成功更新任何物品价格');
    }
    
    $response['success'] = true;
    $response['message'] = '成功更新了 ' . count($response['updated_items']) . ' 个物品的价格，共 ' . count($uniqueHashNames) . ' 种不同饰品';
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("[价格更新错误] " . $e->getMessage());
}

// 返回JSON响应
echo json_encode($response);
?> 