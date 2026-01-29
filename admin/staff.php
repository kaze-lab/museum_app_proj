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

if (!$museum_id) { header('Location: index.php'); exit; }

// 2. 権限チェック（この館の「管理者(admin)」であるか確認）
$stmt = $pdo->prepare("SELECT role FROM admin_museum_permissions WHERE admin_id = ? AND museum_id = ?");
$stmt->execute([$admin_id, $museum_id]);
$my_perm = $stmt->fetch();

if (!$my_perm || $my_perm['role'] !== 'admin') {
	// 編集者(editor)や権限なしの場合は閲覧不可
	header('Location: museum_manage.php?id=' . $museum_id);
	exit;
}

// 博物館名の取得
$st_m = $pdo->prepare("SELECT name_ja FROM museums WHERE id = ?");
$st_m->execute([$museum_id]);
$museum_name = $st_m->fetchColumn();

$error_msg = "";
$success_msg = "";

// 3. スタッフの招待（登録）処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_staff'])) {
	$email = trim($_POST['email']);
	$role = $_POST['role']; // 'admin' or 'editor'

	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$error_msg = "正しいメールアドレスを入力してください。";
	} else {
		try {
			$pdo->beginTransaction();

			// 既存の管理者がいないかチェック
			$st_check = $pdo->prepare("SELECT id FROM museum_admins WHERE email = ?");
			$st_check->execute([$email]);
			$existing_user = $st_check->fetch();

			if ($existing_user) {
				$target_admin_id = $existing_user['id'];
				// 既にこの博物館のスタッフでないかチェック
				$st_dup = $pdo->prepare("SELECT COUNT(*) FROM admin_museum_permissions WHERE admin_id = ? AND museum_id = ?");
				$st_dup->execute([$target_admin_id, $museum_id]);
				if ($st_dup->fetchColumn() > 0) {
					throw new Exception("そのユーザーは既にこの博物館のスタッフです。");
				}
				$is_new = false;
			} else {
				// 新規ユーザー登録（招待トークン発行）
				$is_new = true;
				$token = bin2hex(random_bytes(32));
				$expiry = date("Y-m-d H:i:s", strtotime("+24 hours"));
				$st_add = $pdo->prepare("INSERT INTO museum_admins (email, reset_token, reset_expiry) VALUES (?, ?, ?)");
				$st_add->execute([$email, $token, $expiry]);
				$target_admin_id = $pdo->lastInsertId();
			}

			// 権限紐付け
			$st_perm = $pdo->prepare("INSERT INTO admin_museum_permissions (admin_id, museum_id, role) VALUES (?, ?, ?)");
			$st_perm->execute([$target_admin_id, $museum_id, $role]);

			$pdo->commit();

			// 招待メール送信（新規ユーザーのみ）
			if ($is_new) {
				$url = "https://" . $_SERVER['HTTP_HOST'] . "/museum/admin/set_password.php?token=" . $token;
				$subject = "【博物館ガイド】スタッフ招待のお知らせ";
				$body = "{$museum_name} の管理スタッフとして招待されました。\n\n";
				$body .= "以下のURLからアカウント設定を完了させてください。\n" . $url;
				$headers = "From: 博物館ガイドシステム <webmaster@" . $_SERVER['HTTP_HOST'] . ">";
				mb_send_mail($email, $subject, $body, $headers);
				$success_msg = "新しいスタッフに招待メールを送信しました。";
			} else {
				$success_msg = "既存ユーザーをスタッフとして追加しました。";
			}

		} catch (Exception $e) {
			$pdo->rollBack();
			$error_msg = $e->getMessage();
		}
	}
}

// 4. スタッフの削除処理
if (isset($_GET['remove'])) {
	$remove_id = $_GET['remove'];
	if ($remove_id == $admin_id) {
		$error_msg = "自分自身を削除することはできません。";
	} else {
		$st_del = $pdo->prepare("DELETE FROM admin_museum_permissions WHERE admin_id = ? AND museum_id = ?");
		$st_del->execute([$remove_id, $museum_id]);
		$success_msg = "スタッフを解除しました。";
	}
}

// 5. スタッフ一覧の取得
$sql_list = "
	SELECT ma.id, ma.name, ma.email, amp.role 
	FROM museum_admins ma
	JOIN admin_museum_permissions amp ON ma.id = amp.admin_id
	WHERE amp.museum_id = ?
	ORDER BY amp.role ASC, ma.id ASC
