<?php
require_once __DIR__ . '/../dbconnect.php';

$keyword = trim($_GET['keyword'] ?? '');
$from    = $_GET['from'] ?? '';
$to      = $_GET['to'] ?? '';

$where = [];
$params = [];

if($keyword !== ''){
  $where[] = "i.item_name LIKE :kw";
  $params[':kw'] = "%$keyword%";
}

if($from !== ''){
  $where[] = "d.disposal_date >= :from";
  $params[':from'] = $from;
}

if($to !== ''){
  $where[] = "d.disposal_date <= :to";
  $params[':to'] = $to;
}

$whereSql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$sql = "
SELECT
  d.disposal_date,
  i.item_name,
  i.jan_code,
  d.disposal_quantity,
  d.reason
FROM disposal d
JOIN items i ON d.item_id = i.id
$whereSql
ORDER BY d.disposal_date DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalQty = array_sum(array_column($rows,'disposal_quantity'));
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>廃棄履歴</title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<button class="back-btn" onclick="location.href='home.php'">戻る</button>

<div class="container">
<h1 class="page-title">廃棄履歴</h1>

<div class="card">
<div class="search-area">
<form method="get">
  <input type="text" name="keyword" placeholder="商品名検索"
         value="<?= htmlspecialchars($keyword) ?>">
  <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
  <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
  <button class="btn btn-primary">検索</button>
</form>
</div>

<table class="table">
<thead>
<tr>
  <th>日付</th>
  <th>JAN</th>
  <th>商品名</th>
  <th>数量</th>
  <th>理由</th>
</tr>
</thead>
<tbody>

<?php if($rows): ?>
<?php foreach($rows as $row): ?>
<tr>
  <td><?= htmlspecialchars($row['disposal_date']) ?></td>
  <td><?= htmlspecialchars($row['jan_code']) ?></td>
  <td><?= htmlspecialchars($row['item_name']) ?></td>
  <td><?= (int)$row['disposal_quantity'] ?></td>
  <td><?= htmlspecialchars($row['reason']) ?></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="5" class="no-data">履歴はありません</td></tr>
<?php endif; ?>

</tbody>
</table>

<div style="padding:20px;font-weight:600;">
  合計廃棄数量：<?= $totalQty ?>
</div>

</div>
</div>
</body>
</html>
