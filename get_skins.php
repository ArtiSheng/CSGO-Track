<?php
// 开启错误报告（仅用于调试）
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'includes/Database.php';

// 获取数据库连接
$db = Database::getInstance()->getConnection();

// 解析排序参数
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'default';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';
$showSold = isset($_GET['show_sold']) ? $_GET['show_sold'] : 'all';
$mergeMode = isset($_GET['merge_mode']) ? $_GET['merge_mode'] : 'separate';

error_log("接收到请求参数: sort=$sort, order=$order, show_sold=$showSold, merge_mode=$mergeMode");

// 根据$showSold参数构建SQL的WHERE条件
$condition = "";
if ($showSold === 'sold_only') {
    $condition = "WHERE is_sold = 1";
} else if ($showSold === 'unsold_only') {
    $condition = "WHERE is_sold = 0 OR is_sold IS NULL";
}

// 根据排序参数确定ORDER BY子句
$orderBy = 'id';  // 默认按ID排序
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
    case 'soldprice':
    case 'sold_price':  // 兼容两种写法
        $orderBy = 'sold_price';
        break;
    case 'netprice':
    case 'net_price':  // 兼容两种写法
        $orderBy = 'sold_price - IFNULL(fee, 0)';
        break;
    case 'profitrate':
        $orderBy = '(sold_price - IFNULL(fee, 0) - purchase_price) / purchase_price * 100';
        break;
    case 'days':
        $orderBy = 'DATEDIFF(IFNULL(sold_date, CURRENT_DATE), purchase_date)';
        break;
    case 'fee':
        $orderBy = 'fee';
        break;
    case 'solddate':
    case 'sold_date':  // 兼容两种写法
        $orderBy = 'sold_date';
        break;
    default:
        $orderBy = 'id';  // 默认按ID排序
        break;
}

