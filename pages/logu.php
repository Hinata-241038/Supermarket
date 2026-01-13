<?php
session_start();

/* エラーメッセージ用 */
$error = '';

/* POST送信されたときだけログイン判定 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* DB接続 */
    $dsn = 'mysql:host=localhost;dbname=supermarketmanager;charset=utf8';
    $db_user = 'root';   // 環境に合わせて変更
    $db_pass = '';       // 環境に合わせて変更

    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (PDOException $e) {
        exit('DB接続エラー');
    }

    /* 入力値 */
    $login_id = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    /* ユーザー取得 */
    $sql = "SELECT * FROM users WHERE login_id = :login_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':login_id' => $login_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    /* ログイン判定 */
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['login_id'] = $user['login_id'];

        header('Location: home.php');
        exit;
    } else {
        $error = 'ログインIDまたはパスワードが間違っています';
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>ログイン画面</title>
  <link rel="stylesheet" href="../assets/css/logu.css">
</head>
<body>
  <div class="container">
    <h1 class="fsize">ログイン</h1>

    <!-- エラーメッセージ表示 -->
    <?php if ($error): ?>
      <p style="color:red;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form action="" method="post">
      <label>
        ユーザー名:
        <input type="text" name="username" required>
      </label><br>

      <label>
        パスワード:
        <input type="password" name="password" required>
      </label><br>

      <div class="button-group">
        <a href="sinki.php">新規登録</a>
        <button type="submit">ログイン</button>
      </div>
    </form>
  </div>
</body>
</html>