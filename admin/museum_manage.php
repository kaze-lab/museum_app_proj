<?php
// /admin/museum_manage.php
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

// 2. 権限チェック
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

if (!$permission) {
	header('Location: index.php');
	exit;
}

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
		.museum-header { margin-bottom: 30px; }
		.museum-header h1 { margin: 0; font-size: 1.8rem; color: #333; }
		.role-indicator { display: inline-block; margin-top: 10px; padding: 4px 12px; border-radius: 15px; font-size: 0.8rem; background: #eee; color: #666; font-weight: bold; }
		.alert { background: #e6fff0; color: #1e7e34; padding: 15px 20px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #c3e6cb; font-weight: bold; font-size: 0.9rem; }

		/* メニュータイル */
		.menu-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
		.menu-item { background: white; border-radius: 20px; padding: 30px; text-decoration: none; color: inherit; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: 0.3s; border: 1px solid transparent; }
		.menu-item:hover { transform: translateY(-3px); border-color: var(--primary-color); }
		.menu-item h3 { margin: 0 0 10px 0; color: #333; }
		.menu-item p { margin: 0; font-size: 0.85rem; color: #888; line-height: 1.5; }
		
		.disabled-item { background: #fdfdfd; opacity: 0.6; cursor: not-allowed; }
		.lock-icon { font-size: 0.7rem; background: #ddd; color: #fff; padding: 2px 6px; border-radius: 4px; margin-left: 5px; }
	</style>
</head>
<body>

<header>
	<a href="index.php" class="btn-back">← 博物館一覧に戻る</a>
	<div class="user-info" style="font-size:0.85rem; color:#888;">ログイン中: <?= htmlspecialchars($_SESSION['admin_name'] ?? '管理者') ?></div>
</header>

<div class="container">
	<div class="museum-header">
		<h1><?= htmlspecialchars($permission['name_ja']) ?></h1>
		<div class="role-indicator">あなたの権限: <?= $my_role === 'admin' ? '博物館管理者' : 'データ編集者' ?></div>
	</div>

	<?php if (isset($_GET['msg'])): ?>
		<div class="alert">
			<?php
				if($_GET['msg'] === 'profile_updated') echo "✓ 博物館の基本情報を更新しました。";
				if($_GET['msg'] === 'staff_updated') echo "✓ スタッフ情報を更新しました。";
			?>
		</div>
	<?php endif; ?>

	<div class="menu-grid">
		<a href="edit_profile.php?id=<?= $museum_id ?>" class="menu-item">
			<h3>基本情報の編集</h3>
			<p>住所、電話番号、公式サイトURL、紹介文などの情報を変更します。</p>
		</a>

		<a href="exhibits.php?id=<?= $museum_id ?>" class="menu-item">
			<h3>展示物の管理</h3>
			<p>展示品の登録、編集、公開・非公開の切り替え、QRコード印刷を行います。</p>
		</a>

		<!-- ★追加：館内掲示用QRコードのボタン -->
		<a href="museum_qr_print.php?m_id=<?= $museum_id ?>" class="menu-item" target="_blank">
			<h3>館内掲示用QRコード</h3>
			<p>入り口や受付に掲示する、この博物館専用トップページのQRコードを発行します。</p>
		</a>

		<?php if ($my_role === 'admin'): ?>
			<a href="staff.php?id=<?= $museum_id ?>" class="menu-item">
				<h3>スタッフ管理</h3>
				<p>この博物館を担当する「管理者」や「データ編集者」を追加・削除します。</p>
			</a>
		<?php else: ?>
			<div class="menu-item disabled-item">
				<h3>スタッフ管理 <span class="lock-icon">制限中</span></h3>
				<p>スタッフの管理権限はありません。</p>
			</div>
		<?php endif; ?>
	</div>
</div>

</body>
</html>