<?php
session_start();
require_once __DIR__ . '/../dbconnect.php'; // ←あなたの接続ファイル

// ユーザー一覧取得
$stmt = $pdo->prepare("SELECT id, login_id, role FROM users ORDER BY id ASC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ユーザー管理</title>
    <link rel="stylesheet" href="../assets/css/user_management.css">
</head>
<body>

<div class="container">

    <div class="top-area">
        <button class="btn-back" onclick="history.back()">戻る</button>
    </div>

    <h1 class="title">ユーザー管理</h1>

    <form method="post" action="user_delete.php">

        <table class="user-table">
            <thead>
                <tr>
                    <th>削除対象</th>
                    <th>権限</th>
                    <th>ID</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <input type="checkbox" name="delete_ids[]" value="<?= htmlspecialchars($user['id']) ?>">
                    </td>
                    <td><?= htmlspecialchars($user['role']) ?></td>
                    <td><?= htmlspecialchars($user['login_id']) ?></td>
                    <td>
                        <a href="edit_user.php?id=<?= $user['id'] ?>">
                            <button type="button" class="btn-edit">編集</button>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="delete-area">
            <button type="submit" class="btn-delete">削除</button>
        </div>

    </form>

</div>

</body>
</html>
