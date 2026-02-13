<?php
require_once __DIR__ . '/../dbconnect.php';

if (!isset($_POST['login_id'])) {
    echo "IDが送信されていません";
    exit;
}

$login_id = $_POST['login_id'];


/* ユーザー情報取得 */
$stmt = $pdo->prepare(
    'SELECT login_id, role FROM users WHERE login_id = ?'
);
$stmt->execute([$login_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);


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

        <!-- IDをそのまま引き継ぐ -->
        <input type="hidden" name="login_id"
               value="<?= htmlspecialchars($user['login_id']) ?>">

        <div class="form-row">
            <label>権限</label>
            <input type="text"
                   value="<?= htmlspecialchars($user['role']) ?>"
                   readonly>
        </div>

        <div class="form-row">
            <label>ユーザーID</label>
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
            <button type="submit">設定</button>
        </div>

    </form>
</div>

</body>
</html>