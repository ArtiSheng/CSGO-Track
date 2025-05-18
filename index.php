<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/SteamDTAPI.php';
require_once 'includes/SteamItemManager.php';

$manager = SteamItemManager::getInstance();
$db = Database::getInstance()->getConnection();

// 计算投资统计数据
try {
    // 未售出饰品数据
    $stmtUnsold = $db->query("SELECT 
                                SUM(purchase_price * IFNULL(quantity, 1)) as total_investment,
                                SUM(market_price * IFNULL(quantity, 1)) as total_current_value
                              FROM skins 
                              WHERE is_sold = 0 OR is_sold IS NULL");
    $unsoldData = $stmtUnsold->fetch(PDO::FETCH_ASSOC);
    
    $totalActiveInvestment = floatval($unsoldData['total_investment']) ?? 0;
    $totalActiveValue = floatval($unsoldData['total_current_value']) ?? 0;
    $unrealizedProfit = $totalActiveValue - $totalActiveInvestment;
    $unrealizedProfitPercent = ($totalActiveInvestment > 0) ? ($unrealizedProfit / $totalActiveInvestment * 100) : 0;
    
    // 已售出饰品数据 - 已结盈亏需要考虑手续费
    $stmtSold = $db->query("SELECT 
                              SUM(purchase_price * IFNULL(quantity, 1)) as total_sold_investment,
                              SUM(sold_price * IFNULL(quantity, 1)) as total_sold_value,
                              SUM(IFNULL(fee, 0) * IFNULL(quantity, 1)) as total_fee
                            FROM skins 
                            WHERE is_sold = 1");
    $soldData = $stmtSold->fetch(PDO::FETCH_ASSOC);
    
    $totalSoldInvestment = floatval($soldData['total_sold_investment']) ?? 0;
    $totalSoldValue = floatval($soldData['total_sold_value']) ?? 0;
    $totalFee = floatval($soldData['total_fee']) ?? 0;
    
    // 已结盈亏 = 总卖出价 - 总手续费 - 总投入
    $netSoldValue = $totalSoldValue - $totalFee; // 实际到手金额
    $realizedProfit = $netSoldValue - $totalSoldInvestment;
    $realizedProfitPercent = ($totalSoldInvestment > 0) ? ($realizedProfit / $totalSoldInvestment * 100) : 0;

    // 调试输出
    if (defined('DEBUG') && DEBUG) {
        error_log("投资概览统计数据:");
        error_log("未售出饰品总投资: $totalActiveInvestment");
        error_log("未售出饰品当前总值: $totalActiveValue");
        error_log("未实现盈亏: $unrealizedProfit");
        error_log("未实现盈亏百分比: $unrealizedProfitPercent");
        error_log("已售出饰品总投资: $totalSoldInvestment");
        error_log("已售出饰品总卖出价: $totalSoldValue");
        error_log("已售出饰品总手续费: $totalFee");
        error_log("已售出饰品实际到手金额: $netSoldValue");
        error_log("已实现盈亏: $realizedProfit");
        error_log("已实现盈亏百分比: $realizedProfitPercent");
    }
} catch (Exception $e) {
    // 如果出错，设置默认值
    $totalActiveInvestment = 0;
    $totalActiveValue = 0;
    $unrealizedProfit = 0;
    $unrealizedProfitPercent = 0;
    $realizedProfit = 0;
    $realizedProfitPercent = 0;
}
?>

<!DOCTYPE html>
<html lang="zh" style="height: auto; overflow-y: auto;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>CSGO饰品价格追踪</title>
    
    <!-- 标准网站图标 -->
    <link rel="icon" href="img/icon.png" type="image/png">
    <link rel="shortcut icon" href="img/icon.png" type="image/png">
    
    <!-- PWA支持 -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="CSGOTrack">
    <link rel="apple-touch-icon" href="img/icon.png">
    
    <!-- Web应用清单 -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#5179d6">
    
    <!-- 直接内联加载jQuery以确保可用性 -->
    <script>
        // 内联脚本直接加载关键资源
        (function() {
            // 直接从CDN加载jQuery
            function loadScript(url, callback) {
                var script = document.createElement('script');
                script.src = url;
                script.onload = callback;
                script.onerror = function() {
                    console.error('加载失败:', url);
                    // 尝试备用CDN
                    if (url.includes('cdn.bootcdn.net')) {
                        loadScript(url.replace('cdn.bootcdn.net', 'lib.baomitu.com'), callback);
                    }
                };
                document.head.appendChild(script);
            }
            
            // 直接加载CSS
            function loadCSS(url) {
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = url;
                document.head.appendChild(link);
            }
            
            // 先加载jQuery，然后加载Bootstrap
            loadScript('https://cdn.bootcdn.net/ajax/libs/jquery/3.6.0/jquery.min.js', function() {
                console.log('jQuery加载成功');
                // 加载Bootstrap
                loadScript('https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.1.3/js/bootstrap.bundle.min.js', function() {
                    console.log('Bootstrap加载成功');
                });
            });
            
            // 加载FontAwesome
            loadCSS('https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.4/css/all.min.css');
            
            // 加载Bootstrap CSS
            loadCSS('https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.1.3/css/bootstrap.min.css');
        })();
    </script>
    
    <!-- 资源加载错误处理脚本 - 作为备份 -->
    <script src="js/resource-loader.js"></script>
    <script src="js/network-status.js"></script>
    
    <!-- 引用样式 - 使用CDN替代本地文件 -->
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/codebase.min.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        html, body {
            height: auto;
            overflow-y: auto;
        }
        
        /* 添加模糊背景效果 */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(81, 121, 214, 0.05) 0%, rgba(81, 121, 214, 0.02) 100%);
            z-index: -1;
            backdrop-filter: blur(80px);
            -webkit-backdrop-filter: blur(80px);
        }
        
        /* 动态背景相关样式 */
        .dynamic-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            opacity: 0.1;
        }
        
        .dynamic-bg span {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(81, 121, 214, 0.3), rgba(92, 205, 222, 0.3));
            filter: blur(60px);
            animation: floatBubble 15s infinite linear;
        }
        
        .dynamic-bg span:nth-child(1) {
            width: 300px;
            height: 300px;
            top: 10%;
            left: 10%;
        }
        
        .dynamic-bg span:nth-child(2) {
            width: 250px;
            height: 250px;
            top: 60%;
            left: 80%;
            animation-delay: 2s;
            animation-duration: 18s;
            background: linear-gradient(135deg, rgba(130, 181, 75, 0.3), rgba(81, 121, 214, 0.3));
        }
        
        .dynamic-bg span:nth-child(3) {
            width: 180px;
            height: 180px;
            top: 80%;
            left: 20%;
            animation-delay: 4s;
            animation-duration: 16s;
            background: linear-gradient(135deg, rgba(229, 103, 103, 0.3), rgba(130, 181, 75, 0.3));
        }
        
        @keyframes floatBubble {
            0% {
                transform: translate(0, 0);
            }
            25% {
                transform: translate(5%, 10%);
            }
            50% {
                transform: translate(10%, 5%);
            }
            75% {
                transform: translate(5%, -5%);
            }
            100% {
                transform: translate(0, 0);
            }
        }
        
        /* 禁用Bootstrap按钮点击涟漪效果 */
        .btn {
            position: relative;
            overflow: hidden;
        }
        
        /* 彻底禁用Bootstrap 5的涟漪效果 */
        .ripple, 
        .btn-check:focus + .btn,
        .btn:focus,
        .btn::before,
        .btn::after,
        .btn.ripple-surface::after {
            box-shadow: none !important;
            outline: none !important;
        }
        
        /* Bootstrap 5中的涟漪类 */
        .ripple-surface {
            position: relative;
            overflow: hidden;
            display: inline-block;
            vertical-align: bottom;
        }
        
        .ripple-surface-unbound {
            overflow: visible;
        }
        
        .ripple-surface::after {
            display: none !important;
        }
        
        /* 修复排序下拉菜单问题 */
        #sortDropdown {
            position: relative;
        }
        
        /* 修复下拉菜单突然下移的问题 */
        .dropdown-menu {
            margin-top: 0 !important;
            top: 100% !important;
            transform: none !important;
            position: absolute !important;
            will-change: initial !important;
            min-width: 120px !important; /* 减小下拉菜单宽度 */
            padding: 0.25rem 0 !important; /* 减小上下内边距 */
            box-shadow: 0 2px 5px rgba(0,0,0,0.15) !important; /* 添加阴影效果 */
            border: 1px solid rgba(0,0,0,0.1) !important; /* 设置更细的边框 */
            font-size: 13px !important; /* 整体字体缩小 */
            z-index: 1050 !important; /* 确保在其他元素之上 */
            animation: none !important; /* 禁用可能的动画效果 */
            transition: none !important; /* 禁用可能的过渡效果 */
        }
        
        .dropdown-menu[data-bs-popper] {
            margin-top: 0 !important;
            inset: auto !important;
        }
        
        .dropdown-item {
            padding: 0.25rem 0.75rem !important; /* 减小项目内边距 */
            font-size: 13px !important; /* 减小字体大小 */
            line-height: 1.2 !important; /* 减小行高 */
        }
        
        /* 确保下拉菜单不会漂移 */
        .dropdown {
            position: relative !important;
        }
        
        /* 确保下拉菜单按钮不会有奇怪的边距 */
        .dropdown-toggle {
            margin-bottom: 0 !important;
        }
        
        /* 禁用下拉菜单的过渡动画 */
        .dropdown-menu.show {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
            transform: none !important;
            transition: none !important;
        }
        
        /* 更紧凑的下拉菜单 */
        .compact-menu {
            min-width: 110px !important;
            max-width: 150px !important;
            font-size: 12px !important;
        }
        
        .compact-menu .dropdown-item {
            padding: 0.2rem 0.5rem !important;
            font-size: 12px !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* 统一控制栏按钮样式 */
        .card-header .btn-group .btn,
        .card-header .dropdown .btn {
            font-size: 13px !important;
            padding: 0.25rem 0.75rem !important;
            height: auto !important;
            line-height: 1.5 !important;
        }
        
        /* 确保按钮组中的按钮大小一致 */
        .btn-group > .btn {
            flex: 0 0 auto !important;
        }
        
        /* 精确控制所有控制按钮样式一致性 */
        #showActiveSkins, #showSoldSkins, 
        #showSeparate, #showMerged, 
        #sortDropdown, 
        .order-option {
            font-size: 13px !important;
            padding: 0.25rem 0.75rem !important;
            height: 31px !important; /* 设置固定高度 */
            line-height: 1.5 !important;
            border-radius: 0.25rem !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        /* 确保下拉菜单图标大小一致 */
        .dropdown-toggle::after {
            vertical-align: middle !important;
            margin-left: 0.255em !important;
        }
        
        /* iOS PWA修复 */
        @supports (-webkit-touch-callout: none) {
            .page-container {
                min-height: -webkit-fill-available;
            }
            
            /* 修复iOS PWA底部安全区域 */
            .safe-area-bottom {
                padding-bottom: env(safe-area-inset-bottom);
            }
            
            /* 修复iOS PWA顶部安全区域 */
            .safe-area-top {
                padding-top: env(safe-area-inset-top);
            }
        }
        
        .table-vcenter th,
        .table-vcenter td {
            vertical-align: middle !important;
            word-break: break-word;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        @media (max-width: 768px) {
            .table th, .table td {
                font-size: 0.9rem;
                padding: 0.5rem;
            }
            
            .btn-sm {
                padding: 0.2rem 0.4rem;
                font-size: 0.8rem;
            }
        }
        
        /* 强制固定宽度 */
        .table-fixed {
            table-layout: fixed !important;
            width: 100%;
        }
        
        /* 强制相同宽度应用到th和td */
        .table-fixed th,
        .table-fixed td {
            width: auto;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* 更紧凑的表格样式 */
        .table.table-hover tbody tr td {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .table.table-hover thead th {
            border-bottom: 2px solid rgba(0,0,0,0.1);
            padding-bottom: 0.75rem;
        }
        
        .table.table-hover tbody tr:hover {
            background-color: rgba(81, 121, 214, 0.05);
        }
        
        /* 自定义表格布局 - 防止空白问题 */
        .skin-row {
            height: auto !important;
            min-height: 0 !important;
            margin: 0 !important;
        }
        
        .skin-cell {
            padding: 8px 12px !important;
            height: auto !important;
            vertical-align: middle !important;
        }
        
        .skin-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        
        .skin-name {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-right: 10px;
        }
        
        .skin-actions {
            flex: 0 0 auto;
            white-space: nowrap;
            display: flex;
            gap: 6px;
        }

        /* 新的表格布局样式 */
        .skin-list-wrapper {
            width: 100%;
            overflow-x: auto;
            background-color: #fff;
            border-radius: 0.5rem;
            margin: 0 !important;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        /* 表头样式 */
        .skin-list-header {
            padding: 10px 0;
            font-weight: 600;
            color: #495057;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
        }
        
        /* 确保表头和数据行的每一列都有相同的宽度比例 */
        .skin-header-cell, .skin-item-cell {
            box-sizing: border-box;
            padding: 0 8px;
            text-align: right;
            font-size: 14px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* 确保所有单元格正确对齐 */
        .skin-list-header, .skin-item {
            display: flex;
            width: 100%;
            align-items: center;
        }
        
        /* 饰品名称列 - 固定比例并左对齐 */
        .skin-header-name, .skin-item-name {
            flex: 2.5;
            text-align: left;
            padding-left: 10px;
        }
        
        /* 所有价格相关列 - 相同比例并右对齐 */
        .skin-header-price, .skin-item-price,
        .skin-header-soldprice, .skin-item-soldprice,
        .skin-header-netprice, .skin-item-netprice,
        .skin-header-fee, .skin-item-fee,
        .skin-header-market, .skin-item-market {
            flex: 1;
        }
        
        /* 涨跌幅相关列 */
        .skin-header-change, .skin-item-change,
        .skin-header-profitrate, .skin-item-profitrate,
        .skin-header-profit, .skin-item-profit {
            flex: 1;
        }
        
        /* 日期相关列 */
        .skin-header-days, .skin-item-days,
        .skin-header-date, .skin-item-date,
        .skin-header-solddate, .skin-item-solddate {
            flex: 1;
        }
        
        /* 操作列 */
        .skin-header-actions, .skin-item-actions {
            flex: 2;
            text-align: center;
            justify-content: center;
        }
        
        /* 表格行样式 */
        .skin-item {
            padding: 8px 0;
            margin: 0;
            border: none;
            border-bottom: 1px solid #eaeaea;
            background-color: #fff;
            transition: background-color 0.2s ease;
        }
        
        .skin-item:hover {
            background-color: #f5f9ff;
        }
        
        .skin-item:nth-child(odd) {
            background-color: #fafafa;
        }
        
        .skin-item:nth-child(odd):hover {
            background-color: #f5f9ff;
        }
        
        /* 确保内容溢出处理一致 */
        .skin-item-name {
            font-weight: 400;
            color: #333;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* 确保所有数据单元格样式一致 */
        .skin-item-cell {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* 确保操作按钮排列 */
        .skin-item-actions {
            display: flex;
            justify-content: center;
            gap: 5px;
            white-space: nowrap;
        }
        
        /* 按钮样式 */
        .btn-action {
            padding: 2px 8px;
            font-size: 12px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 24px;
        }
        
        /* 按钮颜色 */
        .btn-sell {
            background-color: #28a745;
            color: white;
            border: none;
        }
        
        .btn-history {
            background-color: #17a2b8;
            color: white;
            border: none;
        }
        
        .btn-edit {
            background-color: #ffc107;
            color: #333;
            border: none;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        
        /* 文本颜色 */
        .text-success {
            color: #28a745 !important;
            font-weight: 500;
        }
        
        .text-danger {
            color: #dc3545 !important;
            font-weight: 500;
        }
        
        /* 确保d-none类真正隐藏元素 */
        .d-none {
            display: none !important;
        }
        
        /* 移除拖动手柄，确保所有单元格正确对齐 */
        .drag-handle {
            display: none !important;
        }
        
        /* 响应式布局调整 */
        @media (max-width: 992px) {
            .skin-list-wrapper {
                overflow-x: auto;
            }
            
            .skin-item-cell {
                font-size: 13px;
                padding: 0 5px;
            }
            
            .skin-header-cell {
                font-size: 13px;
                padding: 0 5px;
            }
            
            .skin-item-name, .skin-header-name {
                padding-left: 8px;
            }
            
            .btn-action {
                padding: 1px 5px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 768px) {
            .skin-item-actions {
                flex: 2;
            }
            
            .btn-action {
                padding: 2px 8px;
                font-size: 11px;
            }
        }
        
        /* 调整容器边距 */
        .container {
            max-width: 2000px !important;
            padding-left: 15px !important;
            padding-right: 15px !important;
        }
        
        /* 响应式调整 */
        @media (max-width: 2000px) {
            .container {
                max-width: 100% !important;
                padding-left: 15px !important;
                padding-right: 15px !important;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 12px !important;
                padding-right: 12px !important;
            }
        }
        
        /* 调整卡片样式 */
        .card {
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .card-body {
            padding: 0.5rem;
        }
        
        .card-body.p-0 {
            padding: 0 !important;
        }
        
        /* 调整卡片头部 */
        .card-header {
            padding: 0.75rem 1rem;
            background-color: rgba(248, 249, 250, 0.9);
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        /* 投资概览卡片 */
        .bg-body-extra-light {
            background-color: rgba(255, 255, 255, 0.8) !important;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.25rem !important;
            padding: 1rem 0 !important;
        }
        
        .bg-body-extra-light .container {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }
        
        .stat-card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 0.5rem;
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        @media (max-width: 768px) {
            .card {
                margin-bottom: 0.75rem;
                border-radius: 0.4rem;
            }
            
            .card-header {
                padding: 0.625rem 0.75rem;
            }
            
            .card-body {
                padding: 0.375rem;
            }
            
            .bg-body-extra-light {
                border-radius: 0.4rem;
                margin-bottom: 1rem !important;
                padding: 0.75rem 0 !important;
            }
            
            .stat-card {
                border-radius: 0.4rem;
            }
            
            .stat-card:hover {
                transform: none;
            }
            
            /* 表格样式 */
            .skin-list-wrapper {
                margin: 0 !important;
                border-radius: 0.4rem;
            }
            
            .skin-list-header {
                padding: 8px 0;
            }
            
            .skin-header-cell, .skin-item-cell {
                padding: 0 5px;
                font-size: 13px;
            }
            
            .skin-item {
                padding: 6px 0;
            }
            
            .btn-action {
                padding: 1px 5px;
                min-width: 35px;
                height: 22px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 576px) {
            .card {
                margin-bottom: 0.5rem;
                border-radius: 0.3rem;
            }
            
            .card-header {
                padding: 0.5rem 0.625rem;
            }
            
            .card-body {
                padding: 0.25rem;
            }
            
            .mobile-controls {
                padding: 0 0.25rem;
            }
            
            .bg-body-extra-light {
                border-radius: 0.3rem;
                margin-bottom: 0.75rem !important;
                padding: 0.5rem 0 !important;
            }
            
            .stat-card {
                border-radius: 0.3rem;
                margin-bottom: 0.5rem;
            }
            
            /* 表格样式 */
            .skin-list-wrapper {
                border-radius: 0.3rem;
            }
            
            .skin-header-cell, .skin-item-cell {
                padding: 0 4px;
                font-size: 12px;
            }
            
            /* 饰品名称列在小屏幕上缩小一点 */
            .skin-header-name, .skin-item-name {
                flex: 2;
            }
            
            .skin-item-actions {
                gap: 3px;
            }
            
            .btn-action {
                padding: 1px 4px;
                min-width: 30px;
                height: 20px;
                font-size: 10px;
            }
        }
        
        /* 新的页面头部按钮样式 */
        .header-btn {
            font-size: 13px !important;
            padding: 0.25rem 0.75rem !important;
            height: 38px !important;
            line-height: 1.5 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-width: 80px !important;
        }
        
        /* 移动端按钮样式调整 */
        @media (max-width: 576px) {
            .header-btn {
                font-size: 12px !important;
                padding: 0.25rem 0.5rem !important;
                height: 36px !important;
                min-width: 70px !important;
            }
        }
    </style>
</head>
<body>
    <!-- 动态背景元素 -->
    <div class="dynamic-bg">
        <span></span>
        <span></span>
        <span></span>
    </div>
    
    <div id="page-container" class="page-header-fixed page-header-glass">
        <!-- 页面头部 -->
        <header id="page-header" class="safe-area-top">
            <div class="content-header">
                <div class="container">
                    <div class="d-flex justify-content-between align-items-center">
                        <!-- 左侧品牌 -->
                        <div class="brand-logo">
                            <span class="fs-4">CSGO</span><span class="fs-4 brand-text-primary">Track</span>
                        </div>
                        
                        <!-- 右侧按钮 -->
                        <div class="d-flex flex-row align-items-center">
                            <a href="https://github.com/ArtiSheng/CSGO-Track" target="_blank" class="btn btn-alt-info me-2 d-flex align-items-center justify-content-center header-btn">
                                <i class="fab fa-github me-1"></i>
                                <span>开源地址</span>
                            </a>
                            <a href="javascript:void(0)" class="btn btn-alt-primary me-2 update-price-btn d-flex align-items-center justify-content-center header-btn">
                                <i class="fa fa-sync-alt me-1"></i>
                                <span class="btn-text">更新价格</span>
                            </a>
                            <button type="button" class="btn btn-alt-success header-btn" data-bs-toggle="modal" data-bs-target="#addSkinModal">
                                <i class="fa fa-plus me-1"></i><span>添加饰品</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- 主要内容区域 -->
        <main id="main-container">
            <div class="container">

                <!-- 投资概览 -->
                <div class="bg-body-extra-light hero-bubbles rounded-3 mb-4 py-3">
                    <span class="hero-bubble bg-primary" style="top: 20%; left: 10%;"></span>
                    <span class="hero-bubble bg-success" style="top: 20%; left: 85%;"></span>
                    <span class="hero-bubble hero-bubble-sm bg-info" style="top: 40%; left: 20%;"></span>
                    <span class="hero-bubble hero-bubble-lg bg-danger" style="top: 10%; left: 25%;"></span>
                    <span class="hero-bubble hero-bubble-sm bg-warning" style="top: 30%; left: 90%;"></span>
                    
                    <div class="container py-2">
                        <h5 class="mb-3">投资概览</h5>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="card stat-card bg-primary text-white h-100">
                                    <div class="card-body d-flex flex-column">
                                        <div class="card-title">当前投资总额</div>
                                        <div class="card-text mt-auto">
                                            ¥<?php echo number_format($totalActiveInvestment, 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card bg-info text-white h-100">
                                    <div class="card-body d-flex flex-column">
                                        <div class="card-title">当前总值</div>
                                        <div class="card-text mt-auto">
                                            ¥<?php echo number_format($totalActiveValue, 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card <?php echo $unrealizedProfit >= 0 ? 'bg-success' : 'bg-danger'; ?> text-white h-100">
                                    <div class="card-body d-flex flex-column">
                                        <div class="card-title">未结盈亏</div>
                                        <div class="card-text mt-auto">
                                            <?php echo ($unrealizedProfit >= 0 ? "+" : "") . number_format($unrealizedProfit, 2); ?>
                                        </div>
                                        <p class="mb-0" id="unrealizedProfitPercent">
                                            <?php echo "(" . ($unrealizedProfitPercent >= 0 ? "+" : "") . number_format($unrealizedProfitPercent, 2) . "%)"; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card <?php echo $realizedProfit >= 0 ? 'bg-success' : 'bg-danger'; ?> text-white h-100">
                                    <div class="card-body d-flex flex-column">
                                        <div class="card-title">已结盈亏</div>
                                        <div class="card-text mt-auto">
                                            <?php echo ($realizedProfit >= 0 ? "+" : "") . number_format($realizedProfit, 2); ?>
                                        </div>
                                        <p class="mb-0" id="realizedProfitPercent">
                                            <?php echo "(" . ($realizedProfitPercent >= 0 ? "+" : "") . number_format($realizedProfitPercent, 2) . "%)"; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 饰品列表 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-3">我的饰品列表</h5>
                        <!-- 移动端优化：三行结构化布局 -->
                        <div class="mobile-controls">
                            <!-- 第一行：未售出、单独显示和降序 -->
                            <div class="d-flex flex-nowrap justify-content-between mb-2">
                                <button type="button" class="btn btn-alt-success btn-sm active flex-grow-1 me-1" id="showActiveSkins">未售出</button>
                                <button type="button" class="btn btn-alt-info btn-sm active flex-grow-1 me-1" id="showSeparate">单独显示</button>
                                <button type="button" class="btn btn-alt-secondary btn-sm order-option active flex-grow-1" data-order="desc">降序</button>
                            </div>
                            
                            <!-- 第二行：已售出、合并显示和升序 -->
                            <div class="d-flex flex-nowrap justify-content-between mb-2">
                                <button type="button" class="btn btn-alt-danger btn-sm flex-grow-1 me-1" id="showSoldSkins">已售出</button>
                                <button type="button" class="btn btn-alt-info btn-sm flex-grow-1 me-1" id="showMerged">合并显示</button>
                                <button type="button" class="btn btn-alt-secondary btn-sm order-option flex-grow-1" data-order="asc">升序</button>
                            </div>
                            
                            <!-- 第三行：排序方式与上两行宽度相同 -->
                            <div class="d-flex flex-nowrap justify-content-between mb-2">
                                <div class="dropdown d-flex flex-grow-1">
                                    <button class="btn btn-alt-primary btn-sm dropdown-toggle flex-grow-1" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        排序方式
                                    </button>
                                    <ul class="dropdown-menu compact-menu" aria-labelledby="sortDropdown">
                                        <li><a class="dropdown-item sort-option active" href="javascript:void(0);" data-sort="default">默认排序</a></li>
                                        <li><a class="dropdown-item sort-option" href="javascript:void(0);" data-sort="price">按购入价格</a></li>
                                        <li><a class="dropdown-item sort-option" href="javascript:void(0);" data-sort="date">按购入日期</a></li>
                                        <!-- 未售出饰品专用排序 -->
                                        <li><a class="dropdown-item sort-option" href="javascript:void(0);" data-sort="change">按涨跌幅</a></li>
                                        <li><a class="dropdown-item sort-option" href="javascript:void(0);" data-sort="profit">按盈亏</a></li>
                                        <!-- 已售出饰品专用排序 -->
                                        <li class="sold-sort d-none"><a class="dropdown-item sort-option" href="javascript:void(0);" data-sort="soldprice">按售出价格</a></li>
                                        <li class="sold-sort d-none"><a class="dropdown-item sort-option" href="javascript:void(0);" data-sort="netprice">按到手价格</a></li>
                                        <li class="sold-sort d-none"><a class="dropdown-item sort-option" href="javascript:void(0);" data-sort="fee">按手续费</a></li>
                                        <li class="sold-sort d-none"><a class="dropdown-item sort-option" href="javascript:void(0);" data-sort="profitrate">按盈亏率</a></li>
                                        <li class="sold-sort d-none"><a class="dropdown-item sort-option" href="javascript:void(0);" data-sort="days">按持有天数</a></li>
                                        <li class="sold-sort d-none"><a class="dropdown-item sort-option" href="javascript:void(0);" data-sort="solddate">按售出日期</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="skin-list-wrapper">
                            <div class="skin-list-header">
                                <div class="skin-header-cell skin-header-name">饰品名称</div>
                                <div class="skin-header-cell skin-header-price">购入价格</div>
                                <div class="skin-header-cell skin-header-soldprice sold-only d-none">售出价格</div>
                                <div class="skin-header-cell skin-header-netprice sold-only d-none">到手价格</div>
                                <div class="skin-header-cell skin-header-fee sold-only d-none">手续费</div>
                                <div class="skin-header-cell skin-header-market unsold-only">市场价格</div>
                                <div class="skin-header-cell skin-header-change">涨跌幅</div>
                                <div class="skin-header-cell skin-header-profitrate sold-only d-none">盈亏率</div>
                                <div class="skin-header-cell skin-header-profit">盈亏</div>
                                <div class="skin-header-cell skin-header-days sold-only d-none">持有天数</div>
                                <div class="skin-header-cell skin-header-date">购入日期</div>
                                <div class="skin-header-cell skin-header-solddate sold-only d-none">售出日期</div>
                                <div class="skin-header-cell skin-header-actions">操作</div>
                            </div>
                            <div id="skin-list" class="skin-list">
                                <!-- 饰品数据将由JavaScript填充 -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        
        <!-- 底部安全区域 (仅iOS PWA时显示) -->
        <div class="safe-area-bottom"></div>
    </div>

    <!-- 添加饰品模态框 -->
    <div class="modal fade" id="addSkinModal" tabindex="-1" aria-labelledby="addSkinModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSkinModalLabel">添加新饰品</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addSkinForm" action="api/add_skin.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">饰品名称</label>
                            <input type="text" class="form-control" name="name" required>
                            <small class="form-text text-muted">输入完整的中文名称，例如：AWP | 二西莫夫 (久经沙场)</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">购入价格</label>
                            <div class="input-group">
                                <span class="input-group-text">¥</span>
                                <input type="number" step="0.01" class="form-control" name="purchase_price" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">购入日期</label>
                            <input type="date" class="form-control" name="purchase_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">数量</label>
                            <input type="number" min="1" class="form-control" name="quantity" value="1">
                            <small class="form-text text-muted">如果购买了多个相同的饰品，可以设置数量</small>
                        </div>
                        
                        <!-- 已售出状态切换 -->
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="alreadySoldSwitch" name="is_sold" value="1">
                            <label class="form-check-label" for="alreadySoldSwitch">这个饰品已经售出</label>
                        </div>
                        
                        <!-- 卖出信息区域，默认隐藏 -->
                        <div id="soldInfoSection" class="border rounded p-3 mb-3 d-none">
                            <h6 class="mb-3">卖出信息</h6>
                            <div class="mb-3">
                                <label class="form-label">卖出价格</label>
                                <div class="input-group">
                                    <span class="input-group-text">¥</span>
                                    <input type="number" step="0.01" class="form-control" name="sold_price" id="addSoldPrice">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">手续费</label>
                                <div class="input-group">
                                    <span class="input-group-text">¥</span>
                                    <input type="number" step="0.01" class="form-control" name="fee" id="addFee" value="0">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">卖出日期</label>
                                <input type="date" class="form-control" name="sold_date" id="addSoldDate" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-plus me-1"></i> 添加
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 卖出饰品模态框 -->
    <div class="modal fade" id="sellSkinModal" tabindex="-1" aria-labelledby="sellSkinModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sellSkinModalLabel">卖出饰品</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="sellSkinForm">
                        <input type="hidden" id="sellSkinId" name="id">
                        <input type="hidden" id="sellMode" name="mode" value="add">
                        <div class="mb-3">
                            <label for="skinName" class="form-label">饰品名称</label>
                            <input type="text" class="form-control" id="skinName" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="soldPrice" class="form-label">出售价格</label>
                            <div class="input-group">
                                <span class="input-group-text">¥</span>
                                <input type="number" class="form-control" id="soldPrice" name="sold_price" required min="0" step="0.01">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="fee" class="form-label">手续费</label>
                            <div class="input-group">
                                <span class="input-group-text">¥</span>
                                <input type="number" class="form-control" id="fee" name="fee" min="0" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="soldDate" class="form-label">卖出日期</label>
                            <input type="date" class="form-control" id="soldDate" name="sold_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-alt-secondary" data-bs-dismiss="modal">关闭</button>
                    <button type="button" class="btn btn-success" id="confirmSellBtn">
                        <i class="fa fa-check me-1"></i> 确认卖出
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 编辑饰品模态框 -->
    <div class="modal fade" id="editSkinModal" tabindex="-1" aria-labelledby="editSkinModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSkinModalLabel">编辑饰品</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editSkinForm">
                        <input type="hidden" id="editSkinId" name="skin_id">
                        <div class="mb-3">
                            <label for="editName" class="form-label">饰品名称</label>
                            <input type="text" class="form-control" id="editName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="editPurchasePrice" class="form-label">购入价格</label>
                            <div class="input-group">
                                <span class="input-group-text">¥</span>
                                <input type="number" step="0.01" class="form-control" id="editPurchasePrice" name="purchase_price" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editPurchaseDate" class="form-label">购入日期</label>
                            <input type="date" class="form-control" id="editPurchaseDate" name="purchase_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="editMarketHashName" class="form-label">市场哈希名称</label>
                            <input type="text" class="form-control" id="editMarketHashName" name="marketHashName">
                            <div class="form-text text-muted">用于访问Steam社区市场价格历史（可选）</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-alt-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="confirmEditBtn">
                        <i class="fa fa-save me-1"></i> 保存修改
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 添加已售出饰品编辑模态框 -->
    <div class="modal fade" id="editSoldSkinModal" tabindex="-1" aria-labelledby="editSoldSkinModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSoldSkinModalLabel">编辑已售出饰品</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editSoldSkinForm">
                        <input type="hidden" id="editSoldSkinId" name="skin_id">
                        <div class="mb-3">
                            <label for="editSoldSkinName" class="form-label">饰品名称</label>
                            <input type="text" class="form-control" id="editSoldSkinName" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="editSoldPurchasePrice" class="form-label">购入价格</label>
                            <div class="input-group">
                                <span class="input-group-text">¥</span>
                                <input type="number" step="0.01" class="form-control" id="editSoldPurchasePrice" name="purchase_price" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editSoldPurchaseDate" class="form-label">购入日期</label>
                            <input type="date" class="form-control" id="editSoldPurchaseDate" name="purchase_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="editSoldPrice" class="form-label">卖出价格</label>
                            <div class="input-group">
                                <span class="input-group-text">¥</span>
                                <input type="number" step="0.01" class="form-control" id="editSoldPrice" name="sold_price" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editSoldFee" class="form-label">手续费</label>
                            <div class="input-group">
                                <span class="input-group-text">¥</span>
                                <input type="number" step="0.01" class="form-control" id="editSoldFee" name="fee" min="0" value="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editSoldDate" class="form-label">卖出日期</label>
                            <input type="date" class="form-control" id="editSoldDate" name="sold_date" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-alt-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="confirmEditSoldBtn">
                        <i class="fa fa-save me-1"></i> 保存修改
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript库 - 使用CDN替代本地文件 -->
    <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/chart.js/3.7.0/chart.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
    
    <!-- 调试信息 -->
    <script>
        // 用于检测移动端和桌面端的兼容性问题
        window.debugInfo = {
            version: '1.0.3', // 更新版本号
            loadTime: new Date().toISOString(),
            screenWidth: window.innerWidth,
            screenHeight: window.innerHeight,
            isMobile: window.innerWidth <= 480,
            userAgent: navigator.userAgent,
            cdnMode: true // 标记使用CDN资源
        };
        console.log('调试信息:', window.debugInfo);
    </script>
    
    <!-- 自定义JavaScript - 确保先加载main.js，再加载responsive.js -->
    <script src="js/main.js"></script>
    <script src="js/pwa.js"></script>
    <script src="js/responsive.js"></script>

    <!-- 在JavaScript部分修改排序相关代码 -->
    <script>
    // 初始化拖拽排序
    let sortable = null;

    // 初始化排序功能
    function initSortable() {
        if (sortable) {
            sortable.destroy();
        }
        
        sortable = new Sortable(document.getElementById('skin-list'), {
            animation: 150,
            handle: '.drag-handle',
            onEnd: function(evt) {
                // 更新排序
                updateSortOrder();
            }
        });
    }

    // 更新排序
    function updateSortOrder() {
        const isSold = $('#showSoldSkins').hasClass('active');
        const items = $('#skin-list .skin-item');
        const orderData = [];
        
        items.each(function(index) {
            const skinId = $(this).data('id');
            orderData.push({
                id: skinId,
                order: index + 1
            });
        });
        
        // 发送排序请求
        $.ajax({
            url: 'api/update_sort.php',
            method: 'POST',
            data: {
                items: orderData,
                is_sold: isSold ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    showToast('排序更新成功', 'success');
                } else {
                    showToast('排序更新失败: ' + response.message, 'error');
                }
            },
            error: function() {
                showToast('排序更新失败，请重试', 'error');
            }
        });
    }

    // 添加拖拽相关样式
    const style = document.createElement('style');
    style.textContent = `
        .skin-item {
            cursor: move;
            position: relative;
        }
        
        .drag-handle {
            position: absolute;
            left: 5px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            cursor: move;
            padding: 5px;
            opacity: 0.5;
            transition: opacity 0.2s;
        }
        
        .skin-item:hover .drag-handle {
            opacity: 1;
        }
        
        .sortable-ghost {
            opacity: 0.5;
            background: #f8f9fa;
        }
        
        .sortable-chosen {
            background: #e9ecef;
        }
    `;
    document.head.appendChild(style);

    // 在页面加载完成后初始化排序
    $(document).ready(function() {
        initSortable();
        
        // 在切换未售出/已售出时重新初始化排序
        $('#showActiveSkins, #showSoldSkins').on('click', function() {
            setTimeout(initSortable, 100);
        });
    });
    </script>
</body>
</html> 