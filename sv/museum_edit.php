<?php
require_once('../common/db_inc.php');
session_start();

if (!isset($_SESSION['sv_logged_in'])) {
	header('Location: login.php');
	exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
	header('Location: index.php');
	exit;
}

// 1. カテゴリ一覧を取得
$categories = $pdo->query("SELECT * FROM categories ORDER BY id")->fetchAll();

// 2. 現在のデータ（博物館 ＋ 筆頭管理者）を取得
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

if (!$museum) {
	header('Location: index.php');
	exit;
}

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name_ja = trim($_POST['name_ja']);
	$name_kana = trim($_POST['name_kana']);
	$category_id = $_POST['category_id'];
	$email = trim($_POST['email']);
	$password = $_POST['password']; // 管理者変更時のみ使用
	$address = $_POST['address'];
	$phone_number = $_POST['phone_number'];
	$website_url = $_POST['website_url'];
	$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
	$notes = $_POST['notes'];

	// バリデーション
	if (empty($name_ja) || empty($name_kana) || empty($category_id) || empty($email)) {
		$error_msg = "必須項目 (*) はすべて入力してください。";
	}

	// 名称重複チェック（自分以外）
	if (empty($error_msg)) {
		$st_name = $pdo->prepare("SELECT COUNT(*) FROM museums WHERE name_ja = ? AND id != ?");
		$st_name->execute([$name_ja, $id]);
		if ($st_name->fetchColumn() > 0) {
			$error_msg = "その博物館名は既に他の施設で使用されています。";
		}
	}

	if (empty($error_msg)) {
		try {
			$pdo->beginTransaction();

			// ① 博物館情報の更新 (updated_atはDBが自動更新するため省略可能ですが、手動でセットしておきます)
			$sql_u = "UPDATE museums SET 
						name_ja = ?, name_kana = ?, category_id = ?, 
						address = ?, phone_number = ?, website_url = ?, 
						is_active = ?, notes = ?, updated_at = NOW() 
					  WHERE id = ?";
			$pdo->prepare($sql_u)->execute([$name_ja, $name_kana, $category_id, $address, $phone_number, $website_url, $is_active, $notes, $id]);

			// ② 管理者の変更処理（メールアドレスが変わった場合）
			if ($email !== $museum['email']) {
				// SV重複チェック
				$st_sv = $pdo->prepare("SELECT COUNT(*) FROM supervisors WHERE email = ?");
				$st_sv->execute([$email]);
				if ($st_sv->fetchColumn() > 0) throw new Exception("SV用アドレスは管理者には使用できません。");

				// 既存の管理者かチェック
				$st_adm = $pdo->prepare("SELECT id FROM museum_admins WHERE email = ?");
				$st_adm->execute([$email]);
				$existing = $st_adm->fetch();

				if ($existing) {
					$admin_id = $existing['id'];
				} else {
					// 新規ならアカウント作成
					if (empty($password)) throw new Exception("新規管理者の場合は初期パスワードを入力してください。");
					$st_new = $pdo->prepare("INSERT INTO museum_admins (email, password) VALUES (?,?)");
					$st_new->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
					$admin_id = $pdo->lastInsertId();
				}

				// 古いadmin権限を削除（もしあれば）して、新しいadminを紐付ける
				$pdo->prepare("DELETE FROM admin_museum_permissions WHERE museum_id = ? AND role = 'admin'")->execute([$id]);
				$pdo->prepare("INSERT INTO admin_museum_permissions (admin_id, museum_id, role) VALUES (?, ?, 'admin')")->execute([$admin_id, $id]);
			}

			$pdo->commit();
			header("Location: index.php?msg=updated");
			exit;

		} catch (Exception $e) { 
			$pdo->rollBack(); 
			$error_msg = "更新エラー: " . $e->getMessage(); 
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
		body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; display: flex; justify-content: center; padding: 40px 0; color:#333; }
		.container { max-width: 800px; width: 100%; padding: 0 20px; }
		.card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
		.card-header { padding-bottom: 20px; margin-bottom: 30px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
		.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
		.form-group { margin-bottom: 10px; position: relative; }
		.full-width { grid-column: 1 / -1; }
		label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 0.9em; }
		label span.req { color: #d00; margin-left: 3px; }
		input, select, textarea { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ccc; box-sizing: border-box; font-size: 1em; }
		.info-text { font-size: 0.8em; color: #888; margin-top: 6px; min-height: 1.4em; }
		.btn-group { display: flex; gap: 10px; margin-top: 30px; border-top: 1px solid var(--border-color); padding-top: 25px; }
		.btn { text-decoration: none; padding: 12px 25px; border-radius: 25px; font-weight: bold; cursor: pointer; border: 1px solid; text-align: center; font-size: 14px; }
		.btn-primary { background: var(--primary-color); color: white; border-color: var(--primary-color); }
		.btn-primary:disabled { background: #ccc; border-color: #ccc; }
		.alert { background: #fff3f3; color: #d00; padding: 15px; border-radius: 10px; margin-bottom: 25px; border: 1px solid #ffcccc; }
		input:disabled { background-color: #f5f5f5; border-color: #eee; }
	</style>
</head>
<body>
<div class="container">
	<div class="card">
		<div class="card-header">
			<h2 style="margin:0;">博物館情報の編集</h2>
			<span style="color:#888; font-size:0.9em;">ID: <?= $museum['id'] ?> / コード: <?= $museum['m_code'] ?></span>
		</div>

		<?php if ($error_msg): ?><div class="alert"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

		<form method="POST">
			<div class="form-grid">
				<div class="form-group">
					<label>博物館名<span class="req">*</span></label>
					<input type="text" id="name_ja" name="name_ja" value="<?= htmlspecialchars($museum['name_ja']) ?>" onblur="checkNameStatus()" required>
					<p id="name-hint" class="info-text">重複しない名称を入力してください。</p>
				</div>
				<div class="form-group"><label>博物館名（かな）<span class="req">*</span></label><input type="text" name="name_kana" value="<?= htmlspecialchars($museum['name_kana']) ?>" required></div>
				
				<div class="form-group">
					<label>カテゴリ<span class="req">*</span></label>
					<select name="category_id" required>
						<?php foreach ($categories as $cat): ?>
							<option value="<?= $cat['id'] ?>" <?= ($museum['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="form-group">
					<label>筆頭管理者メールアドレス<span class="req">*</span></label>
					<input type="email" id="email" name="email" value="<?= htmlspecialchars($museum['email']) ?>" onblur="checkUserStatus()" required>
					<p id="email-hint" class="info-text">変更すると、新しい担当者に管理権限が移ります。</p>
				</div>

				<div id="password-group" class="form-group" style="display:none;">
					<label>新規管理者用初期パスワード<span class="req">*</span></label>
					<input type="password" id="password" name="password">
					<p class="info-text">既存ユーザーの場合は不要です。</p>
				</div>

				<div class="form-group full-width"><label>所在地</label><input type="text" name="address" value="<?= htmlspecialchars($museum['address']) ?>"></div>
				<div class="form-group"><label>電話番号</label><input type="text" name="phone_number" value="<?= htmlspecialchars($museum['phone_number']) ?>"></div>
				<div class="form-group"><label>公式サイトURL</label><input type="url" name="website_url" value="<?= htmlspecialchars($museum['website_url']) ?>"></div>
				
				<div class="form-group full-width">
					<label>公開ステータス<span class="req">*</span></label>
					<div style="margin-top:10px;">
						<label style="font-weight:normal; margin-right:20px; display:inline-flex; align-items:center;"><input type="radio" name="is_active" value="1" <?= $museum['is_active'] ? 'checked' : '' ?> style="width:auto; margin-right:8px;"> 公開</label>
						<label style="font-weight:normal; display:inline-flex; align-items:center;"><input type="radio" name="is_active" value="0" <?= !$museum['is_active'] ? 'checked' : '' ?> style="width:auto; margin-right:8px;"> 非公開</label>
					</div>
				</div>
				<div class="form-group full-width"><label>備考</label><textarea name="notes" rows="3"><?= htmlspecialchars($museum['notes']) ?></textarea></div>
			</div>

			<div class="btn-group">
				<a href="index.php" class="btn btn-outline">キャンセル</a>
				<button type="submit" id="submit-btn" class="btn btn-primary">変更を保存する</button>
			</div>
		</form>
	</div>
</div>

<script>
let currentId = <?= $id ?>;
let currentEmail = "<?= htmlspecialchars($museum['email']) ?>";

async function checkNameStatus() {
	const name = document.getElementById('name_ja').value;
	const hint = document.getElementById('name-hint');
	const btn = document.getElementById('submit-btn');
	if (!name) return;
	const response = await fetch(`check_name.php?name=${encodeURIComponent(name)}&exclude_id=${currentId}`);
	const data = await response.json();
	if (data.exists) {
		hint.innerHTML = "<b style='color:#d00;'>【重複】他の博物館で使用されています。</b>";
		btn.disabled = true;
	} else {
		hint.innerHTML = "<span style='color:#26b396;'>使用可能です。</span>";
		btn.disabled = false;
	}
}

async function checkUserStatus() {
	const email = document.getElementById('email').value;
	const emailHint = document.getElementById('email-hint');
	const pwGroup = document.getElementById('password-group');
	const pwInput = document.getElementById('password');

	if (!email.includes('@')) return;
	if (email === currentEmail) {
		emailHint.innerHTML = "現在の筆頭管理者です。";
		pwGroup.style.display = "none";
		pwInput.required = false;
		return;
	}

	const response = await fetch('check_email.php?email=' + encodeURIComponent(email));
	const data = await response.json();
	if (data.exists) {
		emailHint.innerHTML = "<b style='color:#e67e22;'>【既存ユーザー】</b> 権限をこの人に移譲します。";
		pwGroup.style.display = "none";
		pwInput.required = false;
	} else {
		emailHint.innerHTML = "<b style='color:#26b396;'>【新規ユーザー】</b> アカウントを新規作成して移譲します。";
		pwGroup.style.display = "block";
		pwInput.required = true;
	}
}
</script>
</body>
</html>