";
$st_list = $pdo->prepare($sql_list);
$st_list->execute([$museum_id]);
$staffs = $st_list->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>スタッフ管理 - <?= htmlspecialchars($museum_name) ?></title>
	<style>
		* { box-sizing: border-box; }
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; --border-color: #e9ecef; }
		body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; color: #333; }
		.inner-wrapper { max-width: 1000px; margin: 0 auto; padding: 0 40px; }
		header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 15px 0; margin-bottom: 40px; }
		.header-content { display: flex; justify-content: space-between; align-items: center; }
		.btn-back { text-decoration: none; color: #666; font-size: 0.9rem; }
		
		.card { background: white; border-radius: 20px; padding: 35px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
		h2 { font-size: 1.3rem; margin-top: 0; margin-bottom: 25px; color: #333; }
		
		/* 招待フォーム */
		.invite-form { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
		.form-group { display: flex; flex-direction: column; gap: 8px; flex-grow: 1; }
		label { font-size: 0.85rem; font-weight: bold; color: #666; }
		input, select { padding: 12px; border: 1px solid #ddd; border-radius: 10px; font-size: 0.95rem; }
		.btn-invite { background: var(--primary-color); color: white; border: none; padding: 12px 25px; border-radius: 30px; font-weight: bold; cursor: pointer; }
		
		/* スタッフテーブル */
		.staff-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
		.staff-table th, .staff-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
		.staff-table th { font-size: 0.85rem; color: #888; background: #fcfcfc; }
		
		.role-badge { padding: 4px 10px; border-radius: 10px; font-size: 0.75rem; font-weight: bold; }
		.role-admin { background: #e6fff0; color: #1e7e34; }
		.role-editor { background: #f0f4ff; color: #335eea; }
		
		.btn-remove { color: #dc3545; text-decoration: none; font-size: 0.85rem; font-weight: bold; }
		.btn-remove:hover { text-decoration: underline; }
		
		.alert { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-size: 0.9rem; }
		.alert-error { background: #fff3f3; color: #d00; border: 1px solid #ffcccc; }
		.alert-success { background: #eef9f6; color: #1e7e34; border: 1px solid #c3e6cb; }
	</style>
</head>
<body>

<header>
	<div class="inner-wrapper header-content">
		<a href="museum_manage.php?id=<?= $museum_id ?>" class="btn-back">← 管理メニューに戻る</a>
		<div style="font-weight: bold; color: var(--primary-color);">スタッフ管理システム</div>
	</div>
</header>

<div class="inner-wrapper">
	<h2><?= htmlspecialchars($museum_name) ?> のスタッフ管理</h2>

	<?php if ($error_msg): ?><div class="alert alert-error"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>
	<?php if ($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>

	<!-- スタッフ招待カード -->
	<div class="card">
		<h2>新しいスタッフを招待</h2>
		<form method="POST" class="invite-form">
			<div class="form-group" style="flex: 2;">
				<label>メールアドレス</label>
				<input type="email" name="email" placeholder="staff@example.jp" required>
			</div>
			<div class="form-group" style="flex: 1;">
				<label>役割（権限）</label>
				<select name="role">
					<option value="editor">データ編集者</option>
					<option value="admin">博物館管理者</option>
				</select>
			</div>
			<button type="submit" name="invite_staff" class="btn-invite">招待を送信</button>
		</form>
		<p style="font-size: 0.75rem; color: #999; margin-top: 15px;">
			※新規ユーザーの場合はパスワード設定メールが送信されます。既存ユーザーの場合は即座に権限が付与されます。
		</p>
	</div>

	<!-- 現在のスタッフ一覧カード -->
	<div class="card">
		<h2>スタッフ一覧</h2>
		<table class="staff-table">
			<thead>
				<tr>
					<th>名前</th>
					<th>メールアドレス</th>
					<th>権限</th>
					<th style="text-align:right;">操作</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($staffs as $s): ?>
				<tr>
					<td><strong><?= htmlspecialchars($s['name'] ?? '(未設定)') ?></strong></td>
					<td><?= htmlspecialchars($s['email']) ?></td>
					<td>
						<span class="role-badge <?= $s['role'] === 'admin' ? 'role-admin' : 'role-editor' ?>">
							<?= $s['role'] === 'admin' ? '管理者' : '編集者' ?>
						</span>
					</td>
					<td style="text-align:right;">
						<?php if ($s['id'] != $admin_id): ?>
							<a href="staff.php?id=<?= $museum_id ?>&remove=<?= $s['id'] ?>" class="btn-remove" onclick="return confirm('このスタッフの権限を解除しますか？')">解除</a>
						<?php else: ?>
							<span style="color:#ccc; font-size:0.8rem;">(あなた)</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

</body>
</html>