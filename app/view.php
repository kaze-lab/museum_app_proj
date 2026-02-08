<?php
// /app/view.php
require_once('../common/db_inc.php');

// 1. URLパラメータから博物館コード(m)を取得
$m_code = $_GET['m'] ?? '';

if (empty($m_code)) {
	header('Location: index.php');
	exit;
}

// 2. 閲覧数を +1 する（人気ランキングの元データ）
$stmt_upd = $pdo->prepare("UPDATE museums SET view_count = view_count + 1 WHERE m_code = ?");
$stmt_upd->execute([$m_code]);

// 3. 博物館の情報を取得
$sql = "SELECT m.*, c.name as category_name 
		FROM museums m 
		LEFT JOIN categories c ON m.category_id = c.id 
		WHERE m.m_code = ? AND m.is_active = 1 AND m.deleted_at IS NULL";
$stmt = $pdo->prepare($sql);
$stmt->execute([$m_code]);
$museum = $stmt->fetch();

if (!$museum) {
	echo "博物館が見つかりません。";
	exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($museum['name_ja']) ?></title>
	<style>
		body { font-family: sans-serif; margin: 0; background: #f4f7f6; color: #333; }
		.hero-img { width: 100%; height: 250px; object-fit: cover; }
		.container { padding: 20px; }
		.cat { color: #26b396; font-weight: bold; font-size: 0.8rem; }
		h1 { margin: 10px 0; font-size: 1.5rem; }
		.desc { line-height: 1.6; color: #666; white-space: pre-wrap; }
		.btn-back { display: inline-block; margin-top: 20px; color: #888; text-decoration: none; font-size: 0.9rem; }
	</style>
</head>
<body>

<img src="../<?= $museum['main_image'] ?: 'img/no-image.webp' ?>" class="hero-img">

<div class="container">
	<div class="cat"><?= htmlspecialchars($museum['category_name']) ?></div>
	<h1><?= htmlspecialchars($museum['name_ja']) ?></h1>
	
	<div class="desc"><?= htmlspecialchars($museum['description_ja']) ?></div>

	<a href="index.php" class="btn-back">← 一覧に戻る</a>
</div>

</body>
</html>