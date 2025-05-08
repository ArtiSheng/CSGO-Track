<?php
/**
 * 检视图API测试脚本 - 命令行版本
 * 使用方法：php test_inspect_cli.php "检视链接"
 * 例如：php test_inspect_cli.php "steam://rungame/730/76561202255233023/+csgo_econ_action_preview%20S76561198082398031A29193125493D5227599390355187286"
 */

// 设置显示所有错误
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 包含必要文件
require_once 'config.php';
require_once 'includes/SteamDTAPI.php';

// 检查命令行参数
if ($argc < 2) {
    echo "错误: 请提供检视链接作为参数\n";
    echo "用法: php test_inspect_cli.php \"检视链接\"\n";
    exit(1);
}

// 获取检视链接参数
$inspectUrl = $argv[1];
echo "测试链接: " . $inspectUrl . "\n\n";

// 初始化API
$api = new SteamDTAPI();

// 发送原始curl请求，不通过类方法，以查看最原始的响应
echo "=== 发送原始curl请求 ===\n";
$url = API_BASE_URL . '/open/cs2/v1/inspect/screenshot';
$data = ['inspectUrl' => $inspectUrl];

echo "API URL: " . $url . "\n";
echo "请求数据: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . STEAMDT_API_KEY
]);

// 启用详细输出，显示请求头和响应头
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

// 发送请求
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// 获取详细信息
rewind($verbose);
$verboseLog = stream_get_contents($verbose);
echo "CURL详细日志:\n" . $verboseLog . "\n";

echo "HTTP状态码: " . $httpCode . "\n";

if ($response === false) {
    echo "CURL错误: " . curl_error($ch) . "\n";
} else {
    echo "原始响应 (简短版):\n" . substr($response, 0, 1000) . "...\n\n";
    
    // 尝试解析JSON
    $result = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "解析后的响应 (结构):\n";
        // 输出响应结构而不是完整内容（可能包含大量图片URL）
        $structure = analyzeStructure($result);
        echo json_encode($structure, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
    } else {
        echo "JSON解析错误: " . json_last_error_msg() . "\n\n";
    }
}
curl_close($ch);

// 通过类方法获取检视图
echo "=== 通过SteamDTAPI类方法获取检视图 ===\n";
$inspectResult = $api->getInspectImages($inspectUrl);
echo "API返回结果 (结构):\n";

// 分析并输出结果结构而不是完整内容
$structure = analyzeStructure($inspectResult);
echo json_encode($structure, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

if ($inspectResult['success']) {
    echo "检视图获取成功!\n";
    
    // 检查是否是异步处理
    if (isset($inspectResult['data']['sync']) && !$inspectResult['data']['sync']) {
        echo "异步处理中，需要稍后查询，任务ID: " . ($inspectResult['data']['taskId'] ?? '未知') . "\n";
    } else {
        // 尝试提取并显示图片URL
        $screenshots = $inspectResult['data']['screenshot']['screenshots'] ?? null;
        if ($screenshots) {
            echo "找到以下预览图:\n";
            if (isset($screenshots['front'][0])) {
                echo "- 正面图: " . substr($screenshots['front'][0], 0, 50) . "...\n";
            }
            if (isset($screenshots['back'][0])) {
                echo "- 背面图: " . substr($screenshots['back'][0], 0, 50) . "...\n";
            }
            if (isset($screenshots['detail'][0])) {
                echo "- 细节图: " . substr($screenshots['detail'][0], 0, 50) . "...\n";
            }
        } else {
            echo "未找到预览图信息\n";
        }
    }
} else {
    echo "检视图获取失败!\n";
    echo "错误信息: " . ($inspectResult['errorMsg'] ?? '未知错误') . "\n";
}

// 输出配置信息（不包含敏感信息）
echo "\n=== 配置信息 ===\n";
echo "API基础URL: " . API_BASE_URL . "\n";
echo "API KEY: " . substr(STEAMDT_API_KEY, 0, 5) . '...' . substr(STEAMDT_API_KEY, -5) . "\n";

/**
 * 分析数据结构，将实际值替换为类型描述
 */
function analyzeStructure($data) {
    if (is_array($data)) {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = analyzeStructure($value);
            } else if (is_string($value) && strlen($value) > 50) {
                $result[$key] = "string[" . strlen($value) . "]";
            } else {
                $result[$key] = gettype($value);
            }
        }
        return $result;
    }
    return gettype($data);
}
?> 