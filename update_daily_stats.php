<?php
// 用于通过计划任务（cron job）定期更新每日统计数据
// 可以使用以下计划任务设置每天更新一次（例如午夜12点）：
// 0 0 * * * php /path/to/your/project/update_daily_stats.php > /dev/null 2>&1

require_once 'config.php';
require_once 'includes/Database.php';

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConnection();
    
    // 记录脚本开始执行
    error_log("[" . date('Y-m-d H:i:s') . "] 开始执行每日统计数据更新");
    
    // 调用存储过程更新统计数据
    $db->exec("CALL record_daily_stats()");
    
    // 记录成功消息
    error_log("[" . date('Y-m-d H:i:s') . "] 每日统计数据更新成功");
    
    // 如果是通过命令行执行，输出成功消息
    if (php_sapi_name() === 'cli') {
        echo "每日统计数据更新成功\n";
    }
    
} catch (Exception $e) {
    // 记录错误
    error_log("[" . date('Y-m-d H:i:s') . "] 更新每日统计数据时出错: " . $e->getMessage());
    
    // 如果是通过命令行执行，输出错误消息
    if (php_sapi_name() === 'cli') {
        echo "更新每日统计数据时出错: " . $e->getMessage() . "\n";
    }
} 