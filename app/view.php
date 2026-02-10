<?php
// /app/view.php
require_once('../common/db_inc.php');
session_start();

// 1. URLパラメータから博物館コード(m)を取得
$m_code = $_GET['m'] ?? '';

if (empty($m_code)) {
	header('Location: index.php');
	exit;
}

// 2. 閲覧数を +1 する（非公開であっても、関心度としてカウント）
$stmt_upd = $pdo->prepare("UPDATE museums SET view_count = view_count + 1 WHERE m_code = ?");
$stmt_upd->execute([$m_code]);

// 3. 博物館の情報を取得（is_activeを問わず取得する）
$sql = "SELECT m.*, c.name as category_name 
		FROM museums m 
		LEFT JOIN categories c ON m.category_id = c.id 
		WHERE m.m_code = ? AND m.deleted_at IS NULL";
$stmt = $pdo->prepare($sql);
$stmt->execute([$m_code]);
$museum = $stmt->fetch();

if (!$museum) {
	echo "博物館が見つかりません。";
	exit;
}

// 言語設定（将来の多言語化への布石）
$lang = $_SESSION['app_lang'] ?? 'ja';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<title><?= htmlspecialchars($museum['name_ja']) ?> - 博物館ガイド</title>
	<style>
		:root { --primary: #26b396; --bg: #f4f7f6; --text: #333; }
		body { font-family: sans-serif; margin: 0; background: var(--bg); color: var(--text); padding-bottom: 50px; }
		
		/* ヒーロー画像 */
		.hero-img { width: 100%; height: 260px; object-fit: cover; background: #ddd; }
		
		.content { padding: 25px; margin-top: -30px; background: white; border-radius: 30px 30px 0 0; position: relative; min-height: 400px; box-shadow: 0 -10px 30px rgba(0,0,0,0.05); }
		
		.cat { color: var(--primary); font-weight: bold; font-size: 0.8rem; margin-bottom: 10px; display: block; }
		h1 { margin: 0 0 20px 0; font-size: 1.6rem; line-height: 1.3; }

		/* 紹介文 */
		.desc { line-height: 1.7; color: #555; font-size: 1rem; white-space: pre-wrap; margin-bottom: 40px; }

		/* 準備中メッセージ（is_active = 0 の時に表示） */
		.preparing-box {
			background: #fdf8f0; border: 1px solid #faead1; border-radius: 20px;
			padding: 30px 20px; text-align: center; margin: 20px 0;
		}
		.preparing-icon { font-size: 2.5rem; display: block; margin-bottom: 15px; }
		.preparing-title { font-weight: bold; color: #d68910; font-size: 1.1rem; margin-bottom: 10px; display: block; }
		.preparing-text { font-size: 0.9rem; color: #8d6e63; line-height: 1.6; }

		.btn-back {
			display: block; width: fit-content; margin: 30px auto; 
			color: #aaa; text-decoration: none; font-size: 0.9rem; font-weight: bold;
		}

		/* 基本情報セクション（将来的に電話番号や住所を出す用） */
		.info-section { border-top: 1px solid #eee; padding-top: 20px; margin-top: 40px; }
		.info-item { font-size: 0.85rem; color: #777; margin-bottom: 8px; display: flex; gap: 10px; }
	</style>
</head>
<body>

<img src="../<?= $museum['main_image'] ?: 'img/no-image.webp' ?>" class="hero-img">

<div class="content">
	<span class="cat"><?= htmlspecialchars($museum['category_name']) ?></span>
	<h1><?= htmlspecialchars($museum['name_ja']) ?></h1>

	<?php if ($museum['is_active'] == 1): ?>
		<!-- 【公開中】通常の紹介文を表示 -->
		<div class="desc"><?= htmlspecialchars($museum['description_ja'] ?: '紹介文は現在準備中です。') ?></div>
		
		<!-- ここに将来、展示物一覧などを追加します -->
		
	<?php else: ?>
		<!-- 【準備中】丁寧なアナウンスを表示 -->
		<div class="preparing-box">
			<span class="preparing-icon">🏛️</span>
			<span class="preparing-title">只今、リニューアル準備中</span>
			<p class="preparing-text">
				現在、この博物館の案内情報を整理しております。<br>
				より充実した解説をお届けするため、<br>
				公開まで今しばらくお待ちください。
			</p>
		</div>
	<?php endif; ?>

	<div class="info-section">
		<div class="info-item">📍 <?= htmlspecialchars($museum['address']) ?></div>
		<?php if($museum['phone_number']): ?>
			<div class="info-item">📞 <?= htmlspecialchars($museum['phone_number']) ?></div>
		<?php endif; ?>
	</div>

	<a href="index.php" class="btn-back">← 一覧に戻る</a>
</div>

</body>
</html>