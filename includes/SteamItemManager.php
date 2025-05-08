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
}
?> 