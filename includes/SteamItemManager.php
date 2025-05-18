<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/SteamDTAPI.php';

class SteamItemManager {
    private $api;
    private $db;
    private static $instance = null;

    private function __construct() {
        $this->api = new SteamDTAPI();
        try {
            $this->db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
            );
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            error_log("[SteamItemManager] 数据库连接成功");
        } catch (PDOException $e) {
            error_log("[SteamItemManager] 数据库连接失败: " . $e->getMessage());
            throw $e;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getBaseInfo() {
        $cacheFile = __DIR__ . '/../cache/base_info.json';
        
        // 检查缓存是否存在且在24小时内
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        // 获取新数据
        $result = $this->api->getBaseInfo();
        
        if ($result['success']) {
            // 确保缓存目录存在
            if (!file_exists(__DIR__ . '/../cache')) {
                mkdir(__DIR__ . '/../cache', 0777, true);
            }
            // 保存到缓存
            file_put_contents($cacheFile, json_encode($result));
        }

        return $result;
    }

    private function getMarketHashName($name) {
        error_log("[SteamItemManager] 尝试获取饰品Hash名称，输入名称: " . $name);
        $stmt = $this->db->prepare("SELECT marketHashName FROM skin_names WHERE name = ?");
        $stmt->execute([$name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            error_log("[SteamItemManager] 找到对应的Hash名称: " . $result['marketHashName']);
            return $result['marketHashName'];
        }
        error_log("[SteamItemManager] 未找到对应的Hash名称");
        throw new Exception("未找到该饰品的市场Hash名称，请确保饰品名称正确");
    }

    public function updateMarketPrice($marketHashName) {
        error_log("[SteamItemManager] 开始更新市场价格，Hash名称: " . $marketHashName);
        try {
            $priceInfo = $this->api->getSinglePrice($marketHashName);
            error_log("[SteamItemManager] 价格API返回: " . json_encode($priceInfo));
            
            if ($priceInfo['success'] && isset($priceInfo['data'])) {
                // 获取最低价格
                $lowestPrice = $priceInfo['data']['price'];
                $platform = isset($priceInfo['data']['platform_display']) ? 
                    $priceInfo['data']['platform_display'] : 
                    $priceInfo['data']['platform'];
                    
                error_log("[SteamItemManager] 获取到最低价格: {$lowestPrice} 来自平台: {$platform}");

                if ($lowestPrice > 0) {
                    // 更新数据库中的市场价格
                    $stmt = $this->db->prepare("UPDATE skins SET market_price = ?, price_platform = ?, last_updated = NOW() WHERE marketHashName = ?");
                    $stmt->execute([$lowestPrice, $platform, $marketHashName]);
                    error_log("[SteamItemManager] 更新价格成功");

                    return true;
                }
                error_log("[SteamItemManager] 价格无效");
            } else {
                error_log("[SteamItemManager] 获取价格失败: " . ($priceInfo['message'] ?? '未知错误'));
            }
        } catch (Exception $e) {
            error_log("[SteamItemManager] 更新市场价格失败: " . $e->getMessage());
        }
        return false;
    }

    public function addSkin($name, $purchasePrice, $purchaseDate, $quantity = 1) {
        error_log("[SteamItemManager] 开始添加饰品，名称: " . $name . ", 数量: " . $quantity);
        try {
            // 获取marketHashName
            $marketHashName = $this->getMarketHashName($name);
            error_log("[SteamItemManager] 获取到marketHashName: " . $marketHashName);

            // 获取当前最大的排序值
            $maxSortOrder = 0;
            $stmt = $this->db->query("SELECT MAX(sort_order) as max_order FROM skins");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result['max_order'])) {
                $maxSortOrder = intval($result['max_order']);
            }
            error_log("[SteamItemManager] 当前最大排序值: " . $maxSortOrder);

            $addedIds = [];
            
            // 插入饰品基本信息，获取ID
            $stmt = $this->db->prepare("
                INSERT INTO skins (name, purchase_price, purchase_date, marketHashName, quantity, sort_order) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $purchasePrice, $purchaseDate, $marketHashName, $quantity, $maxSortOrder + 1]);
            $skinId = $this->db->lastInsertId();
            $addedIds[] = $skinId;
            error_log("[SteamItemManager] 插入饰品基本信息成功，ID: " . $skinId);

            // 更新市场价格
            $this->updateMarketPrice($marketHashName);

            return $addedIds;
        } catch (Exception $e) {
            error_log("[SteamItemManager] 添加饰品失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 标记饰品为已售出状态并保存卖出信息
     * 
     * @param int $skinId 饰品ID
     * @param float $soldPrice 售出价格
     * @param string $soldDate 售出日期 (格式: YYYY-MM-DD)
     * @param float $fee 手续费 (默认为0)
     * @return bool 是否标记成功
     */
    public function sellSkin($skinId, $soldPrice, $soldDate, $fee = 0) {
        error_log("[SteamItemManager] 开始标记饰品为已售出，ID: " . $skinId . ", 售出价格: " . $soldPrice);
        try {
            // 确保饰品存在且未售出
            $stmt = $this->db->prepare("SELECT id, name, purchase_price, purchase_date FROM skins WHERE id = ? AND (is_sold = 0 OR is_sold IS NULL)");
            $stmt->execute([$skinId]);
            $skin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$skin) {
                $stmt = $this->db->prepare("SELECT id, is_sold FROM skins WHERE id = ?");
                $stmt->execute([$skinId]);
                $checkSkin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$checkSkin) {
                    error_log("[SteamItemManager] 饰品ID不存在: " . $skinId);
                    throw new Exception("饰品ID不存在: " . $skinId);
                } elseif ($checkSkin['is_sold'] == 1) {
                    error_log("[SteamItemManager] 饰品已经标记为售出: " . $skinId);
                    throw new Exception("饰品已经标记为售出状态，无法重复售出");
                }
            }
            
            // 标记饰品为已售出状态
            $stmt = $this->db->prepare("
                UPDATE skins 
                SET is_sold = 1, 
                    sold_price = ?, 
                    sold_date = ?, 
                    fee = ?
                WHERE id = ?
            ");
            $stmt->execute([$soldPrice, $soldDate, $fee, $skinId]);
            
            error_log("[SteamItemManager] 饰品标记为已售出成功，ID: " . $skinId);
            return true;
        } catch (Exception $e) {
            error_log("[SteamItemManager] 标记饰品为已售出失败: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取当前最大的排序值
     * @param bool $isSold 是否已售出
     * @return int 最大排序值
     */
    private function getMaxSortOrder($isSold = false) {
        $stmt = $this->db->prepare("SELECT MAX(sort_order) as max_order FROM skins WHERE is_sold = ?");
        $stmt->execute([$isSold ? 1 : 0]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && isset($result['max_order']) ? intval($result['max_order']) : 0;
    }

    /**
     * 更新饰品的排序值
     * @param int $skinId 饰品ID
     * @param int $newSortOrder 新的排序值
     * @param bool $isSold 是否已售出
     * @return bool 是否更新成功
     */
    public function updateSortOrder($skinId, $newSortOrder, $isSold = false) {
        try {
            // 验证饰品是否存在且状态正确
            $stmt = $this->db->prepare("SELECT id FROM skins WHERE id = ? AND is_sold = ?");
            $stmt->execute([$skinId, $isSold ? 1 : 0]);
            if (!$stmt->fetch()) {
                throw new Exception("饰品不存在或状态不匹配");
            }

            // 更新排序值
            $stmt = $this->db->prepare("UPDATE skins SET sort_order = ? WHERE id = ?");
            $stmt->execute([$newSortOrder, $skinId]);
            
            return true;
        } catch (Exception $e) {
            error_log("[SteamItemManager] 更新排序值失败: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 重新排序所有饰品
     * @param bool $isSold 是否已售出
     * @return bool 是否排序成功
     */
    public function reorderAllSkins($isSold = false) {
        try {
            // 获取所有需要排序的饰品
            $stmt = $this->db->prepare("
                SELECT id 
                FROM skins 
                WHERE is_sold = ? 
                ORDER BY sort_order ASC, id ASC
            ");
            $stmt->execute([$isSold ? 1 : 0]);
            $skins = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 更新每个饰品的排序值
            foreach ($skins as $index => $skin) {
                $newSortOrder = $index + 1;
                $this->updateSortOrder($skin['id'], $newSortOrder, $isSold);
            }

            return true;
        } catch (Exception $e) {
            error_log("[SteamItemManager] 重新排序失败: " . $e->getMessage());
            throw $e;
        }
    }
}
?> 