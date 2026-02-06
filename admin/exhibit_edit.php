<?php
// /admin/exhibit_edit.php
require_once('../common/db_inc.php');
session_start();

if (!isset($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
$admin_id = $_SESSION['admin_id'];
$exhibit_id = $_GET['id'] ?? null;
$museum_id = $_GET['m_id'] ?? null;

if (!$exhibit_id || !$museum_id) { header('Location: index.php'); exit; }

$sql = "SELECT e.*, m.name_ja AS museum_name FROM exhibits e JOIN museums m ON e.museum_id = m.id JOIN admin_museum_permissions amp ON m.id = amp.museum_id WHERE e.id = ? AND e.museum_id = ? AND amp.admin_id = ? AND e.deleted_at IS NULL";
$stmt = $pdo->prepare($sql);
$stmt->execute([$exhibit_id, $museum_id, $admin_id]);
$exhibit = $stmt->fetch();
if (!$exhibit) { header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$status = $_POST['status'] ?? 'private';
	$title_ja = trim($_POST['title_ja']);
	$title_en = trim($_POST['title_en']);
	$title_zh = trim($_POST['title_zh']);
	$desc_ja = trim($_POST['desc_ja']);
	$desc_en = trim($_POST['desc_en']);
	$desc_zh = trim($_POST['desc_zh']);

	try {
		$pdo->beginTransaction();
		$image_path = $exhibit['image_path'];
		if (!empty($_FILES['image']['name'])) {
			$dir = "../uploads/museums/{$museum_id}/exhibits/";
			$new_path = saveImageAsWebP($_FILES['image'], $dir, 'ex_');
			if ($new_path) {
                // 【お掃除ロジック】新しい画像が成功したら古いファイルを消す
                if (!empty($exhibit['image_path']) && file_exists("../" . $exhibit['image_path'])) {
                    unlink("../" . $exhibit['image_path']);
                }
                $image_path = $new_path;
            }
		}

		$sql_u = "UPDATE exhibits SET title_ja=?, title_en=?, title_zh=?, desc_ja=?, desc_en=?, desc_zh=?, image_path=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?";
		$stmt_u = $pdo->prepare($sql_u);
		$stmt_u->execute([$title_ja, $title_en, $title_zh, $desc_ja, $desc_en, $desc_zh, $image_path, $status, $exhibit_id]);

		$pdo->commit();
		header("Location: exhibits.php?id=" . $museum_id . "&msg=updated");
		exit;
	} catch (Exception $e) { $pdo->rollBack(); }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>展示物の編集</title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; --border-color: #e9ecef; }
		body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; color: #333; }
		header { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
		.container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
		.card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
		label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 0.9rem; color: #555; }
		input[type="text"], textarea { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ccc; box-sizing: border-box; margin-bottom: 20px; }
		.setup-grid { display: grid; grid-template-columns: 280px 1fr; gap: 40px; margin-bottom: 40px; }
		.preview-box { width: 100%; height: 200px; border: 1px solid #eee; border-radius: 15px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
		.preview-box img { width: 100%; height: 100%; object-fit: cover; }
		.tabs { display: flex; gap: 5px; margin-bottom: -1px; }
		.tab { padding: 12px 25px; background: #e0e0e0; border-radius: 12px 12px 0 0; cursor: pointer; font-weight: bold; color: #777; font-size: 0.9rem; }
		.tab.active { background: white; border: 1px solid #ccc; border-bottom: 2px solid white; color: var(--primary-color); }
		.tab-content { border: 1px solid #ccc; padding: 30px; border-radius: 0 20px 20px 20px; background: white; display: none; }
		.tab-content.active { display: block; }
		.btn-group { display: flex; gap: 15px; margin-top: 40px; border-top: 1px solid var(--border-color); padding-top: 30px; }
		.btn { padding: 12px 30px; border-radius: 30px; font-weight: bold; cursor: pointer; border: 1px solid; }
		.btn-primary { background: var(--primary-color); color: white; border-color: var(--primary-color); }
		.btn-outline { background: white; color: #666; border-color: #ddd; text-decoration: none; }
	</style>
</head>
<body>
<header><a href="exhibits.php?id=<?= $museum_id ?>" style="text-decoration:none; color:#666;">← 戻る</a><div style="font-weight:bold;">展示物の編集</div><div style="font-size:0.8rem; color:#aaa;">ID: #<?= $exhibit['id'] ?></div></header>
<div class="container">
	<div class="card">
		<form method="POST" enctype="multipart/form-data">
			<div class="setup-grid">
				<div><label>展示物画像</label><input type="file" name="image" id="img_input" accept="image/*"><div class="preview-box"><img id="img_preview" src="../<?= htmlspecialchars($exhibit['image_path'] ?? '') ?>"></div></div>
				<div><label>公開ステータス</label><input type="radio" name="status" value="public" <?= $exhibit['status'] === 'public' ? 'checked' : '' ?>> 公開 <input type="radio" name="status" value="private" <?= $exhibit['status'] === 'private' ? 'checked' : '' ?>> 非公開</div>
			</div>
			<div class="tabs"><div class="tab active" onclick="switchTab('ja')">日本語</div><div class="tab" onclick="switchTab('en')">英語</div><div class="tab" onclick="switchTab('zh')">中国語</div></div>
			<div id="tab_ja" class="tab-content active"><input type="text" name="title_ja" id="title_ja" value="<?= htmlspecialchars($exhibit['title_ja']) ?>"><textarea name="desc_ja" id="desc_ja" rows="8"><?= htmlspecialchars($exhibit['desc_ja']) ?></textarea></div>
			<div id="tab_en" class="tab-content"><input type="text" name="title_en" value="<?= htmlspecialchars($exhibit['title_en']) ?>"><textarea name="desc_en" rows="8"><?= htmlspecialchars($exhibit['desc_en']) ?></textarea></div>
			<div id="tab_zh" class="tab-content"><input type="text" name="title_zh" value="<?= htmlspecialchars($exhibit['title_zh']) ?>"><textarea name="desc_zh" rows="8"><?= htmlspecialchars($exhibit['desc_zh']) ?></textarea></div>
			<div class="btn-group"><button type="submit" class="btn btn-primary">保存する</button></div>
		</form>
	</div>
</div>
<script>
function switchTab(lang) { document.querySelectorAll('.tab').forEach(el => el.classList.remove('active')); document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active')); event.currentTarget.classList.add('active'); document.getElementById('tab_' + lang).classList.add('active'); }
document.getElementById('img_input').onchange = function(e) { const reader = new FileReader(); reader.onload = function(e) { document.getElementById('img_preview').src = e.target.result; }; reader.readAsDataURL(e.target.files[0]); };
</script>
</body>
</html>