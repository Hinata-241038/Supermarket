<?php
session_start();

$dsn  = 'mysql:host=localhost;dbname=supermarketmanager;charset=utf8';
$user = 'root';
$pass = '';

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = $_POST['login_id'];
    $password = $_POST['password_hash'];

    $stmt = $pdo->prepare(
        'SELECT * FROM users WHERE login_id = ?'
    );
    $stmt->execute([$login_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['id'] = $user['id'];
        $_SESSION['login_id'] = $user['login_id'];
        $_SESSION['role']     = $user['role'];
        
              switch ($user['role']) {
            case 'mng':
                header('Location: manager/home.php');
                break;

            case 'fte':
                header('Location: employee/home.php');
                break;

            case 'ptj':
                header('Location: parttime/home.php');
                break;

            default:
                echo '権限が不正です';
        }
        exit;
    } else {
        echo 'IDまたはパスワードが違います';
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

    <form action="" method="post">
      <label>
        ユーザー名:
        <input type="text" name="login_id" required>
      </label><br>

      <label>
        パスワード:
        <input type="password" name="password_hash" required>
      </label><br>

      <div class="button-group">
        <a href="sinki.php">新規登録</a>
        <button type="submit">ログイン</button>
      </div>
    </form>
  </div>
</body>
</html>