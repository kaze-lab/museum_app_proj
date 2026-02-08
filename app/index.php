<?php
// /app/index.php
require_once('../common/db_inc.php');

// 1. システム設定取得
$stmt_s = $pdo->query("SELECT * FROM system_settings WHERE id = 1");
$settings = $stmt_s->fetch();

// 2. 都道府県リスト（検索用）
$prefectures = ["北海道","青森県","岩手県","宮城県","秋田県","山形県","福島県","茨城県","栃木県","群馬県","埼玉県","千葉県","東京都","神奈川県","新潟県","富山県","石川県","福井県","山梨県","長野県","岐阜県","静岡県","愛知県","三重県","滋賀県","京都府","大阪府","兵庫県","奈良県","和歌山県","鳥取県","島根県","岡山県","広島県","山口県","徳島県","香川県","愛媛県","高知県","福岡県","佐賀県","長崎県","熊本県","大分県","宮崎県","鹿児島県","沖縄県"];

// 3. 検索条件の取得
$q_word = $_GET['q'] ?? '';
$q_cat	= $_GET['cat'] ?? '';
$q_pref = $_GET['pref'] ?? '';

// 4. クエリ構築：【人気順（view_count順）】で取得
$sql = "SELECT m.*, c.name as category_name 
		FROM museums m 
		LEFT JOIN categories c ON m.category_id = c.id 
		WHERE m.is_active = 1 AND m.deleted_at IS NULL";
$params = [];

if ($q_word) {
	$sql .= " AND (m.name_ja LIKE ? OR m.name_en LIKE ? OR m.description_ja LIKE ?)";
	$params = array_merge($params, ["%$q_word%", "%$q_word%", "%$q_word%"]);
}
if ($q_cat) {
	$sql .= " AND m.category_id = ?";
	$params[] = $q_cat;
}
if ($q_pref) {
	$sql .= " AND m.address LIKE ?";
	$params[] = "$q_pref%";
}

// ★修正：閲覧数（view_count）が多い順 ＋ 新着順で並べ替え
$sql .= " ORDER BY m.view_count DESC, m.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_museums = $stmt->fetchAll();

// ★修正：上位3件を「人気の博物館（ランキング）」、4件目以降を「通常リスト」に分割
$featured = array_slice($all_museums, 0, 3);
$regular  = array_slice($all_museums, 3);

// 5. カテゴリ一覧（フィルタ用）
$categories = $pdo->query("SELECT * FROM categories ORDER BY id")->fetchAll();

