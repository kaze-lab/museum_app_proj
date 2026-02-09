<?php
// /admin/login.php
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

		// パスワード照合
		if ($admin && !empty($admin['password']) && password_verify($password, $admin['password'])) {
			
			// 6桁の認証コード生成
			$auth_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
			$expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

			// DBに保存
			$stmt_u = $pdo->prepare("UPDATE museum_admins SET auth_code = ?, auth_expiry = ? WHERE id = ?");
			$stmt_u->execute([$auth_code, $expiry, $admin['id']]);

			// --- 【文字化け対策版】メール送信ロジック ---
			$subject = "【博物館ガイド】ログイン認証コード";
			$body = "博物館管理システムへのログインを承りました。\n";
			$body .= "以下の認証コードを入力してログインを完了させてください。\n\n";
			$body .= "認証コード： " . $auth_code . "\n\n";
			$body .= "※有効期限は10分間です。";

			mb_language("uni"); // UTF-8を扱う設定
			mb_internal_encoding("UTF-8");

			$from_email = "info_museum@41mono.net";
			$from_name = "博物館ガイドシステム";
			
			// 日本語の名前をMIMEエンコードし、ヘッダーを作成
			$headers = "From: " . mb_encode_mimeheader($from_name, "UTF-8") . " <{$from_email}>\r\n";
			$headers .= "Reply-To: {$from_email}\r\n";
			$headers .= "Content-Type: text/plain; charset=UTF-8";

			// mb_send_mail を使用（第5引数に -f を指定して到達率を向上）
			mb_send_mail($email, $subject, $body, $headers, "-f " . $from_email);
			// ------------------------------------------

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
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>スタッフ ログイン - 博物館ガイド</title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; --text-color: #333; }
		body { font-family: sans-serif; background-color: var(--bg-color); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
		.login-card { background: white; padding: 40px 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); width: 100%; max-width: 360px; text-align: center; border-top: 6px solid var(--primary-color); }
		h2 { color: var(--text-color); margin: 0 0 10px 0; font-size: 1.4em; }
		.subtitle { font-size: 0.8rem; color: #888; margin-bottom: 30px; line-height: 1.4; }
		.form-group { text-align: left; margin-bottom: 20px; }
		label { display: block; font-size: 0.85em; font-weight: bold; color: #666; margin-bottom: 8px; }
		input { width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid #ddd; box-sizing: border-box; outline: none; font-size: 16px; }
		input:focus { border-color: var(--primary-color); }
		.btn-login { width: 100%; padding: 14px; border-radius: 30px; border: none; background-color: var(--primary-color); color: white; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 10px; }
		.links { margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; }
		.links a { color: #aaa; text-decoration: none; display: block; margin-bottom: 10px; }
		.alert { background: #fff3f3; color: #d00; padding: 12px; border-radius: 10px; font-size: 0.85em; margin-bottom: 20px; text-align: center; border: 1px solid #ffcccc; }
	</style>
</head>
<body>
<div class="login-card">
	<h2>スタッフ ログイン</h2>
	<p class="subtitle">展示データの登録・編集を行う<br>博物館スタッフ専用の入口です。</p>
	<?php if ($error_msg): ?><div class="alert"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>
	<form method="POST">
		<div class="form-group"><label>メールアドレス</label><input type="email" name="email" required value="<?= htmlspecialchars($email ?? '') ?>"></div>
		<div class="form-group"><label>パスワード</label><input type="password" name="password" required></div>
		<button type="submit" class="btn-login">認証コードを送信</button>
	</form>
	<div class="links">
		<a href="../sv/login.php">システム全体管理者(SV)の方はこちら</a>
		<a href="reissue.php">パスワードを忘れた場合</a>
	</div>
</div>
</body>
</html>