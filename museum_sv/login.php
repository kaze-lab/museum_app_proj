
<?php

require_once('../common/db_inc.php');
session_start();

mb_language("Japanese");
mb_internal_encoding("UTF-8");

$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	
	
	$email = $_POST['email'];
	$pass = $_POST['password'];

	//メールアドレスでユーザーを検索
	$stmt = $pdo->prepare("SELECT * FROM supervisors WHERE email = ?");
	$stmt->execute([$email]);
	$sv = $stmt->fetch();

	if ($sv && password_verify($pass, $sv['password'])) {
		
		// 認証コードを生成(5桁30分有効)
		$auth_code = sprintf('%05d', mt_rand(0, 99999));
		$expiry = date('Y-m-d H:i:s', strtotime('+30 minutes'));

		// コード保存
		$stmt = $pdo->prepare("UPDATE supervisors SET auth_code = ?, auth_expiry = ? WHERE id = ?");
		$stmt->execute([$auth_code, $expiry, $sv['id']]);

		//メールを送信
		$to = $sv['email'];
		$subject = "【博物館ガイド】ログイン認証コードのお知らせ";
		$body = $sv['name'] . " 様\n\n";
		$body .= "博物館ガイド管理システムへのログインを承りました。\n";
		$body .= "以下の認証コードを入力してログインを完了させてください。\n\n";
		$body .= "認証コード： " . $auth_code . "\n\n";
		$body .= "※有効期限は30分間です。\n";
		$body .= "※心当たりがない場合は、このメールを破棄してください。";
		
		$headers = "From: info_museum@41mono.net";

		if (mb_send_mail($to, $subject, $body, $headers)) {
			// 送信成功：認証画面へ
			$_SESSION['auth_sv_id'] = $sv['id'];
			header('Location: auth.php');
			exit;
		} else {
			$message = "メール送信に失敗しました。サーバーの設定を確認してください。";
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
	<title>ログイン - 博物館ガイド</title>
	<style>
		/* カラーテーマ定義 */
		:root {
			--primary-color: #26b396;	/* メインカラー（グリーンシアン系） */
			--bg-color: #f4f7f7;		/* ページ背景（薄いグレー）*/
			--input-bg: #ffffff;		/* 入力欄の背景（白）*/
			--text-color: #333;			/* 文字の色（薄いグレー）*/
		}
		
		/* ログイン画面のレイアウト*/
		body {
			font-family: sans-serif;				/* 全体のフォント */
			background-color: var(--bg-color);		/* 背景色（テーマ変数） */
			
			/*ログインカードを画面中央に配置*/
			display: flex;							
			justify-content: center;				
			align-items: center;					
													
			height: 100vh;							
													
													
			margin: 0;								/* デフォルト余白をリセット*/
													
		}

		/* ログインカードの見た目*/
		.login-card {
			background: white;						/* 白背景でカードを際立たせる */
			padding: 40px 30px;						/* 内側余白（上下40px / 左右30px） */
			border-radius: 20px;					/* 角を丸くして柔らかい印象に */
			box-shadow: 0 4px 15px rgba(0,0,0,0.08);	/* ふわっと浮く影 */
			width: 100%;							/* 親幅いっぱい（狭い画面向け） */
			max-width: 350px;						/* カードの最大幅（広い画面向け） */
			text-align: center;						/* タイトルやボタンを中央揃え */
		}

		/* ログイン画面のタイトル */
		h2 {
			color: var(--text-color);				/* テキストカラー（テーマ変数） */
			margin-bottom: 30px;					/* タイトル下の余白 */
			font-size: 1.4em;						/* 少し大きめの文字サイズ */
		}
		

		/* 入力欄とアイコンのレイアウト用ラッパー */
		.input-group {
			position: relative;						/* 目アイコンを absolute で配置する基準 */
			margin-bottom: 15px;					/* 下の余白*/
		}
		
		/* ログイン画面の入力欄（メール・パスワード）を綺麗に見せるための CSS  */
		input {
			width: 100%;							/* 親幅いっぱいに広げる */
			padding: 14px 20px;						/* 内側余白（タップしやすくする） */
			border-radius: 30px;					/* 丸みのある入力欄 */
			border: 1px solid #ddd;					/* 薄いグレーの枠線*/
			background-color: var(--input-bg);		/* 入力欄の背景色 */
			font-size: 16px;						/* スマホでのズーム防止（iOS対策） */
			box-sizing: border-box;					/* padding/border を含めて幅を計算 */
			outline: none;							/* フォーカス枠を非表示 */
		}
		
		/* 入力欄フォーカス時の強調表示 */
		input:focus {
			border-color: var(--primary-color);		/* 入力中の欄を分かりやすくする */
		}

		/* パスワード欄「目」アイコン */
		.toggle-password {
			position: absolute;					/* input-group を基準に絶対配置 */
			right: 18px;						/* 右端に寄せる */
			top: 50%;							/* 縦中央に配置 */
			transform: translateY(-50%);		/* 完全な中央揃え */
			cursor: pointer;					/* クリック可能に見せる */
			color: #888;						/* 控えめなグレー */
			display: flex;						/* アイコンを中央揃え */
			align-items: center;				

		}

		/* ログインボタン*/
		.btn-login {
			width: 100%;						/* カード幅いっぱいに広げる */
			padding: 14px;						/* 押しやすい余白 */
			border-radius: 30px;				/* 丸みのあるボタン */
			border: none;						 /* フラットな見た目 */
			background-color: var(--primary-color);	/* テーマカラー*/
			color: white;						/* 文字色を白でコントラスト確保*/
			font-size: 16px;					/* スマホでのズーム防止（iOS対策） */
			font-weight: bold;					/* ボタンの視認性を上げる */
			cursor: pointer;					/* クリック可能に見せる */
			margin-top: 15px;					/* 上の余白 */
		}

		/* 補助リンク（パスワード再発行など） */
		.links {
			margin-top: 20px;		/* ボタンとの間隔を確保 */
			font-size: 13px;		/* メイン要素より控えめなサイズ */
		}

		/* 補助リンク（パスワード再発行など） */
		.links a {
			color: #888;				/* メイン要素より控えめな色 */
			text-decoration: none;		/* 下線を消してシンプルに */
		}

		/* エラー時に表示するモーダル背景 */
		.modal-overlay {
			display: <?= $error ? 'flex' : 'none' ?>;				/* PHPで表示/非表示を切り替え */
			position: fixed;										/* 画面全体を覆う */
			top: 0; left: 0; width: 100%; height: 100%;				
			background: rgba(0,0,0,0.5);							/* 半透明の黒背景 */
			justify-content: center; align-items: center;			/* 中央にモーダルを配置 */
			z-index: 1000;											/* 最前面に表示 */
		}

		/* エラーメッセージのポップアップ */
		.modal-content {
			background: white;					/* 半透明背景の上に浮かぶ白いボックス */
			padding: 25px;						/* 適度な内側余白 */
			border-radius: 15px;				/* 柔らかい印象の角丸 */
			text-align: center;					/* 中央揃え */
			width: 80%;							/* 画面幅に応じたサイズ */
			max-width: 280px;					/* ポップアップとして適切な最大幅 */
		}

		/* モーダル内の再試行ボタン*/
		.btn-retry {
			background: var(--primary-color);		/* 操作ボタンとして目立たせる */
			color: white;							/* コントラストを確保 */
			border: none;							/* シンプルな見た目 */
			padding: 8px 25px;						/* モーダル用の少し小さめサイズ */
			border-radius: 20px;					/* 柔らかい印象の角丸 */
			cursor: pointer;						/* クリック可能に見せる */
			margin-top: 15px;						/* 上の余白 */


		}
	</style>
</head>
<body>

<!-- ★ここから追加★ -->
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'reset_success'): ?>
<div style="position:fixed; top:20px; left:50%; transform:translateX(-50%); background:#26b396; color:white; padding:10px 20px; border-radius:20px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
    パスワードが正常に更新されました。
</div>
<?php endif; ?>
<!-- ★ここまで追加★ -->


<!-- ログインカード全体 -->
<div class="login-card">

	<!-- タイトル 	-->
	<h2>ログイン</h2>

	<!-- ログインフォーム -->
	<form method="POST">
		
		<!-- メール入力欄 -->
		<div class="input-group">	
			<input type="email" name="email" placeholder="メールアドレス" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
		</div>

		<!-- パスワード入力欄 -->
		<div class="input-group">
			<input type="password" name="password" id="password" placeholder="パスワード" required>
	
			<!-- パスワード表示切り替えアイコン -->
			<span class="toggle-password" onclick="togglePassword()">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					
					<!-- 目の形 -->
					<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>

					<!-- 瞳 -->
					<circle cx="12" cy="12" r="3"></circle>
					
					
					<!-- パスワード非表示の車線(JSで切り替え) -->
					<line id="eye-slash" x1="1" y1="1" x2="23" y2="23"></line>
				</svg>
			</span>
		</div>

		<!--送信ボタン -->
		<button type="submit" class="btn-login">ログイン</button>	
	</form>
	
	<!-- 補助リンク -->
	<div class="links">
		<a href="reissue.php">パスワードを忘れた方はこちら</a>
	</div>

</div>

<!-- エラー時に表示するモーダル全体 -->
<div class="modal-overlay" id="errorModal">

	<!-- エラーメッセージのポップアップ -->
	<div class="modal-content">
		<p>メールアドレスまたは<br>パスワードが正しくありません</p>
		
		<!-- モーダルを閉じるボタン -->
		<button class="btn-retry" onclick="document.getElementById('errorModal').style.display='none'">再入力</button>

	</div>
</div>

<script>
	/* パスワードの表示/非表示を切り替える */
	function togglePassword() {
		const passInput = document.getElementById('password');
		const eyeSlash = document.getElementById('eye-slash');

		if (passInput.type === 'password') {
			//表示する
			passInput.type = 'text';
			eyeSlash.style.display = 'none';
		} else {
			//隠す
			passInput.type = 'password';
			eyeSlash.style.display = 'block';
		}
	}
</script>

</body>
</html>