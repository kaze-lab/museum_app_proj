<?php
require_once('../common/db_inc.php');
session_start();

if (!isset($_SESSION['sv_logged_in'])) {
	header('Location: login.php');
	exit;
}

// --- ヘルパー関数：孤立した管理者を削除する ---
function cleanupOrphanedAdmins($pdo) {
	// どの博物館にも紐付いていない管理者を一括削除
	$sql = "DELETE FROM museum_admins 
			WHERE id NOT IN (SELECT DISTINCT admin_id FROM admin_museum_permissions)";
	$pdo->query($sql);
}

// --- 1. 30日経過したデータの自動完全消去 (30日ルール) ---
try {
	$pdo->beginTransaction();
	$st_expired = $pdo->query("SELECT id FROM museums WHERE deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
	$expired_ids = $st_expired->fetchAll(PDO::FETCH_COLUMN);

	if ($expired_ids) {
		$placeholders = implode(',', array_fill(0, count($expired_ids), '?'));
		// 権限紐付けを削除
		$pdo->prepare("DELETE FROM admin_museum_permissions WHERE museum_id IN ($placeholders)")->execute($expired_ids);
		// 博物館本体を削除
		$pdo->prepare("DELETE FROM museums WHERE id IN ($placeholders)")->execute($expired_ids);
		
		// ★重要：不要になった管理者アカウントも削除
		cleanupOrphanedAdmins($pdo);
	}
	$pdo->commit();
} catch (Exception $e) {
	$pdo->rollBack();
}

// --- 2. 個別の操作処理 (元に戻す / 完全に消去) ---
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

if ($id && $action === 'restore') {
	$stmt = $pdo->prepare("UPDATE museums SET deleted_at = NULL WHERE id = ?");
	$stmt->execute([$id]);
	header("Location: trash.php?msg=restored");
	exit;
}

if ($id && $action === 'pdelete') {
	try {
		$pdo->beginTransaction();
		// 1. 権限紐付けを削除
		$pdo->prepare("DELETE FROM admin_museum_permissions WHERE museum_id = ?")->execute([$id]);
		// 2. 博物館本体を削除
		$pdo->prepare("DELETE FROM museums WHERE id = ?")->execute([$id]);
		
		// 3. ★重要：この結果、どこの博物館にも所属しなくなった管理者を削除
		cleanupOrphanedAdmins($pdo);

		$pdo->commit();
		header("Location: trash.php?msg=pdeleted");
		exit;
	} catch (Exception $e) {
		$pdo->rollBack();
		header("Location: trash.php?msg=error");
		exit;
	}
}

// --- 3. 表示データの取得 ---
$sql = "
	SELECT m.id, m.name_ja, m.deleted_at,
	DATEDIFF(DATE_ADD(m.deleted_at, INTERVAL 30 DAY), NOW()) as days_left
	FROM museums m WHERE m.deleted_at IS NOT NULL ORDER BY m.deleted_at DESC
";
$museums = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>ゴミ箱 - 博物館ガイド</title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; --border-color: #e9ecef;}
		body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; padding: 40px 0; color: #333; }
		.container { max-width: 800px; margin: auto; padding: 0 20px; }
		.card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
		.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color); }
		.data-table { width: 100%; border-collapse: collapse; }
		.data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
		.data-table th { background-color: #fcfcfc; font-size: 0.85em; color: #666; }
		.btn { text-decoration: none; padding: 8px 16px; border-radius: 20px; font-weight: bold; font-size: 12px; cursor: pointer; display: inline-block; border: 1px solid #ddd; color: #666; background: white; }
		.btn-restore { border-color: var(--primary-color); color: var(--primary-color); }
		.btn-restore:hover { background: var(--primary-color); color: white; }
		.btn-pdelete { border-color: #dc3545; color: #dc3545; }
		.btn-pdelete:hover { background: #dc3545; color: white; }
		.alert { background: #eef9f6; color: #1e7e34; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 0.9em; }
	</style>
</head>
<body>
<div class="container">
	<div class="card">
		<div class="card-header">
			<h2 style="margin:0;">🗑 ゴミ箱</h2>
			<a href="index.php" class="btn">一覧に戻る</a>
		</div>
		<?php if (isset($_GET['msg'])): ?>
			<div class="alert">
				<?php
					if($_GET['msg']==='restored') echo "博物館を元に戻しました。";
					if($_GET['msg']==='pdeleted') echo "データと管理アカウントを完全に削除しました。";
					if($_GET['msg']==='error') echo "エラーが発生しました。";
				?>
			</div>
		<?php endif; ?>
		<?php if (count($museums) > 0): ?>
			<table class="data-table">
				<thead><tr><th>博物館名</th><th>削除日</th><th>残り日数</th><th style="text-align:right;">操作</th></tr></thead>
				<tbody>
					<?php foreach ($museums as $m): ?>
					<tr>
						<td><strong><?= htmlspecialchars($m['name_ja']) ?></strong></td>
						<td><?= date('Y/m/d', strtotime($m['deleted_at'])) ?></td>
						<td style="color:#d00;">あと <?= $m['days_left'] ?> 日</td>
						<td style="text-align:right;">
							<a href="trash.php?action=restore&id=<?= $m['id'] ?>" class="btn btn-restore">元に戻す</a>
							<a href="trash.php?action=pdelete&id=<?= $m['id'] ?>" class="btn btn-pdelete" onclick="return confirm('完全に削除するとメールアドレスの再利用が可能になります。よろしいですか？')">完全削除</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else: ?>
			<div style="text-align:center; padding:50px; color:#888;">ゴミ箱は空です。</div>
		<?php endif; ?>
	</div>
</div>
</body>
</html>