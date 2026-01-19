<<<<<<< HEAD
=======
<?php
// DB接続
$dsn = 'mysql:host=localhost;dbname=supermarketmanager;charset=utf8';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    exit('DB接続失敗: ' . $e->getMessage());
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = $_POST['login_id'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($login_id === '' || $password === '') {
        $message = 'IDとパスワードを入力してください';
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $sql = "
                INSERT INTO users 
                (login_id, password_hash, created_at, updated_at)
                VALUES 
                (:login_id, :password_hash, NOW(), NOW())
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':login_id' => $login_id,
                ':password_hash' => $password_hash
            ]);

            $message = '登録が完了しました';
        } catch (PDOException $e) {
            $message = 'このIDは既に使われています';
        }
    }
}
?>

>>>>>>> origin/main

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>SuperMarketManagement</title>
    <link rel="stylesheet" href="../assets/css/sinki.css">
</head>
<body>
<<<<<<< HEAD

<h1 class="title">新規登録</h1>

<?php if (isset($_GET['error'])): ?>
<p class="error">未入力の項目があります</p>
<?php endif; ?>


<!-- ★ form を必ず追加 -->
<form action="../php/users/add.php" method="post">

    <div class="form-container">
        <div class="form-row">
            <label for="login_id">ID</label>
            <input type="text" id="login_id" name="login_id" required>
=======
    <h1 class="title">新規登録</h1>
    <form method="post">
    <div class="form-container">
        <div class="form-row">
            <label for="user_id">ID</label>
            <input type="text" id="user_id" name="login_id">
<<<<<<< HEAD
>>>>>>> origin/main
=======
>>>>>>> 274123b5cb286705a7445188a0a85c1c859b43fa
        </div>

        <div class="form-row">
            <label for="password">パスワード</label>
<<<<<<< HEAD
<<<<<<< HEAD
            <input type="password" id="password" name="password" required>
=======
            <input type="password" id="password" name="password">            
>>>>>>> origin/main
=======
            <input type="password" id="password" name="password">            
>>>>>>> 274123b5cb286705a7445188a0a85c1c859b43fa
        </div>
    </div>

    <div>
        <a href="logu.php" class="back-btn">戻る</a>
        <button type="submit" class="submit-btn">登録</button>
    </div>

</form>


</body>
</html>
