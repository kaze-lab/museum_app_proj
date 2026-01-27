-- =========== ステップ1：既存テーブルの削除 ===========
DROP TABLE IF EXISTS `admin_museum_permissions`;
DROP TABLE IF EXISTS `museum_admins`;
DROP TABLE IF EXISTS `museums`;
DROP TABLE IF EXISTS `categories`;


-- =========== ステップ2：カテゴリテーブルの作成 ===========
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `categories` (`name`) VALUES
('総合博物館'), ('科学博物館'), ('歴史博物館'), ('美術館'), ('水族館'), ('動物園'), ('その他');


-- =========== ステップ3：博物館マスタテーブルの作成 ===========
CREATE TABLE `museums` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '内部ID (連番)',
  `m_code` varchar(255) NOT NULL COMMENT '人間が識別するユニークなコード',
  `name_ja` varchar(255) NOT NULL COMMENT '博物館名',
  `name_kana` varchar(255) NOT NULL COMMENT 'ソート用の読み仮名',
  `category_id` int(11) DEFAULT NULL COMMENT 'categoriesテーブルのID',
  `address` varchar(255) DEFAULT NULL COMMENT '所在地',
  `phone_number` varchar(20) DEFAULT NULL COMMENT '電話番号',
  `website_url` varchar(255) DEFAULT NULL COMMENT '公式サイトURL',
  `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT '公開ステータス (1:有効, 0:無効)',
  `notes` text DEFAULT NULL COMMENT 'スーパーバイザー用の備考欄',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '登録日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `m_code` (`m_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========== ステップ4：博物館管理者アカウントテーブルの作成 ===========
CREATE TABLE `museum_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '管理者名',
  `email` varchar(255) NOT NULL COMMENT 'ログインID',
  `password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========== ステップ5：管理者と博物館の権限紐付けテーブルの作成 ===========
CREATE TABLE `admin_museum_permissions` (
  `admin_id` int(11) NOT NULL COMMENT 'museum_adminsのID',
  `museum_id` int(11) NOT NULL COMMENT 'museumsのID',
  PRIMARY KEY (`admin_id`,`museum_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;