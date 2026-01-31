<?php
// /admin/exhibits.php
require_once('../common/db_inc.php'); // ここで $pdo が作成されます
session_start();

// 1. ログインチェック（museum_manage.php の仕様に統一）
if (!isset($_SESSION['admin_logged_in'])) {
	header('Location: login.php');
	exit;
}

$admin_id = $_SESSION['admin_id'];
$museum_id = $_GET['id'] ?? null; // URLパラメータからIDを取得

if (!$museum_id) {
	header('Location: index.php');
	exit;
}

// 2. 権限チェック（museum_manage.php のロジックを完全継承）
$sql_p = "
	SELECT 
		m.name_ja, 
		amp.role 
	FROM 
		admin_museum_permissions amp
	JOIN 
		museums m ON amp.museum_id = m.id
	WHERE 
		amp.admin_id = ? 
		AND amp.museum_id = ? 
		AND m.deleted_at IS NULL
";
$stmt_p = $pdo->prepare($sql_p); // $pdo を直接使用
$stmt_p->execute([$admin_id, $museum_id]);
$permission = $stmt_p->fetch();

// 権限がない、または博物館が存在しない場合は戻す
if (!$permission) {
	header('Location: index.php');
	exit;
}

// 3. 展示物一覧の取得（検索・ソート・フィルタリング）
$search = $_GET['search'] ?? '';
$filter_lang = $_GET['filter_lang'] ?? '';
$sort = $_GET['sort'] ?? 'id';
$order = $_GET['order'] ?? 'DESC';

// ソート用バリデーション
$allowed_sorts = ['id', 'e_code', 'title_ja', 'status'];
if (!in_array($sort, $allowed_sorts)) $sort = 'id';
$order_sql = ($order === 'ASC') ? 'ASC' : 'DESC';

// 基本クエリ（論理削除されていないもの）
$sql_e = "SELECT * FROM exhibits WHERE museum_id = ? AND deleted_at IS NULL";
$params = [$museum_id];

if ($search) {
	$sql_e .= " AND (title_ja LIKE ? OR e_code LIKE ?)";
	$params[] = "%$search%";
	$params[] = "%$search%";
}

// 翻訳状況フィルタ
if ($filter_lang === 'en_missing') {
	$sql_e .= " AND (title_en = '' OR desc_en = '' OR title_en IS NULL OR desc_en IS NULL)";
} elseif ($filter_lang === 'zh_missing') {
	$sql_e .= " AND (title_zh = '' OR desc_zh = '' OR title_zh IS NULL OR desc_zh IS NULL)";
}

$sql_e .= " ORDER BY $sort $order_sql";
$stmt_e = $pdo->prepare($sql_e);
$stmt_e->execute($params);
$exhibits = $stmt_e->fetchAll();

// 翻訳完了判定関数
function is_lang_complete($title, $desc) {
	return (!empty($title) && !empty($desc));
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<title>展示物管理 - <?= htmlspecialchars($permission['name_ja']) ?></title>
	<style>
		/* museum_manage.phpのスタイルを継承 */
		:root { --primary-color: #26b396; --bg-color: #f4f7f7; --border-color: #e9ecef; }
		body { font-family: sans-serif; background-color: var(--bg-color); margin: 0; padding: 0; color: #333; }
		
		header { background: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
		.btn-back { text-decoration: none; color: #666; font-size: 0.9rem; }
		
		.container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
		.card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
		
		.header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
		h1 { margin: 0; font-size: 1.5rem; }
		
		/* 検索エリア */
		.search-bar { background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; gap: 15px; align-items: flex-end; }
		.form-group { display: flex; flex-direction: column; gap: 5px; }
		input, select { padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9rem; }
		
		/* テーブル */
		.data-table { width: 100%; border-collapse: collapse; }
		.data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
		.data-table th { color: #888; font-size: 0.85rem; }
		
		/* 状態バッジ・ドット */
		.badge { padding: 4px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: bold; }
		.badge-public { background: #e3f9e5; color: #1db440; }
		.badge-private { background: #fff0f0; color: #d00; }
		
		.lang-status { display: inline-flex; gap: 5px; margin-top: 5px; }
		.lang-dot { width: 22px; height: 22px; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; background: #eee; color: #aaa; font-weight: bold; }
		.lang-dot.complete { background: var(--primary-color); color: white; }
		
		.thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; background: #f0f0f0; }
		
		/* ボタン */
		.btn { text-decoration: none; padding: 10px 20px; border-radius: 25px; font-weight: bold; font-size: 0.9rem; cursor: pointer; transition: 0.2s; display: inline-block; }
		.btn-add { background: var(--primary-color); color: white; border: none; }
		.btn-edit { background: #f4f4f4; color: #666; }
		.btn-delete { color: #d00; font-size: 0.8rem; margin-left: 10px; text-decoration: none; }
		.btn-search { background: #333; color: white; border: none; padding: 10px 25px; }
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
		<a href="exhibit_add.php?id=<?= $museum_id ?>" class="btn btn-add">+ 新しい展示物を登録</a>
	</div>

	<div class="card">
		<form method="GET" class="search-bar">
			<input type="hidden" name="id" value="<?= $museum_id ?>">
			<div class="form-group">
				<label style="font-size:0.75rem; font-weight:bold; color:#666;">キーワード検索</label>
				<input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="名称、コード...">
			</div>
			<div class="form-group">
				<label style="font-size:0.75rem; font-weight:bold; color:#666;">翻訳状況フィルタ</label>
				<select name="filter_lang">
					<option value="">すべて表示</option>
					<option value="en_missing" <?= $filter_lang == 'en_missing' ? 'selected' : '' ?>>英語 未完了</option>
					<option value="zh_missing" <?= $filter_lang == 'zh_missing' ? 'selected' : '' ?>>中国語 未完了</option>
				</select>
			</div>
			<button type="submit" class="btn btn-search">検索</button>
			<a href="exhibits.php?id=<?= $museum_id ?>" style="font-size:0.85rem; color:#aaa; margin-bottom:10px;">リセット</a>
		</form>

		<table class="data-table">
			<thead>
				<tr>
					<th>ID</th>
					<th>画像</th>
					<th>展示物名 / 翻訳状況</th>
					<th>公開状況</th>
					<th>操作</th>
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
					<td colspan="5" style="text-align:center; padding:60px; color:#aaa;">登録されている展示物はありません。</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

</body>
</html>