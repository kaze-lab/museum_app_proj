<?php
require_once('../common/db_inc.php');
session_start();

// ログインプロセスを経ていない場合はログイン画面へ戻す
if (!isset($_SESSION['auth_sv_id'])) {
    header('Location: login.php');
    exit;
}

$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['auth_code'];
    
    // コードと有効期限をチェック
    $stmt = $pdo->prepare("SELECT * FROM supervisors WHERE id = ? AND auth_code = ? AND auth_expiry > NOW()");
    $stmt->execute([$_SESSION['auth_sv_id'], $code]);
    $sv = $stmt->fetch();

    if ($sv) {
        // 認証成功：セッションを確立
        $_SESSION['sv_logged_in'] = true;
        $_SESSION['sv_id'] = $sv['id'];
        $_SESSION['sv_name'] = $sv['name'];
        
        // 使用済みコードをクリア
        $pdo->prepare("UPDATE supervisors SET auth_code = NULL WHERE id = ?")->execute([$sv['id']]);
        

        // --- ここを修正：初回フラグをチェック ---
        if ($sv['is_first_login'] == 1) {
            // 初回ならパスワード変更画面へ（メッセージを添える）
            $_SESSION['force_change'] = true; 
            header('Location: account.php');
        } else {
            header('Location: index.php');
        }

        exit;
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
    <title>認証 - 博物館ガイド</title>
    <style>
        :root {
            --primary-color: #26b396;
            --bg-color: #f4f7f7;
            --input-bg: #ffffff;
            --text-color: #333;
        }
        body {
            font-family: sans-serif;
            background-color: var(--bg-color);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .auth-card {
            background: white;
            padding: 50px 30px;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 350px;
            text-align: center;
        }
        p { color: #666; line-height: 1.6; margin-bottom: 30px; font-size: 0.95em; }
        
        .input-group { margin-bottom: 20px; }
        
        input {
            width: 100%;
            padding: 15px;
            border-radius: 30px;
            border: 1px solid #ddd;
            background-color: var(--input-bg);
            font-size: 24px; /* コード入力なので大きく */
            text-align: center;
            letter-spacing: 8px; /* 数字の間隔を広げる */
            box-sizing: border-box;
            outline: none;
            color: var(--text-color);
        }
        input:focus { border-color: var(--primary-color); }

        .btn-auth {
            width: 100%;
            padding: 14px;
            border-radius: 30px;
            border: none;
            background-color: var(--primary-color);
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-auth:hover { opacity: 0.9; }

        .error-msg { color: #e74c3c; font-size: 0.85em; margin-top: 15px; }
        
        .links { margin-top: 25px; font-size: 13px; }
        .links a { color: #888; text-decoration: none; }
    </style>
</head>
<body>

<div class="auth-card">
    <p>メールに届いた<br><strong>5桁の認証コード</strong>を入力してください</p>
    
    <form method="POST">
        <div class="input-group">
            <!-- 数字5桁のみ、キーボードも数字が出るように設定 -->
            <input type="text" name="auth_code" inputmode="numeric" pattern="\d{5}" maxlength="5" placeholder="00000" required autofocus>
        </div>
        <button type="submit" class="btn-auth">認証する</button>
    </form>

    <?php if ($error): ?>
        <p class="error-msg">認証コードが正しくないか、<br>有効期限が切れています</p>
    <?php endif; ?>
    
    <div class="links">
        <a href="login.php">ログイン画面に戻る</a>
    </div>
</div>

</body>
</html>