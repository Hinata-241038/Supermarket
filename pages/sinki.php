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


<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperMarketMagament</title>
    <link rel="stylesheet" href="../assets/css/sinki.css">
</head>
<body>
    <h1 class="title">新規登録</h1>
    <div class="form-countainer">
        <div class="form-row">
            <label for="user_id">ID</label>
            <input type="text" id="user_id" name="user_id">
        </div>

        <div class="form-row">
            <label for="password">パスワード</label>
            <input type="password" id="password" name="password">            
        </div>
    </div>

    <div>
        <a href = "logu.php">
            <button class="back-btn">戻る</button>
        </a>
        <button class="submit-btn">登録</button>
    </div>
</body>
</html>