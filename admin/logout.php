<?php
session_start();

// 博物館管理者用のセッション情報をクリア
$_SESSION = array();

if (isset($_COOKIE[session_name()])) {
	setcookie(session_name(), '', time() - 42000, '/');
}

session_destroy();

// ログイン画面へリダイレクト
header('Location: login.php');
exit;