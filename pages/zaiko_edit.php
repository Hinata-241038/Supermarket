<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

/* 権限（編集はmng/fteに限定して安全に） */
if (!isset($_SESSION['role'])) {
  header('Location: logu.php');
  exit;
}
$role = $_SESSION['role'];
if (!in_array($role, ['mng','fte'], true)) {
  exit('権限がありません');
}

function h($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
  $st->execute([':c' => $column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

function hasTable(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("SHOW TABLES LIKE :t");
  $st->execute([':t' => $table]);
  return (bool)$st->fetch(PDO::FETCH_NUM);
}

/* ===== カラム存在チェック ===== */
$hasConsume   = hasColumn($pdo, 'stock', 'consume_date');
$hasBest      = hasColumn($pdo, 'stock', 'best_before_date');
$hasLegacy    = hasColumn($pdo, 'stock', 'expire_date');

$hasUnit      = hasColumn($pdo, 'items', 'unit');
$hasCategory  = hasColumn($pdo, 'items', 'category_id');
$hasSupplier  = hasColumn($pdo, 'items', 'supplier');
$hasLimited   = hasColumn($pdo, 'items', 'is_limited');

$hasCategoriesTable = hasTable($pdo, 'categories');
$hasCategoryLabel   = $hasCategoriesTable && hasColumn($pdo, 'categories', 'category_label_ja');

/* ===== 対象stock_id ===== */
$stock_id = (int)($_GET['stock_id'] ?? 0);
if ($stock_id <= 0) {
  exit('不正なアクセス');
}

/* ===== カテゴリ一覧取得（使える場合のみ） ===== */
$categories = [];
if ($hasCategory && $hasCategoriesTable && $hasCategoryLabel) {
  $sqlCat = "SELECT id, category_label_ja FROM categories ORDER BY category_group, id";
  $stCat = $pdo->query($sqlCat);
  $categories = $stCat->fetchAll(PDO::FETCH_ASSOC);
}

/* ===== 在庫ロット + 商品情報取得 ===== */
$selects = [
  "s.id AS stock_id",
  "s.item_id",
  "s.quantity",
  "i.jan_code",
  "i.item_name"
];

if ($hasLegacy)   $selects[] = "s.expire_date";
if ($hasConsume)  $selects[] = "s.consume_date";
if ($hasBest)     $selects[] = "s.best_before_date";

if ($hasUnit)     $selects[] = "i.unit";
if ($hasCategory) $selects[] = "i.category_id";
if ($hasSupplier) $selects[] = "i.supplier";
if ($hasLimited)  $selects[] = "i.is_limited";

if ($hasCategory && $hasCategoriesTable && $hasCategoryLabel) {
  $selects[] = "c.category_label_ja";
}

$sql = "
SELECT
  " . implode(",\n  ", $selects) . "
FROM stock s
JOIN items i ON i.id = s.item_id
" . (($hasCategory && $hasCategoriesTable && $hasCategoryLabel) ? "LEFT JOIN categories c ON c.id = i.category_id" : "") . "
WHERE s.id = :id
";

$st = $pdo->prepare($sql);
$st->execute([':id' => $stock_id]);
$r = $st->fetch(PDO::FETCH_ASSOC);

if (!$r) {
  exit('在庫ロットが見つかりません');
}

/* ===== 初期値 ===== */
$qty      = (int)($r['quantity'] ?? 0);
$consume  = $hasConsume ? ($r['consume_date'] ?? '') : '';
$best     = $hasBest ? ($r['best_before_date'] ?? '') : '';
$legacy   = $hasLegacy ? ($r['expire_date'] ?? '') : '';

$unit        = $hasUnit ? ($r['unit'] ?? '') : '';
$categoryId  = $hasCategory ? (int)($r['category_id'] ?? 0) : 0;
$categoryNm  = ($hasCategory && $hasCategoriesTable && $hasCategoryLabel) ? ($r['category_label_ja'] ?? '') : '';
$supplier    = $hasSupplier ? ($r['supplier'] ?? '') : '';
$isLimited   = $hasLimited ? (int)($r['is_limited'] ?? 0) : 0;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>在庫情報編集</title>
<link rel="stylesheet" href="../assets/css/zaiko_edit.css">
</head>
<body>

<a class="back-btn" href="zaiko.php">戻る</a>
<h1 class="title">在庫情報編集</h1>

<div class="edit-wrap">
  <div class="edit-card">
    <div id="err" class="error-box"></div>

    <form method="post" action="zaiko_edit_save.php" id="editForm">
      <input type="hidden" name="stock_id" value="<?= (int)$stock_id ?>">
      <input type="hidden" name="item_id" value="<?= (int)($r['item_id'] ?? 0) ?>">

      <div class="row">
        <label>JAN</label>
        <input type="text" value="<?= h($r['jan_code'] ?? '') ?>" readonly>
      </div>

      <div class="row">
        <label>商品名</label>
        <input type="text" value="<?= h($r['item_name'] ?? '') ?>" readonly>
      </div>

      <div class="row">
        <label>単位</label>
        <input type="text" value="<?= h($unit) ?>" readonly>
      </div>

      <hr>

      <div class="row">
        <label>カテゴリ</label>
        <?php if ($hasCategory && $hasCategoriesTable && $hasCategoryLabel): ?>
          <select name="category_id">
            <option value="">選択してください</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>" <?= ($categoryId === (int)$cat['id']) ? 'selected' : '' ?>>
                <?= h($cat['category_label_ja']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input type="text" value="<?= h($categoryNm) ?>" readonly>
          <input type="hidden" name="category_id" value="<?= (int)$categoryId ?>">
        <?php endif; ?>
      </div>

      <div class="row">
        <label>発注先</label>
        <?php if ($hasSupplier): ?>
          <input type="text" name="supplier" value="<?= h($supplier) ?>" maxlength="255">
        <?php else: ?>
          <input type="text" value="" readonly placeholder="supplier列がありません">
        <?php endif; ?>
      </div>

      <?php if ($hasConsume): ?>
      <div class="row">
        <label>期限（消費）</label>
        <input type="date" name="consume_date" value="<?= h($consume) ?>">
      </div>
      <?php endif; ?>

      <?php if ($hasBest): ?>
      <div class="row">
        <label>期限（賞味）</label>
        <input type="date" name="best_before_date" value="<?= h($best ?: $legacy) ?>">
      </div>
      <?php elseif ($hasLegacy): ?>
      <div class="row">
        <label>期限</label>
        <input type="date" name="expire_date" value="<?= h($legacy) ?>">
      </div>
      <?php endif; ?>

      <div class="row">
        <label>在庫数</label>
        <input type="number" name="quantity" step="1" min="0" value="<?= $qty ?>" required>
      </div>

      <div class="row row-checkbox">
        <label>期間限定</label>
        <?php if ($hasLimited): ?>
          <label class="checkbox-wrap">
            <input type="checkbox" name="is_limited" value="1" <?= $isLimited ? 'checked' : '' ?>>
            <span>☐</span>
          </label>
        <?php else: ?>
          <input type="text" value="" readonly placeholder="is_limited列がありません">
        <?php endif; ?>
      </div>

      <div class="btns">
        <button type="button" class="btn btn-cancel" onclick="location.href='zaiko.php'">キャンセル</button>
        <button type="submit" class="btn btn-save">保存</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById('editForm');
  const err = document.getElementById('err');

  function showError(msg){
    err.textContent = msg;
    err.style.display = 'block';
  }

  function clearError(){
    err.textContent = '';
    err.style.display = 'none';
  }

  form.addEventListener('submit', function(e){
    clearError();

    const quantity = form.querySelector('input[name="quantity"]');
    const consume = form.querySelector('input[name="consume_date"]');
    const best = form.querySelector('input[name="best_before_date"]');
    const legacy = form.querySelector('input[name="expire_date"]');

    if (quantity && Number(quantity.value) < 0) {
      e.preventDefault();
      showError('在庫数は0以上で入力してください');
      return;
    }

    const hasAnyDate =
      (consume && consume.value) ||
      (best && best.value) ||
      (legacy && legacy.value);

    if (!hasAnyDate) {
      e.preventDefault();
      showError('期限日を入力してください（必須）');
      return;
    }
  });
});
</script>

</body>
</html>