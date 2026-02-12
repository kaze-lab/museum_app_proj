<?php
// /admin/museum_qr_print.php
require_once('../common/db_inc.php');
session_start();

if (!isset($_SESSION['admin_logged_in'])) { exit('Access Denied'); }

$m_id = $_GET['m_id'] ?? null;
if (!$m_id) { exit('Museum ID missing'); }

// 博物館名とm_codeを取得
$stmt = $pdo->prepare("SELECT name_ja, m_code FROM museums WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$m_id]);
$museum = $stmt->fetch();

if (!$museum) { exit('Museum not found'); }

// 博物館トップページ（view.php）のURLを構築
$base_url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . str_replace('admin/museum_qr_print.php', 'app/view.php', $_SERVER['SCRIPT_NAME']);
$target_url = "{$base_url}?m={$museum['m_code']}";
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>掲示用QRコード印刷 - <?= htmlspecialchars($museum['name_ja']) ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root { --primary-color: #26b396; }
        body { font-family: sans-serif; margin: 0; background: #525659; display: flex; flex-direction: column; align-items: center; }
        
        /* ツールバー */
        .toolbar { 
            position: sticky; top: 0; width: 100%; background: #323639; 
            padding: 15px 40px; box-sizing: border-box; display: flex; 
            justify-content: space-between; align-items: center; color: white; z-index: 100;
        }
        .btn-print { background: var(--primary-color); color: white; border: none; padding: 12px 40px; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 1rem; }

        /* A4ポスター用紙 */
        .poster-page { 
            width: 210mm; height: 296mm; background: white; margin: 40px auto; 
            box-shadow: 0 0 20px rgba(0,0,0,0.5); padding: 20mm; box-sizing: border-box;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center;
        }

        /* デザイン：館名とQR */
        .museum-name { font-size: 2.5rem; font-weight: bold; margin-bottom: 60px; color: #000; }
        .qr-container { padding: 20px; border: 1px solid #eee; border-radius: 20px; }
        
        /* 印刷設定 */
        @media print {
            body { background: white; }
            .toolbar { display: none !important; }
            .poster-page { margin: 0; box-shadow: none; width: 100%; height: 100vh; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <div style="font-weight:bold;">館内掲示用ポスター印刷</div>
    <button class="btn-print" onclick="window.print()">印刷を実行する</button>
</div>

<div class="poster-page">
    <div class="museum-name"><?= htmlspecialchars($museum['name_ja']) ?></div>
    
    <div class="qr-container">
        <div id="qrcode"></div>
    </div>
    
    <div style="margin-top: 60px; font-size: 1.2rem; color: #666; letter-spacing: 2px;">
        スマートフォンでQRコードをスキャンして<br>展示ガイドをスタート
    </div>
</div>

<script>
window.onload = function() {
    new QRCode(document.getElementById("qrcode"), {
        text: "<?= $target_url ?>",
        width: 400, // ポスター用なので大きく生成
        height: 400,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
};
</script>

</body>
</html>