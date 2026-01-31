<?php
// /admin/exhibit_add.php
require_once('../common/db_inc.php');
session_start();

// 1. ログインチェック（他ページと同期）
if (!isset($_SESSION['admin_logged_in'])) {
	header('Location: login.php');
	exit;
}

$admin_id = $_SESSION['admin_id'];
$museum_id = $_GET['id'] ?? null;

if (!$museum_id) {
	header('Location: index.php');
	exit;
}

// 2. 権限チェック（不正アクセス防止）
$sql_p = "
	SELECT m.name_ja 
	FROM admin_museum_permissions amp
	JOIN museums m ON amp.museum_id = m.id
	WHERE amp.admin_id = ? AND amp.museum_id = ? AND m.deleted_at IS NULL
";
$stmt_p = $pdo->prepare($sql_p);
$stmt_p->execute([$admin_id, $museum_id]);
$permission = $stmt_p->fetch();

if (!$permission) {
	header('Location: index.php');
	exit;
}

$error_msg = "";

// 3. 登録処理実行
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$status = $_POST['status'] ?? 'private';
	$title_ja = trim($_POST['title_ja']);
	$title_en = trim($_POST['title_en']);
	$title_zh = trim($_POST['title_zh']);
	$desc_ja = trim($_POST['desc_ja']);
	$desc_en = trim($_POST['desc_en']);
	$desc_zh = trim($_POST['desc_zh']);

	if (empty($title_ja)) {
		$error_msg = "展示物名（日本語）を入力してください。";
	}

	if (empty($error_msg)) {
		try {
			$pdo->beginTransaction();

			// ① e_code（システム識別用）の自動生成（博物館のm_codeと同様の仕組み）
			do {
				$e_code = bin2hex(random_bytes(4)); // 8文字のランダム英数字
				$st_c = $pdo->prepare("SELECT COUNT(*) FROM exhibits WHERE museum_id = ? AND e_code = ?");
				$st_c->execute([$museum_id, $e_code]);
			} while ($st_c->fetchColumn() > 0);

			// ② 画像アップロード処理
			$image_path = null;
			if (!empty($_FILES['image']['name'])) {
				$upload_dir = "../uploads/museums/{$museum_id}/exhibits/";
				if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
				
				$file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
				$filename = "ex_" . bin2hex(random_bytes(8)) . "." . $file_ext;
				if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
					$image_path = "uploads/museums/{$museum_id}/exhibits/" . $filename;
				}
			}

			// ③ DBへ保存
			$sql = "INSERT INTO exhibits (
						museum_id, e_code, title_ja, title_en, title_zh, 
						desc_ja, desc_en, desc_zh, image_path, status
					) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
			$stmt = $pdo->prepare($sql);
			$stmt->execute([
				$museum_id, $e_code, $title_ja, $title_en, $title_zh, 
				$desc_ja, $desc_en, $desc_zh, $image_path, $status
			]);

			$pdo->commit();
			header("Location: exhibits.php?id=" . $museum_id . "&msg=added");
			exit;

		} catch (Exception $e) {
			$pdo->rollBack();
			$error_msg = "登録エラーが発生しました。";
		}
	}
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>新規展示物の登録 - <?= htmlspecialchars($permission['name_ja']) ?></title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; --border-color: #e9ecef; }
		body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; color: #333; }
		
		header { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
		.btn-back { text-decoration: none; color: #666; font-size: 0.9rem; }
		
		.container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
		.card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
		h2 { margin: 0 0 30px 0; font-size: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; }
		
		label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 0.9rem; color: #555; }
		.req { color: #d00; margin-left: 3px; }
		input[type="text"], textarea, select { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #ccc; box-sizing: border-box; font-size: 1rem; margin-bottom: 20px; }
		
		/* プレビューとステータス */
		.setup-grid { display: grid; grid-template-columns: 280px 1fr; gap: 40px; margin-bottom: 40px; }
		.preview-box { width: 100%; height: 200px; border: 2px dashed #ddd; border-radius: 15px; display: flex; align-items: center; justify-content: center; background: #fafafa; overflow: hidden; margin-top: 10px; }
		.preview-box img { width: 100%; height: 100%; object-fit: cover; }
		
		/* 多言語タブ */
		.tabs { display: flex; gap: 5px; margin-bottom: -1px; }
		.tab { padding: 12px 25px; background: #e0e0e0; border: 1px solid #ccc; border-bottom: none; border-radius: 12px 12px 0 0; cursor: pointer; font-weight: bold; color: #777; font-size: 0.9rem; }
		.tab.active { background: white; border-bottom: 2px solid white; color: var(--primary-color); }
		.tab-content { border: 1px solid #ccc; padding: 30px; border-radius: 0 20px 20px 20px; background: white; display: none; }
		.tab-content.active { display: block; }
		
		.translate-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
		.btn-translate { background: #444; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; }
		.btn-voice { background: #f8f9fa; border: 1px solid #ddd; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px; }

		/* フッターボタン */
		.btn-group { display: flex; gap: 15px; margin-top: 40px; border-top: 1px solid var(--border-color); padding-top: 30px; }
		.btn { text-decoration: none; padding: 12px 30px; border-radius: 30px; font-weight: bold; cursor: pointer; border: 1px solid; font-size: 1rem; }
		.btn-primary { background: var(--primary-color); color: white; border-color: var(--primary-color); }
		.btn-outline { background: white; color: #666; border-color: #ddd; }
		
		.alert { background: #fff3f3; color: #d00; padding: 15px; border-radius: 10px; margin-bottom: 25px; border: 1px solid #ffcccc; font-size: 0.9rem; }
	</style>
</head>
<body>

<header>
	<a href="exhibits.php?id=<?= $museum_id ?>" class="btn-back">← 展示物一覧に戻る</a>
	<div style="font-size:0.85rem; color:#888;">ログイン中: <?= htmlspecialchars($_SESSION['admin_name'] ?? '管理者') ?></div>
</header>

<div class="container">
	<div class="card">
		<h2>新規展示物の登録</h2>
		
		<?php if ($error_msg): ?><div class="alert"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

		<form method="POST" enctype="multipart/form-data">
			<div class="setup-grid">
				<!-- 左側：画像設定 -->
				<div>
					<label>展示物画像</label>
					<input type="file" name="image" id="img_input" accept="image/*">
					<div class="preview-box" id="preview_box">
						<span id="preview_txt" style="color:#ccc; font-size:0.8rem;">プレビュー表示</span>
						<img id="img_preview" src="" style="display:none;">
					</div>
				</div>
				<!-- 右側：公開設定 -->
				<div>
					<label>公開ステータス<span class="req">*</span></label>
					<div style="margin-top:15px;">
						<label style="font-weight:normal; display:inline-flex; align-items:center; margin-bottom:12px; cursor:pointer;">
							<input type="radio" name="status" value="public" style="width:auto; margin-right:10px;"> 今すぐ公開する
						</label><br>
						<label style="font-weight:normal; display:inline-flex; align-items:center; cursor:pointer;">
							<input type="radio" name="status" value="private" checked style="width:auto; margin-right:10px;"> 非公開（下書き保存）
						</label>
					</div>
					<div style="margin-top:30px; background:#f9f9f9; padding:15px; border-radius:10px; font-size:0.8rem; color:#888; line-height:1.6;">
						※ 展示物ID（連番）は登録時に自動発行されます。<br>
						※ 削除したIDは永久欠番となります。
					</div>
				</div>
			</div>

			<!-- 多言語入力 -->
			<div class="tabs">
				<div class="tab active" onclick="switchTab('ja')">日本語 (必須)</div>
				<div class="tab" onclick="switchTab('en')">英語 (English)</div>
				<div class="tab" onclick="switchTab('zh')">中国語 (中文)</div>
			</div>

			<!-- 日本語タブ -->
			<div id="tab_ja" class="tab-content active">
				<div class="translate-header">
					<label style="margin:0;">日本語情報</label>
					<button type="button" class="btn-translate" onclick="doTranslate()">他言語を一括生成（自動翻訳）</button>
				</div>
				<input type="text" name="title_ja" id="title_ja" placeholder="展示物の名称を入力してください">
				<textarea name="desc_ja" id="desc_ja" rows="8" placeholder="展示物の解説文を入力してください"></textarea>
				<button type="button" class="btn-voice" onclick="testTTS('ja')">🔊 音声再生テスト</button>
			</div>

			<!-- 英語タブ -->
			<div id="tab_en" class="tab-content">
				<label>Name (English)</label>
				<input type="text" name="title_en" id="title_en">
				<label>Description (English)</label>
				<textarea name="desc_en" id="desc_en" rows="8"></textarea>
				<button type="button" class="btn-voice" onclick="testTTS('en')">🔊 Play Voice</button>
			</div>

			<!-- 中国語タブ -->
			<div id="tab_zh" class="tab-content">
				<label>展品名称 (中文)</label>
				<input type="text" name="title_zh" id="title_zh">
				<label>说明 (中文)</label>
				<textarea name="desc_zh" id="desc_zh" rows="8"></textarea>
				<button type="button" class="btn-voice" onclick="testTTS('zh')">🔊 播放声音</button>
			</div>

			<div class="btn-group">
				<a href="exhibits.php?id=<?= $museum_id ?>" class="btn btn-outline">キャンセル</a>
				<button type="submit" class="btn btn-primary">登録を実行する</button>
			</div>
		</form>
	</div>
</div>

<script>
// 画像プレビュー
document.getElementById('img_input').onchange = function(e) {
	const reader = new FileReader();
	reader.onload = function(e) {
		document.getElementById('img_preview').src = e.target.result;
		document.getElementById('img_preview').style.display = 'block';
		document.getElementById('preview_txt').style.display = 'none';
	}
	reader.readAsDataURL(e.target.files[0]);
};

// タブ切り替え
function switchTab(lang) {
	document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
	document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
	event.currentTarget.classList.add('active');
	document.getElementById('tab_' + lang).classList.add('active');
}

// 翻訳シミュレーション
function doTranslate() {
	const title = document.getElementById('title_ja').value;
	const desc = document.getElementById('desc_ja').value;
	if (!title) { alert('まずは日本語の名称を入力してください。'); return; }
	
	if (confirm('日本語の内容を元に、英語と中国語を自動翻訳で作成しますか？')) {
		// 実際にはここでAPI連携
		document.getElementById('title_en').value = title + " [Translated]";
		document.getElementById('title_zh').value = title + " [翻译]";
		document.getElementById('desc_en').value = desc + "\n\n(English translation follows...)";
		document.getElementById('desc_zh').value = desc + "\n\n(中文翻译如下...)";
		alert('翻訳が完了しました。各タブで内容を確認・修正してください。');
	}
}

// 音声再生 (Web Speech API)
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