<?php
// /admin/exhibits.php
require_once('../common/db_inc.php');
session_start();

// 1. ログインチェック
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

// 2. 権限チェック
$sql_p = "SELECT m.name_ja FROM admin_museum_permissions amp JOIN museums m ON amp.museum_id = m.id WHERE amp.admin_id = ? AND amp.museum_id = ? AND m.deleted_at IS NULL";
$stmt_p = $pdo->prepare($sql_p);
$stmt_p->execute([$admin_id, $museum_id]);
$permission = $stmt_p->fetch();

if (!$permission) {
	header('Location: index.php');
	exit;
}

// --- 3. パラメータ取得 ---
$search = $_GET['search'] ?? '';
$filter_lang = $_GET['filter_lang'] ?? '';
$sort = $_GET['sort'] ?? 'id';     // ソート対象
$order = $_GET['order'] ?? 'DESC'; // 並び順

// ソートのバリデーション
$allowed_columns = ['id', 'title_ja', 'status', 'e_code'];
if (!in_array($sort, $allowed_columns)) $sort = 'id';
$order_sql = ($order === 'ASC') ? 'ASC' : 'DESC';

// --- 4. クエリ構築 ---
$sql_e = "SELECT * FROM exhibits WHERE museum_id = ? AND deleted_at IS NULL";
$params = [$museum_id];

if ($search) {
	$sql_e .= " AND (title_ja LIKE ? OR e_code LIKE ?)";
	$params[] = "%$search%";
	$params[] = "%$search%";
}

if ($filter_lang === 'en_missing') {
	$sql_e .= " AND (title_en = '' OR desc_en = '' OR title_en IS NULL OR desc_en IS NULL)";
} elseif ($filter_lang === 'zh_missing') {
	$sql_e .= " AND (title_zh = '' OR desc_zh = '' OR title_zh IS NULL OR desc_zh IS NULL)";
}

// 並び替え適用
$sql_e .= " ORDER BY $sort $order_sql";
$stmt_e = $pdo->prepare($sql_e);
$stmt_e->execute($params);
$exhibits = $stmt_e->fetchAll();

// 翻訳状況チェック
function is_lang_complete($title, $desc) { return (!empty($title) && !empty($desc)); }

/**
 * ソート用URL生成（現在の検索・フィルタ条件を維持）
 */
function getSortUrl($column, $current_sort, $current_order, $m_id, $s, $f) {
    $new_order = ($column === $current_sort && $current_order === 'ASC') ? 'DESC' : 'ASC';
    return "exhibits.php?id={$m_id}&sort={$column}&order={$new_order}&search=" . urlencode($s) . "&filter_lang={$f}";
}

/**
 * ソートアイコン表示
 */
