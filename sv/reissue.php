<?php
require_once('../common/db_inc.php');
session_start();

mb_language("Japanese");
mb_internal_encoding("UTF-8");

$message = "";
$is_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = $_POST['email'];
	
	$stmt = $pdo->prepare("SELECT * FROM supervisors WHERE email = ?");
	$stmt->execute([$email]);
	$sv = $stmt->fetch();

	if ($sv) {
		$token = bin2hex(random_bytes(32));
		$expiry = date('Y-m-d H:i:s', strtotime('+30 minutes'));

		$stmt = $pdo->prepare("UPDATE supervisors SET reset_token = ?, reset_expiry = ? WHERE id = ?");
		$stmt->execute([$token, $expiry, $sv['id']]);

		$reset_url = "https://41mono.net/museum/sv/reset_password.php?token=" . $token;
		
		$to = $sv['email'];
		$subject = "【博物館ガイド】パスワード再設定のご案内";
		$body = $sv['name'] . " 様\n\n";
		$body .= "パスワードの再設定リクエストを受け付けました。\n";
		$body .= "以下のURLをクリックして、30分以内に新しいパスワードを設定してください。\n\n";
		$body .= $reset_url . "\n\n";
		$body .= "※心当たりがない場合は、このメールを破棄してください。";
		$headers = "From: info_museum@41mono.net";

		mb_send_mail($to, $subject, $body, $headers);
	}
	
	$message = "ご入力いただいたメールアドレス宛に、パスワード再設定のご案内を送信しました。<br>メールをご確認ください。";
	$is_success = true;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>パスワード再設定 - 博物館ガイド</title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; }
		body { font-family: sans-serif; background-color: var(--bg-color); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
		.card { background: white; padding: 40px 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); width: 100%; max-width: 350px; text-align: center; }
		h2 { color: #333; margin-bottom: 20px; }
		p.info { font-size: 0.9em; color: #666; margin-bottom: 25px; }
		input { width: 100%; padding: 14px 20px; border-radius: 30px; border: 1px solid #ddd; font-size: 16px; box-sizing: border-box; outline: none; margin-bottom: 20px; }
		input:focus { border-color: var(--primary-color); }
		.btn { width: 100%; padding: 14px; border-radius: 30px; border: none; background-color: var(--primary-color); color: white; font-size: 16px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; box-sizing: border-box; }
		.links { margin-top: 20px; }
		.links a { color: #888; text-decoration: none; font-size: 13px; }
		.message { background: #eef9f7; color: var(--primary-color); padding: 15px; border-radius: 10px; border: 1px solid var(--primary-color); }
	</style>
</head>
<body>
<div class="card">
	<h2>パスワードの再設定</h2>

	<?php if ($message): ?>
		<div class="message"><?= $message ?></div>
		<!-- ★ 変更：class="btn"を削除してリンク文字に戻しました -->
		<div class="links" style="margin-top:30px;">
			<a href="login.php">ログインページへ戻る</a>
		</div>
	<?php else: ?>
		<p class="info">登録済みのメールアドレスを入力してください。<br>再設定用のリンクを送信します。</p>
		<form method="POST">
			<input type="email" name="email" placeholder="メールアドレス" required>
			<button type="submit" class="btn">送信する</button>
		</form>
		<div class="links">
			<a href="login.php">ログインページへ戻る</a>
		</div>
	<?php endif; ?>
</div>
</body>
</html>