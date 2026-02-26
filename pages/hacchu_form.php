<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// カテゴリ一覧
$stmt = $pdo->query("SELECT id, category_label_ja FROM categories ORDER BY category_group, id");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 事前入力JAN
$prefillJan = $_GET['jan'] ?? '';
$prefillJan = preg_replace('/\D/', '', $prefillJan);

// 自動反映（JANがある場合だけDBから引く）
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
      i.category_id
    FROM items i
    WHERE i.jan_code = :jan
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':jan' => $prefillJan]);
  $item = $st->fetch(PDO::FETCH_ASSOC);
}

// 誘導メッセージ（JANがあるのに商品が無いとき）
$notice = null;
if ($prefillJan !== '' && !$item) {
  $notice = "このJANは未登録です。商品追加へ進んでください。";
}

// 初期値
$itemId   = $item ? (int)$item['id'] : 0;
$jan      = $item ? (string)$item['jan_code'] : $prefillJan;
$name     = $item ? (string)$item['item_name'] : '';
$unit     = $item ? (string)$item['unit'] : '';
$price    = $item ? (int)$item['price'] : 0;
$supplier = $item ? (string)$item['supplier'] : '';
$catId    = $item ? (int)$item['category_id'] : 0;

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>発注</title>
  <link rel="stylesheet" href="../assets/css/hacchu.css?v=4">
</head>

<body class="hacchu-form-page">

  <!-- 左上固定：戻る -->
  <a href="home.php" class="btn btn-back">戻る</a>

  <!-- 右上固定：商品追加 / 発注履歴 -->
  <div class="top-actions">
    <a class="btn btn-sub btn-small" href="item_register.php<?= $jan !== '' ? '?jan='.h($jan) : '' ?>">
      商品追加
    </a>
    <a class="btn btn-sub btn-small" href="hacchu_list.php">
      発注履歴
    </a>
  </div>

  <div class="container">
    <h1 class="page-title">発注</h1>

    <div class="card">
      <?php if ($notice): ?>
        <div class="notice">
          <?= h($notice) ?>
          <div class="notice-actions">
            <a class="btn btn-small btn-primary-soft" href="item_register.php?jan=<?= h($jan) ?>">商品追加へ</a>
          </div>
        </div>
      <?php endif; ?>

      <form class="order-form" method="post" action="hacchu.php" novalidate>

        <input type="hidden" name="item_id" value="<?= (int)$itemId ?>">

        <div class="form-row">
          <label for="jan">JAN</label>
          <input id="jan" name="jan" type="text" inputmode="numeric" placeholder="JANコード" value="<?= h($jan) ?>">
        </div>

        <div class="form-row">
          <label for="item_name">商品名</label>
          <input id="item_name" name="item_name_view" type="text" placeholder="商品名" value="<?= h($name) ?>" readonly>
        </div>

        <div class="form-row">
          <label for="category_id">カテゴリ</label>
          <select id="category_id" name="category_id_view" disabled>
            <option value="">選択してください</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === $catId) ? 'selected' : '' ?>>
                <?= h($c['category_label_ja']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <label for="supplier">発注先</label>
          <input id="supplier" name="supplier_view" type="text" placeholder="発注先" value="<?= h($supplier) ?>" readonly>
        </div>

        <div class="form-row">
          <label for="order_date">発注日</label>
          <input id="order_date" name="order_date" type="date" value="<?= h($today) ?>">
        </div>

        <div class="form-row">
          <label for="price">単価（円）</label>
          <input id="price" name="price_view" type="number" value="<?= (int)$price ?>" readonly>
        </div>

        <div class="form-row">
          <label for="order_quantity">個数（点）</label>
          <input id="order_quantity" name="order_quantity" type="number" min="1" value="0">
        </div>

        <div class="form-row">
          <label for="unit">単位</label>
          <input id="unit" name="unit_view" type="text" placeholder="単位" value="<?= h($unit) ?>" readonly>
        </div>

        <div class="form-row">
          <label for="total">合計（円）</label>
          <input id="total" type="number" value="0" readonly>
        </div>

        <!-- ✅ ここが希望の形： 期間限定  ☐  任意 -->
        <div class="form-row form-row--limited">
          <label class="limited-label">期間限定</label>

          <div class="limited-inline">
            <!-- name/value は絶対に変えない（機能維持） -->
            <input id="is_limited" type="checkbox" name="is_limited" value="1">
            <label for="is_limited" class="limited-checktext">任意</label>
          </div>
        </div>

        <!-- 右下固定：発注（submit） -->
        <button type="submit" class="btn btn-primary btn-submit-fixed">発注</button>

      </form>
    </div>
  </div>

<script>
(function(){
  const priceEl = document.getElementById('price');
  const qtyEl   = document.getElementById('order_quantity');
  const totalEl = document.getElementById('total');

  function recalc(){
    const price = parseInt(priceEl?.value || '0', 10) || 0;
    const qty   = parseInt(qtyEl?.value || '0', 10) || 0;
    totalEl.value = price * qty;
  }

  if (qtyEl) qtyEl.addEventListener('input', recalc);
  recalc();
})();
</script>

</body>
</html>