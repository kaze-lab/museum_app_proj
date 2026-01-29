<?php
require_once('../common/db_inc.php');
session_start();

if (!isset($_SESSION['sv_logged_in'])) {
	header('Location: login.php');
	exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY id")->fetchAll();
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name_ja = trim($_POST['name_ja']);
	$name_kana = trim($_POST['name_kana']);
	$category_id = $_POST['category_id'];
	$email = trim($_POST['email']);
	$address = $_POST['address'];
	$phone_number = $_POST['phone_number'];
	$website_url = $_POST['website_url'];
	$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
	$notes = $_POST['notes'];

	// --- 1. バリデーション ---
	if (empty($name_ja) || empty($name_kana) || empty($category_id) || empty($email)) {
		$error_msg = "必須項目 (*) はすべて入力してください。";
	}
	
	// 名称重複チェック
	if (empty($error_msg)) {
		$st_name = $pdo->prepare("SELECT COUNT(*) FROM museums WHERE name_ja = ?");
		$st_name->execute([$name_ja]);
		if ($st_name->fetchColumn() > 0) {
			$error_msg = "その博物館名は既に登録されています。";
		}
	}

	// メール形式・SV重複チェック
	if (empty($error_msg) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$error_msg = "正しい形式のメールアドレスを入力してください。";
	}
	if (empty($error_msg)) {
		$st_sv = $pdo->prepare("SELECT COUNT(*) FROM supervisors WHERE email = ?");
		$st_sv->execute([$email]);
		if ($st_sv->fetchColumn() > 0) {
			$error_msg = "このメールはSV用のため、博物館管理者には使用できません。";
		}
	}

	// --- 2. 登録実行 ---
	if (empty($error_msg)) {
		try {
			$pdo->beginTransaction();

			// ① m_code（内部識別コード）の自動生成
			do {
				$m_code = bin2hex(random_bytes(4)); 
				$st = $pdo->prepare("SELECT COUNT(*) FROM museums WHERE m_code = ?");
				$st->execute([$m_code]);
			} while ($st->fetchColumn() > 0);

			// ② museumsテーブルへ登録
			$sql_m = "INSERT INTO museums (m_code, name_ja, name_kana, category_id, address, phone_number, website_url, is_active, notes) 
					  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
			$stmt_m = $pdo->prepare($sql_m);
			$stmt_m->execute([$m_code, $name_ja, $name_kana, $category_id, $address, $phone_number, $website_url, $is_active, $notes]);
			$museum_id = $pdo->lastInsertId();

			// ③ 管理者(museum_admins)の処理
			$st_adm = $pdo->prepare("SELECT id FROM museum_admins WHERE email = ?");
			$st_adm->execute([$email]);
			$existing_admin = $st_adm->fetch();

			$is_new_user = false;
			if ($existing_admin) {
				// 既存の管理者の場合はそのIDを使う
				$admin_id = $existing_admin['id'];
			} else {
				// 新規管理者の場合：トークンを発行して仮登録
				$is_new_user = true;
				$token = bin2hex(random_bytes(32));
				$expiry = date("Y-m-d H:i:s", strtotime("+24 hours")); // 有効期限24時間

				$sql_a = "INSERT INTO museum_admins (email, reset_token, reset_expiry) VALUES (?, ?, ?)";
				$stmt_a = $pdo->prepare($sql_a);
				$stmt_a->execute([$email, $token, $expiry]);
				$admin_id = $pdo->lastInsertId();
			}

			// ④ 権限紐付け (role='admin' を付与)
			$sql_p = "INSERT INTO admin_museum_permissions (admin_id, museum_id, role) VALUES (?, ?, 'admin')";
			$stmt_p = $pdo->prepare($sql_p);
			$stmt_p->execute([$admin_id, $museum_id]);

			$pdo->commit();

			// ⑤ 招待メール送信（新規ユーザーのみ）
			if ($is_new_user) {
				$set_password_url = "https://" . $_SERVER['HTTP_HOST'] . "/museum/admin/set_password.php?token=" . $token;
				
				$subject = "【重要】博物館管理システムへの招待";
				$body = "{$name_ja} の管理者に設定されました。\n\n";
				$body .= "以下のURLからパスワードを設定し、2段階認証を完了させてログインしてください。\n";
				$body .= $set_password_url . "\n\n";
				$body .= "※有効期限は24時間です。";
				
				$headers = "From: 博物館ガイドシステム <webmaster@" . $_SERVER['HTTP_HOST'] . ">";
				mb_send_mail($email, $subject, $body, $headers);
			}

			header("Location: index.php?msg=added");
			exit;

		} catch (Exception $e) {
			$pdo->rollBack();
			$error_msg = "登録エラーが発生しました。";
		}
	}
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>新しい博物館の登録 - 博物館ガイド</title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; --border-color: #e9ecef;}
		body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; display: flex; justify-content: center; padding: 40px 0; }
		.container { max-width: 800px; width: 100%; padding: 0 20px; }
		.card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
		.card-header { padding-bottom: 20px; margin-bottom: 30px; border-bottom: 1px solid var(--border-color); }
		.card-header h2 { margin: 0; font-size: 1.5em; }
		.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
		.form-group { margin-bottom: 5px; position: relative; }
		.full-width { grid-column: 1 / -1; }
		label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 0.9em; }
		label span.req { color: #d00; margin-left: 3px; }
		input, select, textarea { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ccc; box-sizing: border-box; font-size: 1em; }
		.info-text { font-size: 0.8em; color: #888; margin-top: 6px; min-height: 1.4em; }
		.btn-group { display: flex; gap: 10px; margin-top: 30px; border-top: 1px solid var(--border-color); padding-top: 25px; }
		.btn { text-decoration: none; padding: 12px 25px; border-radius: 25px; font-weight: bold; cursor: pointer; border: 1px solid; text-align: center; }
		.btn-primary { background: var(--primary-color); color: white; border-color: var(--primary-color); }
		.btn-primary:disabled { background: #ccc; border-color: #ccc; cursor: not-allowed; }
		.alert { background: #fff3f3; color: #d00; padding: 15px; border-radius: 10px; margin-bottom: 25px; border: 1px solid #ffcccc; }
	</style>
</head>
<body>
<div class="container">
	<div class="card">
		<div class="card-header"><h2>新しい博物館の登録</h2></div>
		<?php if ($error_msg): ?><div class="alert"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>
		<form method="POST">
			<div class="form-grid">
				<div class="form-group">
					<label>博物館名<span class="req">*</span></label>
					<input type="text" id="name_ja" name="name_ja" value="<?= htmlspecialchars($_POST['name_ja'] ?? '') ?>" onblur="checkNameStatus()" required>
					<p id="name-hint" class="info-text">重複しない名称を入力してください。</p>
				</div>
				<div class="form-group"><label>博物館名（かな）<span class="req">*</span></label><input type="text" name="name_kana" value="<?= htmlspecialchars($_POST['name_kana'] ?? '') ?>" required></div>
				
				<div class="form-group">
					<label>カテゴリ<span class="req">*</span></label>
					<select name="category_id" required>
						<option value="">選択してください</option>
						<?php foreach ($categories as $cat): ?>
							<option value="<?= $cat['id'] ?>" <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="form-group">
					<label>管理者メールアドレス<span class="req">*</span></label>
					<input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" onblur="checkUserStatus()" required>
					<p id="email-hint" class="info-text">筆頭管理者のIDです。新規の場合は招待状を送ります。</p>
				</div>

				<div class="form-group full-width"><label>所在地</label><input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"></div>
				<div class="form-group"><label>電話番号</label><input type="text" name="phone_number" value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>"></div>
				<div class="form-group"><label>公式サイトURL</label><input type="url" name="website_url" value="<?= htmlspecialchars($_POST['website_url'] ?? '') ?>" placeholder="https://example.com"></div>
				
				<div class="form-group full-width">
					<label>公開ステータス<span class="req">*</span></label>
					<div style="margin-top:10px;">
						<label style="font-weight:normal; margin-right:20px; display:inline-flex; align-items:center;"><input type="radio" name="is_active" value="1" <?= (($_POST['is_active'] ?? '0') == '1') ? 'checked' : '' ?> style="width:auto; margin-right:8px;"> 公開</label>
						<label style="font-weight:normal; display:inline-flex; align-items:center;"><input type="radio" name="is_active" value="0" <?= (($_POST['is_active'] ?? '0') == '0') ? 'checked' : '' ?> style="width:auto; margin-right:8px;"> 非公開</label>
					</div>
				</div>
				<div class="form-group full-width"><label>備考</label><textarea name="notes" rows="3"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea></div>
			</div>

			<div class="btn-group">
				<a href="index.php" class="btn btn-outline" style="background:#fff; color:#555; border-color:#ddd;">キャンセル</a>
				<button type="submit" id="submit-btn" class="btn btn-primary">登録を実行する</button>
			</div>
		</form>
	</div>
</div>

<script>
async function checkNameStatus() {
	const name = document.getElementById('name_ja').value;
	const hint = document.getElementById('name-hint');
	if (name === '') return;
	try {
		const response = await fetch('check_name.php?name=' + encodeURIComponent(name));
		const data = await response.json();
		if (data.exists) {
			hint.innerHTML = "<b style='color:#d00;'>【重複】既に使用されている名称です。</b>";
			document.getElementById('submit-btn').disabled = true;
		} else {
			hint.innerHTML = "<span style='color:#26b396;'>使用可能です。</span>";
			document.getElementById('submit-btn').disabled = false;
		}
	} catch (e) { console.error(e); }
}

async function checkUserStatus() {
	const email = document.getElementById('email').value;
	const hint = document.getElementById('email-hint');
	if (!email.includes('@')) return;
	try {
		const response = await fetch('check_email.php?email=' + encodeURIComponent(email));
		const data = await response.json();
		if (data.exists) {
			hint.innerHTML = "<b style='color:#e67e22;'>【既存ユーザー】</b> 権限を追加します（招待メールは送りません）。";
		} else {
			hint.innerHTML = "<span style='color:#26b396;'>新規ユーザーとして招待メールを送信します。</span>";
		}
	} catch (e) { console.error(e); }
}
</script>
</body>
</html>