<?php
session_start();

if (!isset($_SESSION['role'])) {
    header('Location: logu.php');
    exit;
}

$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperMarketManager</title>
    <link rel="stylesheet" href="../assets/css/home.css">
</head>
<body>

<div class="container">

    <h1 class="title">SuperMarketManager</h1>

    <div class="menu-area">

        <?php if ($role === 'mng' || $role === 'fte'): ?>
            <a href="hacchu_form.php" class="menu-button">発注</a>
        <?php endif; ?>

        <a href="zaiko.php" class="menu-button">在庫情報</a>

        <?php if ($role === 'mng'): ?>
            <a href="haiki.php" class="menu-button">廃棄</a>
        <?php endif; ?>

        <?php if ($role === 'mng'): ?>
            <a href="user_management.php" class="menu-button">ユーザー管理</a>
        <?php endif; ?>

    </div>

</div>

<a href="logu.php" class="logout">ログアウト</a>

</body>
</html>