// 构建SQL查询，确保ORDER BY子句语法正确
try {
    // 打印调试信息
    error_log("执行查询: 排序=$sort, 顺序=$order, 显示已售出=$showSold, 合并模式=$mergeMode");
    
    // 特殊处理一些复杂的排序字段，避免SQL错误
    if ($sort === 'days') {
        // 计算持有天数的特殊处理
        $sql = "SELECT *, DATEDIFF(IFNULL(sold_date, CURRENT_DATE), purchase_date) as days_diff FROM skins $condition ORDER BY days_diff " . ($order === 'desc' ? 'DESC' : 'ASC');
    } else if ($sort === 'profitrate' && $showSold === 'sold_only') {
        // 已售出饰品的盈亏率特殊处理
        $sql = "SELECT *, ((sold_price - IFNULL(fee, 0)) - purchase_price) / purchase_price * 100 as profit_rate FROM skins $condition ORDER BY profit_rate " . ($order === 'desc' ? 'DESC' : 'ASC');
    } else if ($sort === 'netprice' && $showSold === 'sold_only') {
        // 到手价格特殊处理
        $sql = "SELECT *, (sold_price - IFNULL(fee, 0)) as net_price FROM skins $condition ORDER BY net_price " . ($order === 'desc' ? 'DESC' : 'ASC');
    } else {
        // 针对已售出饰品的特殊处理
        if ($showSold === 'sold_only') {
            switch ($sort) {
                case 'change':
                    // 已售出饰品的涨跌幅应该使用售出价格与购入价格的比较
                    $sql = "SELECT *, ((sold_price - purchase_price) / purchase_price * 100) as price_change FROM skins $condition ORDER BY price_change " . ($order === 'desc' ? 'DESC' : 'ASC');
                    break;
                case 'profit':
                    // 已售出饰品的盈亏应考虑手续费
                    $sql = "SELECT *, (sold_price - IFNULL(fee, 0) - purchase_price) as actual_profit FROM skins $condition ORDER BY actual_profit " . ($order === 'desc' ? 'DESC' : 'ASC');
                    break;
                case 'soldprice':
                    // 售出价格排序
                    $sql = "SELECT * FROM skins $condition ORDER BY sold_price " . ($order === 'desc' ? 'DESC' : 'ASC');
                    break;  
                case 'solddate':
                    // 售出日期排序
                    $sql = "SELECT * FROM skins $condition ORDER BY sold_date " . ($order === 'desc' ? 'DESC' : 'ASC');
                    break;
                default:
                    // 默认排序
                    $sql = "SELECT * FROM skins $condition ORDER BY " . ($sort === 'default' ? 'id' : $orderBy) . " " . ($order === 'desc' ? 'DESC' : 'ASC');
            }
        } else {
            // 未售出饰品的常规排序
            $sql = "SELECT * FROM skins $condition ORDER BY " . ($sort === 'default' ? 'id' : $orderBy) . " " . ($order === 'desc' ? 'DESC' : 'ASC');
        }
    }
    
    error_log("执行SQL: " . $sql);  // 记录SQL语句用于调试
    
    $stmt = $db->query($sql);
    if (!$stmt) {
        throw new Exception("数据库查询失败: " . print_r($db->errorInfo(), true));
    }
    $rawSkins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("查询结果数量: " . count($rawSkins));

    // 添加更详细的调试日志
    if ($showSold === 'sold_only') {
        $soldSkinsCount = count($rawSkins);
        error_log("已售出饰品数量: $soldSkinsCount");
        
        if ($soldSkinsCount > 0) {
            $firstSkin = $rawSkins[0];
            // 记录第一个饰品的信息用于调试
            error_log("第一个已售出饰品信息: ID={$firstSkin['id']}, 名称={$firstSkin['name']}, " . 
                      "购入日期={$firstSkin['purchase_date']}, 售出日期={$firstSkin['sold_date']}");
        } else {
            error_log("未找到已售出饰品");
        }
    }
} catch (Exception $e) {
    // 记录错误并返回错误信息
    error_log("获取饰品列表错误: " . $e->getMessage());
    error_log("SQL: " . (isset($sql) ? $sql : "未生成SQL"));
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'sql' => isset($sql) ? $sql : null,
        'error_code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'params' => [
            'sort' => $sort,
            'order' => $order,
            'show_sold' => $showSold,
            'merge_mode' => $mergeMode
        ]
    ]);
    exit;
}

