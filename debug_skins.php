<?php
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<pre>";

// 调试信息
echo "您的请求:\n";
echo "GET: " . print_r($_GET, true) . "\n";
echo "POST: " . print_r($_POST, true) . "\n";

try {
    // 尝试从get_skins.php中获取同样的数据
    $sort = $_GET['sort'] ?? 'default';
    $order = $_GET['order'] ?? 'asc';
    $showSold = $_GET['show_sold'] ?? 'unsold_only';

    echo "\n请求参数:\n";
    echo "sort: $sort\n";
    echo "order: $order\n";
    echo "showSold: $showSold\n";

    // 构建SQL条件
    $condition = "";
    if ($showSold === 'sold_only') {
        $condition = "WHERE is_sold = 1";
    } else if ($showSold === 'unsold_only') {
        $condition = "WHERE is_sold = 0 OR is_sold IS NULL";
    }

    // 构建排序
    $orderBy = '';
    switch ($sort) {
        case 'price':
            $orderBy = 'purchase_price';
            break;
        case 'date':
            $orderBy = 'purchase_date';
            break;
        case 'market':
            $orderBy = 'market_price';
            break;
        case 'change':
            $orderBy = '(market_price - purchase_price) / purchase_price * 100';
            break;
        case 'profit':
            $orderBy = 'market_price - purchase_price';
            break;
        case 'sold_profit':
            $orderBy = 'sold_price - fee - purchase_price';
            break;
        case 'sold_date':
            $orderBy = 'sold_date';
            break;
        default:
            $orderBy = 'sort_order';
    }

    // 构建完整SQL
    $sql = "SELECT * FROM skins $condition ORDER BY $orderBy " . ($order === 'desc' ? 'DESC' : 'ASC');
    echo "\n执行的SQL:\n$sql\n";

    // 执行查询
    $stmt = $db->query($sql);
    $skins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\n查询结果数量: " . count($skins) . "\n";
    
    // 显示前5条记录
    echo "\n前5条记录详情:\n";
    $count = 0;
    foreach ($skins as $skin) {
        if ($count >= 5) break;
        echo "------------\n";
        echo "ID: {$skin['id']}\n";
        echo "名称: {$skin['name']}\n";
        echo "购入价格: {$skin['purchase_price']}\n";
        echo "购入日期: {$skin['purchase_date']}\n";
        echo "市场价格: {$skin['market_price']}\n";
        echo "排序顺序: {$skin['sort_order']}\n";
        echo "是否已售: " . ($skin['is_sold'] ? '是' : '否') . "\n";
        if ($skin['is_sold']) {
            echo "卖出价格: {$skin['sold_price']}\n";
            echo "手续费: {$skin['fee']}\n";
            echo "卖出日期: {$skin['sold_date']}\n";
        }
        $count++;
    }
    
    // 检查skin_names表
    echo "\n检查skin_names表数据:\n";
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM skin_names");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "skin_names表中有 {$result['count']} 条记录\n";
        
        // 如果有添加饰品的名称，检查它是否在skin_names表中
        if (isset($_GET['check_name']) && !empty($_GET['check_name'])) {
            $name = $_GET['check_name'];
            $stmt = $db->prepare("SELECT * FROM skin_names WHERE name = ?");
            $stmt->execute([$name]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "\n检查名称 '{$name}' 是否存在:\n";
            if ($result) {
                echo "找到匹配记录: " . print_r($result, true) . "\n";
            } else {
                echo "未找到匹配记录\n";
            }
        }
    } catch (Exception $e) {
        echo "检查skin_names表出错: " . $e->getMessage() . "\n";
    }
    
    // 测试添加功能
    if (isset($_GET['test_add']) && $_GET['test_add'] == '1') {
        echo "\n测试添加功能:\n";
        try {
            $testName = "测试饰品_" . date('YmdHis');
            
            // 先确保skin_names表中有这个名称
            $stmt = $db->prepare("INSERT IGNORE INTO skin_names (name, marketHashName) VALUES (?, ?)");
            $stmt->execute([$testName, 'Test_Market_Hash_Name_' . date('YmdHis')]);
            
            // 然后添加到skins表
            $stmt = $db->prepare("
                INSERT INTO skins (name, purchase_price, purchase_date, marketHashName, sort_order) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $testName, 
                100.00, 
                date('Y-m-d'), 
                'Test_Market_Hash_Name_' . date('YmdHis'),
                999
            ]);
            
            if ($result) {
                $newId = $db->lastInsertId();
                echo "成功添加测试饰品，ID: $newId\n";
                
                // 查询新添加的记录
                $stmt = $db->prepare("SELECT * FROM skins WHERE id = ?");
                $stmt->execute([$newId]);
                $newRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "新记录详情: " . print_r($newRecord, true) . "\n";
                
                // 删除测试数据
                $db->exec("DELETE FROM skins WHERE id = $newId");
                echo "测试记录已删除\n";
            } else {
                echo "添加测试数据失败\n";
            }
        } catch (Exception $e) {
            echo "测试添加功能出错: " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "错误详情: " . print_r($e, true) . "\n";
}

echo "</pre>";
?> 