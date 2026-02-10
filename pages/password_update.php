<?php
session_start();
require_once __DIR__ . '/../dbconnect.php';


/* POST以外拒否 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: password_reset.php');
    exit;
}

/* 入力チェック */
if (
    empty($_POST['password']) ||
    empty($_POST['password_confirm'])
) {
    exit('入力が不足しています');
}

/* 文字数チェック */
if (strlen($_POST['password']) < 8) {
    exit('パスワードは8文字以上にしてください');
}

/* 一致確認 メッセージ機能*/
if ($_POST['password'] !== $_POST['password_confirm']) {
    exit('パスワードが一致しません');
}

/* ハッシュ化 */
$passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);

/* 更新 */
$stmt = $pdo->prepare(
    'UPDATE users
     SET password_hash = ?, updated_at = NOW()
     WHERE login_id = ?'
);
$stmt->execute([
    $passwordHash,
    $_SESSION['login_id']
]);

if ($stmt->rowCount() === 0) {
    exit('更新に失敗しました');
}
?>