// 处理合并模式
if ($mergeMode === 'merged') {
    $mergedSkins = [];
    $skinNameMap = [];
    
    foreach ($rawSkins as $skin) {
        $name = $skin['name'];
        
        if (isset($skinNameMap[$name])) {
            // 如果已经有相同名称的饰品，增加数量
            $quantity = isset($skin['quantity']) ? intval($skin['quantity']) : 1;
            $mergedQuantity = isset($mergedSkins[$skinNameMap[$name]]['quantity']) 
                ? $mergedSkins[$skinNameMap[$name]]['quantity'] + $quantity 
                : 1 + $quantity;
            
            $mergedSkins[$skinNameMap[$name]]['quantity'] = $mergedQuantity;
            
            // 累加价格（用于计算总投入和总价值）
            $mergedSkins[$skinNameMap[$name]]['total_purchase_price'] = isset($mergedSkins[$skinNameMap[$name]]['total_purchase_price']) 
                ? $mergedSkins[$skinNameMap[$name]]['total_purchase_price'] + ($skin['purchase_price'] * $quantity)
                : ($mergedSkins[$skinNameMap[$name]]['purchase_price'] * (isset($mergedSkins[$skinNameMap[$name]]['quantity']) ? $mergedSkins[$skinNameMap[$name]]['quantity'] - $quantity : 1)) + ($skin['purchase_price'] * $quantity);
            
            $mergedSkins[$skinNameMap[$name]]['total_market_price'] = isset($mergedSkins[$skinNameMap[$name]]['total_market_price']) 
                ? $mergedSkins[$skinNameMap[$name]]['total_market_price'] + ($skin['market_price'] * $quantity)
                : ($mergedSkins[$skinNameMap[$name]]['market_price'] * (isset($mergedSkins[$skinNameMap[$name]]['quantity']) ? $mergedSkins[$skinNameMap[$name]]['quantity'] - $quantity : 1)) + ($skin['market_price'] * $quantity);
            
            // 如果是已售出，累加售出价和手续费
            if (isset($skin['is_sold']) && $skin['is_sold'] == 1) {
                $mergedSkins[$skinNameMap[$name]]['total_sold_price'] = isset($mergedSkins[$skinNameMap[$name]]['total_sold_price']) 
                    ? $mergedSkins[$skinNameMap[$name]]['total_sold_price'] + ($skin['sold_price'] * $quantity)
                    : (isset($mergedSkins[$skinNameMap[$name]]['sold_price']) ? $mergedSkins[$skinNameMap[$name]]['sold_price'] * (isset($mergedSkins[$skinNameMap[$name]]['quantity']) ? $mergedSkins[$skinNameMap[$name]]['quantity'] - $quantity : 1) : 0) + ($skin['sold_price'] * $quantity);
                
                $mergedSkins[$skinNameMap[$name]]['total_fee'] = isset($mergedSkins[$skinNameMap[$name]]['total_fee']) 
                    ? $mergedSkins[$skinNameMap[$name]]['total_fee'] + (isset($skin['fee']) ? $skin['fee'] * $quantity : 0)
                    : (isset($mergedSkins[$skinNameMap[$name]]['fee']) ? $mergedSkins[$skinNameMap[$name]]['fee'] * (isset($mergedSkins[$skinNameMap[$name]]['quantity']) ? $mergedSkins[$skinNameMap[$name]]['quantity'] - $quantity : 1) : 0) + (isset($skin['fee']) ? $skin['fee'] * $quantity : 0);
                
                // 设置已售出标志
                $mergedSkins[$skinNameMap[$name]]['is_sold'] = 1;
            }
        } else {
            // 第一次遇到这个名称的饰品
            $skinNameMap[$name] = count($mergedSkins);
            $quantity = isset($skin['quantity']) ? intval($skin['quantity']) : 1;
            
            // 复制基本信息
            $mergedSkins[] = $skin;
            $index = count($mergedSkins) - 1;
            
            // 初始化总价格
            $mergedSkins[$index]['total_purchase_price'] = $skin['purchase_price'] * $quantity;
            $mergedSkins[$index]['total_market_price'] = $skin['market_price'] * $quantity;
            
            // 如果是已售出，初始化总售出价和总手续费
            if (isset($skin['is_sold']) && $skin['is_sold'] == 1) {
                $mergedSkins[$index]['total_sold_price'] = $skin['sold_price'] * $quantity;
                $mergedSkins[$index]['total_fee'] = isset($skin['fee']) ? $skin['fee'] * $quantity : 0;
            }
        }
    }
    
    // 计算平均价格
    foreach ($mergedSkins as &$skin) {
        $quantity = isset($skin['quantity']) ? intval($skin['quantity']) : 1;
        
        // 计算平均购入价和平均市场价
        $skin['purchase_price'] = isset($skin['total_purchase_price']) ? ($skin['total_purchase_price'] / $quantity) : $skin['purchase_price'];
        $skin['market_price'] = isset($skin['total_market_price']) ? ($skin['total_market_price'] / $quantity) : $skin['market_price'];
        
        // 如果是已售出，计算平均售出价和平均手续费
        if (isset($skin['is_sold']) && $skin['is_sold'] == 1) {
            $skin['sold_price'] = isset($skin['total_sold_price']) ? ($skin['total_sold_price'] / $quantity) : $skin['sold_price'];
            $skin['fee'] = isset($skin['total_fee']) ? ($skin['total_fee'] / $quantity) : (isset($skin['fee']) ? $skin['fee'] : 0);
        }
    }
    
    $skins = $mergedSkins;
} else {
    $skins = $rawSkins;
}

