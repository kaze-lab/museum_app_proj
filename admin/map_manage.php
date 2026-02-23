<?php
// /admin/map_manage.php
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
$sql_p = "SELECT m.name_ja FROM admin_museum_permissions amp JOIN museums m ON amp.museum_id = m.id WHERE amp.admin_id = ? AND amp.museum_id = ? AND m.deleted_at IS NULL";
$stmt_p = $pdo->prepare($sql_p);
$stmt_p->execute([$admin_id, (int)$museum_id]);
$permission = $stmt_p->fetch();

if (!$permission) {
	header('Location: index.php');
	exit;
}

$error_msg = "";
$success_msg = "";

// 3. 登録処理（1枚の全体図として登録）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
	if (empty($_FILES['map_image']['name'])) {
		$error_msg = "画像を選択してください。";
	} else {
		// 保存ディレクトリの準備
		$dir = "../uploads/museums/{$museum_id}/maps/";
		$image_path = saveImageAsWebP($_FILES['map_image'], $dir, 'overall_');

		if ($image_path) {
			// 今回は「全体図1枚」の仕様なので、既存のマップ情報を一度クリア（または1件のみ保持）する運用
			// 複数の登録を許容しつつ、アプリ側では最新の1枚を使う形にするため、そのままINSERTします
			$sql = "INSERT INTO museum_maps (museum_id, floor_name_ja, image_path, sort_order) VALUES (?, '館内全体図', ?, 0)";
			$stmt = $pdo->prepare($sql);
			$stmt->execute([$museum_id, $image_path]);
			$success_msg = "館内マップを登録しました。";
		} else {
			$error_msg = "画像の保存に失敗しました。";
		}
	}
}

// 4. 削除処理
if (isset($_GET['delete'])) {
	$map_id = (int)$_GET['delete'];
	$stmt = $pdo->prepare("SELECT image_path FROM museum_maps WHERE id = ? AND museum_id = ?");
	$stmt->execute([$map_id, (int)$museum_id]);
	$map = $stmt->fetch();
	
	if ($map) {
		if (file_exists('../' . $map['image_path'])) unlink('../' . $map['image_path']);
		$pdo->prepare("DELETE FROM museum_maps WHERE id = ?")->execute([$map_id]);
		header("Location: map_manage.php?id=$museum_id&msg=deleted");
		exit;
	}
}

