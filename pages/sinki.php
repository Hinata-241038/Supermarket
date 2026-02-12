
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>SuperMarketManagement</title>
    <link rel="stylesheet" href="../assets/css/sinki.css">
</head>
<body>

<h1 class="title">新規登録</h1>

<?php if (isset($_GET['error'])): ?>
<p class="error">未入力の項目があります</p>
<?php endif; ?>


<form action="../php/users/add.php" method="post">

    <div class="form-row">
        <label for="role">権限</label>
        <select id="role" name="role" required>
            <option value="">選択してください</option>
            <option value="mng">mng</option>
            <option value="fte">fte</option>
            <option value="ptj">ptj</option>
        </select>
    </div>

    <div class="form-container">
        <div class="form-row">
            <label for="login_id">ID</label>
            <input type="text" id="login_id" name="login_id"
            pattern="^[a-zA-Z0-9]+$"
            title="英数字のみ入力できます"
            required>
        </div>

        <div class="form-row">
            <label for="password">パスワード</label>
            <input type="password" id="password" name="password" required>
        </div>
    </div>

    <div>
        <a href="logu.php" class="back-btn">戻る</a>
        <button type="submit" class="submit-btn">登録</button>
    </div>

</form>


</body>
</html>
