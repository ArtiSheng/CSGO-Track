<?php

require_once 'SteamDTAPI.php';
require_once __DIR__ . '/../config.php';

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
            // 使用SteamDTAPI类中已有的getBaseInfo方法替代makeRequest
            $response = $this->api->getBaseInfo();
            
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

            // 更新数据库中的skin_names表
            if (!empty($response['data'])) {
                foreach ($response['data'] as $item) {
                    $stmt = $this->db->prepare("
                        INSERT INTO skin_names (name, marketHashName, updated_at)
                        VALUES (?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                        marketHashName = VALUES(marketHashName),
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