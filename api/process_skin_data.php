<?php
// 处理饰品数据，确保0元购入的饰品显示"不计算"
function processSkinData($skins) {
    foreach ($skins as &$skin) {
        // 检查是否为0元购入的饰品
        $purchasePrice = floatval($skin['purchase_price']);
        if ($purchasePrice === 0.0) {
            // 将涨跌幅和盈亏率设置为"不计算"
            $skin['price_change'] = '不计算';
            $skin['profit_percent'] = '不计算';
            $skin['actual_return'] = '不计算';
            $skin['annualized_return'] = '不计算';
            
            // 确保这些值不会被转换为浮点数
            $skin['_is_zero_price'] = true;
        }

    }
    return $skins;
}
?> 