<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

if (!isset($_SESSION['role'])) {
  header('Location: logu.php');
  exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* =========================
   廃棄履歴を取得（disposal）
========================= */

$sql = "
SELECT
  d.id,
  d.disposal_date,
  d.disposal_quantity,
  d.reason,
  d.expire_date,
  i.jan_code,
  i.item_name,
  c.category_label_ja
FROM disposal d
LEFT JOIN items i ON i.id = d.item_id
LEFT JOIN categories c ON c.id = i.category_id
ORDER BY d.disposal_date DESC, d.id DESC
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>廃棄履歴</title>
<link rel="stylesheet" href="../assets/css/haiki.css">
</head>
<body>

<button class="back-btn" onclick="location.href='home.php'">戻る</button>

<div class="container">
<h1>廃棄履歴</h1>

<div class="table-card">
<table class="item-table">
<thead>
<tr>
  <th>廃棄日</th>
  <th>JAN</th>
  <th>商品名</th>
  <th>カテゴリ</th>
  <th>数量</th>
  <th>期限</th>
  <th>理由</th>
</tr>
</thead>
<tbody>

<?php if($rows): ?>
  <?php foreach($rows as $r): ?>
  <tr>
    <td><?= h($r['disposal_date']) ?></td>
    <td><?= h($r['jan_code']) ?></td>
    <td><?= h($r['item_name']) ?></td>
    <td><?= h($r['category_label_ja']) ?></td>
    <td><?= (int)$r['disposal_quantity'] ?></td>
    <td><?= h($r['expire_date']) ?></td>
    <td><?= h($r['reason']) ?></td>
  </tr>
  <?php endforeach; ?>
<?php else: ?>
  <tr>
    <td colspan="7" class="no-data">廃棄履歴はありません</td>
  </tr>
<?php endif; ?>

</tbody>
</table>
</div>

</div>
</body>
</html>