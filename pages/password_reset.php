<?php
session_start();
require_once __DIR__ . '/../php/db/dbconnect.php';

/* ログインチェック */
if (!isset($_SESSION['logu_id'])) {
    header('Location: logu.php');
    exit;
}

/* ユーザー情報取得 */
$stmt = $pdo->prepare(
    'SELECT login_id, role FROM users WHERE login_id = ?'
);
$stmt->execute([$_SESSION['login_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/*取得エラー*/
if (!$user) {
    exit('ユーザー情報が取得できません');
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>パスワード再設定</title>
    <link rel="stylesheet" href="../assets/css/password_reset.css">
</head>
<body>

<div class="container">
    <h1>パスワード再設定</h1>

    <form action="password_update.php" method="post">

        <div class="form-row">
            <label>role</label>
            <input type="text"
                   value="<?= htmlspecialchars($user['role']) ?>"
                   readonly>
        </div>

        <div class="form-row">
            <label>ID</label>
            <input type="text"
                   value="<?= htmlspecialchars($user['login_id']) ?>"
                   readonly>
        </div>

        <div class="form-row">
            <label>パスワード</label>
            <input type="password" name="password" required>
        </div>

        <div class="form-row">
            <label>パスワード確認用</label>
            <input type="password" name="password_confirm" required>
        </div>

        <div class="button-row">
            <a href="home.php" class="back-btn">戻る</a>
            <button type="submit" class="submit-btn">設定</button>
        </div>

    </form>
</div>

</body>
</html>
