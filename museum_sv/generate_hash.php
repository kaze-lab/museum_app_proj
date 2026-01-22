<?php
// ここに暗号化したいパスワードを入力
$password_to_hash = 'museum_pass';

// ハッシュ値を生成して表示
echo password_hash($password_to_hash, PASSWORD_DEFAULT);
?>