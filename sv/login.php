<?php
// /sv/login.php
require_once('../common/db_inc.php');
session_start();

if (isset($_SESSION['sv_logged_in'])) {
	header('Location: index.php');
	exit;
}

$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = $_POST['email'];
	$pass = $_POST['password'];

	$stmt = $pdo->prepare("SELECT * FROM supervisors WHERE email = ?");
	$stmt->execute([$email]);
	$sv = $stmt->fetch();

	if ($sv && password_verify($pass, $sv['password'])) {
		$auth_code = sprintf('%05d', mt_rand(0, 99999));
		$expiry = date('Y-m-d H:i:s', strtotime('+30 minutes'));

		$stmt = $pdo->prepare("UPDATE supervisors SET auth_code = ?, auth_expiry = ? WHERE id = ?");
		$stmt->execute([$auth_code, $expiry, $sv['id']]);

		// --- 【文字化け対策版】メール送信ロジック ---
		$subject = "【博物館ガイド】全体管理ログイン認証コード";
		$body = $sv['name'] . " 様\n\n";
		$body .= "システム全体管理（SV）へのログインを承りました。\n";
		$body .= "以下の認証コードを入力してログインを完了させてください。\n\n";
		$body .= "認証コード： " . $auth_code . "\n\n";
		$body .= "※有効期限は30分間です。";

		mb_language("uni");
		mb_internal_encoding("UTF-8");

		$from_email = "info_museum@41mono.net";
		$from_name = "博物館ガイド管理事務局";
		
		$headers = "From: " . mb_encode_mimeheader($from_name, "UTF-8") . " <{$from_email}>\r\n";
		$headers .= "Reply-To: {$from_email}\r\n";
		$headers .= "Content-Type: text/plain; charset=UTF-8";

		if (mb_send_mail($sv['email'], $subject, $body, $headers, "-f " . $from_email)) {
			$_SESSION['auth_sv_id'] = $sv['id'];
			header('Location: auth.php');
			exit;
		} else {
			$error = true;
		}
	} else {
		$error = true;
	}
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>全体管理 ログイン (SV) - 博物館ガイド</title>
	<style>
		:root { --primary-color: #34495e; --bg-color: #f4f7f7; --text-color: #333; }
		body { font-family: sans-serif; background-color: var(--bg-color); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
		.login-card { background: white; padding: 40px 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.12); width: 100%; max-width: 360px; text-align: center; border-top: 6px solid var(--primary-color); }
		h2 { color: var(--text-color); margin: 0 0 10px 0; font-size: 1.3em; }
		.subtitle { font-size: 0.8rem; color: #888; margin-bottom: 30px; line-height: 1.4; }
		.input-group { margin-bottom: 15px; text-align: left; }
		label { display: block; font-size: 0.8em; font-weight: bold; color: #777; margin-bottom: 5px; }
		input { width: 100%; padding: 14px 15px; border-radius: 10px; border: 1px solid #ddd; font-size: 16px; box-sizing: border-box; outline: none; }
		input:focus { border-color: var(--primary-color); }
		.btn-login { width: 100%; padding: 14px; border-radius: 30px; border: none; background-color: var(--primary-color); color: white; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 15px; }
		.links { margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; }
		.links a { color: #888; text-decoration: none; display: block; margin-bottom: 10px; }
		.modal-overlay { display: <?= $error ? 'flex' : 'none' ?>; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
		.modal-content { background: white; padding: 25px; border-radius: 15px; text-align: center; width: 80%; max-width: 280px; }
		.btn-retry { background: var(--primary-color); color: white; border: none; padding: 8px 25px; border-radius: 20px; cursor: pointer; margin-top: 15px; }
	</style>
</head>
<body>
<div class="login-card">
	<h2>全体管理 ログイン</h2>
	<p class="subtitle">新規博物館の登録・システム設定を行う<br>スーパーバイザー専用の入口です。</p>
	<form method="POST">
		<div class="input-group"><label>管理用メールアドレス</label><input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
		<div class="input-group"><label>パスワード</label><input type="password" name="password" required></div>
		<button type="submit" class="btn-login">ログイン</button>	
	</form>
	<div class="links">
		<a href="../admin/login.php">博物館スタッフ(展示物編集)の方はこちら</a>
		<a href="reissue.php">パスワードを忘れた場合</a>
	</div>
</div>
<div class="modal-overlay" id="errorModal"><div class="modal-content"><p>認証に失敗しました。</p><button class="btn-retry" onclick="document.getElementById('errorModal').style.display='none'">閉じる</button></div></div>
</body>
</html>