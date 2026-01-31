<?php
// /admin/exhibit_edit.php
require_once('../common/db_inc.php');
session_start();

// 1. ログインチェック
if (!isset($_SESSION['admin_logged_in'])) {
	header('Location: login.php');
	exit;
}

$admin_id = $_SESSION['admin_id'];
$exhibit_id = $_GET['id'] ?? null;
$museum_id = $_GET['m_id'] ?? null;

if (!$exhibit_id || !$museum_id) {
	header('Location: index.php');
	exit;
}

// 2. 権限チェック ＆ 展示物データの取得
$sql = "
	SELECT 
		e.*, 
		m.name_ja AS museum_name 
	FROM 
		exhibits e
	JOIN 
		museums m ON e.museum_id = m.id
	JOIN 
		admin_museum_permissions amp ON m.id = amp.museum_id
	WHERE 
		e.id = ? 
		AND e.museum_id = ? 
		AND amp.admin_id = ? 
		AND e.deleted_at IS NULL
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$exhibit_id, $museum_id, $admin_id]);
$exhibit = $stmt->fetch();

if (!$exhibit) {
	header('Location: index.php');
	exit;
}

$error_msg = "";

// 3. 更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$status = $_POST['status'] ?? 'private';
	$title_ja = trim($_POST['title_ja']);
	$title_en = trim($_POST['title_en']);
	$title_zh = trim($_POST['title_zh']);
	$desc_ja = trim($_POST['desc_ja']);
	$desc_en = trim($_POST['desc_en']);
	$desc_zh = trim($_POST['desc_zh']);

	if (empty($title_ja)) {
		$error_msg = "展示物名（日本語）は必須です。";
	}

	if (empty($error_msg)) {
		try {
			$pdo->beginTransaction();

			// 画像の処理
			$image_path = $exhibit['image_path'];
			if (!empty($_FILES['image']['name'])) {
				$upload_dir = "../uploads/museums/{$museum_id}/exhibits/";
				if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
				
				$file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
				$filename = "ex_" . bin2hex(random_bytes(8)) . "." . $file_ext;
				if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
					$image_path = "uploads/museums/{$museum_id}/exhibits/" . $filename;
				}
			}

			$sql_u = "UPDATE exhibits SET 
						title_ja = ?, title_en = ?, title_zh = ?, 
						desc_ja = ?, desc_en = ?, desc_zh = ?, 
						image_path = ?, status = ?, updated_at = CURRENT_TIMESTAMP
					  WHERE id = ?";
			$stmt_u = $pdo->prepare($sql_u);
			$stmt_u->execute([
				$title_ja, $title_en, $title_zh, 
				$desc_ja, $desc_en, $desc_zh, 
				$image_path, $status, $exhibit_id
			]);

			$pdo->commit();
			header("Location: exhibits.php?id=" . $museum_id . "&msg=updated");
			exit;

		} catch (Exception $e) {
			$pdo->rollBack();
			$error_msg = "更新エラーが発生しました。";
		}
	}
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>展示物の編集 - <?= htmlspecialchars($exhibit['museum_name']) ?></title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; --border-color: #e9ecef; }
		body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; color: #333; }
		header { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
		.btn-back { text-decoration: none; color: #666; font-size: 0.9rem; }
		.container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
		.card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
		h2 { margin: 0 0 30px 0; font-size: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; }
		label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 0.9rem; color: #555; }
		input[type="text"], textarea { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ccc; box-sizing: border-box; font-size: 1rem; margin-bottom: 20px; }
		.setup-grid { display: grid; grid-template-columns: 280px 1fr; gap: 40px; margin-bottom: 40px; }
		.preview-box { width: 100%; height: 200px; border: 1px solid #eee; border-radius: 15px; display: flex; align-items: center; justify-content: center; background: #fafafa; overflow: hidden; margin-top: 10px; }
		.preview-box img { width: 100%; height: 100%; object-fit: cover; }
		.tabs { display: flex; gap: 5px; margin-bottom: -1px; }
		.tab { padding: 12px 25px; background: #e0e0e0; border: 1px solid #ccc; border-bottom: none; border-radius: 12px 12px 0 0; cursor: pointer; font-weight: bold; color: #777; font-size: 0.9rem; }
		.tab.active { background: white; border-bottom: 2px solid white; color: var(--primary-color); }
		.tab-content { border: 1px solid #ccc; padding: 30px; border-radius: 0 20px 20px 20px; background: white; display: none; }
		.tab-content.active { display: block; }
		.btn-group { display: flex; gap: 15px; margin-top: 40px; border-top: 1px solid var(--border-color); padding-top: 30px; }
		.btn { text-decoration: none; padding: 12px 30px; border-radius: 30px; font-weight: bold; cursor: pointer; border: 1px solid; font-size: 1rem; }
		.btn-primary { background: var(--primary-color); color: white; border-color: var(--primary-color); }
		.btn-outline { background: white; color: #666; border-color: #ddd; }
		.btn-translate { background: #444; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; }
		.btn-voice { background: #f8f9fa; border: 1px solid #ddd; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; }
		.alert { background: #fff3f3; color: #d00; padding: 15px; border-radius: 10px; margin-bottom: 25px; border: 1px solid #ffcccc; }
	</style>
</head>
<body>

<header>
	<a href="exhibits.php?id=<?= $museum_id ?>" class="btn-back">← 展示物一覧に戻る</a>
	<div style="font-size:0.85rem; color:#888;">ログイン中: <?= htmlspecialchars($_SESSION['admin_name'] ?? '管理者') ?></div>
</header>

<div class="container">
	<div class="card">
		<h2>展示物の編集 <small style="font-weight:normal; color:#aaa;">ID #<?= $exhibit['id'] ?></small></h2>
		
		<?php if ($error_msg): ?><div class="alert"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

		<form method="POST" enctype="multipart/form-data">
			<div class="setup-grid">
				<div>
					<label>展示物画像</label>
					<input type="file" name="image" id="img_input" accept="image/*">
					<div class="preview-box">
						<img id="img_preview" src="../<?= htmlspecialchars($exhibit['image_path'] ?? '') ?>" style="<?= $exhibit['image_path'] ? '' : 'display:none;' ?>">
						<?php if (!$exhibit['image_path']): ?><span id="preview_txt" style="color:#ccc; font-size:0.8rem;">No Image</span><?php endif; ?>
					</div>
				</div>
				<div>
					<label>公開ステータス</label>
					<div style="margin-top:15px;">
						<label style="font-weight:normal; display:inline-flex; align-items:center; margin-bottom:12px; cursor:pointer;">
							<input type="radio" name="status" value="public" <?= $exhibit['status'] === 'public' ? 'checked' : '' ?> style="width:auto; margin-right:10px;"> 公開中
						</label><br>
						<label style="font-weight:normal; display:inline-flex; align-items:center; cursor:pointer;">
							<input type="radio" name="status" value="private" <?= $exhibit['status'] === 'private' ? 'checked' : '' ?> style="width:auto; margin-right:10px;"> 非公開
						</label>
					</div>
					<p style="margin-top:30px; font-size:0.8rem; color:#888;">
						※ID #<?= $exhibit['id'] ?> はこの展示物固有の管理番号です。
					</p>
				</div>
			</div>

			<div class="tabs">
				<div class="tab active" onclick="switchTab('ja')">日本語</div>
				<div class="tab" onclick="switchTab('en')">英語</div>
				<div class="tab" onclick="switchTab('zh')">中国語</div>
			</div>

			<div id="tab_ja" class="tab-content active">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
					<label style="margin:0;">日本語情報</label>
					<button type="button" class="btn-translate" onclick="doTranslate()">未入力の言語を一括生成</button>
				</div>
				<input type="text" name="title_ja" id="title_ja" value="<?= htmlspecialchars($exhibit['title_ja']) ?>">
				<textarea name="desc_ja" id="desc_ja" rows="8"><?= htmlspecialchars($exhibit['desc_ja']) ?></textarea>
				<button type="button" class="btn-voice" onclick="testTTS('ja')">🔊 音声を聴く</button>
			</div>

			<div id="tab_en" class="tab-content">
				<label>Name (English)</label>
				<input type="text" name="title_en" id="title_en" value="<?= htmlspecialchars($exhibit['title_en']) ?>">
				<label>Description (English)</label>
				<textarea name="desc_en" id="desc_en" rows="8"><?= htmlspecialchars($exhibit['desc_en']) ?></textarea>
				<button type="button" class="btn-voice" onclick="testTTS('en')">🔊 Play Voice</button>
			</div>

			<div id="tab_zh" class="tab-content">
				<label>名称 (中文)</label>
				<input type="text" name="title_zh" id="title_zh" value="<?= htmlspecialchars($exhibit['title_zh']) ?>">
				<label>说明 (中文)</label>
				<textarea name="desc_zh" id="desc_zh" rows="8"><?= htmlspecialchars($exhibit['desc_zh']) ?></textarea>
				<button type="button" class="btn-voice" onclick="testTTS('zh')">🔊 播放声音</button>
			</div>

			<div class="btn-group">
				<a href="exhibits.php?id=<?= $museum_id ?>" class="btn btn-outline">キャンセル</a>
				<button type="submit" class="btn btn-primary">変更を保存する</button>
			</div>
		</form>
	</div>
</div>

<script>
document.getElementById('img_input').onchange = function(e) {
	const reader = new FileReader();
	reader.onload = function(e) {
		document.getElementById('img_preview').src = e.target.result;
		document.getElementById('img_preview').style.display = 'block';
	}
	reader.readAsDataURL(e.target.files[0]);
};
function switchTab(lang) {
	document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
	document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
	event.currentTarget.classList.add('active');
	document.getElementById('tab_' + lang).classList.add('active');
}
function doTranslate() {
	const title = document.getElementById('title_ja').value;
	const desc = document.getElementById('desc_ja').value;
	if (!title) return;
	if (confirm('日本語の内容を元に、空いている欄に翻訳を生成しますか？')) {
		if(!document.getElementById('title_en').value) document.getElementById('title_en').value = title + " [EN]";
		if(!document.getElementById('title_zh').value) document.getElementById('title_zh').value = title + " [ZH]";
		if(!document.getElementById('desc_en').value) document.getElementById('desc_en').value = desc + "\n(Translated)";
		if(!document.getElementById('desc_zh').value) document.getElementById('desc_zh').value = desc + "\n(翻译)";
	}
}
function testTTS(lang) {
	const text = document.getElementById('desc_' + lang).value;
	if (!text) return;
	const uttr = new SpeechSynthesisUtterance(text);
	if (lang === 'ja') uttr.lang = 'ja-JP';
	else if (lang === 'en') uttr.lang = 'en-US';
	else if (lang === 'zh') uttr.lang = 'zh-CN';
	window.speechSynthesis.cancel();
	window.speechSynthesis.speak(uttr);
}
</script>
</body>
</html>