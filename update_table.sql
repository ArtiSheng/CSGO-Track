-- 创建存储API响应的表
CREATE TABLE IF NOT EXISTS skin_names (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL COMMENT '饰品中文名称',
    marketHashName VARCHAR(191) NOT NULL COMMENT '市场Hash名称',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name),
    UNIQUE KEY unique_hash_name (marketHashName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='饰品名称对照表';

-- 创建饰品表（用于存储用户添加的饰品）
CREATE TABLE IF NOT EXISTS skins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL COMMENT '饰品名称',
    marketHashName VARCHAR(191) NOT NULL COMMENT '市场Hash名称',
    purchase_price DECIMAL(10,2) DEFAULT 0.00 COMMENT '购入价格',
    market_price DECIMAL(10,2) DEFAULT 0.00 COMMENT '市场价格',
    purchase_date DATE DEFAULT NULL COMMENT '购入日期',
    stickers TEXT DEFAULT NULL COMMENT '贴纸信息(JSON)',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '最后更新时间',
    price_platform VARCHAR(50) DEFAULT NULL COMMENT '价格平台',
    INDEX idx_marketHashName (marketHashName(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户添加的饰品表';

-- 创建价格历史表
CREATE TABLE IF NOT EXISTS price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    skin_id INT NOT NULL COMMENT '饰品ID',
    price DECIMAL(10,2) NOT NULL COMMENT '价格',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '记录时间',
    FOREIGN KEY (skin_id) REFERENCES skins(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='价格历史记录表'; 