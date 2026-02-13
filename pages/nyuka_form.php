<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) exit('不正なアクセス');

/* 発注＋商品情報取得 */
$sql = "
  SELECT
    o.id,
    o.item_id,
    o.order_quantity,
    o.status,
    i.item_name,
    i.jan_code,
    i.supplier
  FROM orders o
  LEFT JOIN items i ON i.id = o.item_id
  WHERE o.id = :id AND o.status = 0
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) exit('入荷済または存在しない発注です');

$today = (new DateTime('today'))->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>入荷処理</title>
<link rel="stylesheet" href="../assets/css/nyuka.css">
</head>
<body>

<div class="card">
  <h1>入荷処理</h1>

  <!-- 商品情報 -->
  <div class="info-box">
    <p><strong>商品名：</strong><?= htmlspecialchars($order['item_name'] ?? '') ?></p>
    <p><strong>JAN：</strong><?= htmlspecialchars($order['jan_code'] ?? '') ?></p>
    <p><strong>発注先：</strong><?= htmlspecialchars($order['supplier'] ?? '') ?></p>
    <p><strong>発注数量：</strong><?= (int)($order['order_quantity'] ?? 0) ?></p>
  </div>

  <!-- 期限警告バナー -->
  <div id="expireWarning" class="warning-banner">
    ⚠ 期限が近い商品です。取り扱いに注意してください。
  </div>

  <form method="post" action="nyuka.php" id="nyukaForm" novalidate>
    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
    <input type="hidden" name="item_id"  value="<?= (int)$order['item_id'] ?>">

    <!-- 入荷数量 -->
    <div class="form-group">
      <label>入荷数量</label>
      <input
        type="number"
        name="nyuka_quantity"
        value="<?= (int)$order['order_quantity'] ?>"
        min="1"
        required
      >
      <p class="hint">※ 発注数量と異なる場合は入力値で入荷します。</p>
    </div>

    <!-- 期限 -->
    <div class="form-group">
      <label>期限</label>

      <div class="expire-select">
        <label class="radio">
          <input type="radio" name="expire_type" value="consume" checked>
          消費期限
        </label>

        <label class="radio">
          <input type="radio" name="expire_type" value="best">
          賞味期限
        </label>

        <input
          type="date"
          name="expire_date"
          id="expireDate"
          min="<?= htmlspecialchars($today) ?>"
          required
        >
      </div>

      <p class="hint">※ 今日より前の日付は選択できません。</p>
    </div>

    <!-- ボタン -->
    <div class="btn-area">
      <a href="hacchu_list.php" class="btn back">戻る</a>
      <button type="submit" class="btn primary">入荷確定</button>
    </div>
  </form>
</div>

<script>
(() => {
  const dateEl = document.getElementById('expireDate');
  const warning = document.getElementById('expireWarning');
  const WARN_DAYS = 3;

  function daysDiff(fromDateStr, toDateStr){
    const from = new Date(fromDateStr + "T00:00:00");
    const to   = new Date(toDateStr   + "T00:00:00");
    return Math.floor((to - from) / (1000 * 60 * 60 * 24));
  }

  function checkWarning(){
    if (!dateEl.value){
      warning.classList.remove("show");
      return;
    }

    const today = new Date();
    const todayStr =
      today.getFullYear() + "-" +
      String(today.getMonth()+1).padStart(2,"0") + "-" +
      String(today.getDate()).padStart(2,"0");

    const d = daysDiff(todayStr, dateEl.value);

    if (d <= WARN_DAYS){
      warning.classList.add("show");
    } else {
      warning.classList.remove("show");
    }
  }

  dateEl.addEventListener("change", checkWarning);
  checkWarning();
})();
</script>

</body>
</html>
