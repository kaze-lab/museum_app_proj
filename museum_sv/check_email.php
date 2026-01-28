<?php
require_once('../common/db_inc.php');
$email = $_GET['email'] ?? '';
$res = ['exists' => false];

if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
	$stmt = $pdo->prepare("SELECT COUNT(*) FROM museum_admins WHERE email = ?");
	$stmt->execute([$email]);
	if ($stmt->fetchColumn() > 0) {
		$res['exists'] = true;
	}
}
echo json_encode($res);