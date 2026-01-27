<?php
require_once('../common/db_inc.php');
require_once('_header.php');

// --- 1. パラメータの取得 ---
$keyword	 = $_GET['keyword'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$is_active	 = $_GET['is_active'] ?? '';
$sort		 = $_GET['sort'] ?? 'kana_asc'; // デフォルトはかな昇順

// --- 2. カテゴリ一覧の取得 (検索用ドロップダウン) ---
$cat_stmt = $pdo->query("SELECT * FROM categories ORDER BY id");
$categories = $cat_stmt->fetchAll();

// --- 3. SQLの組み立て (検索・抽出ロジック) ---
$sql = "
	SELECT 
		m.id, 
		m.name_ja, 
		m.name_kana, 
		c.name AS category_name, 
		m.is_active 
	FROM 
		museums m
	JOIN 
		categories c ON m.category_id = c.id
	WHERE 1=1
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

// ソート順の適用
if ($sort === 'kana_desc') {
	$sql .= " ORDER BY m.name_kana DESC";
} else {
	$sql .= " ORDER BY m.name_kana ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$museums = $stmt->fetchAll();

// 次のソート順を決定 (ヘッダークリック用)
$next_sort = ($sort === 'kana_asc') ? 'kana_desc' : 'kana_asc';
$sort_icon = ($sort === 'kana_asc') ? '▲' : '▼';
?>

<title>博物館の管理 - 博物館ガイド</title>
<style>
	.card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-top: 20px; }
	.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color); }
	.card-header h2 { margin: 0; font-size: 1.4em; }

	/* 検索バーのスタイル */
	.filter-bar { background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
	.filter-group { display: flex; flex-direction: column; gap: 5px; }
	.filter-group label { font-size: 0.8em; font-weight: bold; color: #666; }
	.filter-bar input, .filter-bar select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9em; }
	
	/* テーブルレイアウトの最適化 */
	.data-table { width: 100%; border-collapse: collapse; table-layout: fixed; } /* 固定レイアウト */
	.data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
	.data-table th { background-color: #fcfcfc; color: #555; font-size: 0.85em; }

	/* 各カラムの幅指定 */
	.col-id { width: 60px; }
	.col-name { width: auto; } /* 博物館名は可変 */
	.col-category { width: 140px; }
	.col-status { width: 110px; }
	.col-action { width: 180px; text-align: center !important; } /* 操作ボタンを中央寄せ */

	/* 博物館名とかなの処理 */
	.name-ja { font-weight: bold; color: #333; margin-bottom: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	.name-kana { 
		font-size: 0.75rem; color: #888; 
		display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; /* 2行制限 */
		overflow: hidden; line-height: 1.4;
	}

	/* ソートリンク */
	.sort-link { text-decoration: none; color: inherit; display: flex; align-items: center; gap: 5px; }
	.sort-link:hover { color: var(--primary-color); }

	.status-badge { padding: 4px 10px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
	.status-public { background: #e6fff0; color: #1e7e34; }
	.status-private { background: #fff0f0; color: #d00; }

	.btn { text-decoration: none; padding: 10px 18px; border-radius: 25px; font-weight: bold; font-size: 13px; cursor: pointer; display: inline-block; }
	.btn-primary { background: var(--primary-color); color: white; border: none; }
	.btn-search { background: #555; color: white; border: none; padding: 9px 20px; }
	.btn-outline { background: white; color: #555; border: 1px solid #ddd; font-size: 12px; padding: 6px 14px; }
	.btn-outline:hover { background: #f8f9fa; }
</style>

<div class="container">
	<div class="card">
		<div class="card-header">
			<h2>登録済み博物館一覧</h2>
			<a href="museum_add.php" class="btn btn-primary">+ 新しい博物館を登録</a>
		</div>

		<!-- 検索・抽出バー -->
		<form method="GET" action="index.php" class="filter-bar">
			<input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
			
			<div class="filter-group" style="flex-grow: 1;">
				<label>キーワード（名前・かな）</label>
				<input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="例: 国立科学">
			</div>

			<div class="filter-group">
				<label>カテゴリ</label>
				<select name="category_id">
					<option value="">すべて</option>
					<?php foreach ($categories as $cat): ?>
						<option value="<?= $cat['id'] ?>" <?= ($category_id == $cat['id']) ? 'selected' : '' ?>>
							<?= htmlspecialchars($cat['name']) ?>
						</option>
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

			<div class="filter-actions">
				<button type="submit" class="btn btn-search">検索</button>
				<a href="index.php" class="btn btn-outline" style="border:none;">リセット</a>
			</div>
		</form>

		<?php if (isset($_GET['msg'])): ?>
			<div style="background:#e6fff0; color:#1e7e34; padding:15px; border-radius:10px; margin-bottom:20px;">
				<?php
					if($_GET['msg']==='added') echo "正常に登録されました。";
					if($_GET['msg']==='updated') echo "情報を更新しました。";
					if($_GET['msg']==='deleted') echo "削除しました。";
				?>
			</div>
		<?php endif; ?>

		<?php if (count($museums) > 0): ?>
			<table class="data-table">
				<thead>
					<tr>
						<th class="col-id">ID</th>
						<th class="col-name">
							<a href="index.php?sort=<?= $next_sort ?>&keyword=<?= urlencode($keyword) ?>&category_id=<?= $category_id ?>&is_active=<?= $is_active ?>" class="sort-link">
								博物館名 (かな順) <span style="font-size: 0.7em;"><?= $sort_icon ?></span>
							</a>
						</th>
						<th class="col-category">カテゴリ</th>
						<th class="col-status">公開状況</th>
						<th class="col-action">操作</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($museums as $m): ?>
					<tr>
						<td class="col-id"><?= htmlspecialchars($m['id']) ?></td>
						<td class="col-name">
							<div class="name-ja"><?= htmlspecialchars($m['name_ja']) ?></div>
							<div class="name-kana"><?= htmlspecialchars($m['name_kana']) ?></div>
						</td>
						<td class="col-category"><?= htmlspecialchars($m['category_name']) ?></td>
						<td class="col-status">
							<?php if ($m['is_active'] == 1): ?>
								<span class="status-badge status-public">公開中</span>
							<?php else: ?>
								<span class="status-badge status-private">非公開</span>
							<?php endif; ?>
						</td>
						<td class="col-action">
							<a href="museum_edit.php?id=<?= $m['id'] ?>" class="btn btn-outline">編集</a>
							<a href="museum_delete.php?id=<?= $m['id'] ?>" class="btn btn-outline" onclick="return confirm('本当に削除しますか？')">削除</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else: ?>
			<div style="text-align:center; padding:50px; color:#888;">
				条件に一致する博物館は見つかりませんでした。
			</div>
		<?php endif; ?>
	</div>
</div>

</body>
</html>