function getSortIcon($column, $current_sort, $current_order) {
    if ($column !== $current_sort) return '<span style="color:#ccc; margin-left:4px; font-size:0.8em;">▲</span>';
    return ($current_order === 'ASC') 
        ? '<span style="color:var(--primary-color); margin-left:4px;">▲</span>' 
        : '<span style="color:var(--primary-color); margin-left:4px;">▼</span>';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>展示物管理 - <?= htmlspecialchars($permission['name_ja']) ?></title>
	<style>
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; --border-color: #e9ecef; }
		body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; padding: 0; color: #333; }
		
		header { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
		.btn-back { text-decoration: none; color: #666; font-size: 0.9rem; }
		
		.container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
		.card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
		
		.header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
		h1 { margin: 0; font-size: 1.5rem; }
		
		/* 検索バーのデザイン改修 */
		.search-bar { background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; gap: 15px; align-items: flex-end; }
		.form-group { display: flex; flex-direction: column; gap: 8px; }
		.form-group label { font-size: 0.75rem; font-weight: bold; color: #666; }
		input, select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9rem; outline: none; }
		input:focus, select:focus { border-color: var(--primary-color); }

		/* 検索ボタンをグリーンに統一 */
		.btn-search { background: var(--primary-color); color: white; border: none; padding: 10px 25px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: opacity 0.2s; }
		.btn-search:hover { opacity: 0.8; }
		
		/* テーブル */
		.data-table { width: 100%; border-collapse: collapse; }
		.data-table th, .data-table td { padding: 18px 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
		.data-table th { background: #fcfcfc; font-size: 0.85rem; color: #888; }
		
		/* ソート用リンク */
		.sort-link { text-decoration: none; color: inherit; display: flex; align-items: center; }
		.sort-link:hover { color: var(--primary-color); }

		.badge { padding: 4px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: bold; }
		.badge-public { background: #e3f9e5; color: #1db440; }
		.badge-private { background: #fff0f0; color: #d00; }
		
		.lang-status { display: inline-flex; gap: 5px; margin-top: 5px; }
		.lang-dot { width: 22px; height: 22px; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; background: #eee; color: #aaa; font-weight: bold; }
		.lang-dot.complete { background: var(--primary-color); color: white; }
		
		.thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 10px; background: #f0f0f0; border: 1px solid #eee; }
		
		/* 操作ボタン */
		.btn { text-decoration: none; padding: 10px 20px; border-radius: 25px; font-weight: bold; font-size: 0.9rem; cursor: pointer; transition: 0.2s; display: inline-block; }
		.btn-add { background: var(--primary-color); color: white; border: none; }
		.btn-edit { background: white; color: #666; border: 1px solid #ddd; font-size: 0.8rem; padding: 6px 14px; }
		.btn-edit:hover { border-color: var(--primary-color); color: var(--primary-color); }
		.btn-delete { color: #dc3545; font-size: 0.8rem; margin-left: 10px; text-decoration: none; }
		.btn-delete:hover { text-decoration: underline; }
	</style>
</head>
<body>

<header>
	<a href="museum_manage.php?id=<?= $museum_id ?>" class="btn-back">← 管理メニューに戻る</a>
	<div style="font-size:0.85rem; color:#888;">ログイン中: <?= htmlspecialchars($_SESSION['admin_name'] ?? '管理者') ?></div>
</header>

<div class="container">
	<div class="header-flex">
		<h1>展示物の管理 <small style="font-weight:normal; color:#888; font-size:1rem;">| <?= htmlspecialchars($permission['name_ja']) ?></small></h1>
		<div>
			<a href="exhibit_trash.php?m_id=<?= $museum_id ?>" class="btn btn-edit" style="margin-right:10px; border:none;">🗑 ゴミ箱を見る</a>
			<a href="exhibit_add.php?id=<?= $museum_id ?>" class="btn btn-add">+ 新しい展示物を登録</a>
		</div>
	</div>

	<div class="card">
		<!-- 検索フォーム -->
		<form method="GET" class="search-bar">
			<input type="hidden" name="id" value="<?= $museum_id ?>">
			<input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
			<input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
			
			<div class="form-group" style="flex-grow: 1;">
				<label>キーワード検索</label>
				<input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="展示物名、コードで検索...">
			</div>
			
			<div class="form-group">
				<label>翻訳状況フィルタ</label>
				<select name="filter_lang">
					<option value="">すべて表示</option>
					<option value="en_missing" <?= $filter_lang == 'en_missing' ? 'selected' : '' ?>>英語 未完了</option>
					<option value="zh_missing" <?= $filter_lang == 'zh_missing' ? 'selected' : '' ?>>中国語 未完了</option>
				</select>
			</div>
			
			<button type="submit" class="btn-search">検索する</button>
			<a href="exhibits.php?id=<?= $museum_id ?>" style="font-size:0.85rem; color:#aaa; margin-left:5px; text-decoration:none;">リセット</a>
		</form>

		<!-- データテーブル -->
		<table class="data-table">
			<thead>
				<tr>
					<th style="width: 80px;">
                        <a href="<?= getSortUrl('id', $sort, $order, $museum_id, $search, $filter_lang) ?>" class="sort-link">
                            ID <?= getSortIcon('id', $sort, $order) ?>
                        </a>
                    </th>
					<th style="width: 100px;">画像</th>
					<th>
                        <a href="<?= getSortUrl('title_ja', $sort, $order, $museum_id, $search, $filter_lang) ?>" class="sort-link">
                            展示物名 / 翻訳状況 <?= getSortIcon('title_ja', $sort, $order) ?>
                        </a>
                    </th>
					<th style="width: 120px;">
                        <a href="<?= getSortUrl('status', $sort, $order, $museum_id, $search, $filter_lang) ?>" class="sort-link">
                            公開状況 <?= getSortIcon('status', $sort, $order) ?>
                        </a>
                    </th>
					<th style="width: 180px;">操作</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($exhibits as $ex): ?>
				<tr>
					<td style="color:#aaa; font-weight:bold;">#<?= $ex['id'] ?></td>
					<td>
						<?php if ($ex['image_path']): ?>
							<img src="../<?= htmlspecialchars($ex['image_path']) ?>" class="thumb">
						<?php else: ?>
							<div class="thumb" style="display:flex; align-items:center; justify-content:center; font-size:0.5rem; color:#ccc;">No Image</div>
						<?php endif; ?>
					</td>
					<td>
						<div style="font-weight:bold; margin-bottom:5px;"><?= htmlspecialchars($ex['title_ja']) ?></div>
						<div class="lang-status">
							<span class="lang-dot complete" title="日本語">日</span>
							<span class="lang-dot <?= is_lang_complete($ex['title_en'], $ex['desc_en']) ? 'complete' : '' ?>" title="英語">英</span>
							<span class="lang-dot <?= is_lang_complete($ex['title_zh'], $ex['desc_zh']) ? 'complete' : '' ?>" title="中国語">中</span>
						</div>
					</td>
					<td>
						<span class="badge <?= $ex['status'] == 'public' ? 'badge-public' : 'badge-private' ?>">
							<?= $ex['status'] == 'public' ? '公開中' : '非公開' ?>
						</span>
					</td>
					<td>
						<a href="exhibit_edit.php?id=<?= $ex['id'] ?>&m_id=<?= $museum_id ?>" class="btn btn-edit">編集</a>
						<a href="exhibit_delete.php?id=<?= $ex['id'] ?>&m_id=<?= $museum_id ?>" class="btn-delete" onclick="return confirm('この展示物をゴミ箱に移動しますか？')">削除</a>
					</td>
				</tr>
				<?php endforeach; ?>
				
				<?php if (empty($exhibits)): ?>
				<tr>
					<td colspan="5" style="text-align:center; padding:80px; color:#aaa;">該当する展示物はありません。</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

</body>
</html>