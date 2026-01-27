<?php
require_once('../common/db_inc.php');
session_start();
if (!isset($_SESSION['sv_logged_in'])) {
	header('Location: login.php');
	exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY id")->fetchAll();
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name_ja = $_POST['name_ja'];
	$name_kana = $_POST['name_kana'];
	$category_id = $_POST['category_id'];
	$email = $_POST['email'];
	$password = $_POST['password'];
	$address = $_POST['address'];
	$phone_number = $_POST['phone_number'];
	$website_url = $_POST['website_url'];
	$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
	$notes = $_POST['notes'];

	if (empty($name_ja) || empty($name_kana) || empty($category_id) || empty($email) || empty($password)) {
		$error_msg = "必須項目 (*) はすべて入力してください。";
	}
	
	// メール形式チェック
	if (empty($error_msg) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$error_msg = "正しい形式のメールアドレスを入力してください。";
	}
	
	// パスワード強度チェック (12文字以上、大文字小文字数字混在)
	$password_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[\S]{12,}$/';
	if (empty($error_msg) && !preg_match($password_pattern, $password)) {
		$error_msg = "初期パスワードは「大文字・小文字・数字を各1文字以上含む、12文字以上の文字列」にしてください。";
	}

	// スーパーバイザー(supervisorsテーブル)との重複チェック
	if (empty($error_msg)) {
		$stmt_sv = $pdo->prepare("SELECT COUNT(*) FROM supervisors WHERE email = ?");
		$stmt_sv->execute([$email]);
		if ($stmt_sv->fetchColumn() > 0) {
			$error_msg = "このメールアドレスはスーパーバイザーとして登録されているため使用できません。";
		}
	}

	// 他の博物館管理者との重複チェック
	if (empty($error_msg)) {
		$stmt_email = $pdo->prepare("SELECT COUNT(*) FROM museum_admins WHERE email = ?");
		$stmt_email->execute([$email]);
		if ($stmt_email->fetchColumn() > 0) {
			$error_msg = "そのメールアドレスは既に登録されています。";
		}
	}

	if (empty($error_msg)) {
		try {
			$pdo->beginTransaction();

			// 1. m_code生成
			do {
				$m_code = bin2hex(random_bytes(4));
				$st = $pdo->prepare("SELECT COUNT(*) FROM museums WHERE m_code = ?");
				$st->execute([$m_code]);
			} while ($st->fetchColumn() > 0);

			// 2. museums登録
			$sql_m = "INSERT INTO museums (m_code, name_ja, name_kana, category_id, address, phone_number, website_url, is_active, notes) 
					  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
			$stmt_m = $pdo->prepare($sql_m);
			$stmt_m->execute([$m_code, $name_ja, $name_kana, $category_id, $address, $phone_number, $website_url, $is_active, $notes]);
			$museum_id = $pdo->lastInsertId();

			// 3. museum_admins登録
			$sql_a = "INSERT INTO museum_admins (email, password) VALUES (?, ?)";
			$stmt_a = $pdo->prepare($sql_a);
			$stmt_a->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
			$admin_id = $pdo->lastInsertId();

			// 4. admin_museum_permissions登録 (role='admin'を付与)
			$sql_p = "INSERT INTO admin_museum_permissions (admin_id, museum_id, role) VALUES (?, ?, 'admin')";
			$stmt_p = $pdo->prepare($sql_p);
			$stmt_p->execute([$admin_id, $museum_id]);

			$pdo->commit();
			header("Location: index.php?msg=added");
			exit;
		} catch (PDOException $e) {
			$pdo->rollBack();
			$error_msg = "データベースエラーが発生しました。";
		}
	}
}
?>
<!-- HTML部分は前回と同じため省略（適宜最新のバリデーションメッセージを反映） -->