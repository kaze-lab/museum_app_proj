<?php
// /admin/qr_print.php
require_once('../common/db_inc.php');
session_start();

if (!isset($_SESSION['admin_logged_in'])) { exit('Access Denied'); }

$m_id = $_GET['m_id'] ?? null;
if (!$m_id) { exit('Museum ID missing'); }

// 博物館のm_codeを取得
$stmt_m = $pdo->prepare("SELECT m_code FROM museums WHERE id = ?");
$stmt_m->execute([$m_id]);
$m_code = $stmt_m->fetchColumn();

// 印刷対象のIDリストを取得（一括POST、または個別GET）
$target_ids = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids'])) {
	$target_ids = json_decode($_POST['ids'], true);
} elseif (isset($_GET['id'])) {
	$target_ids = [$_GET['id']];
}

if (empty($target_ids)) { exit('No items selected'); }

// 展示物情報を一括取得
$placeholders = implode(',', array_fill(0, count($target_ids), '?'));
$sql = "SELECT id, e_code, title_ja FROM exhibits WHERE id IN ($placeholders) AND museum_id = ? AND deleted_at IS NULL";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($target_ids, [$m_id]));
$items = $stmt->fetchAll();

// アプリのベースURLを構築
$base_url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . str_replace('admin/qr_print.php', 'app/exhibit.php', $_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>QRコード印刷プレビュー</title>
	<!-- QRCode.js ライブラリ (MIT License) -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
	<style>
		:root { --primary-color: #26b396; }
		body { font-family: sans-serif; margin: 0; background: #525659; color: #333; }
		
		/* 操作パネル（非表示設定） */
		.no-print-tools { 
			position: sticky; top: 0; left: 0; right: 0; background: #323639; 
			padding: 15px 40px; display: flex; align-items: center; justify-content: space-between; 
			z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.3); color: white;
		}
		.size-selector { display: flex; gap: 10px; background: #202124; padding: 5px; border-radius: 8px; }
		.size-btn { 
			background: none; border: none; color: #aaa; padding: 8px 20px; border-radius: 6px; 
			cursor: pointer; font-weight: bold; transition: 0.2s; 
		}
		.size-btn.active { background: #3c4043; color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.5); }
		.btn-print { background: var(--primary-color); color: white; border: none; padding: 10px 30px; border-radius: 5px; font-weight: bold; cursor: pointer; }

		/* 印刷用紙（A4）の設定 */
		.page { 
			width: 210mm; min-height: 296mm; padding: 10mm; margin: 20px auto; 
			background: white; box-shadow: 0 0 15px rgba(0,0,0,0.5); box-sizing: border-box; 
			display: grid; align-content: start;
		}

		/* カードの基本デザイン（切り取り式） */
		.qr-card { 
			border: 1px solid #ddd; border-style: dotted; box-sizing: border-box; 
			display: flex; flex-direction: column; overflow: hidden; position: relative;
		}
		
		/* 管理ゾーン（上部：切り捨てる部分） */
		.mgr-zone { 
			background: #fcfcfc; padding: 8px; border-bottom: 1px dashed #bbb; 
			flex-shrink: 0; overflow: hidden;
		}
		.mgr-title { font-size: 10px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
		.mgr-id { font-family: monospace; font-size: 9px; color: #888; }

		/* 展示ゾーン（下部：実際に貼る部分） */
		.user-zone { 
			flex-grow: 1; display: flex; align-items: center; justify-content: center; padding: 10px;
		}
		.qr-code-canvas { display: block; }

		/* --- サイズ別レイアウト --- */
		
		/* Sサイズ (4列×6行 = 24枚) */
		.layout-s { grid-template-columns: repeat(4, 1fr); gap: 2mm; }
		.layout-s .qr-card { height: 45mm; }
		.layout-s .mgr-zone { height: 12mm; }

		/* Mサイズ (2列×5行 = 10枚) ※標準 */
		.layout-m { grid-template-columns: repeat(2, 1fr); gap: 5mm; }
		/*.layout-m .qr-card { height: 55mm; }*/
		.layout-m .qr-card { height: 60mm; }
		.layout-m .mgr-zone { height: 15mm; padding: 10px; }
		.layout-m .mgr-title { font-size: 13px; }

		/* Lサイズ (1列×2行 = 2枚) */
		.layout-l { grid-template-columns: 1fr; gap: 10mm; }
		.layout-l .qr-card { height: 130mm; }
		.layout-l .mgr-zone { height: 25mm; padding: 15px; }
		.layout-l .mgr-title { font-size: 20px; }
		.layout-l .mgr-id { font-size: 14px; }

		/* 印刷時設定 */
		@media print {
			body { background: white; }
			.no-print-tools { display: none !important; }
			.page { margin: 0; box-shadow: none; width: 100%; }
			.qr-card { border-color: #eee; } /* 印刷時は薄く */
		}
	</style>
</head>
<body>

<div class="no-print-tools">
	<div style="font-weight:bold;">🖨️ QRコード一括印刷</div>
	
	<div class="size-selector">
		<button class="size-btn" onclick="changeSize('s')">小 (S)</button>
		<button class="size-btn active" onclick="changeSize('m')">中 (M)</button>
		<button class="size-btn" onclick="changeSize('l')">大 (L)</button>
	</div>

	<button class="btn-print" onclick="window.print()">この内容で印刷する</button>
</div>

<div class="page layout-m" id="print-page">
	<?php foreach ($items as $item): 
		$qr_url = "{$base_url}?m={$m_code}&e={$item['e_code']}";
	?>
		<div class="qr-card">
			<!-- 管理ゾーン（切り離し用） -->
			<div class="mgr-zone">
				<div class="mgr-title">🏛️ <?= htmlspecialchars($item['title_ja']) ?></div>
				<div class="mgr-id">ID: M<?= $m_id ?>-E<?= $item['id'] ?></div>
			</div>
			<!-- 展示ゾーン（実際に貼る部分） -->
			<div class="user-zone">
				<div class="qr-code-canvas" data-url="<?= htmlspecialchars($qr_url) ?>"></div>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<script>
/**
 * QRコードの生成実行
 */
function generateQRs(size) {
	const canvases = document.querySelectorAll('.qr-code-canvas');
	let qrSize = 120; // Default M
	if (size === 's') qrSize = 90;
	if (size === 'l') qrSize = 350;

	canvases.forEach(container => {
		container.innerHTML = ''; // 再描画用
		new QRCode(container, {
			text: container.dataset.url,
			width: qrSize,
			height: qrSize,
			colorDark : "#000000",
			colorLight : "#ffffff",
			correctLevel : QRCode.CorrectLevel.H // 暗い場所でも読みやすいように高耐性
		});
	});
}

/**
 * サイズ（レイアウト）切り替え
 */
function changeSize(size) {
	const page = document.getElementById('print-page');
	const btns = document.querySelectorAll('.size-btn');
	
	// レイアウトクラスの差し替え
	page.className = 'page layout-' + size;
	
	// ボタンの見た目変更
	btns.forEach(btn => {
		btn.classList.toggle('active', btn.innerText.toLowerCase().includes(size));
	});

	// QRの再生成
	generateQRs(size);
}

// 初回起動
document.addEventListener('DOMContentLoaded', () => {
	generateQRs('m');
});
</script>

</body>
</html>