// 确保时区正确
date_default_timezone_set('Asia/Shanghai');

// 计算涨跌幅和盈亏
foreach ($skins as &$skin) {
    $purchasePrice = floatval($skin['purchase_price']) ?: 0;
    $marketPrice = floatval($skin['market_price']) ?: 0;
    $quantity = isset($skin['quantity']) ? intval($skin['quantity']) : 1;
    $isSold = isset($skin['is_sold']) && $skin['is_sold'] == 1;
    $soldPrice = $isSold ? (floatval($skin['sold_price']) ?: 0) : 0;
    $fee = $isSold ? (floatval($skin['fee']) ?: 0) : 0;
    
    if ($isSold) {
        // 已售出的饰品，计算售出盈亏（考虑手续费）
        $netIncome = $soldPrice - $fee; // 实际到手金额
        
        // 安全计算涨跌幅
        if ($purchasePrice > 0.001) {  // 避免除以接近零的数
            $priceChange = (($soldPrice - $purchasePrice) / $purchasePrice * 100);
            // 检查是否有效数字
            if (is_finite($priceChange)) {
                $skin['price_change'] = $priceChange;
            } else {
                $skin['price_change'] = 0;
                error_log("涨跌幅计算无效: ID={$skin['id']}, 购入价=$purchasePrice, 售出价=$soldPrice");
            }
        } else {
            $skin['price_change'] = 0;
            error_log("购入价格接近零: ID={$skin['id']}, 购入价=$purchasePrice");
        }
        
        // 如果是合并模式下，total_purchase_price和total_sold_price已经考虑了数量
        if ($mergeMode === 'merged' && isset($skin['total_purchase_price']) && isset($skin['total_sold_price']) && isset($skin['total_fee'])) {
            // 使用总价格计算盈亏
            $totalNetIncome = $skin['total_sold_price'] - $skin['total_fee'];
            $skin['profit'] = $totalNetIncome - $skin['total_purchase_price'];
        } else {
            // 单独模式下，考虑数量的盈亏
            $skin['profit'] = ($netIncome - $purchasePrice) * $quantity;
        }
        
        // 安全计算收益率
        if ($purchasePrice > 0.001) {
            $actualReturn = (($netIncome - $purchasePrice) / $purchasePrice * 100);
            $profitPercent = (($netIncome - $purchasePrice) / $purchasePrice * 100);
            
            // 检查计算结果是否有效
            if (is_finite($actualReturn)) {
                $skin['actual_return'] = $actualReturn;
            } else {
                $skin['actual_return'] = 0;
                error_log("实际收益率计算无效: ID={$skin['id']}, 购入价=$purchasePrice, 净收入=$netIncome");
            }
            
            if (is_finite($profitPercent)) {
                $skin['profit_percent'] = $profitPercent;
            } else {
                $skin['profit_percent'] = 0;
                error_log("盈亏百分比计算无效: ID={$skin['id']}, 购入价=$purchasePrice, 净收入=$netIncome");
            }
        } else {
            $skin['actual_return'] = 0;
            $skin['profit_percent'] = 0;
        }
        
        // 安全计算持有天数和年化收益率
        try {
            if (isset($skin['purchase_date']) && isset($skin['sold_date']) &&
                !empty($skin['purchase_date']) && !empty($skin['sold_date'])) {
                
                // 验证日期格式
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $skin['purchase_date']) && 
                    preg_match('/^\d{4}-\d{2}-\d{2}$/', $skin['sold_date'])) {
                    
                    // 创建日期对象前进行额外检查
                    $purchaseDate = DateTime::createFromFormat('Y-m-d', $skin['purchase_date']);
                    $sellDate = DateTime::createFromFormat('Y-m-d', $skin['sold_date']);
                    
                    if ($purchaseDate && $sellDate) {
                        $daysHeld = $purchaseDate->diff($sellDate)->days;
                        
                        $skin['days_held'] = $daysHeld;
                        
                        // 安全计算年化收益率
                        if ($daysHeld > 0 && $purchasePrice > 0.001) {
                            // 计算总收益率
                            $totalReturn = ($netIncome - $purchasePrice) / $purchasePrice;
                            
                            // 限制总收益率范围，避免极端值
                            if ($totalReturn > -0.99 && $totalReturn < 100) {
                                // 使用复利公式计算年化收益率: (1 + r)^(365/days) - 1
                                $annualizedReturn = (pow(1 + $totalReturn, 365 / $daysHeld) - 1) * 100;
                                
                                // 确保结果是有限数
                                if (is_finite($annualizedReturn)) {
                                    $skin['annualized_return'] = $annualizedReturn;
                                } else {
                                    $skin['annualized_return'] = 0;
                                    error_log("年化收益率计算结果无效: ID={$skin['id']}, totalReturn=$totalReturn, daysHeld=$daysHeld");
                                }
                            } else {
                                $skin['annualized_return'] = 0;
                                error_log("总收益率超出合理范围: ID={$skin['id']}, totalReturn=$totalReturn");
                            }
                        } else {
                            $skin['annualized_return'] = 0;
                        }
                    } else {
                        // 日期对象创建失败
                        $skin['days_held'] = 0;
                        $skin['annualized_return'] = 0;
                        error_log("无法创建日期对象: purchase_date={$skin['purchase_date']}, sold_date={$skin['sold_date']}");
                    }
                } else {
                    // 日期格式无效
                    $skin['days_held'] = 0;
                    $skin['annualized_return'] = 0;
                    error_log("日期格式无效: purchase_date={$skin['purchase_date']}, sold_date={$skin['sold_date']}");
                }
            } else {
                $skin['days_held'] = 0;
                $skin['annualized_return'] = 0;
            }
        } catch (Exception $dateEx) {
            // 日期处理异常
            $skin['days_held'] = 0;
            $skin['annualized_return'] = 0;
            error_log("计算持有天数异常: " . $dateEx->getMessage() . 
                     " purchase_date={$skin['purchase_date']}, sold_date={$skin['sold_date']}");
        }
    } else if ($purchasePrice > 0 && $marketPrice > 0) {
        // 未售出的饰品，使用市场价计算
        if ($purchasePrice > 0.001) {
            $priceChange = ($marketPrice - $purchasePrice) / $purchasePrice * 100;
            if (is_finite($priceChange)) {
                $skin['price_change'] = $priceChange;
            } else {
                $skin['price_change'] = 0;
                error_log("未售出饰品涨跌幅计算无效: ID={$skin['id']}, 购入价=$purchasePrice, 市场价=$marketPrice");
            }
        } else {
            $skin['price_change'] = 0;
        }
        
        // 如果是合并模式
        if ($mergeMode === 'merged' && isset($skin['total_purchase_price']) && isset($skin['total_market_price'])) {
            // 使用总价格计算盈亏
            $skin['profit'] = $skin['total_market_price'] - $skin['total_purchase_price'];
        } else {
            // 单独模式下，盈亏乘以数量
            $skin['profit'] = ($marketPrice - $purchasePrice) * $quantity;
        }
        
        // 添加盈亏百分比字段 - 这个是基于单个饰品计算的
        if ($purchasePrice > 0.001) {
            $profitPercent = (($marketPrice - $purchasePrice) / $purchasePrice * 100);
            if (is_finite($profitPercent)) {
                $skin['profit_percent'] = $profitPercent;
            } else {
                $skin['profit_percent'] = 0;
                error_log("未售出饰品盈亏率计算无效: ID={$skin['id']}, 购入价=$purchasePrice, 市场价=$marketPrice");
            }
        } else {
            $skin['profit_percent'] = 0;
        }
    } else if ($purchasePrice === 0 && $marketPrice > 0) {
        // 0元购入的饰品
        $skin['price_change'] = 0; // 不计算涨跌幅
        
        // 盈亏就是市场价格
        if ($mergeMode === 'merged' && isset($skin['total_market_price'])) {
            $skin['profit'] = $skin['total_market_price'];
        } else {
            $skin['profit'] = $marketPrice * $quantity;
        }
        
        $skin['profit_percent'] = 0; // 不计算盈亏百分比
    } else {
        $skin['price_change'] = 0;
        $skin['profit'] = 0;
        $skin['profit_percent'] = 0;
    }

    // 确保饰品有数量字段
    if (!isset($skin['quantity'])) {
        $skin['quantity'] = 1;
    }
    
    // 将重要的计算结果记录到日志
    if ($mergeMode === 'merged' && $quantity > 1) {
        error_log("合并模式 - 饰品: {$skin['name']}, 数量: $quantity, 购入价: $purchasePrice, 市场价: $marketPrice, 售出状态: " . ($isSold ? "已售出" : "未售出") . ", 盈亏: {$skin['profit']}");
    }
}

