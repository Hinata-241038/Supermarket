<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
  exit('不正なアクセス');
}

/* 発注＋商品情報を取得 */
$sql = "
  SELECT
    o.id,
    o.item_id,
    o.order_quantity,
    o.status,
    i.item_name,
    i.jan_code
  FROM orders o
  LEFT JOIN items i ON i.id = o.item_id
  WHERE o.id = :id AND o.status = 0
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  exit('入荷済または存在しない発注です');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>入荷処理</title>
<link rel="stylesheet" href="../assets/css/hacchu.css">

<!-- 入荷画面では右上固定ボタンを非表示 -->
<style>
  .register-btn-fixed{ display:none !important; }
</style>
</head>

<body>
<div class="container">

  <h1>入荷処理</h1>

  <!-- 商品情報表示 -->
  <div class="info-box">
    <p><strong>商品名：</strong><?= htmlspecialchars($order['item_name']) ?></p>
    <p><strong>JAN：</strong><?= htmlspecialchars($order['jan_code']) ?></p>
    <p><strong>発注数量：</strong><?= (int)$order['order_quantity'] ?></p>
  </div>

  <form method="post" action="nyuka.php" class="order-form">

    <div class="form-row">
      <label>入荷数量</label>
      <input
        type="number"
        name="quantity"
        min="1"
        value="<?= (int)$order['order_quantity'] ?>"
        required
      >
    </div>

    <div class="form-row">
      <label>消費期限</label>
      <input type="date" name="expire_date">
    </div>

    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
    <input type="hidden" name="item_id" value="<?= $order['item_id'] ?>">

    <div class="buttons">
      <button type="button"
              class="back-btn"
              onclick="location.href='hacchu_list.php'">
        戻る
      </button>

      <button type="submit" class="hacchu-btn">
        入荷確定
      </button>
    </div>

  </form>
</div>
</body>
</html>
