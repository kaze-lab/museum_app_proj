-- システム設定テーブルの作成
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY DEFAULT 1,
    deepl_api_key VARCHAR(255) DEFAULT NULL,           -- APIキー
    deepl_api_url VARCHAR(255) DEFAULT 'https://api-free.deepl.com/v2/translate', -- エンドポイント
    app_name VARCHAR(100) DEFAULT '博物館ガイド',      -- アプリ名称
    copyright_display VARCHAR(100) DEFAULT '',         -- 表示用コピーライト名
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初期データの投入（まだデータがない場合のみ実行）
INSERT IGNORE INTO system_settings (id, app_name) VALUES (1, '博物館ガイドシステム');