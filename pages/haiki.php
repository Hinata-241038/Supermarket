<?php
require_once __DIR__ . '/../dbconnect.php';

$sql = "
SELECT
  s.id AS stock_id,
  i.item_name,
  i.jan_code,
  c.category_label_ja,
  s.quantity,
  COALESCE(s.consume_date, s.best_before_date, s.expire_date) AS expire_view
FROM stock s
JOIN items i ON s.item_id = i.id
LEFT JOIN categories c ON i.category_id = c.id
WHERE COALESCE(s.consume_date, s.best_before_date, s.expire_date) < CURDATE()
ORDER BY expire_view
";
$stmt = $pdo->query($sql);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>廃棄対象</title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<button class="back-btn" onclick="location.href='home.php'">戻る</button>

<div class="container">
<h1 class="page-title">廃棄対象（期限切れ）</h1>

<?php if(!empty($_GET['done'])): ?>
  <div class="notice">廃棄処理が完了しました。</div>
<?php endif; ?>

<div class="card">
<table class="table">
<thead>
<tr>
  <th>JAN</th>
  <th>商品名</th>
  <th>カテゴリ</th>
  <th>数量</th>
  <th>期限</th>
  <th>操作</th>
</tr>
</thead>
<tbody>

<?php if($items): ?>
<?php foreach($items as $item): ?>
<tr class="row-expired">
  <td><?= htmlspecialchars($item['jan_code']) ?></td>
  <td><?= htmlspecialchars($item['item_name']) ?></td>
  <td><?= htmlspecialchars($item['category_label_ja'] ?? '') ?></td>
  <td><?= (int)$item['quantity'] ?></td>
  <td><?= htmlspecialchars($item['expire_view']) ?></td>
  <td>
    <!-- 互換：単発でも廃棄できる（haiki_execute.phpが両対応） -->
    <form method="post" action="haiki_execute.php"
          onsubmit="return confirm('この在庫ロットを廃棄しますか？');">
      <input type="hidden" name="stock_id" value="<?= (int)$item['stock_id'] ?>">
      <input type="hidden" name="view" value="expired">
      <input type="hidden" name="reason" value="期限切れ">
      <button class="btn btn-danger">廃棄確定</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="6" class="no-data">廃棄対象はありません</td></tr>
<?php endif; ?>

</tbody>
</table>
</div>

</div>
</body>
</html>
