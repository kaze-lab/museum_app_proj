<?php
require_once('../common/db_inc.php');
session_start();
if (!isset($_SESSION['sv_logged_in'])) {
	header('Location: login.php');
	exit;
}

$id = $_GET['id'] ?? null;
if (!$id) { header('Location: index.php'); exit; }

// カテゴリ一覧
$categories = $pdo->query("SELECT * FROM categories ORDER BY id")->fetchAll();

// 現在の博物館情報と、紐付いている「admin」権限の管理者のメールアドレスを取得
$sql = "
	SELECT m.*, ma.email 
	FROM museums m
	LEFT JOIN admin_museum_permissions amp ON m.id = amp.museum_id AND amp.role = 'admin'
	LEFT JOIN museum_admins ma ON amp.admin_id = ma.id
	WHERE m.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$museum = $stmt->fetch();

if (!$museum) { header('Location: index.php'); exit; }

$error_msg = "";
$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name_ja = $_POST['name_ja'];
	$name_kana = $_POST['name_kana'];
	$category_id = $_POST['category_id'];
	$address = $_POST['address'];
	$phone_number = $_POST['phone_number'];
	$website_url = $_POST['website_url'];
	$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
	$notes = $_POST['notes'];

	if (empty($name_ja) || empty($name_kana) || empty($category_id)) {
		$error_msg = "必須項目 (*) はすべて入力してください。";
	}

	if (empty($error_msg)) {
		try {
			$sql_u = "UPDATE museums SET 
						name_ja = ?, name_kana = ?, category_id = ?, 
						address = ?, phone_number = ?, website_url = ?, 
						is_active = ?, notes = ?, updated_at = NOW() 
					  WHERE id = ?";
			$stmt_u = $pdo->prepare($sql_u);
			$stmt_u->execute([$name_ja, $name_kana, $category_id, $address, $phone_number, $website_url, $is_active, $notes, $id]);
			
			header("Location: index.php?msg=updated");
			exit;
		} catch (PDOException $e) {
			$error_msg = "更新中にエラーが発生しました。";
		}
	}
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>博物館情報の編集 - 博物館ガイド</title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; --border-color: #e9ecef;}
		body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; padding: 40px 0; color: #333; }
		.container { max-width: 800px; margin: auto; padding: 0 20px; }
		.card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
		.card-header { border-bottom: 1px solid var(--border-color); margin-bottom: 30px; padding-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
		.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
		.full-width { grid-column: 1 / -1; }
		.form-group { margin-bottom: 15px; }
		label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 0.9em; }
		input[type="text"], input[type="url"], select, textarea { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; box-sizing: border-box; }
		.admin-info { background: #f8f9fa; padding: 15px; border-radius: 10px; border: 1px solid #eee; margin-bottom: 25px; }
		.btn-group { margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color); display: flex; gap: 10px; }
		.btn { padding: 12px 25px; border-radius: 25px; font-weight: bold; cursor: pointer; text-decoration: none; border: none; }
		.btn-primary { background: var(--primary-color); color: white; }
		.btn-outline { background: white; border: 1px solid #ddd; color: #666; }
		.alert { background: #fff3f3; color: #d00; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
	</style>
</head>
<body>

<div class="container">
	<div class="card">
		<div class="card-header">
			<h2 style="margin:0;">博物館情報の編集</h2>
			<span style="color:#888; font-size:0.9em;">ID: <?= $museum['id'] ?></span>
		</div>

		<?php if ($error_msg): ?><div class="alert"><?= $error_msg ?></div><?php endif; ?>

		<!-- 管理階層図に基づき、筆頭管理者は「確認」のみ -->
		<div class="admin-info">
			<label style="color:#666;">筆頭管理者（最初の一人）</label>
			<div style="font-weight:bold; font-size:1.1em;"><?= htmlspecialchars($museum['email'] ?? '未設定') ?></div>
			<p style="font-size:0.8em; color:#999; margin:5px 0 0;">※管理者の追加や変更は、この管理者が自ら行います。</p>
		</div>

		<form method="POST">
			<div class="form-grid">
				<div class="form-group">
					<label>博物館名<span style="color:red;">*</span></label>
					<input type="text" name="name_ja" value="<?= htmlspecialchars($museum['name_ja']) ?>" required>
				</div>
				<div class="form-group">
					<label>かな<span style="color:red;">*</span></label>
					<input type="text" name="name_kana" value="<?= htmlspecialchars($museum['name_kana']) ?>" required>
				</div>
				<div class="form-group">
					<label>カテゴリ<span style="color:red;">*</span></label>
					<select name="category_id">
						<?php foreach ($categories as $cat): ?>
							<option value="<?= $cat['id'] ?>" <?= ($museum['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-group">
					<label>内部識別コード (m_code)</label>
					<input type="text" value="<?= $museum['m_code'] ?>" disabled style="background:#f0f0f0;">
				</div>
				<div class="form-group full-width">
					<label>所在地</label>
					<input type="text" name="address" value="<?= htmlspecialchars($museum['address']) ?>">
				</div>
				<div class="form-group">
					<label>電話番号</label>
					<input type="text" name="phone_number" value="<?= htmlspecialchars($museum['phone_number']) ?>">
				</div>
				<div class="form-group">
					<label>公式サイトURL</label>
					<input type="url" name="website_url" value="<?= htmlspecialchars($museum['website_url']) ?>">
				</div>
				<div class="form-group full-width">
					<label>公開ステータス</label>
					<label style="font-weight:normal; display:inline-block; margin-right:20px;">
						<input type="radio" name="is_active" value="1" <?= $museum['is_active'] ? 'checked' : '' ?>> 公開
					</label>
					<label style="font-weight:normal; display:inline-block;">
						<input type="radio" name="is_active" value="0" <?= !$museum['is_active'] ? 'checked' : '' ?>> 非公開
					</label>
				</div>
				<div class="form-group full-width">
					<label>備考 (スーパーバイザー用メモ)</label>
					<textarea name="notes" rows="4"><?= htmlspecialchars($museum['notes']) ?></textarea>
				</div>
			</div>

			<div class="btn-group">
				<a href="index.php" class="btn btn-outline">キャンセル</a>
				<button type="submit" class="btn btn-primary">変更を保存する</button>
			</div>
		</form>
	</div>
</div>

</body>
</html>