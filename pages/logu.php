<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>ログイン画面</title>
  <link rel="stylesheet" href="../assts/css/logu.css">
</head>
<body>
  <div class="container">
    <h1 class="fsize">ログイン</h1>
    <form action="login.php" method="post">
      <label>ユーザー名: <input type="text" name="username" required></label><br>
      <label>パスワード: <input type="password" name="password" required></label><br>
      <div class="button-group">
        <a href="sinki.php">新規登録</a>
        <a href="home.php">ログイン</a>
      </div>
    </form>
  </div>
</body>
</html>