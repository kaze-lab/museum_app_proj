<?php
// /app/view.php
require_once('../common/db_inc.php');
session_start();

// 1. パラメータ取得
$m_code = $_GET['m'] ?? '';
$q		= $_GET['q'] ?? '';

if (empty($m_code)) {
	header('Location: index.php');
	exit;
}

// 2. 閲覧数カウントアップ
$stmt_upd = $pdo->prepare("UPDATE museums SET view_count = view_count + 1 WHERE m_code = ?");
$stmt_upd->execute([$m_code]);

// 3. 博物館情報取得
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

// 4. 展示物リスト取得（公開中のもののみ）
$ex_sql = "SELECT * FROM exhibits WHERE museum_id = ? AND status = 'public' AND deleted_at IS NULL";
$ex_params = [$museum['id']];

if ($q) {
	$ex_sql .= " AND (title_ja LIKE ? OR desc_ja LIKE ?)";
	$ex_params[] = "%$q%";
	$ex_params[] = "%$q%";
}

$ex_sql .= " ORDER BY id DESC";
$ex_stmt = $pdo->prepare($ex_sql);
$ex_stmt->execute($ex_params);
$exhibits = $ex_stmt->fetchAll();

// 5. 広告データの準備
$ad_html = "";
$ad_type = (int)$museum['ad_type']; 

