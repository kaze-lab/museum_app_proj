<?php
require_once('../common/db_inc.php');
session_start();

// すでにログイン済みならダッシュボードへ
if (isset($_SESSION['admin_logged_in'])) {
	header('Location: index.php');
	exit;
}

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = trim($_POST['email']);
	$password = $_POST['password'];

	if ($email && $password) {
		// ユーザー取得
		$stmt = $pdo->prepare("SELECT * FROM museum_admins WHERE email = ?");
		$stmt->execute([$email]);
		$admin = $stmt->fetch();

		// パスワード照合 (パスワードが未設定の場合はログイン不可)
		if ($admin && !empty($admin['password']) && password_verify($password, $admin['password'])) {
			
			// 6桁の認証コード生成
			$auth_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
			$expiry = date("Y-m-d H:i:s", strtotime("+10 minutes")); // 10分間有効

			// DBに保存
			$stmt_u = $pdo->prepare("UPDATE museum_admins SET auth_code = ?, auth_expiry = ? WHERE id = ?");
			$stmt_u->execute([$auth_code, $expiry, $admin['id']]);

			// 認証メール送信 (SVのロジックを流用)
			$subject = "【博物館ガイド】ログイン認証コード";
			$body = "ログインを完了するには、以下の認証コードを入力してください。\n\n";
			$body .= "認証コード： " . $auth_code . "\n\n";
			$body .= "※有効期限は10分間です。";
			$headers = "From: 博物館ガイドシステム <webmaster@" . $_SERVER['HTTP_HOST'] . ">";
			
			mb_send_mail($email, $subject, $body, $headers);

			// 一時的なセッションを保存して認証画面へ
			$_SESSION['admin_temp_id'] = $admin['id'];
			header('Location: auth_verify.php');
			exit;

		} else {
			$error_msg = "メールアドレスまたはパスワードが正しくありません。";
		}
	} else {
		$error_msg = "すべて入力してください。";
	}
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>管理者ログイン - 博物館ガイド</title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; }
		body { font-family: sans-serif; background-color: var(--bg-color); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
		.card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); width: 100%; max-width: 380px; text-align: center; }
		.form-group { text-align: left; margin-bottom: 20px; }
		label { display: block; font-size: 0.85em; font-weight: bold; color: #666; margin-bottom: 8px; }
		input { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ddd; box-sizing: border-box; }
		.btn { width: 100%; padding: 14px; border-radius: 30px; border: none; background: var(--primary-color); color: white; font-weight: bold; cursor: pointer; }
		.alert { background: #fff3f3; color: #d00; padding: 12px; border-radius: 10px; font-size: 0.85em; margin-bottom: 20px; border: 1px solid #ffcccc; }
	</style>
</head>
<body>
<div class="card">
	<h2>管理者ログイン</h2>
	<?php if ($error_msg): ?><div class="alert"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>
	<form method="POST">
		<div class="form-group">
			<label>メールアドレス</label>
			<input type="email" name="email" required value="<?= htmlspecialchars($email ?? '') ?>">
		</div>
		<div class="form-group">
			<label>パスワード</label>
			<input type="password" name="password" required>
		</div>
		<button type="submit" class="btn">認証コードを送信</button>
	</form>
</div>
</body>
</html>