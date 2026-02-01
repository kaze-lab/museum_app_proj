<?php
// /admin/translate_ajax.php
require_once('../common/db_inc.php');
session_start();

// 1. 認証チェック
if (!isset($_SESSION['admin_logged_in'])) {
	echo json_encode(['error' => 'セッション切れです。']);
	exit;
}

// 2. パラメータ取得
$text = $_POST['text'] ?? '';
$target_lang = $_POST['target_lang'] ?? '';

if (empty($text) || empty($target_lang)) {
	echo json_encode(['error' => '翻訳対象がありません。']);
	exit;
}

// 3. 設定取得
$stmt = $pdo->query("SELECT deepl_api_key, deepl_api_url FROM system_settings WHERE id = 1");
$settings = $stmt->fetch();

$api_key = trim($settings['deepl_api_key'] ?? '');
$url	 = trim($settings['deepl_api_url'] ?? '');

// 4. 言語コードの調整
$deepl_lang = ($target_lang === 'ZH') ? 'ZH' : $target_lang;
if ($target_lang === 'EN') $deepl_lang = 'EN-US';

// 5. 送信データの準備
// 【修正ポイント】text を配列 [ ] ではなく、単純な文字列として設定します
$data = [
	'text' => $text, 
	'target_lang' => $deepl_lang,
	'source_lang' => 'JA'
];

// 6. cURL実行（最新のヘッダー認証方式）
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

// 最新の認証ルール：ヘッダーにキーを記載
$headers = [
	'Authorization: DeepL-Auth-Key ' . $api_key,
	'Content-Type: application/x-www-form-urlencoded'
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 7. 結果判定
if ($http_code === 200) {
	$result = json_decode($response, true);
	if (isset($result['translations'][0]['text'])) {
		echo json_encode(['translated_text' => $result['translations'][0]['text']]);
	} else {
		echo json_encode(['error' => '翻訳結果の解析に失敗しました。']);
	}
} else {
	echo json_encode(['error' => "DeepL Error ($http_code): " . $response]);
}