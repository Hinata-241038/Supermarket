<?php
require_once __DIR__ . '/../dbconnect.php';

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) exit('不正なアクセス');

$sql = "
    SELECT *
    FROM orders
    WHERE id = :id AND status = 0
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) exit('入荷済または存在しない発注です');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>入荷処理</title>
<link rel="stylesheet" href="../assets/css/hacchu.css">
</head>
<body>
<div class="container">
<h1>入荷処理</h1>

<form method="post" action="nyuka.php">
    <p>商品ID：<?= $order['item_id'] ?></p>
    <p>発注数量：<?= $order['order_quantity'] ?></p>

    <label>入荷数量</label>
    <input type="number" name="quantity" required>

    <label>消費期限</label>
    <input type="date" name="expire_date">

    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
    <input type="hidden" name="item_id" value="<?= $order['item_id'] ?>">

    <button type="submit" class="hacchu-btn">入荷確定</button>
</form>
</div>
</body>
</html>
