<?php
// /app/view.php
require_once('../common/db_inc.php');
session_start();

// 1. パラメータ取得
$m_code = $_GET['m'] ?? '';
$q      = $_GET['q'] ?? '';

if (empty($m_code)) {
    header('Location: index.php');
    exit;
}

// 2. 閲覧数カウント
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

if (!$museum) { echo "博物館が見つかりません。"; exit; }

// 4. 展示物リスト取得
$ex_sql = "SELECT * FROM exhibits WHERE museum_id = ? AND status = 'public' AND deleted_at IS NULL";
$ex_params = [$museum['id']];
if ($q) {
    $ex_sql .= " AND (title_ja LIKE ? OR desc_ja LIKE ?)";
    $ex_params[] = "%$q%"; $ex_params[] = "%$q%";
}
$ex_sql .= " ORDER BY id DESC";
$ex_stmt = $pdo->prepare($ex_sql);
$ex_stmt->execute($ex_params);
$exhibits = $ex_stmt->fetchAll();

// 5. 広告準備（出し分けロジック）
$ad_html = "";
$ad_type = (int)$museum['ad_type']; 
// ad_type=0 (AdSense) の場合は広告を生成しないように変更
if ($ad_type === 1) { // SV指定広告
    $sv_ad = $pdo->query("SELECT * FROM ads WHERE is_active=1 ORDER BY RAND() LIMIT 1")->fetch();
    if ($sv_ad) $ad_html = '<a href="'.htmlspecialchars($sv_ad['link_url']).'" class="ad-box" target="_blank"><img src="../'.htmlspecialchars($sv_ad['image_path']).'"><span class="ad-tag">PR</span></a>';
} elseif ($ad_type === 2 && $museum['ad_custom_image']) { // 自館PR広告
    $ad_html = '<a href="'.htmlspecialchars($museum['ad_custom_link']).'" class="ad-box" target="_blank"><img src="../'.htmlspecialchars($museum['ad_custom_image']).'"></a>';
}
// ad_type=9 (非表示) および ad_type=0 (旧AdSense) の場合は$ad_htmlは空のままとなる

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
        
        .header { background: white; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .page-title { font-size: 1.1rem; font-weight: bold; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; flex: 1; text-align: center; }
        .info-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--primary); padding: 0; width: 40px; text-align: right; }
        .header-spacer { width: 40px; }

        .search-area { padding: 10px 15px; background: #fff; border-bottom: 1px solid #eee; position: sticky; top: 56px; z-index: 90; }
        .search-box { display: flex; background: #f0f2f5; border-radius: 8px; padding: 8px 12px; align-items: center; }
        .search-input { border: none; outline: none; width: 100%; font-size: 1rem; background: transparent; margin-left: 10px; }

        .list-area { padding: 15px; }
        .ex-card { display: flex; background: white; border-radius: 12px; padding: 10px; margin-bottom: 15px; text-decoration: none; color: inherit; box-shadow: 0 2px 5px rgba(0,0,0,0.03); align-items: center; }
        .ex-thumb { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; background: #eee; flex-shrink: 0; }
        .ex-body { flex: 1; padding-left: 15px; overflow: hidden; }
        .ex-title { font-weight: bold; font-size: 1rem; line-height: 1.4; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ex-meta { font-size: 0.75rem; color: #888; }
        
        .btn-more { display: block; width: 100%; padding: 12px; background: #e0e0e0; color: #555; text-align: center; border: none; border-radius: 25px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .hidden-item { display: none; }

        /* ★ 修正：広告エリアの比率を3:1に固定 */
        .ad-area { margin: 40px 15px 20px; text-align: center; }
        .ad-box { display: block; width: 100%; border-radius: 10px; overflow: hidden; position: relative; text-decoration: none; border: 1px solid #eee; background: white; }
        .ad-box img { 
            width: 100%; 
            aspect-ratio: 3 / 1; /* 3:1比率を強制 */
            object-fit: cover;   /* 枠に合わせて切り抜き */
            display: block; 
        }
        /* ad-sense スタイリングは広告表示しないため不要だが、念のため残す */
        .ad-sense { aspect-ratio: 3/1; display: flex; align-items: center; justify-content: center; background: #eee; color: #aaa; font-size: 0.8rem; border: 1px dashed #ccc; }
        .ad-tag { position: absolute; top: 0; right: 0; background: rgba(0,0,0,0.4); color: white; font-size: 0.6rem; padding: 2px 6px; }

        .footer-link { text-align: center; font-size: 0.7rem; margin-top: 30px; opacity: 0.5; }
        .footer-link a { color: #aaa; text-decoration: none; }

        .preparing { text-align: center; padding: 80px 20px; color: #888; }
        .prep-icon { font-size: 3.5rem; display: block; margin-bottom: 15px; }

        /* 統一デザインQRボタン */
        .qr-floating-btn {
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
            background: var(--primary); color: white; padding: 12px 28px; border-radius: 50px;
            display: flex; align-items: center; gap: 12px; z-index: 100; cursor: pointer;
            font-weight: bold; font-size: 1rem; border: none; box-shadow: 0 8px 25px rgba(38, 179, 150, 0.4);
        }
        
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 200; justify-content: center; align-items: center; padding: 20px; }
        .modal-content { background: white; width: 100%; max-width: 400px; border-radius: 20px; overflow: hidden; max-height: 85vh; overflow-y: auto; position: relative; }
        .modal-close { position: absolute; top: 15px; right: 15px; font-size: 1.8rem; color: #fff; text-shadow: 0 1px 4px rgba(0,0,0,0.8); cursor: pointer; z-index: 10; }
        .modal-hero { width: 100%; height: 200px; object-fit: cover; background: #ddd; }
        .modal-body { padding: 25px; }
        .info-row { display: flex; gap: 12px; font-size: 0.9rem; color: #555; margin-bottom: 15px; }
        .desc-text { line-height: 1.6; color: #444; margin-bottom: 25px; white-space: pre-wrap; font-size: 0.95rem; }

        #scanner-ui { position: fixed; inset: 0; background: #000; z-index: 1000; display: none; flex-direction: column; align-items: center; justify-content: center; }
        #v-frame { width: 280px; height: 280px; border: 2px solid var(--primary); border-radius: 40px; overflow: hidden; }
        video { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body>

<header class="header">
    <div class="header-spacer"></div>
    <div class="page-title"><?= htmlspecialchars($museum['name_ja']) ?></div>
    <button class="info-btn" onclick="toggleModal()">ℹ️</button>
</header>

<?php if ($museum['is_active'] == 1): ?>
    
    <div class="search-area"><form method="GET" class="search-box"><input type="hidden" name="m" value="<?= $m_code ?>"><span>🔍</span><input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="search-input" placeholder="展示物を検索..."></form></div>
    
    <div class="list-area">
        <?php if (count($exhibits) > 0): ?>
            <?php $count = 0; foreach ($exhibits as $ex): $count++; $cls = ($count > 20) ? 'hidden-item' : ''; ?>
            <a href="exhibit.php?m=<?= $m_code ?>&e=<?= $ex['e_code'] ?>" class="ex-card <?= $cls ?>">
                <img src="../<?= $ex['image_path'] ?: 'img/no-image.webp' ?>" class="ex-thumb" loading="lazy">
                <div class="ex-body"><div class="ex-title"><?= htmlspecialchars($ex['title_ja']) ?></div><div class="ex-meta">🎧 音声ガイド</div></div>
                <span style="color:#ddd;">❯</span>
            </a>
            <?php endforeach; ?>
            <?php if (count($exhibits) > 20): ?><button id="btn-more" class="btn-more" onclick="showMore()">もっと見る (+<?= count($exhibits) - 20 ?>件)</button><?php endif; ?>
        <?php else: ?><div style="text-align:center; padding:60px 20px; color:#aaa;">展示物が見つかりませんでした。</div><?php endif; ?>
    </div>

    <!-- 広告エリア（最下段・3:1固定） -->
    <?php if($ad_html): ?><div class="ad-area"><?= $ad_html ?></div><?php endif; ?>

<?php else: ?>
    <div class="preparing"><span class="prep-icon">🏛️</span><h3>只今、展示準備中</h3><p>公開まで今しばらくお待ちください。</p></div>
<?php endif; ?>

<div class="footer-link"><a href="index.php">Powered by Museum Guide</a></div>

<!-- 統一QRボタン -->
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

<!-- モーダル -->
<div id="info-modal" class="modal-overlay" onclick="if(event.target === this) toggleModal()">
    <div class="modal-content">
        <span onclick="toggleModal()" class="modal-close">×</span>
        <img src="../<?= $museum['main_image'] ?: 'img/no-image.webp' ?>" class="modal-hero">
        <div class="modal-body">
            <h2 style="margin-top:0; font-size:1.3rem;"><?= htmlspecialchars($museum['name_ja']) ?></h2>
            <div class="desc-text"><?= htmlspecialchars($museum['description_ja']) ?></div>
            <div class="info-row">📍 <?= htmlspecialchars($museum['address']) ?></div>
            <button onclick="toggleModal()" style="width:100%; margin-top:20px; padding:12px; border:1px solid #ddd; background:#f9f9f9; border-radius:10px; font-weight:bold; color:#666;">閉じる</button>
        </div>
    </div>
</div>

<!-- スキャナー -->
<div id="scanner-ui">
    <div id="v-frame"><video id="v" playsinline></video></div>
    <button onclick="stopScan()" style="margin-top:30px; background:none; border:1px solid #999; color:#ccc; padding:12px 40px; border-radius:30px;">閉じる</button>
</div>

<script>
function toggleModal() { const m = document.getElementById('info-modal'); m.style.display = (m.style.display === 'flex') ? 'none' : 'flex'; }
function showMore() { document.querySelectorAll('.hidden-item').forEach(el => el.classList.remove('hidden-item')); document.getElementById('btn-more').style.display = 'none'; }
let v = document.getElementById('v'), sc = false;
function startScan() {
    document.getElementById('scanner-ui').style.display = 'flex';
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(s => { v.srcObject = s; v.play(); sc = true; tick(); })
    .catch(err => { alert("カメラを起動できません"); stopScan(); });
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