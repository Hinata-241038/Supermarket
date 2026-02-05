<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

$stmt = $pdo->query("SELECT id, category_label_ja FROM categories ORDER BY category_group, id");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$prefillJan = $_GET['jan'] ?? '';
$prefillJan = preg_replace('/\D/', '', $prefillJan);

// 自動反映用（JANがある場合だけDBから引く）
$item = null;
if ($prefillJan !== '') {
  $sql = "
    SELECT
      i.id,
      i.jan_code,
      i.item_name,
      i.unit,
      i.price,
      i.supplier,
      i.category_id,
      c.category_label_ja
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.jan_code = :jan
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':jan' => $prefillJan]);
  $item = $st->fetch(PDO::FETCH_ASSOC);
}

// 反映値（見つからなければ空）
$val_supplier   = $item['supplier']   ?? '';
$val_item_name  = $item['item_name']  ?? '';
$val_category_id= $item['category_id']?? '';
$val_price      = $item['price']      ?? '';
$val_unit       = $item['unit']       ?? '';
$val_item_id    = $item['id']         ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>発注</title>
  <link rel="stylesheet" href="../assets/css/hacchu.css?v=3">
</head>
<body>

<!-- ✅ 右上：商品追加 & 発注履歴 -->
<a href="item_register.php" class="register-btn-fixed">商品追加</a>
<a href="hacchu_list.php" class="register-btn-fixed second-btn">発注履歴</a>

<div class="container">
  <h1>発注</h1>

  <form class="order-form" method="post" action="/Supermarket/pages/hacchu.php" id="orderForm">

    <!-- 発注先（自動反映） -->
    <div class="form-row">
      <label for="supplier">発注先</label>
      <input type="text" id="supplier" name="supplier"
             value="<?= htmlspecialchars($val_supplier, ENT_QUOTES, 'UTF-8') ?>"
             <?= $item ? 'readonly' : '' ?>
             required>
    </div>

    <!-- 発注日 -->
    <div class="form-row">
      <label for="order_date">発注日</label>
      <input type="date" id="order_date" name="order_date" required>
    </div>

    <!-- JAN（在庫画面から来るとここが埋まる） -->
    <div class="form-row">
      <label for="jan_code">JAN</label>
      <input type="text" id="jan_code" name="jan_code"
             value="<?= htmlspecialchars($prefillJan, ENT_QUOTES, 'UTF-8') ?>"
             inputmode="numeric" autocomplete="off" required>
    </div>

    <!-- 商品名（自動反映） -->
    <div class="form-row">
      <label for="item_name">商品名</label>
      <input type="text" id="item_name" name="item_name"
             value="<?= htmlspecialchars($val_item_name, ENT_QUOTES, 'UTF-8') ?>"
             <?= $item ? 'readonly' : '' ?>
             required>
    </div>

    <!-- 単位（自動反映 / 表示用） -->
    <div class="form-row">
      <label for="unit">単位</label>
      <input type="text" id="unit" name="unit"
             value="<?= htmlspecialchars($val_unit, ENT_QUOTES, 'UTF-8') ?>"
             <?= $item ? 'readonly' : '' ?>
             required>
    </div>

    <!-- カテゴリ（自動反映：表示も兼ねる） -->
    <div class="form-row">
      <label for="category_id">カテゴリ</label>
      <select id="category_id" name="category_id" required>
        <option value="">選択してください</option>
        <?php foreach ($categories as $c): ?>
          <?php
            $cid = (string)$c['id'];
            $selected = ((string)$val_category_id !== '' && (string)$val_category_id === $cid) ? 'selected' : '';
          ?>
          <option value="<?= htmlspecialchars($cid, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
            <?= htmlspecialchars((string)$c['category_label_ja'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- 数量 -->
    <div class="form-row">
      <label for="order_quantity">個数（点）</label>
      <input type="number" id="order_quantity" name="order_quantity" min="1" step="1" required>
    </div>

    <!-- 単価（自動反映） -->
    <div class="form-row">
      <label for="price">単価（円）</label>
      <input type="number" id="price" name="price" min="0" step="1"
             value="<?= htmlspecialchars((string)$val_price, ENT_QUOTES, 'UTF-8') ?>"
             <?= $item ? 'readonly' : '' ?>
             required>
    </div>

    <!-- 合計（JSで計算） -->
    <div class="form-row">
      <label for="total_amount">合計（円）</label>
      <input type="number" id="total_amount" name="total_amount" readonly>
    </div>

    <!-- item_id（発注登録が item_id で動くならこれが最重要） -->
    <input type="hidden" id="item_id" name="item_id" value="<?= htmlspecialchars((string)$val_item_id, ENT_QUOTES, 'UTF-8') ?>">
    <p class="hint" id="janHint" aria-live="polite"></p>

    <div class="buttons">
      <button type="button" class="back-btn" onclick="location.href='home.php'">戻る</button>
      <button type="submit" class="hacchu-btn">発注</button>
    </div>
  </form>
</div>

<!-- 既存の自動計算・JAN補完ロジックを活かす -->
<script src="hacchu_form.js"></script>

<!-- JANから来た場合、単価×数量の合計が即更新されるように（JSが無い環境でも最低限動かす） -->
<script>
(function(){
  const q = document.getElementById('order_quantity');
  const p = document.getElementById('price');
  const t = document.getElementById('total_amount');
  if (!q || !p || !t) return;

  function calc(){
    const qq = parseInt(q.value || '0', 10);
    const pp = parseInt(p.value || '0', 10);
    t.value = (qq > 0 && pp >= 0) ? (qq * pp) : '';
  }
  q.addEventListener('input', calc);
  p.addEventListener('input', calc);
})();
</script>

</body>
</html>
