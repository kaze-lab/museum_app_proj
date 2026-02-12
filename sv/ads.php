<?php
// /sv/ads.php
require_once('../common/db_inc.php');
session_start();

if (!isset($_SESSION['sv_logged_in'])) { header('Location: login.php'); exit; }

// 削除処理
if (isset($_GET['delete'])) {
	$id = (int)$_GET['delete'];
	// 画像ファイルを消すため一旦取得
	$st = $pdo->prepare("SELECT image_path FROM ads WHERE id = ?");
	$st->execute([$id]);
	$img = $st->fetchColumn();
	if ($img && file_exists("../" . $img)) unlink("../" . $img);

	$pdo->prepare("DELETE FROM ads WHERE id = ?")->execute([$id]);
	header("Location: ads.php?msg=deleted");
	exit;
}

$ads = $pdo->query("SELECT * FROM ads ORDER BY sort_order ASC, id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>共通広告管理 - SV</title>
	<style>
		:root { --primary-color: #34495e; --accent-color: #26b396; --bg-color: #f4f7f7; --border-color: #e9ecef; }
		body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; padding: 40px 0; color: #333; }
		.container { max-width: 1000px; margin: auto; padding: 0 20px; }
		.card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
		.header-flex { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 20px; margin-bottom: 25px; }
		h2 { margin: 0; color: var(--primary-color); }
		
		.btn { text-decoration: none; padding: 10px 20px; border-radius: 25px; font-weight: bold; font-size: 0.9rem; cursor: pointer; display: inline-block; border: 1px solid; }
		.btn-primary { background: var(--primary-color); color: white; border-color: var(--primary-color); }
		.btn-outline { background: white; color: #666; border-color: #ddd; }

		.ad-table { width: 100%; border-collapse: collapse; }
		.ad-table th, .ad-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
		.ad-table th { background: #fcfcfc; font-size: 0.8rem; color: #888; }
		
		.ad-thumb { width: 120px; height: 60px; object-fit: cover; border-radius: 6px; background: #eee; border: 1px solid #eee; }
		.status-badge { padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; }
		.status-on { background: #e6fff0; color: #1e7e34; }
		.status-off { background: #f0f0f0; color: #999; }
	</style>
</head>
<body>
<div class="container">
	<div class="card">
		<div class="header-flex">
			<h2>📢 システム共通広告の管理</h2>
			<div>
				<a href="index.php" class="btn btn-outline" style="margin-right:10px;">一覧に戻る</a>
				<a href="ad_edit.php" class="btn btn-primary">+ 新規広告登録</a>
			</div>
		</div>

		<?php if(isset($_GET['msg'])): ?>
			<div style="background:#e6fff0; color:#1e7e34; padding:15px; border-radius:10px; margin-bottom:20px; font-size:0.9rem;">
				処理が完了しました。
			</div>
		<?php endif; ?>

		<table class="ad-table">
			<thead>
				<tr>
					<th style="width:140px;">バナー</th>
					<th>広告タイトル / リンクURL</th>
					<th style="width:80px;">順序</th>
					<th style="width:80px;">状態</th>
					<th style="width:150px; text-align:right;">操作</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($ads as $ad): ?>
				<tr>
					<td><img src="../<?= htmlspecialchars($ad['image_path']) ?>" class="ad-thumb"></td>
					<td>
						<div style="font-weight:bold; margin-bottom:5px;"><?= htmlspecialchars($ad['title']) ?></div>
						<div style="font-size:0.75rem; color:#888;"><?= htmlspecialchars($ad['link_url']) ?></div>
					</td>
					<td><?= $ad['sort_order'] ?></td>
					<td>
						<span class="status-badge <?= $ad['is_active'] ? 'status-on' : 'status-off' ?>">
							<?= $ad['is_active'] ? '配信中' : '停止' ?>
						</span>
					</td>
					<td style="text-align:right;">
						<a href="ad_edit.php?id=<?= $ad['id'] ?>" class="btn btn-outline" style="font-size:0.75rem; padding:5px 12px;">編集</a>
						<a href="?delete=<?= $ad['id'] ?>" class="btn btn-outline" style="font-size:0.75rem; padding:5px 12px; color:#d00;" onclick="return confirm('削除しますか？')">削除</a>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php if(empty($ads)): ?>
					<tr><td colspan="5" style="text-align:center; padding:60px; color:#aaa;">登録された広告はありません。</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
</body>
</html>