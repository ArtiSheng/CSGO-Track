-- 向skins表添加sort_order字段
ALTER TABLE skins ADD COLUMN sort_order INT DEFAULT 0 COMMENT '排序顺序';

-- 初始化现有数据的排序值（使用ID作为初始排序）
UPDATE skins SET sort_order = id; 