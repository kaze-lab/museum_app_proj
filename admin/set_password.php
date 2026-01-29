<?php
require_once('../common/db_inc.php');
session_start();

// メール送信用設定（日本語対応）
mb_language("Japanese");
mb_internal_encoding("UTF-8");

$token = $_GET['token'] ?? '';
$error_msg = "";
$success = false;
$invited_museums = [];

// 1. トークンの有効性チェック
if (!empty($token)) {
	$stmt = $pdo->prepare("SELECT * FROM museum_admins WHERE reset_token = ? AND reset_expiry > NOW()");
	$stmt->execute([$token]);
	$admin = $stmt->fetch();

	if ($admin) {
		// 2. 招待されている博物館の名前を取得
		$sql_m = "SELECT m.name_ja FROM museums m JOIN admin_museum_permissions amp ON m.id = amp.museum_id WHERE amp.admin_id = ?";
		$st_m = $pdo->prepare($sql_m);
		$st_m->execute([$admin['id']]);
		$invited_museums = $st_m->fetchAll(PDO::FETCH_COLUMN);
	} else {
		$error_msg = "このリンクは無効か、有効期限が切れています。";
	}
} else {
	$error_msg = "不正なアクセスです。";
}

// 3. フォーム送信時の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($admin)) {
	$name = trim($_POST['name']);
	$new_pass = $_POST['new_password'];
	$new_pass_conf = $_POST['new_password_conf'];

	if (empty($name) || empty($new_pass)) {
		$error_msg = "すべて入力してください。";
	} elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[\S]{12,}$/', $new_pass)) {
		$error_msg = "パスワードは大文字・小文字・数字を含む12文字以上にしてください。";
	} elseif ($new_pass !== $new_pass_conf) {
		$error_msg = "確認用パスワードが一致しません。";
	}

	if (empty($error_msg)) {
		try {
			$pdo->beginTransaction();

			// パスワードと名前を更新し、トークンを消去
			$hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
			$stmt_u = $pdo->prepare("UPDATE museum_admins SET name = ?, password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
			$stmt_u->execute([$name, $hashed_pass, $admin['id']]);
			
			// --- ★ 4. 設定完了メールの送信ロジック ---
			$museums_str = implode('、', $invited_museums);
			$login_url = "https://" . $_SERVER['HTTP_HOST'] . "/museum/admin/login.php";
			$support_url = "https://" . $_SERVER['HTTP_HOST'] . "/museum/support/";

			$subject = "【重要】博物館管理システム アカウント設定完了のお知らせ";
			$mail_body = "{$name} 様\n\n";
			$mail_body .= "博物館管理システムのアカウント設定が完了しました。\n";
			$mail_body .= "担当施設: {$museums_str}\n\n";
			$mail_body .= "今後は以下のURLからログインしてご利用ください。\n\n";
			$mail_body .= "■ ログインURL（ログインには認証コードが必要です）\n";
			$mail_body .= $login_url . "\n\n";
			$mail_body .= "■ 管理者用サポートページ（マニュアル・FAQ）\n";
			$mail_body .= $support_url . "\n\n";
			$mail_body .= "※このメールは大切に保管してください。";

			$headers = "From: 博物館ガイドシステム <webmaster@" . $_SERVER['HTTP_HOST'] . ">";
			
			mb_send_mail($admin['email'], $subject, $mail_body, $headers);
			// ----------------------------------------

			$pdo->commit();
			$success = true;

		} catch (PDOException $e) {
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
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>アカウント設定 - 博物館ガイド</title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; }
		body { font-family: sans-serif; background-color: var(--bg-color); display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
		.card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); width: 100%; max-width: 440px; }
		h2 { text-align: center; color: #333; margin-top: 0; margin-bottom: 25px; font-size: 1.5rem; }
		.invitation-box { background: #f0fdfa; border: 1px solid #ccf2ea; border-radius: 12px; padding: 20px; margin-bottom: 25px; text-align: center; }
		.invitation-label { font-size: 0.8rem; color: #26b396; font-weight: bold; margin-bottom: 8px; display: block; }
		.museum-names { font-size: 1.1rem; font-weight: bold; color: #333; line-height: 1.4; }
		.form-group { margin-bottom: 20px; }
		label { display: block; font-size: 0.85em; font-weight: bold; color: #666; margin-bottom: 8px; }
		input { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ddd; box-sizing: border-box; font-size: 1rem; }
		.rule { font-size: 0.75em; color: #888; margin-top: 6px; line-height: 1.5; }
		.btn { width: 100%; padding: 14px; border-radius: 30px; border: none; background: var(--primary-color); color: white; font-weight: bold; cursor: pointer; margin-top: 10px; font-size: 1rem; transition: 0.2s; }
		.btn:hover { opacity: 0.9; }
		.alert { background: #fff3f3; color: #d00; padding: 15px; border-radius: 10px; font-size: 0.85em; margin-bottom: 20px; border: 1px solid #ffcccc; }
		.success-box { text-align: center; }
	</style>
</head>
<body>
<div class="card">
	<?php if ($success): ?>
		<div class="success-box">
			<h2>設定完了</h2>
			<p style="color:#666; line-height:1.6;">アカウントの設定が完了しました。<br>ご登録のメールアドレスに、ログインURLを送信しましたのでご確認ください。</p>
			<a href="login.php" class="btn" style="display:block; text-decoration:none;">ログイン画面へ進む</a>
		</div>
	<?php else: ?>
		<h2>アカウントの初期設定</h2>
		<?php if ($error_msg): ?><div class="alert"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>
		<?php if (isset($admin)): ?>
			<div class="invitation-box">
				<span class="invitation-label">招待された博物館</span>
				<div class="museum-names"><?= implode('<br>', array_map('htmlspecialchars', $invited_museums)) ?></div>
			</div>
			<p style="font-size:0.85rem; color:#666; margin-bottom:25px; text-align:center;">お名前とパスワードを設定してください。</p>
			<form method="POST">
				<div class="form-group">
					<label>あなたのお名前</label>
					<input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="例：博物館 太郎" required autofocus>
				</div>
				<div class="form-group">
					<label>ログインパスワード</label>
					<input type="password" name="new_password" required>
					<div class="rule">12文字以上、大文字・小文字・数字をすべて含めてください。</div>
				</div>
				<div class="form-group">
					<label>パスワード（確認用）</label>
					<input type="password" name="new_password_conf" required>
				</div>
				<button type="submit" class="btn">設定を完了する</button>
			</form>
		<?php else: ?>
			<div style="text-align:center; padding:20px;">
				<a href="../index.php" style="color:#888; text-decoration:none; font-size:0.9em;">トップページに戻る</a>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
</body>
</html>