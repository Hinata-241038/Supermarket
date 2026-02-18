<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

if (!isset($_SESSION['role'])) {
  header('Location: logu.php');
  exit;
}
$role = $_SESSION['role'];
if (!($role === 'mng' || $role === 'fte')) exit('権限がありません');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtDate($d){ if(!$d) return ''; return date('Y-m-d', strtotime($d)); }
function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
  $st->execute([':c'=>$column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

$hasConsume = hasColumn($pdo,'stock','consume_date');
$hasBest    = hasColumn($pdo,'stock','best_before_date');
$hasLegacy  = hasColumn($pdo,'stock','expire_date');

$view = $_POST['view'] ?? 'best';
$stockIds = $_POST['stock_ids'] ?? [];

if (!is_array($stockIds) || count($stockIds) === 0) {
  header('Location: zaiko.php?view=' . urlencode($view));
  exit;
}

$stockIds = array_values(array_unique(array_filter(array_map('intval', $stockIds), fn($v)=>$v>0)));
if (!$stockIds) {
  header('Location: zaiko.php?view=' . urlencode($view));
  exit;
}

$expireLabel = '期限';
$expireExpr  = "COALESCE(" .
  ($hasConsume ? "s.consume_date," : "") .
  ($hasBest    ? "s.best_before_date," : "") .
  ($hasLegacy  ? "s.expire_date" : "NULL") .
")";

if ($view === 'consume') { $expireLabel='消費期限'; $expireExpr = $hasConsume ? "s.consume_date" : "NULL"; }
if ($view === 'limited') { $expireLabel='販売終了日'; $expireExpr = "i.limited_end"; }
if ($view === 'best')    { $expireLabel='賞味期限'; $expireExpr = $hasBest ? "COALESCE(s.best_before_date, s.expire_date)" : "s.expire_date"; }

$placeholders = implode(',', array_fill(0, count($stockIds), '?'));

$sql = "
SELECT
  s.id AS stock_id,
  i.jan_code,
  i.item_name,
  c.category_label_ja,
  i.supplier,
  s.quantity,
  {$expireExpr} AS expire_view
FROM stock s
LEFT JOIN items i ON i.id = s.item_id
LEFT JOIN categories c ON c.id = i.category_id
WHERE s.id IN ({$placeholders})
ORDER BY expire_view ASC, s.id DESC
";
$st = $pdo->prepare($sql);
$st->execute($stockIds);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
  header('Location: zaiko.php?view=' . urlencode($view));
  exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>廃棄確認</title>
<link rel="stylesheet" href="../assets/css/zaiko.css">
</head>
<body>

<a href="zaiko.php?view=<?= h(urlencode($view)) ?>" class="back-btn">戻る</a>
<h1 class="title">廃棄確認</h1>

<div class="confirm-card">
  <p class="confirm-lead">選択した在庫ロットを廃棄します。内容を確認してください。</p>

  <form method="post" action="haiki_execute.php" id="disposeForm">
    <?php foreach ($stockIds as $id): ?>
      <input type="hidden" name="stock_ids[]" value="<?= (int)$id ?>">
    <?php endforeach; ?>
    <input type="hidden" name="view" value="<?= h($view) ?>">

    <div class="reason-row">
      <label for="reason">廃棄理由</label>
      <select name="reason" id="reason">
        <option value="期限切れ">期限切れ</option>
        <option value="破損">破損</option>
        <option value="返品">返品</option>
        <option value="棚卸調整">棚卸調整</option>
        <option value="その他">その他</option>
      </select>
      <span class="reason-hint">※廃棄履歴に保存されます</span>
    </div>

    <div class="table-wrap">
      <table class="item-table">
        <thead>
          <tr>
            <th>JAN</th>
            <th>商品名</th>
            <th>カテゴリ</th>
            <th>発注先</th>
            <th><?= h($expireLabel) ?></th>
            <th>廃棄数量</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
          <tr>
            <td><?= h($r['jan_code']) ?></td>
            <td><?= h($r['item_name']) ?></td>
            <td><?= h($r['category_label_ja']) ?></td>
            <td><?= h($r['supplier']) ?></td>
            <td><?= h(fmtDate($r['expire_view'] ?? '')) ?></td>
            <td><?= (int)$r['quantity'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="confirm-actions">
      <button type="submit" class="dispose-btn">OK（廃棄実行）</button>
    </div>
  </form>
</div>

<script>
document.getElementById('disposeForm').addEventListener('submit', function(e){
  if(!confirm('最終確認：選択した在庫を廃棄します。本当にOKですか？')){
    e.preventDefault();
  }
});
</script>

</body>
</html>
