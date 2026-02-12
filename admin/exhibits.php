<?php
// /admin/exhibits.php
require_once('../common/db_inc.php');
session_start();

if (!isset($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }

$admin_id = $_SESSION['admin_id'];
$museum_id = $_GET['id'] ?? null;
if (!$museum_id) { header('Location: index.php'); exit; }

// 権限チェック
$sql_p = "SELECT m.name_ja FROM admin_museum_permissions amp JOIN museums m ON amp.museum_id = m.id WHERE amp.admin_id = ? AND amp.museum_id = ? AND m.deleted_at IS NULL";
$stmt_p = $pdo->prepare($sql_p);
$stmt_p->execute([$admin_id, $museum_id]);
$permission = $stmt_p->fetch();
if (!$permission) { header('Location: index.php'); exit; }

// --- パラメータ取得 ---
$search = $_GET['search'] ?? '';
$filter_lang = $_GET['filter_lang'] ?? '';
$sort = $_GET['sort'] ?? 'id';
$order = $_GET['order'] ?? 'DESC';

$allowed_columns = ['id', 'title_ja', 'status'];
if (!in_array($sort, $allowed_columns)) $sort = 'id';
$order_sql = ($order === 'ASC') ? 'ASC' : 'DESC';

// --- クエリ構築 ---
$sql_e = "SELECT * FROM exhibits WHERE museum_id = ? AND deleted_at IS NULL";
$params = [$museum_id];
if ($search) {
	$sql_e .= " AND (title_ja LIKE ? OR e_code LIKE ?)";
	$params[] = "%$search%"; $params[] = "%$search%";
}
if ($filter_lang === 'en_missing') {
	$sql_e .= " AND (title_en = '' OR desc_en = '' OR title_en IS NULL OR desc_en IS NULL)";
} elseif ($filter_lang === 'zh_missing') {
	$sql_e .= " AND (title_zh = '' OR desc_zh = '' OR title_zh IS NULL OR desc_zh IS NULL)";
}
$sql_e .= " ORDER BY $sort $order_sql";
$stmt_e = $pdo->prepare($sql_e);
$stmt_e->execute($params);
$exhibits = $stmt_e->fetchAll();

// --- 一括削除処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    if (!empty($_POST['selected_ids'])) {
        $ids = $_POST['selected_ids'];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt_bulk = $pdo->prepare("UPDATE exhibits SET deleted_at = NOW() WHERE id IN ($placeholders) AND museum_id = ?");
        $stmt_bulk->execute(array_merge($ids, [$museum_id]));
        header("Location: exhibits.php?id={$museum_id}&msg=bulk_trashed");
        exit;
    }
}

