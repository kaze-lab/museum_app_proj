<?php
// このヘッダーを読み込む全てのページでセッションを開始
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// ログインチェック
if (!isset($_SESSION['sv_logged_in'])) {
	header('Location: login.php');
	exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; --border-color: #e9ecef;}
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
			background-color: var(--bg-color);
			margin: 0;
			color: #333;
		}
		.header {
			background: white;
			padding: 0 30px;
			display: flex;
			justify-content: space-between;
			align-items: center;
			border-bottom: 1px solid var(--border-color);
			height: 60px;
		}
		.header-logo { font-size: 1.2em; font-weight: bold; }
		.header-nav a {
			text-decoration: none;
			color: #555;
			margin-left: 20px;
			font-size: 0.9em;
		}
		.header-nav a:hover { color: var(--primary-color); }
		.container {
			max-width: 900px;
			margin: 40px auto;
			padding: 0 20px;
		}
	</style>
</head>
<body>

<header class="header">
	<div class="header-logo">博物館ガイド</div>
	<nav class="header-nav">
		<span>こんにちは、<?= htmlspecialchars($_SESSION['sv_name']) ?> さん</span>
		<a href="account.php">アカウント情報</a>
		<a href="logout.php">ログアウト</a>
	</nav>
</header>