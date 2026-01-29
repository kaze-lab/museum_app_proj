<?php
require_once('../common/db_inc.php');
session_start();

// 1. ログインチェック
if (!isset($_SESSION['admin_logged_in'])) {
	header('Location: login.php');
	exit;
}

$admin_id = $_SESSION['admin_id'];
$museum_id = $_GET['id'] ?? null;

if (!$museum_id) {
	header('Location: index.php');
	exit;
}

// 2. 権限チェック（IDトラバーサル対策）
// 今ログインしている人が、この博物館を管理する権利を持っているかDBに問い合わせる
$sql = "
	SELECT 
		m.name_ja, 
		amp.role 
	FROM 
		admin_museum_permissions amp
	JOIN 
		museums m ON amp.museum_id = m.id
	WHERE 
		amp.admin_id = ? 
		AND amp.museum_id = ? 
		AND m.deleted_at IS NULL
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$admin_id, $museum_id]);
$permission = $stmt->fetch();

// 権限がない、または博物館が存在しない場合はダッシュボードへ戻す
if (!$permission) {
	header('Location: index.php');
	exit;
}

// 役割を変数に格納 (admin か editor)
$my_role = $permission['role'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title><?= htmlspecialchars($permission['name_ja']) ?> 管理 - 博物館ガイド</title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; --border-color: #e9ecef; }
		body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; color: #333; }
		
		header { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
		.btn-back { text-decoration: none; color: #666; font-size: 0.9rem; }
		
		.container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
		.museum-header { margin-bottom: 40px; }
		.museum-header h1 { margin: 0; font-size: 1.8rem; color: #333; }
		.role-indicator { display: inline-block; margin-top: 10px; padding: 4px 12px; border-radius: 15px; font-size: 0.8rem; background: #eee; color: #666; font-weight: bold; }

		/* メニュータイル */
		.menu-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
		.menu-item { background: white; border-radius: 20px; padding: 30px; text-decoration: none; color: inherit; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: 0.3s; border: 1px solid transparent; }
		.menu-item:hover { transform: translateY(-3px); border-color: var(--primary-color); }
		.menu-item h3 { margin: 0 0 10px 0; color: #333; }
		.menu-item p { margin: 0; font-size: 0.85rem; color: #888; line-height: 1.5; }
		
		/* 権限による制限スタイル */
		.disabled-item { background: #fdfdfd; opacity: 0.6; cursor: not-allowed; }
		.disabled-item:hover { transform: none; border-color: transparent; }
		.lock-icon { font-size: 0.7rem; background: #ddd; color: #fff; padding: 2px 6px; border-radius: 4px; margin-left: 5px; }
	</style>
</head>
<body>

<header>
	<a href="index.php" class="btn-back">← 博物館一覧に戻る</a>
	<div class="user-info" style="font-size:0.85rem; color:#888;">
		ログイン中: <?= htmlspecialchars($_SESSION['admin_name']) ?>
	</div>
</header>

<div class="container">
	<div class="museum-header">
		<h1><?= htmlspecialchars($permission['name_ja']) ?></h1>
		<div class="role-indicator">
			あなたの権限: <?= $my_role === 'admin' ? '博物館管理者' : 'データ編集者' ?>
		</div>
	</div>

	<div class="menu-grid">
		<!-- 1. プロフィール編集 (全権限共通) -->
		<a href="edit_profile.php?id=<?= $museum_id ?>" class="menu-item">
			<h3>基本情報の編集</h3>
			<p>住所、電話番号、公式サイトURL、紹介文などの情報を変更します。</p>
		</a>

		<!-- 2. 展示物管理 (全権限共通) -->
		<a href="exhibits.php?id=<?= $museum_id ?>" class="menu-item">
			<h3>展示物の管理</h3>
			<p>展示品の登録、編集、公開・非公開の切り替えを行います。</p>
		</a>

		<!-- 3. スタッフ管理 (adminロールのみ) -->
		<?php if ($my_role === 'admin'): ?>
			<a href="staff.php?id=<?= $museum_id ?>" class="menu-item">
				<h3>スタッフ管理</h3>
				<p>この博物館を担当する「管理者」や「データ編集者」を追加・削除します。</p>
			</a>
		<?php else: ?>
			<div class="menu-item disabled-item">
				<h3>スタッフ管理 <span class="lock-icon">制限中</span></h3>
				<p>スタッフの管理権限はありません。博物館責任者にお問い合わせください。</p>
			</div>
		<?php endif; ?>
	</div>
</div>

</body>
</html>