<?php
// /admin/exhibit_edit.php
require_once('../common/db_inc.php');
session_start();

if (!isset($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
$admin_id = $_SESSION['admin_id'];
$exhibit_id = $_GET['id'] ?? null;
$museum_id = $_GET['m_id'] ?? null;

if (!$exhibit_id || !$museum_id) { header('Location: index.php'); exit; }

// データの取得
$sql = "SELECT e.*, m.name_ja AS museum_name 
		FROM exhibits e 
		JOIN museums m ON e.museum_id = m.id 
		JOIN admin_museum_permissions amp ON m.id = amp.museum_id 
		WHERE e.id = ? AND e.museum_id = ? AND amp.admin_id = ? AND e.deleted_at IS NULL";
$stmt = $pdo->prepare($sql);
$stmt->execute([$exhibit_id, $museum_id, $admin_id]);
$exhibit = $stmt->fetch();

if (!$exhibit) { header('Location: index.php'); exit; }

$error_msg = "";

// 更新処理
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
				// 古いファイルを削除（お掃除）
				if (!empty($exhibit['image_path']) && file_exists("../" . $exhibit['image_path'])) {
					unlink("../" . $exhibit['image_path']);
				}
				$image_path = $new_path;
			}
		}

		$sql_u = "UPDATE exhibits SET 
					title_ja=?, title_en=?, title_zh=?, 
					desc_ja=?, desc_en=?, desc_zh=?, 
					image_path=?, status=?, updated_at=CURRENT_TIMESTAMP 
				  WHERE id=?";
		$stmt_u = $pdo->prepare($sql_u);
		$stmt_u->execute([$title_ja, $title_en, $title_zh, $desc_ja, $desc_en, $desc_zh, $image_path, $status, $exhibit_id]);

		$pdo->commit();
		header("Location: exhibits.php?id=" . $museum_id . "&msg=updated");
		exit;
	} catch (Exception $e) {
		$pdo->rollBack();
		$error_msg = "更新エラーが発生しました。";
	}
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>展示物の編集 - <?= htmlspecialchars($exhibit['title_ja']) ?></title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; --border-color: #e9ecef; }
		body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; color: #333; }
		header { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
		.btn-back { text-decoration: none; color: #666; font-size: 0.9rem; }
		.container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
		.card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
		h2 { margin: 0 0 25px 0; font-size: 1.4rem; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; }

		label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 0.9rem; color: #555; }
		input[type="text"], textarea, select { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ccc; box-sizing: border-box; font-size: 1rem; margin-bottom: 20px; }
		
		.setup-grid { display: grid; grid-template-columns: 320px 1fr; gap: 40px; margin-bottom: 20px; }
		.preview-box { width: 100%; height: 220px; border: 2px dashed #ddd; border-radius: 15px; display: flex; align-items: center; justify-content: center; background: #fafafa; overflow: hidden; margin-bottom: 15px; }
		.preview-box img { width: 100%; height: 100%; object-fit: cover; }
		
		.tabs { display: flex; gap: 5px; margin-top: 30px; border-bottom: 1px solid #ddd; }
		.tab { padding: 12px 25px; background: #e0e0e0; border-radius: 12px 12px 0 0; cursor: pointer; font-weight: bold; color: #777; font-size: 0.9rem; }
		.tab.active { background: white; border: 1px solid #ddd; border-bottom: 2px solid white; color: var(--primary-color); margin-bottom: -1px; }
		
		.tab-content { padding: 30px 0; display: none; }
		.tab-content.active { display: block; }

		.tab-inner-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px dashed #eee; padding-bottom: 10px; }
		.tab-inner-title { font-size: 1rem; font-weight: bold; color: var(--primary-color); }

		.btn-translate { 
			background: white; color: var(--primary-color); border: 1px solid var(--primary-color); 
			padding: 8px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; 
			cursor: pointer; display: flex; align-items: center; gap: 6px; transition: 0.2s;
		}
		.btn-translate:hover { background: var(--primary-color); color: white; }

		.btn-group { display: flex; gap: 15px; margin-top: 40px; border-top: 1px solid var(--border-color); padding-top: 30px; }
		.btn { text-decoration: none; padding: 12px 35px; border-radius: 30px; font-weight: bold; cursor: pointer; border: 1px solid; font-size: 1rem; }
		.btn-primary { background: var(--primary-color); color: white; border-color: var(--primary-color); }
		.btn-outline { background: white; color: #666; border-color: #ddd; }
		.btn-sm { padding: 8px 15px; font-size: 0.8rem; background: #444; color: white; border: none; border-radius: 6px; cursor: pointer; }

		/* 翻訳中オーバーレイ */
		.loading-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; }
		.loading-box { background: white; padding: 30px; border-radius: 15px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.3); width: 280px; }
		.spinner { width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid var(--primary-color); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 15px; }
		@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
	</style>
</head>
<body>

<!-- 翻訳中ダイアログ -->
<div id="loadingOverlay" class="loading-overlay">
	<div class="loading-box">
		<div class="spinner"></div>
		<div id="loadingMessage" style="font-weight:bold; margin-bottom:15px;">翻訳を実行中...</div>
		<button type="button" class="btn-sm" style="background:#e63946;" onclick="cancelTranslate()">キャンセル</button>
	</div>
</div>

<header>
	<a href="exhibits.php?id=<?= $museum_id ?>" class="btn-back">← 展示物一覧に戻る</a>
	<div style="font-size:0.85rem; color:#aaa;">ID: #<?= $exhibit['id'] ?> / <?= htmlspecialchars($exhibit['museum_name']) ?></div>
</header>

<div class="container">
	<div class="card">
		<?php if ($error_msg): ?><div style="color:red; margin-bottom:20px;"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

		<form method="POST" enctype="multipart/form-data">
			<div class="setup-grid">
				<div class="image-upload-area">
					<label>展示物画像</label>
					<div class="preview-box">
						<img id="img_preview" src="../<?= htmlspecialchars($exhibit['image_path'] ?? '') ?>" style="<?= $exhibit['image_path'] ? '' : 'display:none;' ?>">
					</div>
					<input type="file" name="image" id="img_input" accept="image/*" style="font-size:0.8rem;">
				</div>
				<div>
					<label>公開ステータス</label>
					<select name="status">
						<option value="public" <?= $exhibit['status'] === 'public' ? 'selected' : '' ?>>公開中</option>
						<option value="private" <?= $exhibit['status'] === 'private' ? 'selected' : '' ?>>非公開（下書き）</option>
					</select>
					
					<div style="background:#f9f9f9; padding:15px; border-radius:10px; font-size:0.8rem; color:#888; border:1px solid #eee;">
						<p style="margin-top:0;"><b>内部管理用コード:</b> <?= htmlspecialchars($exhibit['e_code']) ?></p>
						<p style="margin-bottom:0;">※画像を差し替えると、古い画像は自動的に削除されます。</p>
					</div>
				</div>
			</div>

			<div class="tabs">
				<div class="tab active" onclick="switchTab('ja')">日本語</div>
				<div class="tab" onclick="switchTab('en')">英語</div>
				<div class="tab" onclick="switchTab('zh')">中国語</div>
			</div>

			<!-- 日本語タブ -->
			<div id="tab_ja" class="tab-content active">
				<div class="tab-inner-header">
					<div class="tab-inner-title">日本語の解説</div>
					<button type="button" class="btn-translate" onclick="runTranslate()">
						<span>🪄</span> 他言語を一括生成
					</button>
				</div>
				<label>展示物名（日本語）</label>
				<input type="text" name="title_ja" id="title_ja" value="<?= htmlspecialchars($exhibit['title_ja']) ?>">
				
				<label>解説文（日本語）</label>
				<textarea name="desc_ja" id="desc_ja" rows="8"><?= htmlspecialchars($exhibit['desc_ja']) ?></textarea>
				
				<button type="button" class="btn-sm" style="background:#f8f9fa; color:#333; border:1px solid #ddd;" onclick="testTTS('ja')">🔊 日本語の音声を試聴</button>
			</div>

			<!-- 英語タブ -->
			<div id="tab_en" class="tab-content">
				<div class="tab-inner-header">
					<div class="tab-inner-title">English Guide</div>
				</div>
				<label>Exhibit Title (English)</label>
				<input type="text" name="title_en" id="title_en" value="<?= htmlspecialchars($exhibit['title_en']) ?>">
				
				<label>Description (English)</label>
				<textarea name="desc_en" id="desc_en" rows="8"><?= htmlspecialchars($exhibit['desc_en']) ?></textarea>
				
				<button type="button" class="btn-sm" style="background:#f8f9fa; color:#333; border:1px solid #ddd;" onclick="testTTS('en')">🔊 Play Voice (EN)</button>
			</div>

			<!-- 中国語タブ -->
			<div id="tab_zh" class="tab-content">
				<div class="tab-inner-header">
					<div class="tab-inner-title">中文指南</div>
				</div>
				<label>展示物名称 (中文)</label>
				<input type="text" name="title_zh" id="title_zh" value="<?= htmlspecialchars($exhibit['title_zh']) ?>">
				
				<label>解说词 (中文)</label>
				<textarea name="desc_zh" id="desc_zh" rows="8"><?= htmlspecialchars($exhibit['desc_zh']) ?></textarea>
				
				<button type="button" class="btn-sm" style="background:#f8f9fa; color:#333; border:1px solid #ddd;" onclick="testTTS('zh')">🔊 播放声音 (CN)</button>
			</div>

			<div class="btn-group">
				<button type="submit" class="btn btn-primary">変更を保存する</button>
				<a href="exhibits.php?id=<?= $museum_id ?>" class="btn btn-outline" style="text-decoration:none;">キャンセル</a>
			</div>
		</form>
	</div>
</div>

<script>
let abortController = null;

function switchTab(lang) {
	document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
	document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
	event.currentTarget.classList.add('active');
	document.getElementById('tab_' + lang).classList.add('active');
}

document.getElementById('img_input').onchange = function(e) {
	const reader = new FileReader();
	reader.onload = function(e) {
		document.getElementById('img_preview').src = e.target.result;
		document.getElementById('img_preview').style.display = 'block';
	}
	reader.readAsDataURL(e.target.files[0]);
};

function cancelTranslate() {
	if (abortController) {
		abortController.abort();
		document.getElementById('loadingOverlay').style.display = 'none';
		alert("翻訳を中断しました。");
	}
}

async function runTranslate() {
	const name = document.getElementById('title_ja').value;
	const desc = document.getElementById('desc_ja').value;
	if(!name) { alert('展示物名（日本語）を入力してください'); return; }

	const overlay = document.getElementById('loadingOverlay');
	const msg = document.getElementById('loadingMessage');
	overlay.style.display = 'flex';
	
	abortController = new AbortController();
	const signal = abortController.signal;

	const targets = [
		{id:'title_en', text:name, lang:'EN', label:'名称を英語に翻訳中...'},
		{id:'title_zh', text:name, lang:'ZH', label:'名称を中国語に翻訳中...'},
		{id:'desc_en', text:desc, lang:'EN', label:'説明文を英語に翻訳中...'},
		{id:'desc_zh', text:desc, lang:'ZH', label:'説明文を中国語に翻訳中...'}
	];

	try {
		for (const t of targets) {
			if(!t.text) continue;
			msg.innerText = t.label;
			const fd = new FormData();
			fd.append('text', t.text);
			fd.append('target_lang', t.lang);

			const res = await fetch('translate_ajax.php', { method: 'POST', body: fd, signal: signal });
			const data = await res.json();
			if (data.translated_text) document.getElementById(t.id).value = data.translated_text;
		}
		overlay.style.display = 'none';
	} catch (err) {
		if (err.name === 'AbortError') { console.log('Aborted'); } 
		else { alert("翻訳エラーが発生しました。"); overlay.style.display = 'none'; }
	} finally { abortController = null; }
}

function testTTS(lang) {
	const id = lang === 'ja' ? 'desc_ja' : (lang === 'en' ? 'desc_en' : 'desc_zh');
	const text = document.getElementById(id).value;
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