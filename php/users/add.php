<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* 未入力チェック */
if (empty($_POST['login_id']) || empty($_POST['password']) || empty($_POST['role'])) {
    header('Location: sinki.php?error=1');
    exit;
}

$login_id = trim($_POST['login_id']);
$password = $_POST['password'];           
$role     = $_POST['role'];

/* DB接続 */
$dsn = 'mysql:host=localhost;dbname=supermarketmanager;charset=utf8'; 
$user = 'root'; 
$pass = '';

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

/* 重複チェック */
$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE login_id = ?');
$stmt->execute([$login_id]);

if ($stmt->fetchColumn() > 0) {
    exit('このIDはすでに登録されています');
}

/* パスワードをハッシュ化 */
$hash = password_hash($password, PASSWORD_DEFAULT);

/* ★ 正しいINSERT */
$stmt = $pdo->prepare(
    'INSERT INTO users (login_id, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())'
);
$stmt->execute([$login_id, $hash, $role]);

/* 画面遷移 */
header('Location: ../../pages/logu.php');
exit;
