<?php
// /sv/museum_edit.php
require_once('../common/db_inc.php');
session_start();

// 1. SVログインチェック
if (!isset($_SESSION['sv_logged_in'])) {
	header('Location: login.php');
	exit;
}

$id = $_GET['id'] ?? null;
if (!$id) { header('Location: index.php'); exit; }

// カテゴリ一覧
$categories = $pdo->query("SELECT * FROM categories ORDER BY id")->fetchAll();

// 博物館情報 ＋ 筆頭管理者のメール取得
$sql = "SELECT m.*, ma.email 
		FROM museums m 
		LEFT JOIN admin_museum_permissions amp ON m.id = amp.museum_id AND amp.role = 'admin'
		LEFT JOIN museum_admins ma ON amp.admin_id = ma.id
		WHERE m.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$museum = $stmt->fetch();

if (!$museum) { header('Location: index.php'); exit; }

$error_msg = "";

// 2. 更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name_ja = trim($_POST['name_ja']);
	$name_kana = trim($_POST['name_kana']);
	$category_id = $_POST['category_id'];
	$address = trim($_POST['address']);
	$phone_number = $_POST['phone_number'];
	$website_url = $_POST['website_url'];
	$is_active = (int)$_POST['is_active'];
	$ad_type = (int)$_POST['ad_type'];
	$ad_custom_link = trim($_POST['ad_custom_link']);
	$notes = $_POST['notes'];

	if (empty($name_ja) || empty($name_kana)) {
		$error_msg = "必須項目を入力してください。";
	}

	if (empty($error_msg)) {
		try {
			$pdo->beginTransaction();

			// 自館広告画像の処理（アップロードされた場合）
			$ad_custom_image = $museum['ad_custom_image'];
			if (!empty($_FILES['ad_image']['name'])) {
				$dir = "../uploads/museums/{$id}/ads/";
				$new_ad_path = saveImageAsWebP($_FILES['ad_image'], $dir, 'ad_');
				if ($new_ad_path) {
					if ($ad_custom_image && file_exists("../" . $ad_custom_image)) unlink("../" . $ad_custom_image);
					$ad_custom_image = $new_ad_path;
				}
			}

			// 更新SQL
			$sql_u = "UPDATE museums SET 
						name_ja = ?, name_kana = ?, category_id = ?, 
						address = ?, phone_number = ?, website_url = ?, 
						is_active = ?, ad_type = ?, ad_custom_image = ?, ad_custom_link = ?, 
						notes = ?, updated_at = NOW() 
					  WHERE id = ?";
			$pdo->prepare($sql_u)->execute([
				$name_ja, $name_kana, $category_id, 
				$address, $phone_number, $website_url, 
				$is_active, $ad_type, $ad_custom_image, $ad_custom_link, 
				$notes, $id
			]);

			$pdo->commit();
			header("Location: index.php?msg=updated");
			exit;

		} catch (Exception $e) { 
			$pdo->rollBack(); 
			$error_msg = "更新エラー: " . $e->getMessage(); 
		}
	}
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>博物館の編集 - SV管理</title>
	<style>
		:root { --primary-color: #34495e; --accent-color: #26b396; --bg-color: #f4f7f7; --border-color: #e9ecef; }
		body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; padding: 40px 0; color: #333; }
		.container { max-width: 900px; margin: auto; padding: 0 20px; }
		.card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
		
		.header-flex { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 20px; margin-bottom: 30px; }
		h2 { margin: 0; font-size: 1.5rem; color: var(--primary-color); }
		
		.section-title { font-size: 1rem; font-weight: bold; color: var(--primary-color); background: #f8f9fa; padding: 10px 15px; border-left: 5px solid var(--primary-color); margin: 30px 0 20px; }
		
		.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
		.full-width { grid-column: 1 / -1; }
		
		label { display: block; font-weight: bold; margin-bottom: 8px; font-size: 0.9rem; color: #555; }
		input, select, textarea { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ccc; box-sizing: border-box; font-size: 1rem; }
		
		/* 広告設定エリア */
		.ad-config-box { background: #f0f4f8; padding: 20px; border-radius: 12px; border: 1px solid #d1d9e6; }

		.btn-group { display: flex; gap: 15px; margin-top: 40px; padding-top: 30px; border-top: 1px solid var(--border-color); }
		.btn { text-decoration: none; padding: 12px 35px; border-radius: 30px; font-weight: bold; cursor: pointer; border: 1px solid; font-size: 1rem; }
		.btn-primary { background: var(--primary-color); color: white; border-color: var(--primary-color); }
		.btn-outline { background: white; color: #666; border-color: #ddd; }
		
		.btn-sm { padding: 8px 15px; font-size: 0.75rem; background: var(--accent-color); color: white; border: none; border-radius: 6px; cursor: pointer; }
	</style>
</head>
<body>

<div class="container">
	<div class="card">
		<div class="header-flex">
			<h2>博物館情報の編集</h2>
			<div style="font-size:0.8rem; color:#888;">ID: <?= $museum['id'] ?> / Code: <?= $museum['m_code'] ?></div>
		</div>

		<form method="POST" enctype="multipart/form-data">
			
			<div class="section-title">基本情報</div>
			<div class="form-grid">
				<div class="form-group">
					<label>博物館名</label>
					<input type="text" name="name_ja" value="<?= htmlspecialchars($museum['name_ja']) ?>" required>
				</div>
				<div class="form-group">
					<label>かな名称</label>
					<input type="text" name="name_kana" value="<?= htmlspecialchars($museum['name_kana']) ?>" required>
				</div>
				<div class="form-group">
					<label>カテゴリ</label>
					<select name="category_id">
						<?php foreach ($categories as $cat): ?>
							<option value="<?= $cat['id'] ?>" <?= $museum['category_id']==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-group">
					<label>管理者メールアドレス (筆頭)</label>
					<input type="text" value="<?= htmlspecialchars($museum['email'] ?: '未設定') ?>" disabled style="background:#f9f9f9;">
				</div>
			</div>

			<div class="section-title">所在地・連絡先</div>
			<div class="form-group" style="margin-bottom:15px;">
				<label>住所</label>
				<div style="display:flex; gap:10px;">
					<input type="text" name="address" id="address" value="<?= htmlspecialchars($museum['address']) ?>">
					<button type="button" class="btn-sm" onclick="getCoords()">座標取得</button>
				</div>
			</div>
			<div class="form-grid">
				<div class="form-group"><label>電話番号</label><input type="text" name="phone_number" value="<?= htmlspecialchars($museum['phone_number']) ?>"></div>
				<div class="form-group"><label>公式サイトURL</label><input type="url" name="website_url" value="<?= htmlspecialchars($museum['website_url']) ?>"></div>
			</div>

			<!-- ★新規追加：広告・収益プラン設定 -->
			<div class="section-title">広告・収益プラン設定</div>
			<div class="ad-config-box">
				<div class="form-group" style="margin-bottom:20px;">
					<label>広告表示モード</label>
					<select name="ad_type" id="ad_type" onchange="toggleAdFields()">
						<option value="0" <?= $museum['ad_type']==0?'selected':'' ?>>標準（AdSense：自動収益）</option>
						<option value="1" <?= $museum['ad_type']==1?'selected':'' ?>>システム共通広告（SV指定）</option>
						<option value="2" <?= $museum['ad_type']==2?'selected':'' ?>>自館PR広告（博物館独自）</option>
						<option value="9" <?= $museum['ad_type']==9?'selected':'' ?>>非表示（有料プラン・公立博物館用）</option>
					</select>
				</div>

				<div id="custom_ad_fields" style="<?= $museum['ad_type']==2 ? '' : 'display:none;' ?>">
					<div class="form-group" style="margin-bottom:15px;">
						<label>自館PR用バナー画像</label>
						<?php if($museum['ad_custom_image']): ?>
							<img src="../<?= htmlspecialchars($museum['ad_custom_image']) ?>" style="width:200px; display:block; margin-bottom:10px; border-radius:5px;">
						<?php endif; ?>
						<input type="file" name="ad_image" accept="image/*">
					</div>
					<div class="form-group">
						<label>バナーのリンク先URL</label>
						<input type="url" name="ad_custom_link" value="<?= htmlspecialchars($museum['ad_custom_link']) ?>" placeholder="https://...">
					</div>
				</div>
			</div>

			<div class="section-title">ステータス・備考</div>
			<div class="form-group">
				<label>公開ステータス</label>
				<select name="is_active">
					<option value="1" <?= $museum['is_active']==1?'selected':'' ?>>公開中</option>
					<option value="0" <?= $museum['is_active']==0?'selected':'' ?>>非公開 (準備中メッセージを表示)</option>
				</select>
			</div>
			<div class="form-group" style="margin-top:20px;">
				<label>内部向け備考メモ</label>
				<textarea name="notes" rows="4"><?= htmlspecialchars($museum['notes']) ?></textarea>
			</div>

			<div class="btn-group">
				<a href="index.php" class="btn btn-outline">キャンセル</a>
				<button type="submit" class="btn btn-primary">変更を保存する</button>
			</div>
		</form>
	</div>
</div>

<script>
function toggleAdFields() {
	const type = document.getElementById('ad_type').value;
	const fields = document.getElementById('custom_ad_fields');
	fields.style.display = (type == '2') ? 'block' : 'none';
}

async function getCoords() {
    let addr = document.getElementById('address').value;
    if(!addr) return;
    addr = addr.replace(/[０-９]/g, s => String.fromCharCode(s.charCodeAt(0) - 0xFEE0)).replace(/[－ー]/g, '-');
    const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(addr)}&limit=1`);
    const data = await res.json();
    if(data.length > 0) { 
        alert("座標が確認されました（内部的に緯度経度を保持します）"); 
    } else { 
        alert("住所から座標が見つかりませんでした。"); 
    }
}
</script>
</body>
</html>