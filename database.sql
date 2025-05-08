CREATE DATABASE IF NOT EXISTS csgo_skins;
USE csgo_skins;

CREATE TABLE IF NOT EXISTS skins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    inspect_url TEXT,
    purchase_price DECIMAL(10,2),
    purchase_date DATE,
    stickers TEXT,
    market_price DECIMAL(10,2),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    skin_id INT,
    price DECIMAL(10,2),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (skin_id) REFERENCES skins(id)
); 