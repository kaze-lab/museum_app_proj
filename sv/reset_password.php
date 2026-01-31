<?php
require_once('../common/db_inc.php');

$error_msg = "";
$token = $_GET['token'] ?? '';
$is_valid_token = false;

// トークンの有効性チェック
if (!empty($token)) {
	$stmt = $pdo->prepare("SELECT * FROM supervisors WHERE reset_token = ? AND reset_expiry > NOW()");
	$stmt->execute([$token]);
	$sv = $stmt->fetch();
	if ($sv) {
		$is_valid_token = true;
	} else {
		$error_msg = "トークンが無効か、有効期限が切れています。";
	}
} else {
	$error_msg = "不正なアクセスです。";
}

// フォームが送信されたときの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_valid_token) {
	$new_pass = $_POST['new_password'];
	$new_pass_conf = $_POST['new_password_conf'];

	// パスワード強度チェック
	$pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[\S]{12,}$/';
	if (!preg_match($pattern, $new_pass)) {
		$error_msg = "パスワードは「大文字・小文字・数字を各1文字以上含む、12文字以上の文字列」にしてください。";
	} elseif ($new_pass !== $new_pass_conf) {
		$error_msg = "新しいパスワードと確認用が一致しません。";
	}

	if (empty($error_msg)) {
		// パスワードを更新し、トークンを無効化する
		$hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
		$stmt = $pdo->prepare("UPDATE supervisors SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
		$stmt->execute([$hashed_pass, $sv['id']]);

		header("Location: login.php?msg=reset_success");
		exit;
	}
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>新しいパスワードの設定 - 博物館ガイド</title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; }
		body { font-family: sans-serif; background-color: var(--bg-color); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
		.card { background: white; padding: 40px 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); width: 100%; max-width: 380px; text-align: center; }
		h2 { color: #333; margin-bottom: 20px; }
		/* ★ 変更：position:relative を追加 */
		.form-group { margin-bottom: 15px; text-align: left; position: relative; }
		label { display: block; font-size: 0.85em; color: #666; margin-bottom: 5px; }
		input { width: 100%; padding: 12px; border-radius: 25px; border: 1px solid #ddd; box-sizing: border-box; outline: none; }
		input:focus { border-color: var(--primary-color); }
		.rule-box { background: #f9f9f9; padding: 15px; border-radius: 10px; font-size: 0.8em; color: #777; margin-bottom: 20px; border: 1px solid #eee; text-align: left; }
		.btn { width: 100%; padding: 14px; border-radius: 30px; border: none; background-color: var(--primary-color); color: white; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 15px; }
		.alert { background: #fff3f3; color: #d00; padding: 12px; border-radius: 10px; font-size: 0.85em; margin-bottom: 20px; border: 1px solid #ffcccc; }
		.links { margin-top: 20px; font-size: 13px; }
		.links a { color: #888; text-decoration: none; }

		/* ★ 変更：toggle-password用のスタイルを追加 */
		.toggle-password {
			position: absolute;
			right: 18px;
			top: 32px; /* labelの高さを考慮した位置 */
			cursor: pointer;
			color: #888;
			display: flex;
			align-items: center;
		}
	</style>
</head>
<body>
<div class="card">
	<h2>新しいパスワードの設定</h2>

	<?php if ($is_valid_token): ?>
		<?php if ($error_msg): ?><div class="alert"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>
		
		<div class="rule-box">
			<strong>パスワード設定ルール：</strong><br>
			・12文字以上 ・英大文字、英小文字、数字をすべて含む
		</div>

		<form method="POST">
			<!-- ★ 変更：HTMLにidとtoggle機能を追加 -->
			<div class="form-group">
				<label>新しいパスワード</label>
				<input type="password" name="new_password" id="new_password" required>
				<span class="toggle-password" onclick="togglePassword('new_password', 'eye-slash-1')">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>
						<line id="eye-slash-1" x1="1" y1="1" x2="23" y2="23"></line>
					</svg>
				</span>
			</div>
			<div class="form-group">
				<label>新しいパスワード（確認用）</label>
				<input type="password" name="new_password_conf" id="new_password_conf" required>
				<span class="toggle-password" onclick="togglePassword('new_password_conf', 'eye-slash-2')">
					 <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>
						<line id="eye-slash-2" x1="1" y1="1" x2="23" y2="23"></line>
					</svg>
				</span>
			</div>
			<button type="submit" class="btn">パスワードを設定する</button>
		</form>
	<?php else: ?>
		<div class="alert"><?= htmlspecialchars($error_msg) ?></div>
		<div class="links">
			<a href="reissue.php">もう一度やり直す</a>
		</div>
	<?php endif; ?>
</div>

<!-- ★ 変更：JavaScriptを追加 -->
<script>
	function togglePassword(inputId, eyeSlashId) {
		const passInput = document.getElementById(inputId);
		const eyeSlash = document.getElementById(eyeSlashId);

		if (passInput.type === 'password') {
			passInput.type = 'text';
			eyeSlash.style.display = 'none';
		} else {
			passInput.type = 'password';
			eyeSlash.style.display = 'block';
		}
	}
</script>

</body>
</html>