// 输出JSON结果
try {
    // 最后的安全检查，确保每个饰品对象包含必要字段且类型正确
    foreach ($skins as &$skin) {
        // 确保所有日期格式化为标准格式
        if (isset($skin['purchase_date']) && !empty($skin['purchase_date'])) {
            $dateObj = DateTime::createFromFormat('Y-m-d', $skin['purchase_date']);
            if ($dateObj) {
                $skin['purchase_date'] = $dateObj->format('Y-m-d');
            }
        }
        
        if (isset($skin['sold_date']) && !empty($skin['sold_date'])) {
            $dateObj = DateTime::createFromFormat('Y-m-d', $skin['sold_date']);
            if ($dateObj) {
                $skin['sold_date'] = $dateObj->format('Y-m-d');
            }
        }
        
        // 检查并修复所有数值字段，确保不是Inf或NaN
        $numericFields = [
            'purchase_price', 'market_price', 'sold_price', 'fee', 'profit', 
            'price_change', 'profit_percent', 'actual_return', 'annualized_return'
        ];
        
        foreach ($numericFields as $field) {
            if (isset($skin[$field])) {
                // 先转换为浮点数
                $value = (float)$skin[$field];
                
                // 检查是否为Inf或NaN
                if (is_nan($value) || is_infinite($value)) {
                    error_log("检测到无效数值: $field = " . $skin[$field] . " (ID: {$skin['id']})");
                    $skin[$field] = 0; // 替换为0
                } else {
                    $skin[$field] = $value;
                }
            }
        }
        
        // 特别检查可能导致除以零的计算
        if (isset($skin['price_change']) && abs($skin['price_change']) > 1000000) {
            error_log("异常大的涨跌幅: {$skin['price_change']} (ID: {$skin['id']})");
            $skin['price_change'] = 0;
        }
        
        if (isset($skin['profit_percent']) && abs($skin['profit_percent']) > 1000000) {
            error_log("异常大的盈亏率: {$skin['profit_percent']} (ID: {$skin['id']})");
            $skin['profit_percent'] = 0;
        }
        
        if (isset($skin['annualized_return']) && abs($skin['annualized_return']) > 1000000) {
            error_log("异常大的年化收益率: {$skin['annualized_return']} (ID: {$skin['id']})");
            $skin['annualized_return'] = 0;
        }
    }
    
    header('Content-Type: application/json');
    $jsonResult = json_encode($skins);
    
    // 检查JSON编码是否成功
    if ($jsonResult === false) {
        // 捕获JSON编码错误
        throw new Exception("JSON编码失败: " . json_last_error_msg());
    }
    
    echo $jsonResult;
} catch (Exception $jsonEx) {
    // 记录错误
    error_log("JSON编码错误: " . $jsonEx->getMessage());
    
    // 返回基本的错误信息
    echo json_encode([
        'success' => false,
        'message' => '数据处理错误: ' . $jsonEx->getMessage(),
        'count' => count($skins)
    ]);
}
exit; 