// 5. 登録済みマップの取得（最新のものを上に）
$stmt_list = $pdo->prepare("SELECT * FROM museum_maps WHERE museum_id = ? ORDER BY id DESC");
$stmt_list->execute([(int)$museum_id]);
$map_list = $stmt_list->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>フロアマップ管理 - <?= htmlspecialchars($permission['name_ja']) ?></title>
	<style>
		/* index.php と完全に統一したスタイル */
		* { box-sizing: border-box; }
		:root { 
			--primary-color: #26b396; 
			--bg-color: #f4f7f7; 
			--border-color: #e9ecef; 
		}
		
		body { 
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
			background-color: var(--bg-color); 
			margin: 0; 
			color: #333; 
		}

		.inner-wrapper {
			max-width: 1100px;
			margin: 0 auto;
			padding: 0 40px;
		}
		
		header { 
			background: white; 
			box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
			width: 100%;
		}

		.header-content {
			display: flex; 
			justify-content: space-between; 
			align-items: center; 
			height: 70px;
		}
		
		.btn-back { text-decoration: none; color: #666; font-size: 0.9rem; }
		.btn-back:hover { color: var(--primary-color); }

		.container { 
			margin-top: 50px; 
			margin-bottom: 50px;
		}
		
		.card { 
			background: white; 
			border-radius: 20px; 
			padding: 40px; 
			box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
			margin-bottom: 30px; 
		}

		h1 { font-size: 1.6rem; margin: 0 0 10px 0; color: #333; }
		.sub-text { color: #888; font-size: 0.9rem; margin-bottom: 30px; }

		/* アップロードエリア */
		.upload-box {
			background: #f9f9f9;
			border: 2px dashed #ddd;
			border-radius: 15px;
			padding: 40px;
			text-align: center;
			margin-bottom: 20px;
		}
		
		.btn { 
			padding: 12px 35px; 
			border-radius: 30px; 
			font-weight: bold; 
			cursor: pointer; 
			border: none; 
			font-size: 1rem; 
			transition: 0.2s;
			display: inline-block;
			text-decoration: none;
		}
		.btn-primary { background: var(--primary-color); color: white; }
		.btn-primary:hover { opacity: 0.9; }

		/* 登録済みマップの表示 */
		.map-preview-card {
			display: flex;
			gap: 30px;
			align-items: flex-start;
			border-top: 1px solid #eee;
			padding-top: 30px;
			margin-top: 30px;
		}
		
		.map-img-container {
			flex: 0 0 400px;
		}
		
		.map-img-container img {
			width: 100%;
			height: auto;
			border-radius: 12px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			border: 1px solid #eee;
		}
		
		.map-details {
			flex: 1;
		}
		
		.map-details h3 { margin: 0 0 10px 0; font-size: 1.2rem; }
		.map-details p { color: #888; font-size: 0.85rem; line-height: 1.6; }

		.btn-delete { color: #d00; text-decoration: none; font-weight: bold; font-size: 0.85rem; }
		.btn-delete:hover { text-decoration: underline; }

		.alert { 
			padding: 15px 20px; 
			border-radius: 12px; 
			margin-bottom: 30px; 
			font-size: 0.9rem; 
			font-weight: bold;
		}
		.alert-error { background: #fff0f0; color: #d00; border: 1px solid #fecaca; }
		.alert-success { background: #e6fff0; color: #1e7e34; border: 1px solid #c3e6cb; }

		@media (max-width: 800px) {
			.map-preview-card { flex-direction: column; }
			.map-img-container { flex: none; width: 100%; }
		}
	</style>
</head>
<body>

<header>
	<div class="inner-wrapper header-content">
		<a href="museum_manage.php?id=<?= $museum_id ?>" class="btn-back">← 博物館管理に戻る</a>
		<div style="font-size:0.85rem; color:#888;">館内図の設定</div>
	</div>
</header>

<div class="inner-wrapper container">
	<div class="card">
		<h1>館内図（全体マップ）の管理</h1>
		<p class="sub-text">
			博物館の全体構造がわかる案内図を1枚登録してください。<br>
			来館者はこの画像を自由に拡大・縮小して、トイレや各施設の場所を確認できます。
		</p>

		<?php if($error_msg): ?><div class="alert alert-error"><?= $error_msg ?></div><?php endif; ?>
		<?php if($success_msg || isset($_GET['msg'])): ?><div class="alert alert-success"><?= $success_msg ?: '削除が完了しました。' ?></div><?php endif; ?>

		<form method="POST" enctype="multipart/form-data">
			<input type="hidden" name="action" value="upload">
			<div class="upload-box">
				<label style="display:block; margin-bottom:15px; font-weight:bold; color:#555;">画像を選択 (PNG, JPG, WebP)</label>
				<input type="file" name="map_image" accept="image/*" style="margin-bottom:20px;">
				<br>
				<button type="submit" class="btn btn-primary">画像をアップロードする</button>
			</div>
		</form>

		<?php if (count($map_list) > 0): ?>
			<div class="map-preview-card">
				<div class="map-img-container">
					<img src="../<?= htmlspecialchars($map_list[0]['image_path']) ?>" alt="館内図">
				</div>
				<div class="map-details">
					<h3>現在の館内図</h3>
					<p>
						ファイル: <?= basename($map_list[0]['image_path']) ?><br>
						登録日: <?= $map_list[0]['created_at'] ?>
					</p>
					<a href="?id=<?= $museum_id ?>&delete=<?= $map_list[0]['id'] ?>" class="btn-delete" onclick="return confirm('館内図を削除しますか？')">この画像を削除する</a>
				</div>
			</div>
		<?php else: ?>
			<div style="text-align:center; padding:40px; color:#ccc;">
				館内図が登録されていません。上のフォームからアップロードしてください。
			</div>
		<?php endif; ?>
	</div>
</div>

</body>
</html>