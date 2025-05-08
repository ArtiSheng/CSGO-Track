<?php
require_once 'config.php';

try {
    // 检查数据库连接
    if (!isset($db) || $db === null) {
        die("错误：数据库连接失败。请检查config.php中的数据库配置是否正确。\n");
    }
    
    // 测试数据库连接
    $db->query("SELECT 1");
    echo "数据库连接成功\n";
    
    // 检查quantity字段是否存在
    $stmt = $db->query("SHOW COLUMNS FROM skins LIKE 'quantity'");
    if ($stmt->rowCount() == 0) {
        // 添加quantity字段
        $db->exec("ALTER TABLE skins ADD COLUMN quantity INT DEFAULT 1 AFTER name");
        echo "成功添加quantity字段\n";
        
        // 初始化现有数据的数量为1
        $db->exec("UPDATE skins SET quantity = 1 WHERE quantity IS NULL");
        echo "初始化数量数据完成\n";
    } else {
        echo "quantity字段已存在，检查是否有空值\n";
        
        // 更新空值为1
        $stmt = $db->prepare("UPDATE skins SET quantity = 1 WHERE quantity IS NULL");
        $stmt->execute();
        $count = $stmt->rowCount();
        echo "更新了 {$count} 条记录的数量信息\n";
    }
    
    echo "数据库更新完成！\n";
} catch (PDOException $e) {
    echo "错误：" . $e->getMessage() . "\n";
    echo "错误代码：" . $e->getCode() . "\n";
} catch (Exception $e) {
    echo "发生错误：" . $e->getMessage() . "\n";
} 