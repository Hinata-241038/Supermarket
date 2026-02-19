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
    <title>SuperMarketMagament</title>
    <link rel="stylesheet" href="../assets/css/home.css">
    <script src="home.js" defer></script>
</head>
<body>
   <h1>SuperMarketManager</h1>

<!-- 発注：管理者・正社員 -->
<?php if ($role === 'mng' || $role === 'fte'): ?>
    <a href="hacchu_form.php" id="hacchuBtn" class="menu-button">発注</a>
<?php endif; ?>

<!-- 在庫情報：全員 -->
<a href="zaiko.php" id="zaikoBtn" class="menu-button">在庫情報</a>

<!-- 廃棄：管理者のみ -->
<?php if ($role === 'mng'): ?>
    <a href="haiki.php" id="haikiBtn" class="menu-button">廃棄</a>
    <a href="haiki.php" id="haikiBtn" class="menu-button">ユーザー管理</a>
<?php endif; ?>

<!-- ログアウト -->
<a href="logu.php" class="logout">ログアウト</a>
 
</body>
</html> 