<?php
require_once('../common/db_inc.php');
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
	header('Location: login.php');
	exit;
}

$admin_id = $_SESSION['admin_id'];
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '管理者';

$sql = "
	SELECT 
		m.id, 
		m.name_ja, 
		m.name_kana, 
		c.name AS category_name, 
		m.is_active,
		amp.role
	FROM 
		museums m
	JOIN 
		admin_museum_permissions amp ON m.id = amp.museum_id
	LEFT JOIN 
		categories c ON m.category_id = c.id
	WHERE 
		amp.admin_id = ? 
		AND m.deleted_at IS NULL
	ORDER BY 
		m.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$admin_id]);
$museums = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>ダッシュボード - 博物館管理者用</title>
	<style>
		/* 全ての要素でパディングを幅に含める設定 */
		* { box-sizing: border-box; }

		:root { 
			--primary-color: #26b396; 
			--bg-color: #f4f7f7; 
			--border-color: #e9ecef; 
		}
		
		body { 
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
			background-color: var(--bg-color); 
			margin: 0; 
			color: #333; 
		}

		.inner-wrapper {
			max-width: 1100px;
			margin: 0 auto;
			padding: 0 40px;
		}
		
		header { 
			background: white; 
			box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
			width: 100%;
		}

		.header-content {
			display: flex; 
			justify-content: space-between; 
			align-items: center; 
			height: 70px;
		}
		
		.logo { font-weight: bold; color: var(--primary-color); font-size: 1.2rem; }
		.user-info { font-size: 0.9rem; color: #666; display: flex; align-items: center; }
		.btn-logout { 
			text-decoration: none; 
			color: #888; 
			margin-left: 20px; 
			font-size: 0.85rem; 
			border-left: 1px solid #ddd; 
			padding-left: 20px; 
			display: inline-block;
		}
		.btn-logout:hover { color: #d00; }

		.container { 
			margin-top: 50px; 
			margin-bottom: 50px;
		}
		
		h1 { font-size: 1.6rem; margin: 0 0 35px 0; color: #333; }

		.museum-grid { 
			display: grid; 
			grid-template-columns: repeat(auto-fill, 360px); 
			gap: 30px; 
		}
		
		.museum-card { 
			background: white; 
			border-radius: 20px; 
			padding: 30px; /* 左右上下均等のパディング */
			box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
			transition: 0.3s; 
			position: relative; 
			border: 1px solid transparent;
			display: flex;
			flex-direction: column;
			min-height: 280px;
		}
		
		.museum-card:hover { 
			transform: translateY(-5px); 
			border-color: var(--primary-color); 
			box-shadow: 0 8px 25px rgba(38, 179, 150, 0.1);
		}
		
		.role-badge { 
			position: absolute; 
			top: 25px; 
			right: 25px; 
			font-size: 0.7rem; 
			padding: 4px 10px; 
			border-radius: 10px; 
			background: #f0f0f0; 
			color: #777; 
			font-weight: bold; 
		}
		.role-admin { background: #e6fff0; color: #1e7e34; }

		.museum-name { font-size: 1.25rem; font-weight: bold; margin: 10px 0 5px 0; color: #333; line-height: 1.4; padding-right: 50px; }
		.museum-kana { font-size: 0.8rem; color: #999; margin-bottom: 15px; }
		
		.museum-meta { 
			font-size: 0.8rem; 
			color: #666; 
			margin-bottom: 25px; 
			display: flex; 
			align-items: center;
			gap: 10px; 
		}
		
		.status-tag { 
			display: inline-block; 
			padding: 3px 10px; 
			border-radius: 6px; 
			font-size: 0.7rem; 
			font-weight: bold; 
		}
		.status-on { background: #e6fff0; color: #1e7e34; }
		.status-off { background: #fff0f0; color: #d00; }

		/* --- ボタンの修正：カード内で幅100%かつ中央に --- */
		.btn-manage { 
			display: block; 
			width: 100%; 
			margin: auto 0 0 0; /* 上にマージンを自動で取り、下部に配置 */
			padding: 14px 0; 
			border-radius: 12px; 
			background: var(--primary-color); 
			color: white; 
			text-align: center; 
			text-decoration: none; 
			font-weight: bold; 
			font-size: 0.9rem; 
			transition: 0.2s;
			border: none;
		}
		.btn-manage:hover { opacity: 0.9; }
		
		.empty-state { 
			text-align: center; 
			background: white; 
			padding: 80px 40px; 
			border-radius: 20px; 
			color: #888; 
			box-shadow: 0 4px 15px rgba(0,0,0,0.05);
		}
	</style>
</head>
<body>

<header>
	<div class="inner-wrapper header-content">
		<div class="logo">博物館ガイド <span style="font-weight:normal; font-size:0.8em; color:#ccc; margin-left:8px;">| 管理者</span></div>
		<div class="user-info">
			<span>こんにちは、<strong><?php echo htmlspecialchars($admin_name); ?></strong> さん</span>
			<a href="logout.php" class="btn-logout">ログアウト</a>
		</div>
	</div>
</header>

<div class="inner-wrapper container">
	<h1>担当博物館の一覧</h1>

	<?php if (count($museums) > 0): ?>
		<div class="museum-grid">
			<?php foreach ($museums as $m): ?>
				<div class="museum-card">
					<div class="role-badge <?php echo ($m['role'] === 'admin') ? 'role-admin' : ''; ?>">
						<?php echo ($m['role'] === 'admin') ? '管理者' : '編集者'; ?>
					</div>
					
					<div class="museum-name"><?php echo htmlspecialchars($m['name_ja']); ?></div>
					<div class="museum-kana"><?php echo htmlspecialchars($m['name_kana']); ?></div>
					
					<div class="museum-meta">
						<span style="background:#f0f2f5; padding:3px 8px; border-radius:4px;"><?php echo htmlspecialchars($m['category_name']); ?></span>
						<span class="status-tag <?php echo $m['is_active'] ? 'status-on' : 'status-off'; ?>">
							<?php echo $m['is_active'] ? '● 公開中' : '● 非公開'; ?>
						</span>
					</div>

					<a href="museum_manage.php?id=<?php echo (int)$m['id']; ?>" class="btn-manage">この博物館を管理する</a>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else: ?>
		<div class="empty-state">
			<h3 style="margin-top:0;">担当している博物館がありません。</h3>
			<p>スーパーバイザーによる権限の付与をお待ちください。</p>
		</div>
	<?php endif; ?>
</div>

</body>
</html>