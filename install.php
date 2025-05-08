<?php
require_once 'config.php';

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 检查是否需要添加market_hash_name列
    $stmt = $db->query("SHOW COLUMNS FROM skins LIKE 'market_hash_name'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE skins ADD COLUMN market_hash_name VARCHAR(255) AFTER market_price");
        echo "添加market_hash_name列成功！<br>";
    }

    // 创建饰品表
    $db->exec("CREATE TABLE IF NOT EXISTS skins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        inspect_url TEXT,
        purchase_price DECIMAL(10,2),
        purchase_date DATE,
        stickers TEXT,
        market_price DECIMAL(10,2),
        market_hash_name VARCHAR(255),
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // 创建价格历史表
    $db->exec("CREATE TABLE IF NOT EXISTS price_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        skin_id INT,
        price DECIMAL(10,2),
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (skin_id) REFERENCES skins(id)
    )");

    // 创建缓存目录
    if (!file_exists(__DIR__ . '/cache')) {
        mkdir(__DIR__ . '/cache', 0777, true);
    }

    echo "数据库表创建/更新成功！";
    echo "<br><a href='index.php'>返回首页</a>";

} catch(PDOException $e) {
    die("数据库错误: " . $e->getMessage());
}
?> 