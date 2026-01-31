-- 1. 古いテーブルを一旦削除する
DROP TABLE IF EXISTS exhibit_translations; -- もし存在していれば先に削除
DROP TABLE IF EXISTS exhibits;

-- 2. 新しい仕様（日本語・英語・中国語が並んだ構造）で作成する
CREATE TABLE exhibits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    museum_id INT NOT NULL,                -- 博物館との内部紐付け用
    e_code VARCHAR(10) NOT NULL,          -- 展示物コード(0001等)
    title_ja VARCHAR(255) NOT NULL,       -- 展示物名（日本語）
    title_en VARCHAR(255),                 -- 展示物名（英語）
    title_zh VARCHAR(255),                 -- 展示物名（中国語）
    desc_ja TEXT,                          -- 解説（日本語）
    desc_en TEXT,                          -- 解説（英語）
    desc_zh TEXT,                          -- 解説（中国語）
    image_path VARCHAR(255),               -- 画像パス
    status ENUM('public', 'private') DEFAULT 'private',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (museum_id) REFERENCES museums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;