if ($ad_type === 0) {
	$ad_html = '<div class="ad-box ad-sense"><span>広告エリア</span></div>';
} elseif ($ad_type === 1) {
	$sv_ad = $pdo->query("SELECT * FROM ads WHERE is_active=1 ORDER BY RAND() LIMIT 1")->fetch();
	if ($sv_ad) {
		$ad_html = '<a href="'.htmlspecialchars($sv_ad['link_url']).'" class="ad-box" target="_blank"><img src="../'.htmlspecialchars($sv_ad['image_path']).'"></a>';
	}
} elseif ($ad_type === 2 && $museum['ad_custom_image']) {
	$ad_html = '<a href="'.htmlspecialchars($museum['ad_custom_link']).'" class="ad-box" target="_blank"><img src="../'.htmlspecialchars($museum['ad_custom_image']).'"></a>';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<title><?= htmlspecialchars($museum['name_ja']) ?></title>
	<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
	<style>
		:root { --primary: #26b396; --bg: #f8f9fa; --text: #333; }
		body { font-family: sans-serif; margin: 0; background: var(--bg); color: var(--text); padding-bottom: 120px; }
		
		/* ヘッダー (シンプル化) */
		.header { 
			background: white; padding: 15px 20px; 
			display: flex; align-items: center; justify-content: space-between;
			position: sticky; top: 0; z-index: 100;
			box-shadow: 0 2px 10px rgba(0,0,0,0.05);
		}
		.page-title { font-size: 1.1rem; font-weight: bold; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
		.info-btn { 
			background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--primary); padding: 0;
		}

		/* 検索バー (ヘッダーの下に配置) */
		.search-area { padding: 10px 15px; background: #fff; border-bottom: 1px solid #eee; }
		.search-box { display: flex; background: #f0f2f5; border-radius: 8px; padding: 8px 12px; align-items: center; }
		.search-input { border: none; outline: none; width: 100%; font-size: 1rem; background: transparent; margin-left: 10px; }

		/* 展示物リスト */
		.list-area { padding: 15px; }
		.ex-card { 
			display: flex; background: white; border-radius: 12px; padding: 10px; 
			margin-bottom: 15px; text-decoration: none; color: inherit; 
			box-shadow: 0 2px 5px rgba(0,0,0,0.03); align-items: center; 
		}
		.ex-card:active { background-color: #f9f9f9; }
		.ex-thumb { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; background: #eee; flex-shrink: 0; }
		.ex-body { flex: 1; padding-left: 15px; overflow: hidden; }
		.ex-title { font-weight: bold; font-size: 1rem; line-height: 1.4; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
		.ex-meta { font-size: 0.75rem; color: #888; display: flex; align-items: center; gap: 5px; }
		
		/* もっと見るボタン */
		.btn-more { display: block; width: 100%; padding: 12px; background: #e0e0e0; color: #555; text-align: center; border: none; border-radius: 25px; font-weight: bold; cursor: pointer; margin-top: 20px; }
		.hidden-item { display: none; }

		/* 広告 */
		.ad-area { margin: 30px 15px; text-align: center; }
		.ad-box { display: block; width: 100%; border-radius: 8px; overflow: hidden; }
		.ad-box img { width: 100%; height: auto; }
		.ad-sense { background: #eee; padding: 20px; color: #aaa; font-size: 0.8rem; border: 1px dashed #ccc; }

		/* フッターリンク */
		.footer-link { text-align: center; font-size: 0.75rem; margin-top: 30px; }
		.footer-link a { color: #aaa; text-decoration: none; }

		/* 準備中表示 */
		.preparing { text-align: center; padding: 60px 20px; color: #888; }
		.prep-icon { font-size: 3rem; display: block; margin-bottom: 15px; }

		/* QRボタン（中央下固定・グリーン） */
		.qr-float-center {
			position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
			background: var(--primary); color: white; padding: 12px 35px; border-radius: 30px;
			display: flex; align-items: center; gap: 10px;
			box-shadow: 0 5px 20px rgba(38,179,150,0.4); z-index: 90; cursor: pointer;
			font-weight: bold; font-size: 1rem; border: 2px solid rgba(255,255,255,0.2);
		}
		
		/* モーダル（博物館情報） */
		.modal-overlay { 
			display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 200; 
			justify-content: center; align-items: center; padding: 20px;
		}
		.modal-content { 
			background: white; width: 100%; max-width: 400px; border-radius: 20px; overflow: hidden; 
			max-height: 80vh; overflow-y: auto; position: relative;
		}
		.modal-close { position: absolute; top: 15px; right: 15px; font-size: 1.5rem; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.5); cursor: pointer; z-index: 10; text-decoration: none;}
		.modal-hero { width: 100%; height: 200px; object-fit: cover; background: #ddd; }
		.modal-body { padding: 25px; }
		.info-row { display: flex; gap: 12px; font-size: 0.9rem; color: #555; margin-bottom: 12px; align-items: flex-start; }
		.info-icon { width: 20px; text-align: center; }
		.desc-text { line-height: 1.6; color: #444; margin-bottom: 25px; white-space: pre-wrap; font-size: 0.95rem; }

		/* スキャナーUI */
		#scanner-ui { position: fixed; inset: 0; background: #000; z-index: 1000; display: none; flex-direction: column; align-items: center; justify-content: center; }
		#v-frame { width: 280px; height: 280px; border: 2px solid var(--primary); border-radius: 30px; overflow: hidden; }
		video { width: 100%; height: 100%; object-fit: cover; }
	</style>
</head>
<body>

<!-- ヘッダー -->
<div class="header">
	<div class="page-title"><?= htmlspecialchars($museum['name_ja']) ?></div>
	<button class="info-btn" onclick="toggleModal()">ℹ️</button>
</div>

<?php if ($museum['is_active'] == 1): ?>
	
	<!-- 検索バー -->
	<div class="search-area">
		<form method="GET" class="search-box">
			<input type="hidden" name="m" value="<?= $m_code ?>">
			<span>🔍</span>
			<input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="search-input" placeholder="展示物を探す...">
		</form>
	</div>

	<!-- 展示物リスト -->
	<div class="list-area">
		<?php if (count($exhibits) > 0): ?>
			<?php 
			$count = 0;
			foreach ($exhibits as $ex): 
				$count++;
				$cls = ($count > 20) ? 'hidden-item' : '';
			?>
			<a href="exhibit.php?m=<?= $m_code ?>&e=<?= $ex['e_code'] ?>" class="ex-card item-row <?= $cls ?>">
				<img src="../<?= $ex['image_path'] ?: 'img/no-image.webp' ?>" class="ex-thumb" loading="lazy">
				<div class="ex-body">
					<div class="ex-title"><?= htmlspecialchars($ex['title_ja']) ?></div>
					<div class="ex-meta">🎧 音声ガイド</div>
				</div>
				<span style="color:#ddd;">❯</span>
			</a>
			<?php endforeach; ?>

			<?php if (count($exhibits) > 20): ?>
				<button id="btn-more" class="btn-more" onclick="showMore()">もっと見る (+<?= count($exhibits) - 20 ?>件)</button>
			<?php endif; ?>

		<?php else: ?>
			<div style="text-align:center; padding:60px 20px; color:#aaa;">展示物が見つかりませんでした。<br>別のキーワードをお試しください。</div>
		<?php endif; ?>
	</div>

	<!-- 広告エリア（最下段） -->
	<?php if($ad_html): ?>
		<div class="ad-area"><?= $ad_html ?></div>
	<?php endif; ?>

	<!-- フッターリンク -->
	<div class="footer-link">
		<a href="index.php">Powered by 博物館ガイド</a>
	</div>

	<!-- QRボタン -->
	<div class="qr-float-center" onclick="startScan()">
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 7h10v10H7zM7 12h10M12 7v10"/></svg>
		<span>QRスキャン</span>
	</div>

<?php else: ?>
	<!-- 準備中画面 -->
	<div class="preparing">
		<span class="prep-icon">🏛️</span>
		<h3>只今、リニューアル準備中</h3>
		<p>展示情報を更新しています。<br>公開までお待ちください。</p>
		<div style="margin-top:30px; font-size:0.9rem;">📍 <?= htmlspecialchars($museum['address']) ?></div>
	</div>
	<!-- フッターリンク -->
	<div class="footer-link">
		<a href="index.php">Powered by 博物館ガイド</a>
	</div>
<?php endif; ?>

<!-- 博物館情報モーダル -->
<div id="info-modal" class="modal-overlay" onclick="if(event.target === this) toggleModal()">
	<div class="modal-content">
		<a href="javascript:void(0)" onclick="toggleModal()" class="modal-close">×</a>
		<img src="../<?= $museum['main_image'] ?: 'img/no-image.webp' ?>" class="modal-hero">
		<div class="modal-body">
			<h2 style="margin-top:0; margin-bottom:15px; font-size:1.3rem;"><?= htmlspecialchars($museum['name_ja']) ?></h2>
			<div class="desc-text"><?= htmlspecialchars($museum['description_ja'] ?: '詳細情報は準備中です。') ?></div>
			
			<hr style="border:none; border-top:1px solid #eee; margin:20px 0;">
			
			<div class="info-row"><div class="info-icon">📍</div><div><?= htmlspecialchars($museum['address']) ?></div></div>
			<?php if($museum['phone_number']): ?>
				<div class="info-row"><div class="info-icon">📞</div><div><a href="tel:<?= htmlspecialchars($museum['phone_number']) ?>" style="color:var(--primary);"><?= htmlspecialchars($museum['phone_number']) ?></a></div></div>
			<?php endif; ?>
			<?php if($museum['website_url']): ?>
				<div class="info-row"><div class="info-icon">🌐</div><div><a href="<?= htmlspecialchars($museum['website_url']) ?>" target="_blank" style="color:var(--primary);">公式サイト</a></div></div>
			<?php endif; ?>
			
			<button onclick="toggleModal()" style="width:100%; margin-top:20px; padding:12px; border:1px solid #ddd; background:#f9f9f9; border-radius:10px; font-weight:bold; color:#666;">閉じる</button>
		</div>
	</div>
</div>

<!-- スキャナーUI -->
<div id="scanner-ui">
	<div id="v-frame"><video id="v" playsinline></video></div>
	<p style="color:white; margin-top:20px; font-weight:bold;">QRコードをかざしてください</p>
	<button onclick="stopScan()" style="margin-top:30px; background:none; border:1px solid #999; color:#ccc; padding:10px 40px; border-radius:30px;">閉じる</button>
</div>

<script>
function toggleModal() {
	const m = document.getElementById('info-modal');
	m.style.display = (m.style.display === 'flex') ? 'none' : 'flex';
}

function showMore() {
	document.querySelectorAll('.hidden-item').forEach(el => el.classList.remove('hidden-item'));
	document.getElementById('btn-more').style.display = 'none';
}

// QRスキャン
let v = document.getElementById('v'), sc = false;
function startScan() {
	document.getElementById('scanner-ui').style.display = 'flex';
	navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(s => {
		v.srcObject = s; v.play(); sc = true; tick();
	}).catch(err => { alert("カメラを起動できません"); stopScan(); });
}
function stopScan() {
	sc = false; if(v.srcObject) v.srcObject.getTracks().forEach(t => t.stop());
	document.getElementById('scanner-ui').style.display = 'none';
}
function tick() {
	if(v.readyState === v.HAVE_ENOUGH_DATA && sc) {
		const canvas = document.createElement('canvas');
		canvas.width = v.videoWidth; canvas.height = v.videoHeight;
		const ctx = canvas.getContext('2d');
		ctx.drawImage(v, 0, 0);
		const code = jsQR(ctx.getImageData(0,0,canvas.width,canvas.height).data, canvas.width, canvas.height);
		if(code && code.data.includes('.php')) {
			window.location.href = code.data; return;
		}
	}
	if(sc) requestAnimationFrame(tick);
}
</script>

</body>
</html>