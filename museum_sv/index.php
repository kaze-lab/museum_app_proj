<?php
require_once('../common/db_inc.php');
require_once('_header.php'); // ヘッダーを読み込み、ログインチェックも実行

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$m_code = $_POST['m_code'];
	$name_ja = $_POST['name_ja'];
	$password = $_POST['password'];

	// --- バリデーション ---
	// 1. 必須項目の空チェック
	if (empty($m_code) || empty($name_ja) || empty($password)) {
		$error_msg = "すべての項目を入力してください。";
	}
	// 2. 博物館コードのフォーマットチェック（半角英数字とアンダースコアのみ）
	elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $m_code)) {
		$error_msg = "博物館コードは半角英数字とアンダースコア(_)のみ使用できます。";
	}
	// 3. 博物館コードの重複チェック
	else {
		$stmt = $pdo->prepare("SELECT COUNT(*) FROM museums WHERE m_code = ?");
		$stmt->execute([$m_code]);
		if ($stmt->fetchColumn() > 0) {
			$error_msg = "その博物館コードは既に使用されています。";
		}
	}

	// エラーがなければデータベースに登録
	if (empty($error_msg)) {
		try {
			// ★ パスワードは必ずハッシュ化して保存する
			$hashed_password = password_hash($password, PASSWORD_DEFAULT);

			$sql = "INSERT INTO museums (m_code, name_ja, password) VALUES (?, ?, ?)";
			$stmt = $pdo->prepare($sql);
			$stmt->execute([$m_code, $name_ja, $hashed_password]);

			// 成功したら一覧ページにリダイレクト
			header("Location: index.php?msg=added");
			exit;

		} catch (PDOException $e) {
			$error_msg = "データベースへの登録に失敗しました。";
		}
	}
}
?>
<title>新しい博物館の登録 - 博物館ガイド</title>
<style>
	.card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
	.card-header { padding-bottom: 20px; margin-bottom: 25px; border-bottom: 1px solid var(--border-color); }
	.card-header h2 { margin: 0; font-size: 1.5em; }
	.form-group { margin-bottom: 20px; position: relative; }
	label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 0.9em; }
	input { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ccc; box-sizing: border-box; }
	.info-text { font-size: 0.8em; color: #888; margin-top: 5px; }
	.btn-group { display: flex; gap: 10px; margin-top: 30px; }
	.btn { text-decoration: none; padding: 12px 25px; border-radius: 25px; font-weight: bold; font-size: 14px; border: 1px solid; cursor: pointer; text-align: center; }
	.btn-primary { background: var(--primary-color); color: white; border-color: var(--primary-color); }
	.btn-outline { background: white; color: #555; border-color: #ddd; }
	.alert { background: #fff3f3; color: #d00; padding: 12px; border-radius: 10px; margin-bottom: 20px; }
	.toggle-password { position: absolute; right: 15px; top: 40px; cursor: pointer; color: #888; }
</style>

<div class="container">
	<div class="card">
		<div class="card-header">
			<h2>新しい博物館の登録</h2>
		</div>

		<?php if ($error_msg): ?>
			<div class="alert"><?= htmlspecialchars($error_msg) ?></div>
		<?php endif; ?>

		<form method="POST">
			<div class="form-group">
				<label for="name_ja">博物館名</label>
				<input type="text" id="name_ja" name="name_ja" value="<?= htmlspecialchars($_POST['name_ja'] ?? '') ?>" required>
			</div>

			<div class="form-group">
				<label for="m_code">博物館コード</label>
				<input type="text" id="m_code" name="m_code" value="<?= htmlspecialchars($_POST['m_code'] ?? '') ?>" required>
				<p class="info-text">管理者がログインIDとして使用します。半角英数字とアンダースコア(_)のみ使用可能です。</p>
			</div>
			
			<div class="form-group">
				<label for="password">管理者用の初期パスワード</label>
				<input type="password" id="password" name="password" required>
				 <span class="toggle-password" onclick="togglePassword()">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>
						<line id="eye-slash" x1="1" y1="1" x2="23" y2="23"></line>
					</svg>
				</span>
				<p class="info-text">このパスワードを博物館の管理者に伝えてください。</p>
			</div>

			<div class="btn-group">
				<a href="index.php" class="btn btn-outline">キャンセル</a>
				<button type="submit" class="btn btn-primary">登録する</button>
			</div>
		</form>
	</div>
</div>

<script>
function togglePassword() {
	const passInput = document.getElementById('password');
	const eyeSlash = document.getElementById('eye-slash');
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