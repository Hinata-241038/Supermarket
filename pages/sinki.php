
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>SuperMarketManagement</title>
    <link rel="stylesheet" href="../assets/css/sinki.css">
</head>
<body>
    <h1 class="title">新規登録</h1>
    <form method="post">
    <div class="form-container">
        <div class="form-row">
            <label for="user_id">ID</label>
            <input type="text" id="user_id" name="login_id">
        </div>

        <div class="form-row">
            <label for="password">パスワード</label>
            <input type="password" id="password" name="password">            
            <input type="password" id="password" name="password">            

        </div>
    </div>

    <div>
        <a href="logu.php" class="back-btn">戻る</a>
        <button type="submit" class="submit-btn">登録</button>
    </div>

</form>


</body>
</html>
