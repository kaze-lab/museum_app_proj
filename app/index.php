<?php
// /app/index.php
require_once('../common/db_inc.php');
session_start();

// 1. システム設定取得
$stmt_s = $pdo->query("SELECT * FROM system_settings WHERE id = 1");
$settings = $stmt_s->fetch();

// 2. 検索条件の取得
$q = $_GET['q'] ?? '';

// 3. 博物館一覧を取得（非公開も含めて全て表示）
$sql = "SELECT m.*, c.name as category_name 
		FROM museums m 
		LEFT JOIN categories c ON m.category_id = c.id 
		WHERE m.deleted_at IS NULL";
$params = [];
if ($q) {
	$sql .= " AND (m.name_ja LIKE ? OR m.description_ja LIKE ?)";
	$params = ["%$q%", "%$q%"];
}
$sql .= " ORDER BY m.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$museums = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<title><?= htmlspecialchars($settings['app_name'] ?? '博物館ガイド') ?></title>
	<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
	<style>
		:root { --primary: #26b396; --bg: #f4f7f6; --text: #333; }
		body { font-family: sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 20px; padding-bottom: 120px; }
		
		header { text-align: center; margin-bottom: 20px; }
		.app-name { font-size: 1.1rem; font-weight: bold; color: var(--primary); margin: 0; }

		.search-area { margin-bottom: 25px; }
		.search-area input {
			width: 100%; padding: 12px 15px; border-radius: 12px; border: 1px solid #ddd;
			box-sizing: border-box; font-size: 1rem; outline: none; background: white;
		}

		.section-title { font-size: 0.85rem; font-weight: bold; color: #999; margin-bottom: 15px; letter-spacing: 1px; }

		.m-card {
			background: white; border-radius: 18px; display: flex; gap: 15px; padding: 12px;
			margin-bottom: 15px; text-decoration: none; color: inherit;
			box-shadow: 0 4px 12px rgba(0,0,0,0.04); align-items: center; transition: 0.2s;
		}
		.m-thumb { width: 75px; height: 75px; border-radius: 12px; object-fit: cover; background: #eee; flex-shrink: 0; }
		.m-info { flex: 1; }
		.m-cat { font-size: 0.65rem; color: var(--primary); font-weight: bold; margin-bottom: 2px; }
		.m-name { font-size: 1rem; font-weight: bold; margin: 0; line-height: 1.3; }
		.badge-preparing { display: inline-block; background: #f0f0f0; color: #999; font-size: 0.6rem; padding: 2px 8px; border-radius: 4px; margin-top: 5px; font-weight: bold; }
		.is-inactive { opacity: 0.7; }

		/* 画像に合わせた共通のQRスキャンボタン設定 */
		.qr-floating-btn {
			position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
			background: var(--primary); color: white; padding: 12px 28px; border-radius: 50px;
			text-decoration: none; font-weight: bold; font-size: 1rem;
			box-shadow: 0 8px 25px rgba(38, 179, 150, 0.4);
			display: flex; align-items: center; gap: 12px; z-index: 500;
			border: none; cursor: pointer; white-space: nowrap;
		}
		.qr-floating-btn:active { opacity: 0.8; transform: translateX(-50%) scale(0.95); }

		#scanner-ui { position: fixed; inset: 0; background: #000; z-index: 1000; display: none; flex-direction: column; align-items: center; justify-content: center; }
		#v-frame { width: 280px; height: 280px; border: 2px solid var(--primary); border-radius: 30px; overflow: hidden; }
		video { width: 100%; height: 100%; object-fit: cover; }
	</style>
</head>
<body>

<header><h1 class="app-name"><?= htmlspecialchars($settings['app_name'] ?? '博物館ガイド') ?></h1></header>

<form method="GET" class="search-area">
	<input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="キーワードで博物館を探す">
</form>

<div class="section-title">MUSEUM LIST / 博物館一覧</div>

<div class="museum-list">
	<?php foreach ($museums as $m): ?>
	<a href="view.php?m=<?= $m['m_code'] ?>" class="m-card <?= $m['is_active'] ? '' : 'is-inactive' ?>">
		<img src="../<?= $m['main_image'] ?: 'img/no-image.webp' ?>" class="m-thumb">
		<div class="m-info">
			<div class="m-cat"><?= htmlspecialchars($m['category_name']) ?></div>
			<p class="m-name"><?= htmlspecialchars($m['name_ja']) ?></p>
			<?php if (!$m['is_active']): ?><span class="badge-preparing">只今準備中</span><?php endif; ?>
		</div>
		<span style="color:#ddd;">❯</span>
	</a>
	<?php endforeach; ?>
</div>

<!-- QRボタン (画像に合わせたデザイン) -->
<button class="qr-floating-btn" onclick="startScan()">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
        <rect x="7" y="7" width="3" height="3"></rect>
        <rect x="14" y="7" width="3" height="3"></rect>
        <rect x="7" y="14" width="3" height="3"></rect>
        <rect x="14" y="14" width="3" height="3"></rect>
    </svg>
    <span>QRスキャン</span>
</button>

<div id="scanner-ui">
	<div id="v-frame"><video id="v" playsinline></video></div>
	<button onclick="stopScan()" style="margin-top:30px; background:none; border:1px solid #555; color:#888; padding:12px 40px; border-radius:30px;">閉じる</button>
</div>

<script>
let v = document.getElementById('v'), sc = false;
function startScan() {
	document.getElementById('scanner-ui').style.display = 'flex';
	navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(s => { v.srcObject = s; v.play(); sc = true; tick(); })
	.catch(err => { alert("カメラの使用を許可してください"); stopScan(); });
}
function stopScan() { sc = false; if(v.srcObject) v.srcObject.getTracks().forEach(t => t.stop()); document.getElementById('scanner-ui').style.display = 'none'; }
function tick() {
	if(v.readyState === v.HAVE_ENOUGH_DATA && sc) {
		const canvas = document.createElement('canvas'); canvas.width = v.videoWidth; canvas.height = v.videoHeight;
		const ctx = canvas.getContext('2d'); ctx.drawImage(v, 0, 0);
		const code = jsQR(ctx.getImageData(0,0,canvas.width,canvas.height).data, canvas.width, canvas.height);
		if(code && code.data.includes('.php')) { window.location.href = code.data; return; }
	}
	if(sc) requestAnimationFrame(tick);
}
</script>
</body>
</html>