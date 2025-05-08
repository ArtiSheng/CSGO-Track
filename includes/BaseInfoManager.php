<?php

require_once 'SteamDTAPI.php';
require_once 'config.php';

class BaseInfoManager {
    private $db;
    private $api;

    public function __construct($db) {
        $this->db = $db;
        $this->api = new SteamDTAPI();
    }

    /**
     * 获取并保存饰品基础信息
     * @return array 处理结果
     */
    public function fetchAndSaveBaseInfo() {
        try {
            // 调用API获取基础信息
            $url = 'https://open.steamdt.com/open/cs2/v1/base';
            $response = $this->api->makeRequest($url, 'GET');
            
            if (!$response['success']) {
                throw new Exception("API请求失败: " . ($response['errorMsg'] ?? '未知错误'));
            }

            // 保存返回的数据到文件
            $timestamp = date('Y-m-d_H-i-s');
            $filename = __DIR__ . "/../logs/base_info_{$timestamp}.json";
            if (!is_dir(__DIR__ . "/../logs")) {
                mkdir(__DIR__ . "/../logs", 0777, true);
            }
            file_put_contents($filename, json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // 更新数据库中的饰品信息
            if (!empty($response['data'])) {
                foreach ($response['data'] as $item) {
                    $stmt = $this->db->prepare("
                        INSERT INTO skins (name, market_hash_name, updated_at)
                        VALUES (?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                        market_hash_name = VALUES(market_hash_name),
                        updated_at = NOW()
                    ");
                    
                    $stmt->execute([
                        $item['name'],
                        $item['marketHashName']
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => '基础信息已成功获取并保存',
                'saved_file' => $filename
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
} 