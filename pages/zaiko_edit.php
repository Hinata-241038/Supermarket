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

$qty = (int)($r['quantity'] ?? 0);
$consume = $hasConsume ? ($r['consume_date'] ?? '') : '';
$best    = $hasBest ? ($r['best_before_date'] ?? '') : '';
$legacy  = $hasLegacy ? ($r['expire_date'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>在庫ロット 編集</title>

<!-- ★編集画面専用CSSに分離 -->
<link rel="stylesheet" href="../assets/css/zaiko_edit.css">
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
        <input type="text" value="<?= h($r['jan_code'] ?? '') ?>" readonly>
      </div>

      <div class="row">
        <label>商品名</label>
        <input type="text" value="<?= h($r['item_name'] ?? '') ?>" readonly>
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