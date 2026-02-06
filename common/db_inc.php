<?php
/**
 * データベース接続設定ファイル (db_inc.php)
 * このファイルは他のプログラムから読み込まれて使われます
 */

// --- データベース接続情報 ---
$host = 'mysql8018.xserver.jp'; 
$dbname = 'windworks_museumapp';  
$user = 'windworks_admin';		  
$pass = 'eLAjuGT07vdzz5p#'; 	 

try {
	$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
	die("データベース接続失敗: " . $e->getMessage());
}

/**
 * 【追加機能】アップロードされた画像をWebPに変換・リサイズして保存する
 */
function saveImageAsWebP($file, $dir, $prefix = '', $max_width = 1200) {
	if (empty($file['tmp_name'])) return false;

	// 保存先フォルダの準備
	if (!is_dir($dir)) mkdir($dir, 0777, true);

	$info = getimagesize($file['tmp_name']);
	if (!$info) return false;
	$orig_w = $info[0];
	$orig_h = $info[1];
	$type	= $info[2];

	// 画像の読み込み
	switch ($type) {
		case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($file['tmp_name']); break;
		case IMAGETYPE_PNG:  $source = imagecreatefrompng($file['tmp_name']); break;
		case IMAGETYPE_WEBP: $source = imagecreatefromwebp($file['tmp_name']); break;
		default: return false;
	}

	// リサイズ計算（横幅を最大1200pxに）
	$new_w = $orig_w;
	$new_h = $orig_h;
	if ($orig_w > $max_width) {
		$new_w = $max_width;
		$new_h = floor($orig_h * ($max_width / $orig_w));
	}

	// WebPへの変換と保存
	$canvas = imagecreatetruecolor($new_w, $new_h);
	imagealphablending($canvas, false);
	imagesavealpha($canvas, true);
	imagecopyresampled($canvas, $source, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);

	$filename = $prefix . bin2hex(random_bytes(8)) . '.webp';
	$dest_path = rtrim($dir, '/') . '/' . $filename;
	
	imagewebp($canvas, $dest_path, 80); // 画質80%で保存

	imagedestroy($source);
	imagedestroy($canvas);

	// 呼び出し元の画面で使いやすいよう、相対パスを返す
	return str_replace('../', '', $dest_path);
}