// ヘルパー
function is_lang_complete($t, $d) { return (!empty($t) && !empty($d)); }
function getSortUrl($col, $cur, $ord, $m_id, $s, $f) {
    $new_ord = ($col === $cur && $ord === 'ASC') ? 'DESC' : 'ASC';
    return "exhibits.php?id={$m_id}&sort={$col}&order={$new_ord}&search=".urlencode($s)."&filter_lang={$f}";
}
function getSortIcon($col, $cur, $ord) {
    if ($col !== $cur) return '<span style="color:#ccc; margin-left:4px; font-size:0.8em;">▲</span>';
    return ($ord === 'ASC') ? '<span style="color:var(--primary-color);">▲</span>' : '<span style="color:var(--primary-color);">▼</span>';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>展示物管理 - <?= htmlspecialchars($permission['name_ja']) ?></title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #ffffff; --row-hover: #f2f5f5; --row-selected: #e8f0fe; --text-main: #202124; --text-sub: #5f6368; }
		body { font-family: 'Google Sans', Roboto, Arial, sans-serif; background-color: #f6f8fc; margin: 0; padding: 0; color: var(--text-main); }
		
		/* ヘッダー */
		header { background: white; padding: 10px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e0e0e0; }
		.btn-back { text-decoration: none; color: var(--text-sub); font-size: 1.2rem; padding: 8px; border-radius: 50%; transition: background 0.2s; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
		.btn-back:hover { background: #eee; }
		
		.container { max-width: 1200px; margin: 15px auto; padding: 0 20px; }

		/* 上部パネル（検索と操作の入れ替え） */
		.top-bar { background: white; height: 60px; border-radius: 12px; margin-bottom: 10px; position: relative; overflow: hidden; display: flex; align-items: center; box-shadow: 0 1px 2px rgba(60,64,67,0.3); }
		.bar-inner { position: absolute; inset: 0; padding: 0 20px; display: flex; align-items: center; transition: transform 0.2s, opacity 0.2s; }
		
		/* 検索モード */
		#search-mode { opacity: 1; visibility: visible; }
		.search-box { display: flex; gap: 15px; flex: 1; align-items: center; }
		.search-box input { border: none; background: #f1f3f4; padding: 10px 20px; border-radius: 8px; width: 300px; font-size: 0.9rem; outline: none; }
		.search-box input:focus { background: white; box-shadow: 0 1px 1px rgba(65,69,73,0.3); }
		.btn-search { background: var(--primary-color); color: white; border: none; padding: 8px 20px; border-radius: 4px; font-weight: bold; cursor: pointer; }

		/* 一括操作モード（Gmail風） */
		#bulk-mode { opacity: 0; visibility: hidden; transform: translateY(10px); background: white; justify-content: flex-start; gap: 20px; }
		#bulk-mode.active { opacity: 1; visibility: visible; transform: translateY(0); }
		.bulk-count { font-size: 0.9rem; color: var(--text-main); font-weight: 500; min-width: 100px; }
		.icon-btn { background: none; border: none; padding: 10px; border-radius: 50%; cursor: pointer; color: var(--text-sub); transition: background 0.2s; display: flex; align-items: center; justify-content: center; }
		.icon-btn:hover { background: #eee; color: var(--text-main); }
		.v-line { width: 1px; height: 24px; background: #e0e0e0; }

		/* テーブルデザイン */
		.main-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 2px rgba(60,64,67,0.3); }
		.data-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
		.data-table th { padding: 12px 15px; text-align: left; font-size: 0.8rem; color: var(--text-sub); border-bottom: 1px solid #f1f3f4; font-weight: 500; }
		.data-table td { padding: 8px 15px; border-bottom: 1px solid #f1f3f4; font-size: 0.9rem; position: relative; height: 60px; }
		
		/* カラム幅固定 */
		.col-check { width: 48px; }
		.col-id { width: 70px; }
		.col-img { width: 70px; }
		.col-status-action { width: 220px; text-align: right !important; }

		/* 行ホバー挙動 */
		.data-table tr:hover { background-color: var(--row-hover); box-shadow: inset 1px 0 0 #dadce0, inset -1px 0 0 #dadce0, 0 1px 2px 0 rgba(60,64,67,.3), 0 1px 3px 1px rgba(60,64,67,.15); z-index: 1; }
		.data-table tr.selected { background-color: var(--row-selected) !important; }

		/* チェックボックスの円形ホバー */
		.check-wrapper { position: relative; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: background 0.2s; }
		.check-wrapper:hover { background: rgba(95,99,104,0.1); }
		.check-wrapper input { cursor: pointer; width: 18px; height: 18px; margin: 0; }

		/* サムネイル */
		.thumb { width: 45px; height: 45px; border-radius: 4px; object-fit: cover; background: #f1f3f4; }

		/* 多言語ドット */
		.lang-dot { width: 18px; height: 18px; border-radius: 3px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.6rem; background: #eee; color: #aaa; font-weight: bold; margin-right: 2px; }
		.lang-dot.complete { background: var(--primary-color); color: white; }

		/* ステータス vs 操作（入れ替えアニメーション） */
		.status-label { transition: opacity 0.15s; opacity: 1; font-weight: 500; font-size: 0.85rem; }
		.badge-pub { color: #1e8e3e; }
		.badge-priv { color: #d93025; }

		.row-actions { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); opacity: 0; visibility: hidden; transition: opacity 0.15s; display: flex; gap: 12px; background: inherit; padding-left: 20px; }
		tr:hover .status-label { opacity: 0; }
		tr:hover .row-actions { opacity: 1; visibility: visible; }

		.action-link { text-decoration: none; color: var(--text-sub); font-size: 0.8rem; font-weight: bold; padding: 4px 8px; border-radius: 4px; }
		.action-link:hover { background: #e0e0e0; color: var(--text-main); }
		.link-delete:hover { color: #d93025; background: #feebeb; }
	</style>
</head>
<body>

<header>
	<div style="display:flex; align-items:center; gap:20px;">
		<a href="museum_manage.php?id=<?= $museum_id ?>" class="btn-back" title="戻る">←</a>
		<div style="font-weight:500; font-size:1.1rem;"><?= htmlspecialchars($permission['name_ja']) ?></div>
	</div>
	<a href="exhibit_add.php?id=<?= $museum_id ?>" style="text-decoration:none; background:var(--primary-color); color:white; padding:8px 24px; border-radius:4px; font-size:0.9rem; font-weight:bold; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">新規登録</a>
</header>

<div class="container">
	<!-- 上部パネル：Gmail風入れ替え -->
	<div class="top-bar">
		<div class="bar-inner" id="search-mode">
			<form method="GET" class="search-box">
				<input type="hidden" name="id" value="<?= $museum_id ?>">
				<input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="展示物内を検索">
				<select name="filter_lang">
					<option value="">すべての翻訳状況</option>
					<option value="en_missing" <?= $filter_lang=='en_missing'?'selected':'' ?>>英語 未完了</option>
					<option value="zh_missing" <?= $filter_lang=='zh_missing'?'selected':'' ?>>中国語 未完了</option>
				</select>
				<button type="submit" class="btn-search">検索</button>
			</form>
		</div>
		
		<div class="bar-inner" id="bulk-mode">
			<div class="bulk-count"><span id="selected-count">0</span> 件を選択中</div>
			<div class="v-line"></div>
			<button type="button" class="icon-btn" title="一括QR印刷" onclick="bulkAction('print')">🖨️</button>
			<button type="button" class="icon-btn" title="一括削除" onclick="bulkAction('delete')">🗑️</button>
			<button type="button" class="icon-btn" title="選択解除" onclick="cancelAll()">✕</button>
		</div>
	</div>

	<div class="main-card">
		<form method="POST" id="bulk-form">
			<input type="hidden" name="action" id="bulk-action-val">
			<table class="data-table">
				<thead>
					<tr>
						<th class="col-check">
							<div class="check-wrapper" title="すべて選択">
								<input type="checkbox" id="master-check">
							</div>
						</th>
						<th class="col-id"><a href="<?= getSortUrl('id',$sort,$order,$museum_id,$search,$filter_lang) ?>" class="sort-link">ID<?= getSortIcon('id',$sort,$order) ?></a></th>
						<th class="col-img">画像</th>
						<th><a href="<?= getSortUrl('title_ja',$sort,$order,$museum_id,$search,$filter_lang) ?>" class="sort-link">名称 / 翻訳状況<?= getSortIcon('title_ja',$sort,$order) ?></a></th>
						<th class="col-status-action">操作</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($exhibits as $ex): ?>
					<tr id="row-<?= $ex['id'] ?>">
						<td class="col-check">
							<div class="check-wrapper" title="選択">
								<input type="checkbox" name="selected_ids[]" value="<?= $ex['id'] ?>" class="item-check">
							</div>
						</td>
						<td style="color:#bdc1c6; font-size:0.8rem;">#<?= $ex['id'] ?></td>
						<td><img src="../<?= $ex['image_path'] ?: 'img/no-image.webp' ?>" class="thumb"></td>
						<td>
							<div style="font-weight:500; margin-bottom:4px;"><?= htmlspecialchars($ex['title_ja']) ?></div>
							<div style="display:flex;">
								<span class="lang-dot complete">日</span>
								<span class="lang-dot <?= is_lang_complete($ex['title_en'],$ex['desc_en'])?'complete':'' ?>">英</span>
								<span class="lang-dot <?= is_lang_complete($ex['title_zh'],$ex['desc_zh'])?'complete':'' ?>">中</span>
							</div>
						</td>
						<td class="col-status-action">
							<!-- 通常時：ステータスを表示 -->
							<div class="status-label <?= $ex['status']=='public'?'badge-pub':'badge-priv' ?>">
								<?= $ex['status']=='public' ? '● 公開' : '● 非公開' ?>
							</div>
							<!-- ホバー時：操作リンクを表示 -->
							<div class="row-actions">
								<a href="exhibit_edit.php?id=<?= $ex['id'] ?>&m_id=<?= $museum_id ?>" class="action-link">編集</a>
								<a href="qr_print.php?m_id=<?= $museum_id ?>&id=<?= $ex['id'] ?>" target="_blank" class="action-link">QR印刷</a>
								<a href="exhibit_delete.php?id=<?= $ex['id'] ?>&m_id=<?= $museum_id ?>" class="action-link link-delete" onclick="return confirm('削除しますか？')">削除</a>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
					<?php if(empty($exhibits)): ?>
						<tr><td colspan="5" style="text-align:center; padding:100px; color:#999;">展示物がありません。</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</form>
	</div>
</div>

<script>
const masterCheck = document.getElementById('master-check');
const itemChecks = document.querySelectorAll('.item-check');
const searchMode = document.getElementById('search-mode');
const bulkMode = document.getElementById('bulk-mode');
const countSpan = document.getElementById('selected-count');

masterCheck.addEventListener('change', () => {
	itemChecks.forEach(c => c.checked = masterCheck.checked);
	updateUI();
});

itemChecks.forEach(c => {
	c.addEventListener('change', updateUI);
});

function updateUI() {
	const selectedCount = Array.from(itemChecks).filter(c => c.checked).length;
	
	// 行のハイライト
	itemChecks.forEach(c => {
		const row = document.getElementById('row-' + c.value);
		if (c.checked) row.classList.add('selected');
		else row.classList.remove('selected');
	});

	if (selectedCount > 0) {
		searchMode.style.opacity = '0'; searchMode.style.visibility = 'hidden';
		bulkMode.classList.add('active');
		countSpan.innerText = selectedCount;
	} else {
		searchMode.style.opacity = '1'; searchMode.style.visibility = 'visible';
		bulkMode.classList.remove('active');
		masterCheck.checked = false;
	}
}

function cancelAll() {
	itemChecks.forEach(c => c.checked = false);
	masterCheck.checked = false;
	updateUI();
}

function bulkAction(type) {
	const selectedIds = Array.from(itemChecks).filter(c => c.checked).map(c => c.value);
	if (type === 'print') {
		const form = document.createElement('form');
		form.method = 'POST'; form.action = 'qr_print.php?m_id=<?= $museum_id ?>'; form.target = '_blank';
		const input = document.createElement('input');
		input.type = 'hidden'; input.name = 'ids'; input.value = JSON.stringify(selectedIds);
		form.appendChild(input);
		document.body.appendChild(form); form.submit(); document.body.removeChild(form);
	} else if (type === 'delete') {
		if (confirm(selectedIds.length + ' 件の項目を削除（ゴミ箱へ移動）しますか？')) {
			document.getElementById('bulk-action-val').value = 'bulk_delete';
			document.getElementById('bulk-form').submit();
		}
	}
}
</script>
</body>
</html>