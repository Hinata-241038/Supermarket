<?php
require_once __DIR__ . '/../dbconnect.php';

/* POST以外拒否 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: logu.php');
    exit;
}

/* login_id も必須 */
if (
    empty($_POST['login_id']) ||
    empty($_POST['password']) ||
    empty($_POST['password_confirm'])
) {
    exit('入力が不足しています');
}

$login_id = $_POST['login_id'];

/* 文字数チェック */
if (strlen($_POST['password']) < 8) {
    exit('パスワードは8文字以上にしてください');
}

/* 一致確認 */
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
    $login_id
]);

if ($stmt->rowCount() === 0) {
    exit('更新に失敗しました');
}

/* 成功したらログイン画面へ */
header('Location: logu.php?reset=success');
exit;
