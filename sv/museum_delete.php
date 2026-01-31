<?php
require_once('../common/db_inc.php');
session_start();

// スーパーバイザーの認証チェック
if (!isset($_SESSION['sv_logged_in'])) {
	header('Location: login.php');
	exit;
}

$id = $_GET['id'] ?? null;

if ($id) {
	try {
		// 論理削除（deleted_atに現在時刻をセット）
		$stmt = $pdo->prepare("UPDATE museums SET deleted_at = NOW() WHERE id = ?");
		$stmt->execute([$id]);

		// 一覧へリダイレクト（メッセージ: trashed）
		header("Location: index.php?msg=trashed");
		exit;

	} catch (PDOException $e) {
		// エラー時は一覧に戻す
		header("Location: index.php?msg=error");
		exit;
	}
} else {
	header('Location: index.php');
	exit;
}