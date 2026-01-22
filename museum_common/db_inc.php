<?php
/**
 * データベース接続設定ファイル (db_inc.php)
 * このファイルは他のプログラムから読み込まれて使われます
 */

// --- ここを自分のエックスサーバーの情報に書き換えてください ---
$host = 'mysql8018.xserver.jp'; // MySQLホスト名
$dbname = 'windworks_museumapp';  // データベース名
$user = 'windworks_admin';        // データベースユーザ名
$pass = 'eLAjuGT07vdzz5p#';      // データベースパスワード
// -------------------------------------------------------

try {
    // データベースに接続します
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    
    // エラーが起きた時に詳細を表示する設定
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // データを取得する際の形式を「連想配列」に固定
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // 接続に失敗した場合、エラーを表示して終了します
    die("データベース接続失敗: " . $e->getMessage());
}