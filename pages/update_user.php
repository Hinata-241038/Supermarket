<?php
session_start();
require_once __DIR__ . '/../dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: user_management.php");
    exit;
}

if (empty($_POST['id']) || empty($_POST['login_id']) || empty($_POST['role'])) {
    exit("入力が不足しています");
}

$id = $_POST['id'];
$login_id = trim($_POST['login_id']);
$role = $_POST['role'];

$password = $_POST['password'];
$password_confirm = $_POST['password_confirm'];

/* パスワード確認 */
if ($password !== $password_confirm) {
    echo "<script>
            alert('パスワードが一致しません');
            history.back();
          </script>";
    exit;
}

/* パスワードハッシュ化 */
$hash = password_hash($password, PASSWORD_DEFAULT);

/* 更新処理 */
$stmt = $pdo->prepare("
UPDATE users
SET login_id = ?, role = ?, password_hash = ?
WHERE id = ?
");

$stmt->execute([$login_id, $role, $hash, $id]);

header("Location: user_management.php");
exit;