// 6. ★新規追加：スポンサー広告の取得（最下段用）
$ads_stmt = $pdo->query("SELECT * FROM ads WHERE is_active = 1 ORDER BY sort_order ASC, id DESC");
$ads = $ads_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<title><?= htmlspecialchars($settings['app_name'] ?? '博物館ガイド') ?></title>
	<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
	<style>
		:root { --primary: #26b396; --bg: #f4f7f6; --text: #2d3436; --card-bg: #ffffff; }
		body { font-family: 'Helvetica Neue', Arial, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding-bottom: 120px; }

		/* スプラッシュ */
		#splash { position: fixed; inset: 0; background: var(--primary); color: white; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 9999; transition: 0.6s ease-in-out; }
		.splash-logo { width: 80px; height: 80px; margin-bottom: 15px; }

		/* ヘッダー・検索 */
		.header-sticky { position: sticky; top: 0; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); z-index: 100; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
		.search-bar { background: #eee; border-radius: 12px; padding: 10px 15px; display: flex; align-items: center; margin-bottom: 15px; }
		.search-bar input { border: none; background: transparent; width: 100%; font-size: 0.95rem; outline: none; margin-left: 10px; }

		/* フィルタタブ（横スクロール） */
		.filter-scroll { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; scrollbar-width: none; }
		.filter-scroll::-webkit-scrollbar { display: none; }
		.filter-btn { white-space: nowrap; padding: 8px 18px; border-radius: 20px; background: white; border: 1px solid #ddd; font-size: 0.8rem; font-weight: bold; text-decoration: none; color: #666; }
		.filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

		/* 人気枠（上段） */
		.section-label { font-size: 0.85rem; font-weight: 900; margin: 25px 20px 15px; color: #888; text-transform: uppercase; letter-spacing: 1px; }
		.featured-card { position: relative; margin: 0 20px 25px; border-radius: 25px; overflow: hidden; height: 240px; box-shadow: 0 15px 30px rgba(0,0,0,0.15); display: block; text-decoration: none; color: white; }
		.featured-img { width: 100%; height: 100%; object-fit: cover; filter: brightness(0.8); }
		.featured-info { position: absolute; bottom: 0; left: 0; right: 0; padding: 25px; background: linear-gradient(transparent, rgba(0,0,0,0.8)); }
		.rank-badge { position: absolute; top: 20px; left: 20px; background: var(--primary); padding: 4px 12px; border-radius: 10px; font-size: 0.75rem; font-weight: bold; }

		/* リスト枠（中段） */
		.museum-list { padding: 0 20px; }
		.m-card { background: var(--card-bg); border-radius: 20px; display: flex; gap: 15px; padding: 12px; margin-bottom: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.04); text-decoration: none; color: inherit; align-items: center; }
		.m-thumb { width: 90px; height: 90px; border-radius: 15px; object-fit: cover; background: #eee; flex-shrink: 0; }
		.m-info { flex-grow: 1; }
		.m-cat { font-size: 0.65rem; color: var(--primary); font-weight: 900; margin-bottom: 4px; }
		.m-name { font-size: 1rem; font-weight: bold; margin-bottom: 6px; line-height: 1.2; }
		.m-dist { font-size: 0.75rem; color: #aaa; }

		/* 広告枠（下段） */
		.ad-section { margin-top: 40px; padding: 0 20px; }
		.ad-card { display: block; text-decoration: none; margin-bottom: 20px; border-radius: 15px; overflow: hidden; border: 1px solid #eee; background: white; }
		.ad-img { width: 100%; height: auto; display: block; }
		.ad-tag { font-size: 0.6rem; color: #999; padding: 5px 10px; background: #f9f9f9; text-align: right; }

		/* QRボタン */
		#btn-qr-main { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: #111; color: white; padding: 12px 30px; border-radius: 40px; display: flex; align-items: center; gap: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 200; cursor: pointer; border: 2px solid rgba(255,255,255,0.1); }

		/* スキャナー */
		#scanner-ui { position: fixed; inset: 0; background: #000; z-index: 1000; display: none; flex-direction: column; align-items: center; justify-content: center; }
		#v-frame { width: 280px; height: 280px; border: 2px solid var(--primary); border-radius: 30px; overflow: hidden; position: relative; }
		video { width: 100%; height: 100%; object-fit: cover; }
	</style>
</head>
<body>

<div id="splash">
	<svg class="splash-logo" viewBox="0 0 100 100" fill="white"><path d="M10 85h80v-5H10v5zM20 80v-40h8v40h-8zM46 80v-40h8v40h-8zM72 80v-40h8v40h-8zM10 40l40-25 40 25H10z"/></svg>
	<div style="font-weight: 900; letter-spacing: 3px;">MUSEUM GUIDE</div>
</div>

<div class="header-sticky">
	<form method="GET" class="search-bar">
		<span>🔍</span>
		<input type="text" name="q" value="<?= htmlspecialchars($q_word) ?>" placeholder="美術館、歴史、展示物..." id="ui-search">
	</form>
	
	<div class="filter-scroll">
		<a href="index.php" class="filter-btn <?= !$q_cat && !$q_pref ? 'active' : '' ?>">すべて</a>
		<a href="#" class="filter-btn" onclick="togglePrefMenu(); return false;">📍 エリア: <?= $q_pref ?: '全国' ?></a>
		<?php foreach ($categories as $cat): ?>
			<a href="?cat=<?= $cat['id'] ?>" class="filter-btn <?= $q_cat == $cat['id'] ? 'active' : '' ?>"><?= htmlspecialchars($cat['name']) ?></a>
		<?php endforeach; ?>
	</div>
</div>

<!-- 人気ランキング枠（自動：view_count順の上位3件） -->
<?php if (!$q_word && !$q_cat && !$q_pref && !empty($featured)): ?>
	<div class="section-label">Popular / 人気の博物館</div>
	<?php 
	$rank = 1;
	foreach ($featured as $f): 
	?>
	<a href="view.php?m=<?= $f['m_code'] ?>" class="featured-card">
		<div class="rank-badge">RANK <?= $rank++ ?></div>
		<img src="../<?= $f['main_image'] ?: 'img/no-image.webp' ?>" class="featured-img">
		<div class="featured-info">
			<div style="font-size:0.7rem; opacity:0.8; font-weight:bold;"><?= htmlspecialchars($f['category_name']) ?></div>
			<div style="font-size:1.4rem; font-weight:bold; margin-top:5px;"><?= htmlspecialchars($f['name_ja']) ?></div>
		</div>
	</a>
	<?php endforeach; ?>
<?php endif; ?>

<!-- 一般リスト -->
<div class="section-label"><?= ($q_word || $q_cat || $q_pref) ? 'Search Results / 検索結果' : 'All Museums / 博物館一覧' ?></div>
<div class="museum-list">
	<?php foreach ($regular as $m): ?>
	<a href="view.php?m=<?= $m['m_code'] ?>" class="m-card" data-lat="<?= $m['latitude'] ?>" data-lng="<?= $m['longitude'] ?>">
		<img src="../<?= $m['main_image'] ?: 'img/no-image.webp' ?>" class="m-thumb">
		<div class="m-info">
			<div class="m-cat"><?= htmlspecialchars($m['category_name']) ?></div>
			<div class="m-name"><?= htmlspecialchars($m['name_ja']) ?></div>
			<div class="m-dist">📍 <span class="dist-val">---</span></div>
		</div>
	</a>
	<?php endforeach; ?>
	
	<?php if (empty($all_museums)): ?>
		<div style="text-align:center; padding:40px; color:#aaa;">見つかりませんでした。</div>
	<?php endif; ?>
</div>

<!-- 広告・スポンサー枠（下段：楽天トラベル風） -->
<?php if (!empty($ads)): ?>
	<div class="section-label">Special / お得な情報・PR</div>
	<div class="ad-section">
		<?php foreach ($ads as $ad): ?>
			<a href="<?= htmlspecialchars($ad['link_url']) ?>" class="ad-card" target="_blank">
				<img src="../<?= htmlspecialchars($ad['image_path']) ?>" class="ad-img">
				<div class="ad-tag">スポンサー広告</div>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<!-- QRボタン -->
<div id="btn-qr-main" onclick="startScan()">
	<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 7h10v10H7zM7 12h10M12 7v10"/></svg>
	<span style="font-weight:900; font-size:0.8rem; letter-spacing:1px;">QR SCAN</span>
</div>

<!-- 都道府県選択モーダル（簡易版） -->
<div id="pref-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:1000; padding:40px 20px; overflow-y:auto;">
	<div style="background:white; border-radius:20px; padding:20px;">
		<h3 style="margin-top:0;">エリアを選択</h3>
		<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px;">
			<a href="index.php" style="text-decoration:none; color:var(--primary); font-weight:bold; padding:10px; border:1px solid #eee; text-align:center; border-radius:10px;">全国</a>
			<?php foreach ($prefectures as $p): ?>
				<a href="?pref=<?= urlencode($p) ?>" style="text-decoration:none; color:#666; padding:10px; border:1px solid #eee; text-align:center; border-radius:10px; font-size:0.8rem;"><?= $p ?></a>
			<?php endforeach; ?>
		</div>
		<button onclick="togglePrefMenu()" style="width:100%; margin-top:20px; padding:15px; border:none; background:#eee; border-radius:10px; font-weight:bold;">閉じる</button>
	</div>
</div>

<!-- スキャナーUI -->
<div id="scanner-ui">
	<div id="v-frame"><video id="v" playsinline></video></div>
	<button onclick="stopScan()" style="margin-top:30px; background:none; border:1px solid white; color:white; padding:10px 40px; border-radius:20px;">キャンセル</button>
</div>

<script>
window.onload = () => {
	setTimeout(() => { 
		const s = document.getElementById('splash');
		if(s) {
			s.style.opacity = '0'; 
			setTimeout(() => s.remove(), 600); 
		}
	}, 1500);
	if (navigator.geolocation) navigator.geolocation.getCurrentPosition(updateDist);
};

function togglePrefMenu() {
	const m = document.getElementById('pref-modal');
	m.style.display = m.style.display === 'none' ? 'block' : 'none';
}

function updateDist(pos) {
	const lat = pos.coords.latitude, lng = pos.coords.longitude;
	document.querySelectorAll('.m-card').forEach(c => {
		const mLat = c.dataset.lat, mLng = c.dataset.lng;
		if(!mLat) return;
		const d = getDist(lat, lng, mLat, mLng);
		c.querySelector('.dist-val').innerText = d < 1 ? (d*1000).toFixed(0)+'m' : d.toFixed(1)+'km';
	});
}

function getDist(la1, lo1, la2, lo2) {
	const R = 6371;
	const dLa = (la2-la1)*Math.PI/180, dLo = (lo2-lo1)*Math.PI/180;
	const a = Math.sin(dLa/2)**2 + Math.cos(la1*Math.PI/180)*Math.cos(la2*Math.PI/180)*Math.sin(dLo/2)**2;
	return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

// QRスキャンロジック
let v = document.getElementById('v'), sc = false;
function startScan() {
	document.getElementById('scanner-ui').style.display = 'flex';
	navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(s => {
		v.srcObject = s; v.play(); sc = true; tick();
	});
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
		if(code && (code.data.includes('view.php?m=') || code.data.includes('view.php?e='))) {
			window.location.href = code.data; return;
		}
	}
	if(sc) requestAnimationFrame(tick);
}
</script>
</body>
</html>