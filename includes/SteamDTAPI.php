<?php
require_once __DIR__ . '/../config.php';

class SteamDTAPI {
    private $apiKey;
    private $baseUrl;
    private $db;
    
    public function __construct() {
        $this->apiKey = STEAMDT_API_KEY;
        $this->baseUrl = API_BASE_URL;
        try {
            $this->db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
        } catch (PDOException $e) {
            throw new Exception("数据库连接失败，请检查配置");
        }
    }

    public function getMarketHashName($name) {
        $stmt = $this->db->prepare("SELECT marketHashName FROM skin_names WHERE name = ?");
        $stmt->execute([$name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['marketHashName'];
        }
        throw new Exception("未找到该饰品的市场Hash名称，请确保饰品名称正确");
    }

    public function getInspectImages($inspectUrl) {
        error_log("[SteamDTAPI] 开始获取检视图，检视链接: " . $inspectUrl);
        
        $url = $this->baseUrl . '/open/cs2/v1/inspect/screenshot';
        
        $data = [
            'inspectUrl' => $inspectUrl
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);

        $response = curl_exec($ch);
        error_log("[SteamDTAPI] 检视图API原始响应: " . $response);
        
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("[SteamDTAPI] CURL错误: " . $error);
            return [
                'success' => false,
                'errorMsg' => 'CURL错误: ' . $error
            ];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("[SteamDTAPI] HTTP错误: " . $httpCode);
            return [
                'success' => false,
                'errorMsg' => '请求失败，HTTP状态码：' . $httpCode
            ];
        }

        $result = json_decode($response, true);
        error_log("[SteamDTAPI] 检视图解析后的响应: " . json_encode($result));
        
        // 检查API返回结果
        if (!isset($result['success']) || !$result['success']) {
            $errorMsg = isset($result['errorMsg']) ? $result['errorMsg'] : (isset($result['message']) ? $result['message'] : '未知错误');
            error_log("[SteamDTAPI] API返回错误: " . $errorMsg);
            return [
                'success' => false,
                'errorMsg' => $errorMsg
            ];
        }
        
        // 检查数据是否存在
        if (!isset($result['data'])) {
            error_log("[SteamDTAPI] API返回数据缺失");
            return [
                'success' => false,
                'errorMsg' => 'API返回数据缺失'
            ];
        }
        
        // 检查是否需要异步处理
        if (isset($result['data']['sync']) && !$result['data']['sync']) {
            error_log("[SteamDTAPI] 需要异步处理，任务ID: " . ($result['data']['taskId'] ?? 'unknown'));
            // 这不是错误，而是异步处理的正常响应
            return $result;
        }
        
        // 尝试找到截图数据
        if (!isset($result['data']['screenshot']) || !isset($result['data']['screenshot']['screenshots'])) {
            error_log("[SteamDTAPI] API返回数据中没有找到截图信息");
            return [
                'success' => false,
                'errorMsg' => 'API返回数据中没有找到截图信息'
            ];
        }
        
        // 成功找到截图数据
        return $result;
    }

    public function getBaseInfo() {
        $url = $this->baseUrl . '/open/cs2/v1/base';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                'success' => false,
                'errorMsg' => 'CURL错误: ' . $error
            ];
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'errorMsg' => '请求失败，HTTP状态码：' . $httpCode
            ];
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['success']) || !$result['success']) {
            $errorMsg = isset($result['errorMsg']) ? $result['errorMsg'] : (isset($result['message']) ? $result['message'] : '未知错误');
            return [
                'success' => false,
                'errorMsg' => $errorMsg
            ];
        }
        
        return $result;
    }

    /**
     * 批量获取价格
     * @param array $marketHashNames 市场Hash名称数组
     * @return array 价格数据数组
     */
    public function getBatchPrices($marketHashNames) {
        if (empty($marketHashNames)) {
            return [
                'success' => false,
                'message' => '市场Hash名称列表为空'
            ];
        }
        
        // 最多一次查询999个物品
        if (count($marketHashNames) > 999) {
            $marketHashNames = array_slice($marketHashNames, 0, 999);
        }
        
        $url = $this->baseUrl . '/open/cs2/v1/price/batch';
        
        $data = [
            'marketHashNames' => $marketHashNames
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                'success' => false,
                'message' => 'CURL错误: ' . $error
            ];
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => '请求失败，HTTP状态码：' . $httpCode
            ];
        }
        
        $result = json_decode($response, true);
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['errorMsg'] ?? '未知错误'
            ];
        }
        
        // 处理返回的数据
        $processedData = [];
        $platformDisplayNames = [
            'YOUPIN' => '悠悠',
            'BUFF' => 'BUFF',
            'C5' => 'C5',
            'STEAM' => 'Steam',
            'WAXPEER' => 'Waxpeer',
            'DMARKET' => 'Dmarket',
            'SKINPORT' => 'Skinport',
            'HALOSKINS' => 'Haloskins'
        ];
        
        foreach ($result['data'] as $item) {
            $marketHashName = $item['marketHashName'];
            $lowestPrice = PHP_FLOAT_MAX;
            $lowestPlatform = '';
            $lowestDisplayName = '';
            
            // 只从BUFF、C5和悠悠有品三个平台中找出最低价格
            foreach ($item['dataList'] as $platformData) {
                // 只考虑BUFF、C5和悠悠有品(YOUPIN)三个平台
                $validPlatforms = ['BUFF', 'C5', 'YOUPIN'];
                if (in_array($platformData['platform'], $validPlatforms) && 
                    $platformData['sellPrice'] >= 0 && 
                    $platformData['sellPrice'] < $lowestPrice) {
                    $lowestPrice = $platformData['sellPrice'];
                    $lowestPlatform = $platformData['platform'];
                    $lowestDisplayName = isset($platformDisplayNames[$platformData['platform']]) 
                        ? $platformDisplayNames[$platformData['platform']] 
                        : $platformData['platform'];
                }
            }
            
            $processedData[$marketHashName] = [
                'price' => ($lowestPrice === PHP_FLOAT_MAX) ? 0 : $lowestPrice,
                'platform' => $lowestPlatform,
                'platform_display' => $lowestDisplayName,
                'all_platforms' => $item['dataList']
            ];
        }
        
        return [
            'success' => true,
            'data' => $processedData
        ];
    }

    /**
     * 获取单个饰品价格
     * @param string $marketHashName 市场Hash名称
     * @return array 价格数据
     */
    public function getSinglePrice($marketHashName) {
        if (empty($marketHashName)) {
            return [
                'success' => false,
                'message' => '市场Hash名称为空'
            ];
        }
        
        error_log("[SteamDTAPI] 开始获取单个饰品价格，Hash名称: " . $marketHashName);
        
        // 使用批量价格接口，但只传递一个饰品名称
        $result = $this->getBatchPrices([$marketHashName]);
        
        if (!$result['success']) {
            error_log("[SteamDTAPI] 获取价格失败: " . ($result['message'] ?? '未知错误'));
            return $result;
        }
        
        if (!isset($result['data'][$marketHashName])) {
            error_log("[SteamDTAPI] 没有找到该饰品的价格数据");
            return [
                'success' => false,
                'message' => '没有找到该饰品的价格数据'
            ];
        }
        
        error_log("[SteamDTAPI] 获取价格成功: " . json_encode($result['data'][$marketHashName]));
        
        return [
            'success' => true,
            'data' => $result['data'][$marketHashName]
        ];
    }
}
?>