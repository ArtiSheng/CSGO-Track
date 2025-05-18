<?php

// 检查是否已存在相同名称的饰品
$stmt = $db->prepare("SELECT * FROM skins WHERE name = ?");
$stmt->execute([$name]);
$existingSkin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingSkin) {
    // 如果已存在，更新数量、价格和时间
    $newQuantity = $existingSkin['quantity'] + 1;
    $newPrice = (($existingSkin['price'] * $existingSkin['quantity']) + $price) / $newQuantity;
    $newPurchaseDate = min($existingSkin['purchase_date'], $purchaseDate);
    
    $stmt = $db->prepare("UPDATE skins SET 
        quantity = ?,
        price = ?,
        purchase_date = ?,
        updated_at = NOW()
        WHERE id = ?");
    $stmt->execute([$newQuantity, $newPrice, $newPurchaseDate, $existingSkin['id']]);
    
    echo json_encode(['success' => true, 'message' => '饰品数量已更新']);
} else {
    // 如果不存在，插入新记录
    $stmt = $db->prepare("INSERT INTO skins (name, price, purchase_date, quantity) VALUES (?, ?, ?, 1)");
    $stmt->execute([$name, $price, $purchaseDate]);
    
    echo json_encode(['success' => true, 'message' => '饰品添加成功']);
} 