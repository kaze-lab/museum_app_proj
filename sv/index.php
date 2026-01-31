<?php
require_once('../common/db_inc.php');
require_once('_header.php');

// --- 1. パラメータの取得 ---
$keyword	 = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$category_id = $_GET['category_id'] ?? '';
$is_active	 = $_GET['is_active'] ?? '';
$sort		 = $_GET['sort'] ?? 'id_desc';

// --- 2. カテゴリ一覧の取得 ---
$cat_list_stmt = $pdo->query("SELECT * FROM categories ORDER BY id");
$cat_list = $cat_list_stmt->fetchAll();

// --- 3. SQLの組み立て (deleted_at IS NULL を追加) ---
$sql = "
	SELECT 
		m.id, 
		m.name_ja, 
		m.name_kana, 
		COALESCE(c.name, '未設定') AS category_name, 
		m.is_active 
	FROM 
		museums m 
	LEFT JOIN 
		categories c ON m.category_id = c.id 
	WHERE 
		m.deleted_at IS NULL
";

$params = [];
if ($keyword !== '') {
	$sql .= " AND (m.name_ja LIKE :keyword OR m.name_kana LIKE :keyword)";
	$params[':keyword'] = '%' . $keyword . '%';
}
if ($category_id !== '') {
	$sql .= " AND m.category_id = :category_id";
	$params[':category_id'] = $category_id;
}
if ($is_active !== '') {
	$sql .= " AND m.is_active = :is_active";
	$params[':is_active'] = $is_active;
}

