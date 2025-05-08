<?php
require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

try {
    $db->beginTransaction();
    
    $stmt = $db->prepare("UPDATE skins SET sort_order = :order WHERE id = :id");
    
    foreach ($data as $item) {
        $stmt->execute([
            ':order' => $item['order'],
            ':id' => $item['id']
        ]);
    }
    
    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 