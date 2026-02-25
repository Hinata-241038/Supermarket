<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* =========================
  カテゴリ一覧
========================= */
$stmt = $pdo->query("SELECT id, category_label_ja FROM categories ORDER BY category_group, id");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
  JANの事前入力（例：item_register.php から戻る時）
========================= */
$prefillJan = $_GET['jan'] ?? '';
$prefillJan = preg_replace('/\D/', '', $prefillJan);

/* =========================
  JANがある場合：itemsから自動反映
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
      i.category_id
    FROM items i
    WHERE i.jan_code = :jan
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':jan' => $prefillJan]);
  $item = $st->fetch(PDO::FETCH_ASSOC);
}

$today = date('Y-m-d');

/* =========================
  初期値
========================= */
$valJan      = $item['jan_code']    ?? $prefillJan;
$valName     = $item['item_name']   ?? '';
$valUnit     = $item['unit']        ?? '';
$valPrice    = $item['price']       ?? '';
$valSupplier = $item['supplier']    ?? '';
$valCatId    = $item['category_id'] ?? '';
$valItemId   = $item['id']          ?? 0;

/* item_register.php から誘導された時のメッセージ（任意） */
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>発注</title>
  <link rel="stylesheet" href="../assets/css/hacchu.css">
</head>
<body>

<!-- 左上固定：戻る -->
<a class="btn btn-back" href="home.php">戻る</a>

<!-- 左下固定：ショートカット -->
<div class="shortcut">
  <a class="btn btn-sub" href="item_register.php">商品追加</a>
  <a class="btn btn-sub" href="hacchu_list.php">発注履歴</a>
</div>

<div class="container">
  <h1 class="page-title">発注</h1>

  <div class="card">

    <?php if ($msg !== ''): ?>
      <div class="notice"><?= h($msg) ?></div>
    <?php endif; ?>

    <form class="order-form" method="post" action="hacchu.php" novalidate>

      <!-- 重要：JANを変更したら0に戻す（JSで） -->
      <input type="hidden" name="item_id" id="item_id" value="<?= (int)$valItemId ?>">

      <!-- JAN：hacchu.php が見る name="jan" に統一 -->
      <div class="form-row">
        <label for="jan">JAN</label>
        <input
          id="jan"
          type="text"
          name="jan"
          inputmode="numeric"
          autocomplete="off"
          value="<?= h($valJan) ?>"
          placeholder="JANコード"
        >
      </div>

      <div class="form-row">
        <label for="item_name">商品名</label>
        <input id="item_name" type="text" value="<?= h($valName) ?>" placeholder="商品名" readonly>
      </div>

      <div class="form-row">
        <label for="category_id">カテゴリ</label>
        <select id="category_id" disabled>
          <option value="">選択してください</option>
          <?php foreach($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((string)$valCatId === (string)$c['id']) ? 'selected' : '' ?>>
              <?= h($c['category_label_ja']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <label for="supplier">発注先</label>
        <input id="supplier" type="text" value="<?= h($valSupplier) ?>" placeholder="発注先" readonly>
      </div>

      <div class="form-row">
        <label for="order_date">発注日</label>
        <input id="order_date" type="date" name="order_date" value="<?= h($today) ?>" required>
      </div>

      <div class="form-row">
        <label for="price">単価（円）</label>
        <input id="price" type="number" name="price" value="<?= h($valPrice) ?>" placeholder="0" readonly>
      </div>

      <div class="form-row">
        <label for="order_quantity">個数（点）</label>
        <input id="order_quantity" type="number" name="order_quantity" min="0" value="0" placeholder="0" required>
      </div>

      <div class="form-row">
        <label for="unit">単位</label>
        <input id="unit" type="text" value="<?= h($valUnit) ?>" placeholder="単位" readonly>
      </div>

      <div class="form-row">
        <label for="total">合計（円）</label>
        <input id="total" type="number" value="0" readonly>
      </div>

      <div class="form-row">
        <label>期間限定</label>
        <div class="check-wrap">
          <input id="is_limited" type="checkbox" name="is_limited" value="1">
          <label class="check-label" for="is_limited">期間限定商品</label>
          <span class="optional">(任意)</span>
        </div>
      </div>

      <!-- 右下固定：submit -->
      <button type="submit" class="btn btn-primary btn-submit-fixed">発注</button>
    </form>
  </div>
</div>

<script>
/* =========================
  合計計算（単価×個数）
========================= */
function calcTotal(){
  const price = Number(document.getElementById('price').value || 0);
  const qty   = Number(document.getElementById('order_quantity').value || 0);
  document.getElementById('total').value = price * qty;
}
document.getElementById('order_quantity').addEventListener('input', calcTotal);
calcTotal();

/* =========================
  重要：JANを変更したら item_id を0に戻す
  → 古いitem_idで別商品が発注される事故防止
========================= */
document.getElementById('jan').addEventListener('input', () => {
  document.getElementById('item_id').value = 0;
});
</script>

</body>
</html>