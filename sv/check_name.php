<?php
require_once('../common/db_inc.php');
$name = trim($_GET['name'] ?? '');
$exclude_id = $_GET['exclude_id'] ?? 0;
$res = ['exists' => false];

if ($name !== '') {
	$stmt = $pdo->prepare("SELECT COUNT(*) FROM museums WHERE name_ja = ? AND id != ?");
	$stmt->execute([$name, $exclude_id]);
	if ($stmt->fetchColumn() > 0) { $res['exists'] = true; }
}
header('Content-Type: application/json');
echo json_encode($res);