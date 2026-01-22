<?php
/**
 * スーパーバイザー管理画面 - 全体表示 (index.php)
 * 仕様書 6ページ対応
 */

// 1. データベース接続設定ファイルを読み込みます
// パスは museum/common/db_inc.php を指しています
require_once('../common/db_inc.php');

try {
    // 2. 登録されている全ての博物館を「博物館コード」順に取得します
    $sql = "SELECT * FROM museums ORDER BY m_code ASC";
    $stmt = $pdo->query($sql);
    $museums = $stmt->fetchAll();
} catch (PDOException $e) {
    die("データ取得エラー: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>スーパーバイザー管理画面 - 全体表示</title>
    <style>
        /* 仕様書に近いシンプルなデザインに整えます */
        body { font-family: "Hiragino Kaku Gothic ProN", "Meiryo", sans-serif; padding: 20px; color: #333; }
        h1 { font-size: 1.2em; border-bottom: 2px solid #004080; padding-bottom: 10px; }
        h2 { font-size: 1.0em; margin-top: 20px; }

        /* テーブルのスタイル */
        table { border-collapse: collapse; width: 100%; max-width: 600px; margin-top: 10px; }
        th { background-color: #004080; color: white; padding: 10px; text-align: left; border: 1px solid #ccc; }
        td { padding: 10px; border: 1px solid #ccc; }
        tr:nth-child(even) { background-color: #f9f9f9; } /* 1行おきに色を変えて見やすくします */

        /* ボタンのスタイル */
        .btn-group { margin-top: 30px; display: flex; gap: 10px; }
        .btn { 
            padding: 10px 20px; 
            border: 1px solid #333; 
            background-color: #fff; 
            cursor: pointer; 
            text-decoration: none; 
            color: #333;
            font-size: 0.9em;
        }
        .btn:hover { background-color: #eee; }
        
        .info-text { margin-top: 15px; font-size: 0.9em; color: #666; }
    </style>
</head>
<body>

    <h1>スーパーバイザー管理画面（PCで行うことを想定）</h1>
    
    <h2>全体表示</h2>
    <p class="info-text">● 全体表示では登録済みの全博物館の一覧が表示される</p>

    <!-- 博物館一覧テーブル -->
    <table>
        <thead>
            <tr>
                <th>博物館名</th>
                <th>博物館コード</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($museums)): ?>
                <!-- データが1件もない場合の表示 -->
                <tr>
                    <td colspan="2" style="text-align: center; padding: 20px;">登録済みの博物館はありません。</td>
                </tr>
            <?php else: ?>
                <!-- 取得したデータを1行ずつループして表示します -->
                <?php foreach ($museums as $m): ?>
                <tr>
                    <td><?php echo htmlspecialchars($m['name_ja'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($m['m_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- 操作ボタンエリア（仕様書 6ページ下部） -->
    <div class="btn-group">
        <!-- 新規登録ボタン：add.phpへ移動 -->
        <a href="add.php" class="btn">新規登録</a>
        
        <!-- 削除ボタン：delete_select.phpへ移動（後ほど作成） -->
        <a href="delete_select.php" class="btn">削除</a>
        
        <!-- 修正ボタン：edit_select.phpへ移動（後ほど作成） -->
        <a href="edit_select.php" class="btn">修正</a>
    </div>

</body>
</html>