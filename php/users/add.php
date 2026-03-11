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
require_once __DIR__ . '/../db/dbconnect.php';

/* ★ mngが既に存在するかチェック */
if ($role === 'mng') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'mng'");
    $stmt->execute();

    if ($stmt->fetchColumn() > 0) {
        echo "<script>
                alert('管理者アカウントは1つしか作成できません');
                location.href='../../pages/sinki.php';
              </script>";
        exit;
    }
}

/* 重複チェック */
$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE login_id = ?');
$stmt->execute([$login_id]);

if ($stmt->fetchColumn() > 0) {
    echo "<script>
            alert('このIDはすでに登録されています');
            location.href='../../pages/sinki.php';
          </script>";
    exit;
}

/* パスワードをハッシュ化 */
$hash = password_hash($password, PASSWORD_DEFAULT);

/* INSERT */
$stmt = $pdo->prepare(
    'INSERT INTO users (login_id, password_hash, role, created_at, updated_at)
     VALUES (?, ?, ?, NOW(), NOW())'
);
$stmt->execute([$login_id, $hash, $role]);

/* 登録完了 */
echo "<script>
        alert('ユーザー登録が完了しました');
        location.href='../../pages/logu.php';
      </script>";
exit;