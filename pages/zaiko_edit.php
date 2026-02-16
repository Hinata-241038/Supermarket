<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$item_id = (int)($_GET['item_id'] ?? 0);
if ($item_id <= 0) exit('不正なアクセス');

function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
  $st->execute([':c'=>$column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}
$hasConsume = hasColumn($pdo,'stock','consume_date');
$hasBest    = hasColumn($pdo,'stock','best_before_date');
$hasLegacy  = hasColumn($pdo,'stock','expire_date'); // 必須列

$categories = $pdo->query("
  SELECT id, category_label_ja
  FROM categories
  ORDER BY category_group, id
")->fetchAll(PDO::FETCH_ASSOC);

$sqlItem = "
  SELECT i.*, c.category_label_ja
  FROM items i
  LEFT JOIN categories c ON i.category_id = c.id
  WHERE i.id = :id
";
$st = $pdo->prepare($sqlItem);
$st->execute([':id'=>$item_id]);
$item = $st->fetch(PDO::FETCH_ASSOC);
if(!$item) exit('商品が見つかりません');

$consumeExpr = $hasConsume ? "MIN(consume_date)" : "NULL";
$bestExpr    = $hasBest ? "MIN(best_before_date)" : "NULL";
$legacyExpr  = $hasLegacy ? "MIN(expire_date)" : "NULL";

$sqlStock = "
  SELECT
    IFNULL(SUM(quantity),0) AS qty,
    {$consumeExpr} AS consume_date,
    {$bestExpr} AS best_before_date,
    {$legacyExpr} AS expire_date
  FROM stock
  WHERE item_id = :id
";
$st = $pdo->prepare($sqlStock);
$st->execute([':id'=>$item_id]);
$stock = $st->fetch(PDO::FETCH_ASSOC);

$qty = (int)($stock['qty'] ?? 0);
$consume = $stock['consume_date'] ?? '';
$best    = $stock['best_before_date'] ?? '';
$legacy  = $stock['expire_date'] ?? '';

$sessionMode = $_SESSION['expire_mode'] ?? 'best';
$selectedType = ($sessionMode==='consume' && $hasConsume) ? 'consume' : 'best';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>在庫 編集</title>
<link rel="stylesheet" href="../assets/css/zaiko.css">
<style>
.edit-wrap{width:90%; margin:20px auto; max-width:820px;}
.edit-card{background:#ffffff; padding:20px; border-radius:12px; box-shadow:0 4px 14px rgba(0,0,0,0.05);}
.row{display:flex; gap:12px; margin:12px 0; flex-wrap:wrap; align-items:center;}
.row label{width:140px; font-weight:700;}
.row input, .row select{flex:1; min-width:220px; padding:10px 12px; border:1px solid #D1D5DB; border-radius:8px;}
.btns{display:flex; gap:10px; justify-content:flex-end; margin-top:16px;}
.btn{padding:10px 16px; border:none; border-radius:8px; cursor:pointer; font-weight:700;}
.btn-save{background:#2563EB; color:#fff;}
.btn-cancel{background:#E5E7EB;}
.expire-switch{display:flex; gap:20px; font-weight:700;}
hr{border:none; border-top:1px solid #E5E7EB; margin:16px 0;}
.error-box{background:#FEF2F2; border:1px solid #FCA5A5; padding:10px 12px; border-radius:10px; color:#991B1B; font-weight:700; margin-bottom:12px; display:none;}
</style>
</head>
<body>

<button class="back-btn" onclick="location.href='zaiko.php'">戻る</button>
<h1 class="title">在庫 編集</h1>

<div class="edit-wrap">
  <div class="edit-card">

    <div id="err" class="error-box"></div>

    <form method="post" action="zaiko_edit_save.php" id="editForm">
      <input type="hidden" name="item_id" value="<?= (int)$item_id ?>">

      <div class="row">
        <label>JAN</label>
        <input type="text" value="<?= h($item['jan_code']) ?>" readonly>
      </div>

      <div class="row">
        <label>商品名</label>
        <input type="text" name="item_name" value="<?= h($item['item_name']) ?>" required>
      </div>

      <div class="row">
        <label>カテゴリ</label>
        <select name="category_id" required>
          <option value="">選択してください</option>
          <?php foreach($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)$item['category_id']===(int)$c['id'])?'selected':'' ?>>
              <?= h($c['category_label_ja']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="row">
        <label>単位</label>
        <input type="text" name="unit" value="<?= h($item['unit'] ?? '') ?>" required>
      </div>

      <div class="row">
        <label>発注先</label>
        <input type="text" name="supplier" value="<?= h($item['supplier'] ?? '') ?>" required>
      </div>

      <div class="row">
        <label>単価</label>
        <input type="number" name="price" min="0" step="1" value="<?= (int)($item['price'] ?? 0) ?>" required>
      </div>

      <hr>

      <div class="row">
        <label>在庫数</label>
        <input type="number" name="quantity" step="1" min="0" value="<?= $qty ?>" required>
      </div>

      <div class="row">
        <label>期限種別</label>
        <div class="expire-switch">
          <label><input type="radio" name="expire_type" value="consume" <?= $selectedType==='consume'?'checked':'' ?>> 消費期限</label>
          <label><input type="radio" name="expire_type" value="best" <?= $selectedType==='best'?'checked':'' ?>> 賞味期限</label>
        </div>
      </div>

      <div id="consumeRow" class="row">
        <label>消費期限</label>
        <input type="date" name="consume_date" value="<?= h($consume) ?>">
      </div>

      <div id="bestRow" class="row">
        <label>賞味期限</label>
        <input type="date" name="best_before_date" value="<?= h($best ?: $legacy) ?>">
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

  const radios = document.querySelectorAll('input[name="expire_type"]');
  const consumeRow = document.getElementById('consumeRow');
  const bestRow = document.getElementById('bestRow');
  const consumeInput = consumeRow.querySelector('input');
  const bestInput = bestRow.querySelector('input');

  function showError(msg){
    err.textContent = msg;
    err.style.display = 'block';
  }
  function clearError(){
    err.textContent = '';
    err.style.display = 'none';
  }

  function toggleExpire() {
    clearError();
    const selected = document.querySelector('input[name="expire_type"]:checked').value;

    if (selected === 'consume') {
      consumeRow.style.display = 'flex';
      bestRow.style.display = 'none';
      consumeInput.disabled = false;
      bestInput.disabled = true;
      bestInput.value = '';
    } else {
      consumeRow.style.display = 'none';
      bestRow.style.display = 'flex';
      consumeInput.disabled = true;
      bestInput.disabled = false;
      consumeInput.value = '';
    }
  }

  radios.forEach(r => r.addEventListener('change', toggleExpire));
  toggleExpire();

  // ✅ DBのexpire_dateがNOT NULLなので「選択した期限は必須」
  form.addEventListener('submit', function(e){
    clearError();
    const selected = document.querySelector('input[name="expire_type"]:checked').value;
    if (selected === 'consume') {
      if (!consumeInput.value) {
        e.preventDefault();
        showError('消費期限を入力してください（必須）');
      }
    } else {
      if (!bestInput.value) {
        e.preventDefault();
        showError('賞味期限を入力してください（必須）');
      }
    }
  });
});
</script>

</body>
</html>
