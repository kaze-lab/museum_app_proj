<?php
require_once('../common/db_inc.php');
$name = trim($_GET['name'] ?? '');
$res = ['exists' => false];

if ($name !== '') {
	$stmt = $pdo->prepare("SELECT COUNT(*) FROM museums WHERE name_ja = ?");
	$stmt->execute([$name]);
	if ($stmt->fetchColumn() > 0) {
		$res['exists'] = true;
	}
}
header('Content-Type: application/json');
echo json_encode($res);