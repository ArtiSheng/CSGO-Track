<?php
require_once 'config.php';

// 测试用的检视链接
$inspectUrl = "steam://rungame/730/76561202255233023/+csgo_econ_action_preview%20S76561199754085438A42893041351D17026534067577471989";

// API配置
$apiKey = STEAMDT_API_KEY;
$baseUrl = API_BASE_URL;

echo "开始测试磨损度API...\n\n";

// 准备请求数据
$url = $baseUrl . '/open/cs2/v1/wear';
$data = [
    'inspectUrl' => $inspectUrl
];

echo "请求URL: " . $url . "\n";
echo "请求数据: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

// 发起请求
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);

// 输出完整请求信息
echo "请求头: \n";
echo "Content-Type: application/json\n";
echo "Authorization: Bearer " . $apiKey . "\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP状态码: " . $httpCode . "\n\n";

if ($response === false) {
    echo "CURL错误: " . curl_error($ch) . "\n";
} else {
    echo "原始响应: \n" . $response . "\n\n";
    
    $result = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "解析后的响应: \n" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "JSON解析错误: " . json_last_error_msg() . "\n";
    }
}

curl_close($ch); 