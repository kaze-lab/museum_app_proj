<?php
require_once('../common/db_inc.php');
session_start();

if (!isset($_SESSION['sv_logged_in'])) {
    header('Location: login.php');
    exit;
}

$error_msg = "";
$is_force = isset($_SESSION['force_change']); 

$stmt = $pdo->prepare("SELECT * FROM supervisors WHERE id = ?");
$stmt->execute([$_SESSION['sv_id']]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = $_POST['name'];
    $new_email = $_POST['email'];
    $new_pass = $_POST['new_password'];
    $new_pass_conf = $_POST['new_password_conf'];
    $current_pass = $_POST['current_password'];

    // --- バリデーション開始 ---

    if (!password_verify($current_pass, $user['password'])) {
        $error_msg = "現在のパスワードが正しくありません。";
    } 
    elseif ($is_force && empty($new_pass)) {
        $error_msg = "初回ログインのため、新しいパスワードを設定してください。";
    }
    elseif (!empty($new_pass)) {
        // ★ 変更：正規表現を[\S]（空白以外の文字）に修正
        $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[\S]{12,}$/';
        
        if (!preg_match($pattern, $new_pass)) {
            // ★ 変更：エラーメッセージをより親切に
            $error_msg = "パスワードは「大文字・小文字・数字を各1文字以上含む、12文字以上の文字列」にしてください。記号も使用可能です。";
        } 
        elseif ($new_pass !== $new_pass_conf) {
            $error_msg = "新しいパスワードと確認用が一致しません。";
        }
        elseif ($new_pass === $current_pass) {
            $error_msg = "現在のパスワードと同じものは設定できません。";
        }
    }

    if (empty($error_msg)) {
        try {
            $pdo->beginTransaction();
            
            $sql = "UPDATE supervisors SET name = ?, email = ?, is_first_login = 0 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_name, $new_email, $_SESSION['sv_id']]);

            if (!empty($new_pass)) {
                $stmt = $pdo->prepare("UPDATE supervisors SET password = ? WHERE id = ?");
                $stmt->execute([password_hash($new_pass, PASSWORD_DEFAULT), $_SESSION['sv_id']]);
            }

            $pdo->commit();
            $_SESSION['sv_name'] = $new_name;
            unset($_SESSION['force_change']);
            header("Location: index.php?msg=updated");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "更新に失敗しました。";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アカウント情報 - 博物館ガイド</title>
    <style>
        :root { --primary-color: #26b396; --bg-color: #f4f7f7; }
        body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; padding: 20px; }
        .card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); max-width: 450px; margin: auto; }
        h2 { text-align: center; color: #333; margin-bottom: 25px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 0.85em; color: #666; margin-bottom: 5px; }
        input { width: 100%; padding: 12px; border-radius: 25px; border: 1px solid #ddd; box-sizing: border-box; outline: none; }
        input:focus { border-color: var(--primary-color); }
        .rule-box { background: #f9f9f9; padding: 15px; border-radius: 10px; font-size: 0.8em; color: #777; margin-bottom: 20px; border: 1px solid #eee; }
        .btn-group { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 25px; }
        .btn { padding: 12px; border-radius: 25px; border: none; cursor: pointer; font-weight: bold; text-align: center; text-decoration: none; font-size: 14px; }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-outline { background: white; color: #888; border: 1px solid #ddd; }
        .alert { background: #fff3f3; color: #d00; padding: 12px; border-radius: 10px; font-size: 0.85em; margin-bottom: 20px; text-align: center; border: 1px solid #ffcccc; }
    </style>
</head>
<body>

<div class="card">
    <h2>アカウント情報</h2>

    <?php if ($error_msg): ?>
        <div class="alert"><?= htmlspecialchars($error_msg) ?></div>
    <?php elseif ($is_force): ?>
        <div class="alert" style="background: #eef9f7; color: var(--primary-color); border-color: var(--primary-color);">
            初回ログインです。安全のため<br>パスワードを新しく設定してください。
        </div>
    <?php endif; ?>

    <!-- ★ 変更：ルールの説明に記号のことを追記 -->
    <div class="rule-box">
        <strong>パスワード設定ルール：</strong><br>
        ・12文字以上であること<br>
        ・英大文字、英小文字、数字をそれぞれ1文字以上含むこと<br>
        ・アンダースコア(_)などの記号も使用できます
    </div>

    <form method="POST">
        <div class="form-group">
            <label>氏名</label>
            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
        </div>
        <div class="form-group">
            <label>メールアドレス</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>
        
        <div style="background: #fcfcfc; padding: 15px; border-radius: 15px; border: 1px solid #f0f0f0; margin-top: 20px;">
            <div class="form-group">
                <label>新しいパスワード</label>
                <input type="password" name="new_password" placeholder="12文字以上（大文字小文字数字含む）">
            </div>
            <div class="form-group">
                <label>新しいパスワード（確認用）</label>
                <input type="password" name="new_password_conf" placeholder="もう一度入力してください">
            </div>
        </div>

        <div class="form-group" style="margin-top: 30px; padding-top: 20px; border-top: 2px dashed #eee;">
            <label style="color: var(--primary-color); font-weight: bold;">現在のパスワード（本人確認）</label>
            <input type="password" name="current_password" required placeholder="現在のパスワードを入力して確定">
        </div>

        <div class="btn-group">
            <?php if (!$is_force): ?>
                <a href="index.php" class="btn btn-outline">中止</a>
            <?php else: ?>
                <div></div> <!-- 初回強制変更時は「中止」ボタンを表示しないためのスペーサー -->
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">更新する</button> <!-- ボタン名を「OK」から「更新する」に変更 -->
        </div>
    </form>
</div>

</body>
</html>