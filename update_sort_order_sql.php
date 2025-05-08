<?php
require_once 'config.php';

// 检查数据库连接
if (!isset($db) || $db === null) {
    die("错误：数据库连接失败。请检查config.php中的数据库配置是否正确。\n");
}

try {
    // 测试数据库连接
    $db->query("SELECT 1");
    echo "数据库连接成功\n";
    
    // 添加sort_order字段
    $db->exec("ALTER TABLE skins ADD COLUMN sort_order INT DEFAULT 0 COMMENT '排序顺序'");
    echo "成功添加sort_order字段\n";
    
    // 初始化现有数据的排序值
    $db->exec("UPDATE skins SET sort_order = id");
    echo "成功初始化排序值\n";
    
    echo "数据库更新完成！\n";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "sort_order字段已存在，无需添加\n";
    } else {
        echo "错误：" . $e->getMessage() . "\n";
        echo "错误代码：" . $e->getCode() . "\n";
    }
} catch (Exception $e) {
    echo "发生错误：" . $e->getMessage() . "\n";
} 