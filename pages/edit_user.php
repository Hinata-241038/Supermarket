<?php
session_start();
require_once __DIR__ . '/../dbconnect.php';

if (!isset($_GET['id'])) {
    header("Location: user_management.php");
    exit;
}

$id = $_GET['id'];

// 該当ユーザー取得
$stmt = $pdo->prepare("SELECT id, login_id, role FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: user_management.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ユーザーID/権限変更</title>
    <link rel="stylesheet" href="../assets/css/edit_user.css">
</head>
<body>

<div class="container">

    <div class="top-area">
        <button class="btn-back" onclick="location.href='user_management.php'">戻る</button>
    </div>

    <h1 class="title">ユーザーID/権限変更</h1>

    <form method="post" action="update_user.php">

        <input type="hidden" name="id" value="<?= htmlspecialchars($user['id']) ?>">

        <div class="form-row">
            <label>ユーザーID</label>
            <input type="text" name="login_id"
                   value="<?= htmlspecialchars($user['login_id']) ?>" required>
        </div>

        <div class="form-row">
            <label>ユーザー権限</label>
            <select name="role" required>
                <option value="mng" <?= $user['role'] === 'mng' ? 'selected' : '' ?>>店長</option>
                <option value="fte" <?= $user['role'] === 'fte' ? 'selected' : '' ?>>正社員</option>
                <option value="ptj" <?= $user['role'] === 'ptj' ? 'selected' : '' ?>>アルバイト兼パート</option>
            </select>
        </div>

        <div class="btn-area">
            <button type="submit" class="btn-setting">設定</button>
        </div>

    </form>

</div>

</body>
</html>