// ソート適用
switch ($sort) {
	case 'id_asc':	  $sql .= " ORDER BY m.id ASC"; break;
	case 'id_desc':   $sql .= " ORDER BY m.id DESC"; break;
	case 'kana_asc':  $sql .= " ORDER BY m.name_kana ASC"; break;
	case 'kana_desc': $sql .= " ORDER BY m.name_kana DESC"; break;
	default:		  $sql .= " ORDER BY m.id DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$museums = $stmt->fetchAll();

// ヘルパー関数
function getSortUrl($base, $current_sort, $k, $c, $i) {
	$next = ($current_sort === $base . '_asc') ? $base . '_desc' : $base . '_asc';
	return "index.php?sort={$next}&keyword=" . urlencode($k) . "&category_id={$c}&is_active={$i}";
}
function getSortIcon($base, $current_sort) {
	if (strpos($current_sort, $base) === 0) {
		return ($current_sort === $base . '_asc') ? '<span style="color:#333; margin-left:4px;">▲</span>' : '<span style="color:#333; margin-left:4px;">▼</span>';
	}
	return '<span style="color:#ccc; margin-left:4px; font-size:0.8em;">▲</span>';
}
?>

<title>博物館の管理 - 博物館ガイド</title>
<style>
	.card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-top: 20px; }
	.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color); }
	.card-header h2 { margin: 0; font-size: 1.4em; }
	.filter-bar { background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; gap: 15px; align-items: flex-end; }
	.filter-group { display: flex; flex-direction: column; gap: 8px; }
	.filter-group label { font-size: 0.85em; font-weight: bold; color: #555; }
	.data-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
	.data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
	.data-table th { background-color: #fcfcfc; color: #555; font-size: 0.9em; }
	.col-id { width: 90px; }
	.col-action { width: 180px; text-align: center !important; }
	.name-ja { font-weight: bold; color: #333; margin-bottom: 2px; }
	.name-kana { font-size: 0.75rem; color: #888; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
	.sort-link { text-decoration: none; color: inherit; display: flex; align-items: center; font-weight: bold; }
	.status-badge { padding: 4px 12px; border-radius: 15px; font-size: 0.8em; font-weight: bold; }
	.status-public { background: #e6fff0; color: #1e7e34; }
	.status-private { background: #fff0f0; color: #d00; }
	.btn { text-decoration: none; padding: 10px 20px; border-radius: 25px; font-weight: bold; font-size: 13px; cursor: pointer; display: inline-block; }
	.btn-primary { background: var(--primary-color); color: white; border: none; }
	.btn-outline { background: white; color: #666; border: 1px solid #ddd; font-size: 12px; padding: 6px 14px; }
	.alert { background: #e6fff0; color: #1e7e34; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
</style>

<div class="container">
	<div class="card">
		<div class="card-header">
			<h2>登録済み博物館一覧</h2>
			<div>
				<!-- システム設定へのリンクを追加 -->
				<a href="settings.php" class="btn btn-outline" style="border:none; margin-right:10px;">⚙ システム設定</a>
				<a href="trash.php" class="btn btn-outline" style="border:none; margin-right:10px;">🗑 ゴミ箱を見る</a>
				<a href="museum_add.php" class="btn btn-primary">+ 新しい博物館を登録</a>
			</div>
		</div>

		<?php if (isset($_GET['msg'])): ?>
			<div class="alert">
				<?php
					if($_GET['msg']==='added') echo "正常に登録されました。";
					if($_GET['msg']==='updated') echo "情報を更新しました。";
					if($_GET['msg']==='trashed') echo "博物館をゴミ箱に移動しました。";
				?>
			</div>
		<?php endif; ?>

		<form method="GET" class="filter-bar">
			<input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
			<div class="filter-group" style="flex-grow: 1;">
				<label>キーワード</label>
				<input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="例: 国立科学">
			</div>
			<div class="filter-group">
				<label>カテゴリ</label>
				<select name="category_id">
					<option value="">すべて</option>
					<?php foreach ($cat_list as $cat): ?>
						<option value="<?= $cat['id'] ?>" <?= ($category_id == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="filter-group">
				<label>公開状況</label>
				<select name="is_active">
					<option value="">すべて</option>
					<option value="1" <?= ($is_active === '1') ? 'selected' : '' ?>>公開中</option>
					<option value="0" <?= ($is_active === '0') ? 'selected' : '' ?>>非公開</option>
				</select>
			</div>
			<button type="submit" class="btn" style="background:#555; color:white; padding:10px 25px; border-radius:8px;">検索</button>
			<a href="index.php" class="btn btn-outline" style="border:none; margin-left:5px;">リセット</a>
		</form>

		<?php if (count($museums) > 0): ?>
			<table class="data-table">
				<thead>
					<tr>
						<th class="col-id">
							<a href="<?= getSortUrl('id', $sort, $keyword, $category_id, $is_active) ?>" class="sort-link">ID <?= getSortIcon('id', $sort) ?></a>
						</th>
						<th class="col-name">
							<a href="<?= getSortUrl('kana', $sort, $keyword, $category_id, $is_active) ?>" class="sort-link">博物館名 (かな順) <?= getSortIcon('kana', $sort) ?></a>
						</th>
						<th class="col-category">カテゴリ</th>
						<th class="col-status">公開状況</th>
						<th class="col-action">操作</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($museums as $m): ?>
					<tr>
						<td><?= htmlspecialchars($m['id']) ?></td>
						<td>
							<div class="name-ja"><?= htmlspecialchars($m['name_ja']) ?></div>
							<div class="name-kana"><?= htmlspecialchars($m['name_kana']) ?></div>
						</td>
						<td><?= htmlspecialchars($m['category_name']) ?></td>
						<td><span class="status-badge <?= $m['is_active'] == 1 ? 'status-public' : 'status-private' ?>"><?= $m['is_active'] == 1 ? '公開中' : '非公開' ?></span></td>
						<td class="col-action">
							<a href="museum_edit.php?id=<?= $m['id'] ?>" class="btn btn-outline">編集</a>
							<a href="museum_delete.php?id=<?= $m['id'] ?>" class="btn btn-outline" onclick="return confirm('ゴミ箱に移動しますか？')">削除</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else: ?>
			<div style="text-align:center; padding:50px; color:#888;">登録されている博物館はありません。</div>
		<?php endif; ?>
	</div>
</div>
</body>
</html>