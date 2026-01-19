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