<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

function h($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* =========================
   初期化（Undefined防止）
========================= */
$notice = null;

/* =========================
   カテゴリ取得
========================= */
$stmt = $pdo->query("
  SELECT id, category_label_ja
  FROM categories
  ORDER BY category_group, id
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   JAN取得
========================= */
$prefillJan = $_GET['jan'] ?? '';
$prefillJan = preg_replace('/\D/', '', $prefillJan);

/* =========================
   商品取得（is_limited含む）
========================= */
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
      i.is_limited
    FROM items i
    WHERE i.jan_code = :jan
    LIMIT 1
  ";

  $st = $pdo->prepare($sql);
  $st->execute([':jan' => $prefillJan]);
  $item = $st->fetch(PDO::FETCH_ASSOC);

  if (!$item) {
    $notice = "このJANは未登録です。商品追加へ進んでください。";
  }
}

/* =========================
   初期値
========================= */
$itemId    = $item ? (int)$item['id'] : 0;
$jan       = $item ? (string)$item['jan_code'] : $prefillJan;
$name      = $item ? (string)$item['item_name'] : '';
$unit      = $item ? (string)$item['unit'] : '';
$price     = $item ? (int)$item['price'] : 0;
$supplier  = $item ? (string)$item['supplier'] : '';
$catId     = $item ? (int)$item['category_id'] : 0;
$isLimited = $item ? (int)$item['is_limited'] : 0;

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>発注</title>
<link rel="stylesheet" href="../assets/css/hacchu.css?v=6">
</head>

<body class="hacchu-form-page">

<a href="home.php" class="btn btn-back">戻る</a>

<div class="top-actions">
  <a class="btn btn-sub btn-small"
     href="item_register.php<?= $jan !== '' ? '?jan='.h($jan) : '' ?>">
     商品追加
  </a>
  <a class="btn btn-sub btn-small" href="hacchu_list.php">
     発注履歴
  </a>
</div>

<div class="container">
<h1 class="page-title">発注</h1>

<div class="card">

<?php if ($notice !== null): ?>
  <div class="notice">
    <?= h($notice) ?>
    <div class="notice-actions">
      <a class="btn btn-small btn-primary-soft"
         href="item_register.php?jan=<?= h($jan) ?>">
         商品追加へ
      </a>
    </div>
  </div>
<?php endif; ?>

<form class="order-form" method="post" action="hacchu.php">

<input type="hidden" name="item_id" value="<?= $itemId ?>">

<div class="form-row">
<label for="jan">JAN</label>
<input id="jan" name="jan" type="text"
       inputmode="numeric"
       value="<?= h($jan) ?>">
</div>

<div class="form-row">
<label>商品名</label>
<input type="text" value="<?= h($name) ?>" readonly>
</div>

<div class="form-row">
<label>カテゴリ</label>
<select disabled>
<option value="">選択してください</option>
<?php foreach ($categories as $c): ?>
<option value="<?= (int)$c['id'] ?>"
<?= ((int)$c['id'] === $catId) ? 'selected' : '' ?>>
<?= h($c['category_label_ja']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="form-row">
<label>発注先</label>
<input type="text" value="<?= h($supplier) ?>" readonly>
</div>

<div class="form-row">
<label>発注日</label>
<input type="date" name="order_date"
       value="<?= h($today) ?>">
</div>

<div class="form-row">
<label>単価（円）</label>
<input id="price" type="number"
       value="<?= $price ?>" readonly>
</div>

<div class="form-row">
<label>個数（点）</label>
<input id="order_quantity"
       name="order_quantity"
       type="number"
       min="1"
       value="0">
</div>

<div class="form-row">
<label>単位</label>
<input type="text" value="<?= h($unit) ?>" readonly>
</div>

<div class="form-row">
<label>合計（円）</label>
<input id="total" type="number" value="0" readonly>
</div>

<!-- 期間限定（自動制御・任意削除） -->
<div class="form-row form-row--limited">
<label>期間限定</label>
<div class="limited-inline">
<input type="checkbox"
       <?= $isLimited === 1 ? 'checked' : '' ?>
       disabled>
</div>
</div>

<button type="submit"
        class="btn btn-primary btn-submit-fixed">
発注
</button>

</form>
</div>
</div>

<script>
(function(){
  const priceEl = document.getElementById('price');
  const qtyEl   = document.getElementById('order_quantity');
  const totalEl = document.getElementById('total');

  function recalc(){
    const price = parseInt(priceEl.value || 0,10);
    const qty   = parseInt(qtyEl.value || 0,10);
    totalEl.value = price * qty;
  }
  qtyEl.addEventListener('input', recalc);
})();
</script>

</body>
</html>