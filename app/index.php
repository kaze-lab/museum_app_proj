<?php
// /app/index.php
require_once('../common/db_inc.php');
require_once('../common/copyright_config.php'); // ★ 著作権表示の共通化ファイルを追加 ★
session_start();

// 1. システム設定取得
// ★ SELECT文を修正 (header_system_name, copyright_display を取得) ★
$stmt_s = $pdo->query("SELECT header_system_name, copyright_display, deepl_api_key, deepl_api_url FROM system_settings WHERE id = 1");
$settings = $stmt_s->fetch();

// 読み込めなかった場合の初期値
if (!$settings) {
	$settings = [
		'header_system_name' => '博物館ガイド', // デフォルト値
		'copyright_display' => 'Museum Guide', // デフォルト値
		'deepl_api_key' => '',
		'deepl_api_url' => 'https://api-free.deepl.com/v2/translate'
	];
}

// ヘッダーに表示するシステム名
$display_header_system_name = htmlspecialchars($settings['header_system_name']);
// フッターに表示する会社・システム名
$display_footer_company_system_name = htmlspecialchars($settings['copyright_display']);


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

// ★ もっと見る機能のための初期表示件数 ★
$initial_display_count = 20;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<!-- ★ title タグの app_name 表示を修正 ★ -->
	<title><?= $display_header_system_name ?></title>
	<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
	<style>
		:root { --primary: #26b396; --bg: #f4f7f6; --text: #333; }
		body { font-family: sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 20px; padding-bottom: 120px; }
		
		header { text-align: center; margin-bottom: 20px; }
		/* app-name の表示を修正 */
		.app-name-fixed { font-size: 1.1rem; font-weight: bold; color: var(--primary); margin: 0; } /* 固定表示用クラス */

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

		/* ★ フッター関連スタイル (背景・影を削除し、シンプルなテキスト表示に) ★ */
        .footer-area {
            margin-top: 50px; /* リストエリアとの間隔 */
            padding: 20px 15px;
            /* background: #fff; */ /* 削除 */
            border-top: 1px solid #eee; /* 必要であれば残すか調整 */
            text-align: center;
            color: #888;
            font-size: 0.8rem;
            /* box-shadow: none; */ /* 削除 */
        }
        .footer-main-info {
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: #555;
        }
        .footer-copyright {
            margin-bottom: 5px;
            color: #aaa; /* 著作権表示は少し控えめに */
        }
        /* フッターのリンクが必要なければ削除 */
        /* .footer-links { margin-top: 10px; } */
        /* .footer-links a { color: #aaa; text-decoration: none; margin: 0 5px; } */

		/* ★ もっと見る機能用CSS ★ */
		.btn-more { 
			display: block; width: 100%; padding: 12px; background: #e0e0e0; 
			color: #555; text-align: center; border: none; border-radius: 25px; 
			font-weight: bold; cursor: pointer; margin-top: 10px; 
		}
        .hidden-item { display: none; }
	</style>
</head>
<body>

<header>
	<!-- ヘッダーのシステム名表示を修正 -->
	<h1 class="app-name-fixed"><?= $display_header_system_name ?></h1>
</header>

<form method="GET" class="search-area">
	<input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="キーワードで博物館を探す">
</form>

<div class="section-title">MUSEUM LIST / 博物館一覧</div>

<div class="museum-list">
	<?php $count = 0; foreach ($museums as $m): $count++; $cls = ($count > $initial_display_count) ? 'hidden-item' : ''; ?>
	<a href="view.php?m=<?= $m['m_code'] ?>" class="m-card <?= $cls ?> <?= $m['is_active'] ? '' : 'is-inactive' ?>">
		<img src="../<?= $m['main_image'] ?: 'img/no-image.webp' ?>" class="m-thumb">
		<div class="m-info">
			<div class="m-cat"><?= htmlspecialchars($m['category_name']) ?></div>
			<p class="m-name"><?= htmlspecialchars($m['name_ja']) ?></p>
			<?php if (!$m['is_active']): ?><span class="badge-preparing">只今準備中</span><?php endif; ?>
		</div>
		<span style="color:#ddd;">❯</span>
	</a>
	<?php endforeach; ?>
	<?php if (count($museums) > $initial_display_count): ?><button id="btn-more" class="btn-more" onclick="showMore()">もっと見る (+<?= count($museums) - $initial_display_count ?>件)</button><?php endif; ?>
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
	<p style="color:white; margin-top:25px; font-weight:bold;">QRコードを枠内にかざしてください</p>
	<button onclick="stopScan()" style="margin-top:30px; background:none; border:1px solid #999; color:#ccc; padding:12px 40px; border-radius:30px;">閉じる</button>
</div>

<!-- ★★★ フッターエリア 追加 ★★★ -->
<div class="footer-area">
	<?php if ($display_footer_company_system_name): ?>
		<div class="footer-main-info">powered by <?= $display_footer_company_system_name ?></div>
	<?php endif; ?>
	
	<!-- 貴社著作権表示（固定） -->
	<div class="footer-copyright">
		<?= YOUR_COPYRIGHT_TEXT ?>
	</div>
	
	<!-- 必要であれば、ここに追加のリンクなども配置可能 -->
	<!-- <div class="footer-links">
		<a href="#">利用規約</a>
	</div> -->
</div>
<!-- ★★★ フッターエリア 追加ここまで ★★★ -->

<script>
// もっと見る機能用JS
function showMore() {
    document.querySelectorAll('.hidden-item').forEach(el => el.classList.remove('hidden-item'));
    document.getElementById('btn-more').style.display = 'none';
}

let v = document.getElementById('v'), sc = false;
function startScan() {
	document.getElementById('scanner-ui').style.display = 'flex';
	// QRスキャン機能改善のためのgetUserMedia設定
	navigator.mediaDevices.getUserMedia({ 
        video: { 
            facingMode: 'environment',
            width: { ideal: 1280 }, // 理想の解像度 (720p)
            height: { ideal: 720 },
            advanced: [{ focusMode: 'continuous' }] // 連続オートフォーカスを要求
        } 
    }).then(s => { 
        v.srcObject = s; 
        v.play(); 
        sc = true; 
        tick(); 
    })
	.catch(err => { 
        console.error("Camera access error:", err); // エラー詳細をコンソールに出力
        alert("カメラを起動できませんでした。許可されているかご確認ください。"); // ユーザー向けメッセージを具体的に
        stopScan(); 
    });
}
function stopScan() { sc = false; if(v.srcObject) v.srcObject.getTracks().forEach(t => t.stop()); document.getElementById('scanner-ui').style.display = 'none'; }
function tick() {
	if(v.readyState === v.HAVE_ENOUGH_DATA && sc) {
		const canvas = document.createElement('canvas'); 
        canvas.width = v.videoWidth; 
        canvas.height = v.videoHeight;
		const ctx = canvas.getContext('2d'); 
        ctx.drawImage(v, 0, 0, canvas.width, canvas.height);

		const code = jsQR(ctx.getImageData(0,0,canvas.width,canvas.height).data, canvas.width, canvas.height);
		if(code && code.data.includes('.php')) { window.location.href = code.data; return; }
	}
	if(sc) requestAnimationFrame(tick);
}
</script>
</body>
</html>