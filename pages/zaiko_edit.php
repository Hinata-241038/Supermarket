<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
  $st->execute([':c'=>$column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

$hasConsume = hasColumn($pdo,'stock','consume_date');
$hasBest    = hasColumn($pdo,'stock','best_before_date');
$hasLegacy  = hasColumn($pdo,'stock','expire_date'); // 必須

$stock_id = (int)($_GET['stock_id'] ?? 0);
if ($stock_id <= 0) exit('不正なアクセス');

$sql = "
SELECT
  s.*,
  i.jan_code,
  i.item_name
FROM stock s
JOIN items i ON i.id = s.item_id
WHERE s.id = :id
";
$st = $pdo->prepare($sql);
$st->execute([':id'=>$stock_id]);
$r = $st->fetch(PDO::FETCH_ASSOC);
if(!$r) exit('在庫ロットが見つかりません');

$qty = (int)$r['quantity'];
$consume = $hasConsume ? ($r['consume_date'] ?? '') : '';
$best    = $hasBest ? ($r['best_before_date'] ?? '') : '';
$legacy  = $hasLegacy ? ($r['expire_date'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>在庫ロット 編集</title>
<link rel="stylesheet" href="../assets/css/zaiko.css">
<style>
.edit-wrap{width:90%; margin:20px auto; max-width:820px;}
.edit-card{background:#ffffff; padding:20px; border-radius:12px; box-shadow:0 4px 14px rgba(0,0,0,0.05);}
.row{display:flex; gap:12px; margin:12px 0; flex-wrap:wrap; align-items:center;}
.row label{width:140px; font-weight:700;}
.row input{flex:1; min-width:220px; padding:10px 12px; border:1px solid #D1D5DB; border-radius:8px;}
.btns{display:flex; gap:10px; justify-content:flex-end; margin-top:16px;}
.btn{padding:10px 16px; border:none; border-radius:8px; cursor:pointer; font-weight:700;}
.btn-save{background:#2563EB; color:#fff;}
.btn-cancel{background:#E5E7EB;}
hr{border:none; border-top:1px solid #E5E7EB; margin:16px 0;}
.error-box{background:#FEF2F2; border:1px solid #FCA5A5; padding:10px 12px; border-radius:10px; color:#991B1B; font-weight:700; margin-bottom:12px; display:none;}
</style>
</head>
<body>

<a class="back-btn" href="zaiko.php">戻る</a>
<h1 class="title">在庫ロット 編集</h1>

<div class="edit-wrap">
  <div class="edit-card">
    <div id="err" class="error-box"></div>

    <form method="post" action="zaiko_edit_save.php" id="editForm">
      <input type="hidden" name="stock_id" value="<?= (int)$stock_id ?>">

      <div class="row">
        <label>JAN</label>
        <input type="text" value="<?= h($r['jan_code']) ?>" readonly>
      </div>

      <div class="row">
        <label>商品名</label>
        <input type="text" value="<?= h($r['item_name']) ?>" readonly>
      </div>

      <hr>

      <div class="row">
        <label>在庫数</label>
        <input type="number" name="quantity" step="1" min="0" value="<?= $qty ?>" required>
      </div>

      <?php if ($hasConsume): ?>
      <div class="row">
        <label>消費期限</label>
        <input type="date" name="consume_date" value="<?= h($consume) ?>">
      </div>
      <?php endif; ?>

      <?php if ($hasBest): ?>
      <div class="row">
        <label>賞味期限</label>
        <input type="date" name="best_before_date" value="<?= h($best ?: $legacy) ?>">
      </div>
      <?php else: ?>
      <div class="row">
        <label>期限</label>
        <input type="date" name="expire_date" value="<?= h($legacy) ?>">
      </div>
      <?php endif; ?>

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

  // expire_date NOT NULL 前提：少なくとも何かの日付は必要（消費or賞味or期限）
  form.addEventListener('submit', function(e){
    clearError();
    const consume = form.querySelector('input[name="consume_date"]');
    const best    = form.querySelector('input[name="best_before_date"]');
    const legacy  = form.querySelector('input[name="expire_date"]');

    const hasAny = (consume && consume.value) || (best && best.value) || (legacy && legacy.value);
    if (!hasAny) {
      e.preventDefault();
      showError('期限日を入力してください（必須）');
    }
  });
});
</script>

</body>
</html>
