<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/../dbconnect.php';

/* =========================================================
   期限モード管理（完全セッション制御）
========================================================= */
if (!isset($_SESSION['expire_mode'])) {
  $_SESSION['expire_mode'] = 'best'; // 初期値
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_expire_mode'])) {
  if ($_POST['change_expire_mode'] === 'consume') {
    $_SESSION['expire_mode'] = 'consume';
  } elseif ($_POST['change_expire_mode'] === 'best') {
    $_SESSION['expire_mode'] = 'best';
  }
}

$expireMode = $_SESSION['expire_mode'];

/* =========================================================
   カラムチェック
========================================================= */
function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
  $st->execute([':c'=>$column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

$hasConsume = hasColumn($pdo,'stock','consume_date');
$hasBest    = hasColumn($pdo,'stock','best_before_date');
$hasLegacy  = hasColumn($pdo,'stock','expire_date');

/* =========================================================
   期限列決定（モード優先）
========================================================= */
if ($expireMode === 'consume' && $hasConsume) {
  $expireCol = 's.consume_date';
} elseif ($hasBest) {
  $expireCol = 's.best_before_date';
} else {
  $expireCol = 's.expire_date';
}

/* =========================================================
   データ取得
========================================================= */
$sql = "
SELECT
  s.id,
  s.item_id,
  COALESCE(i.jan_code,'') AS jan_code,
  COALESCE(i.item_name,'') AS item_name,
  COALESCE({$expireCol}, '') AS expire_date,
  COALESCE(s.quantity, 0) AS quantity
FROM stock s
LEFT JOIN items i ON i.id = s.item_id
ORDER BY i.item_name
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($v){
  return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>在庫</title>
<link rel="stylesheet" href="../assets/css/zaiko.css">
</head>
<body>

<a href="home.php" class="back-btn">戻る</a>

<h1 class="title">在庫</h1>

<!-- 🔹 モード表示ヘッダー -->
<div style="width:90%; margin:0 auto 10px; font-weight:bold;">
  現在：
  <span style="color:#1976d2;">
    <?= $expireMode === 'consume' ? '消費期限モード' : '賞味期限モード' ?>
  </span>
</div>

<!-- 🔹 モード切替（POST方式） -->
<form method="post" style="width:90%; margin:0 auto 20px;">
  <?php if ($expireMode === 'consume'): ?>
    <button type="submit" name="change_expire_mode" value="best">
      賞味期限に切替
    </button>
  <?php else: ?>
    <button type="submit" name="change_expire_mode" value="consume">
      消費期限に切替
    </button>
  <?php endif; ?>
</form>

<div class="table-wrap">
<table class="item-table">
<tr>
  <th>JAN</th>
  <th>商品名</th>
  <th>期限</th>
  <th>在庫</th>
  <th>操作</th>
</tr>

<?php foreach($rows as $r): ?>
<tr>
  <td><?= h($r['jan_code']) ?></td>
  <td><?= h($r['item_name']) ?></td>
  <td><?= h($r['expire_date']) ?></td>
  <td><?= (int)$r['quantity'] ?></td>
  <td>
    <a href="zaiko_edit.php?item_id=<?= (int)$r['item_id'] ?>">
      編集
    </a>
  </td>
</tr>
<?php endforeach; ?>

</table>
</div>

</body>
</html>
