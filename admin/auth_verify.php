<?php
require_once('../common/db_inc.php');
session_start();

// 一時的なセッション（admin_temp_id）がない場合は、直接アクセスを禁止しログインへ戻す
if (!isset($_SESSION['admin_temp_id'])) {
	header('Location: login.php');
	exit;
}

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$code = trim($_POST['auth_code']);

	// データベースでID、認証コード、有効期限をチェック
	$stmt = $pdo->prepare("SELECT * FROM museum_admins WHERE id = ? AND auth_code = ? AND auth_expiry > NOW()");
	$stmt->execute([$_SESSION['admin_temp_id'], $code]);
	$admin = $stmt->fetch();

	if ($admin) {
		// --- 認証成功 ---
		
		// セッションの固定化攻撃対策
		session_regenerate_id(true);

		// 正式なログイン情報をセッションに格納
		$_SESSION['admin_logged_in'] = true;
		$_SESSION['admin_id'] = $admin['id'];
		$_SESSION['admin_email'] = $admin['email'];
		$_SESSION['admin_name'] = $admin['name']; // ログイン後の「こんにちは、〇〇さん」用

		// 使用済みの認証コードをDBから消去（セキュリティ）
		$pdo->prepare("UPDATE museum_admins SET auth_code = NULL, auth_expiry = NULL WHERE id = ?")->execute([$admin['id']]);

		// 一時セッションを消去
		unset($_SESSION['admin_temp_id']);

		// ダッシュボードへ
		header('Location: index.php');
		exit;
	} else {
		$error_msg = "認証コードが正しくないか、有効期限が切れています。もう一度確認してください。";
	}
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>認証コード確認 - 博物館管理者</title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; }
		body { font-family: sans-serif; background-color: var(--bg-color); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; color: #333; }
		.card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); width: 100%; max-width: 380px; text-align: center; }
		h2 { margin-top: 0; color: #333; }
		p { font-size: 0.9rem; color: #666; margin-bottom: 25px; line-height: 1.5; }
		input[type="text"] { width: 100%; padding: 15px; border-radius: 10px; border: 1px solid #ddd; box-sizing: border-box; text-align: center; font-size: 1.8rem; letter-spacing: 0.3em; font-weight: bold; outline: none; }
		input:focus { border-color: var(--primary-color); }
		.btn { width: 100%; padding: 14px; border-radius: 30px; border: none; background: var(--primary-color); color: white; font-weight: bold; cursor: pointer; margin-top: 25px; font-size: 1rem; }
		.alert { background: #fff3f3; color: #d00; padding: 12px; border-radius: 10px; font-size: 0.85em; margin-bottom: 20px; border: 1px solid #ffcccc; }
		.footer-link { margin-top: 25px; }
		.footer-link a { color: #888; text-decoration: none; font-size: 0.85rem; }
	</style>
</head>
<body>
<div class="card">
	<h2>認証コードの確認</h2>
	<p>ご登録のメールアドレスに送信された<br><strong>6桁の認証コード</strong>を入力してください。</p>

	<?php if ($error_msg): ?><div class="alert"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

	<form method="POST">
		<!-- 半角数字のみ、6文字を強制 -->
		<input type="text" name="auth_code" maxlength="6" pattern="\d{6}" placeholder="000000" required autofocus autocomplete="one-time-code">
		<button type="submit" class="btn">ログインを完了する</button>
	</form>

	<div class="footer-link">
		<a href="login.php">← ログイン画面に戻る</a>
	</div>
</div>
</body>
</html>