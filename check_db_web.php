<?php
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<pre>";

try {
    // 显示所有表
    echo "数据库表列表:\n";
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    // 检查skins表的结构
    echo "\nskins表结构:\n";
    $stmt = $db->query("DESCRIBE skins");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Default']}\n";
    }
    
    // 检查skins表中的数据
    echo "\nskins表数据统计:\n";
    $stmt = $db->query("SELECT COUNT(*) as total FROM skins");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "总记录数: $count\n";
    
    // 检查is_sold字段
    $stmt = $db->query("SELECT COUNT(*) as sold FROM skins WHERE is_sold = 1");
    $soldCount = $stmt->fetch(PDO::FETCH_ASSOC)['sold'];
    echo "已售出记录数: $soldCount\n";
    echo "未售出记录数: " . ($count - $soldCount) . "\n";
    
    // 显示最近添加的5条记录
    echo "\n最近添加的5条记录:\n";
    $stmt = $db->query("SELECT id, name, purchase_price, purchase_date, market_price, is_sold FROM skins ORDER BY id DESC LIMIT 5");
    $recentSkins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($recentSkins as $skin) {
        echo "ID: {$skin['id']}, 名称: {$skin['name']}, 价格: {$skin['purchase_price']}, 日期: {$skin['purchase_date']}, 市场价: {$skin['market_price']}, 是否已售: " . ($skin['is_sold'] ? '是' : '否') . "\n";
    }
    
    // 检查skin_names表
    if (in_array('skin_names', $tables)) {
        echo "\nskin_names表数据统计:\n";
        $stmt = $db->query("SELECT COUNT(*) as total FROM skin_names");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "总记录数: $count\n";
        
        // 显示几条示例记录
        echo "\nskin_names表示例记录:\n";
        $stmt = $db->query("SELECT name, marketHashName FROM skin_names LIMIT 5");
        $examples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($examples as $example) {
            echo "名称: {$example['name']}, Hash名称: {$example['marketHashName']}\n";
        }
    }
    
    // 测试向skins表添加一行测试数据
    echo "\n尝试添加测试数据:\n";
    $testSql = "
        INSERT INTO skins (name, purchase_price, purchase_date, marketHashName, sort_order) 
        VALUES ('测试饰品', 100.00, '2023-01-01', 'test_market_hash_name', 9999)
    ";
    $result = $db->exec($testSql);
    echo "添加结果: " . ($result ? "成功，影响了 $result 行" : "失败") . "\n";
    
    if ($result) {
        // 查询刚添加的数据
        $lastId = $db->lastInsertId();
        echo "刚插入的ID: $lastId\n";
        $stmt = $db->query("SELECT * FROM skins WHERE id = $lastId");
        $testData = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "测试数据详情: " . print_r($testData, true) . "\n";
        
        // 删除测试数据
        $db->exec("DELETE FROM skins WHERE id = $lastId");
        echo "测试数据已删除\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "错误详情: " . print_r($e, true) . "\n";
}

echo "</pre>";
?> 