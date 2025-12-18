<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>発注</title>
    <link rel="stylesheet" href="../assets/css/hacchu.css">
</head>
<body>

<div class="container">
    <h1>発注</h1>

    <form class="order-form" method="post" action="/Supermarket/pages/hacchu.php">

        <label>発注先</label>
        <input type="text" name="supplier" required>

        <label>発注日</label>
        <input type="date" name="order_date" required>

        <label>JAN</label>
        <input type="text" name="jan_code" required>

        <label>商品名</label>
        <input type="text" name="item_name" required>

        <label>カテゴリ</label>
        <input type="text" name="category">

        <label>個数（点）</label>
        <input type="number" name="order_quantity" required>

        <label>単価（円）</label>
        <input type="number" name="price" required>

        <!-- 今回は仮でJANを item_id として使う -->
        <input type="hidden" name="item_id" value="1">

        <div class="buttons">
            <a href="home.php">
                <button type="button" class="back-btn">戻る</button>
            </a>
            <button type="submit" class="hacchu-btn">発注</button>
        </div>

    </form>
</div>

</body>
</html>
