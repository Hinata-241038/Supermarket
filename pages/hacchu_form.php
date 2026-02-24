<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* カテゴリ一覧 */
$stmt = $pdo->query("SELECT id, category_label_ja FROM categories ORDER BY category_group, id");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* JAN 事前入力（GET） */
$prefillJan = $_GET['jan'] ?? '';
$prefillJan = preg_replace('/\D/', '', $prefillJan);

/* JANがある場合、itemsから自動反映 */
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
      COALESCE(i.is_limited,0) AS is_limited
    FROM items i
    WHERE i.jan_code = :jan
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':jan' => $prefillJan]);
  $item = $st->fetch(PDO::FETCH_ASSOC);
}

/* 初期値 */
$val_item_id    = (int)($item['id'] ?? 0);
$val_supplier   = $item['supplier']   ?? '';
$val_order_date = $_GET['order_date'] ?? '';
$val_jan        = $item['jan_code']   ?? $prefillJan;
$val_item_name  = $item['item_name']  ?? '';
$val_unit       = $item['unit']       ?? '';
$val_category   = $item['category_id']?? '';
$val_qty        = $_GET['qty']        ?? '';
$val_price      = $item['price']      ?? '';
$val_is_limited = isset($item['is_limited']) ? (int)$item['is_limited'] : 0;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>発注</title>
  <link rel="stylesheet" href="../assets/css/hacchu.css?v=2">
</head>
<body>

<div class="top-actions">
  <a class="top-btn" href="item_register.php">商品追加</a>
  <a class="top-btn" href="hacchu_list.php">発注履歴</a>
</div>

<div class="container">
  <h1 class="page-title">発注</h1>

  <!-- ★保存先を hacchu.php に統一 -->
  <form class="order-form" method="post" action="hacchu.php" novalidate>

    <!-- ★商品が特定できたら item_id を送る（これが無いと発注できない） -->
    <input type="hidden" name="item_id" id="item_id" value="<?= (int)$val_item_id ?>">

    <div class="form-row">
      <label for="supplier">発注先</label>
      <input id="supplier" name="supplier" type="text" value="<?= h($val_supplier) ?>">
    </div>

    <div class="form-row">
      <label for="order_date">発注日</label>
      <input id="order_date" name="order_date" type="date" value="<?= h($val_order_date) ?>">
    </div>

    <div class="form-row">
      <label for="jan">JAN</label>
      <input
        id="jan"
        name="jan"
        type="text"
        inputmode="numeric"
        autocomplete="off"
        value="<?= h($val_jan) ?>"
      >
    </div>

    <!-- ★JAN下の注意文 -->
    <div class="form-hint-row">
      <div class="form-hint">12桁で入力すると自動で13桁に補完します</div>
    </div>

    <!-- ★期間限定（任意） -->
    <div class="form-row">
      <label>期間限定</label>
      <div class="inline-controls">
        <input type="hidden" name="is_limited" value="0">
        <label class="check-pill">
          <input
            type="checkbox"
            id="is_limited"
            name="is_limited"
            value="1"
            <?= $val_is_limited === 1 ? 'checked' : '' ?>
          >
          <span>期間限定商品</span>
        </label>
        <span class="mini-note">（任意）</span>
      </div>
    </div>

    <div class="form-row">
      <label for="item_name">商品名</label>
      <input id="item_name" name="item_name" type="text" value="<?= h($val_item_name) ?>" readonly>
    </div>

    <div class="form-row">
      <label for="unit">単位</label>
      <input id="unit" name="unit" type="text" value="<?= h($val_unit) ?>" readonly>
    </div>

    <div class="form-row">
      <label for="category_id">カテゴリ</label>
      <select id="category_id" name="category_id" disabled>
        <option value="">選択してください</option>
        <?php foreach($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ((string)$val_category === (string)$c['id']) ? 'selected' : '' ?>>
            <?= h($c['category_label_ja']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="order_quantity">個数（点）</label>
      <input id="order_quantity" name="order_quantity" type="number" min="0" step="1" value="<?= h($val_qty) ?>">
    </div>

    <div class="form-row">
      <label for="price">単価（円）</label>
      <input id="price" name="price" type="number" min="0" step="1" value="<?= h($val_price) ?>" readonly>
    </div>

    <div class="form-row">
      <label for="total">合計（円）</label>
      <input id="total" name="total" type="number" readonly value="">
    </div>

    <div class="bottom-actions">
      <a class="btn ghost" href="home.php">戻る</a>
      <button class="btn primary" type="submit">発注</button>
    </div>
  </form>
</div>

<script>
/* 12桁→13桁 補完（EAN-13） */
function calcEan13CheckDigit(twelveDigits){
  let sum = 0;
  for (let i = 0; i < 12; i++){
    const n = parseInt(twelveDigits[i], 10);
    sum += (i % 2 === 0) ? n : n * 3;
  }
  const mod = sum % 10;
  return String((10 - mod) % 10);
}

const janEl = document.getElementById('jan');

janEl.addEventListener('blur', () => {
  const raw = (janEl.value || '').replace(/\D/g,'');
  if (raw.length === 12){
    janEl.value = raw + calcEan13CheckDigit(raw);
  } else {
    janEl.value = raw;
  }
});

/* 合計計算 */
const qtyEl   = document.getElementById('order_quantity');
const priceEl = document.getElementById('price');
const totalEl = document.getElementById('total');

function updateTotal(){
  const q = parseInt(qtyEl.value || '0', 10);
  const p = parseInt(priceEl.value || '0', 10);
  totalEl.value = String((isNaN(q)?0:q) * (isNaN(p)?0:p));
}
qtyEl.addEventListener('input', updateTotal);
priceEl.addEventListener('input', updateTotal);
updateTotal();
</script>

</body>
</html>