<?php
require_once __DIR__ . '/../dbconnect.php';

$sql = "
SELECT
  s.id AS stock_id,
  i.item_name,
  i.jan_code,
  c.category_label_ja,
  s.quantity,
  s.expire_date
FROM stock s
JOIN items i ON s.item_id = i.id
LEFT JOIN categories c ON i.category_id = c.id
WHERE s.expire_date < CURDATE()
ORDER BY s.expire_date
";
$stmt = $pdo->query($sql);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>廃棄対象一覧</title>
<link rel="stylesheet" href="../assets/css/haiki.css">
</head>
<body>

<button class="back-btn" onclick="location.href='home.php'">戻る</button>

<div class="container">
<h1>廃棄対象</h1>

<table class="item-table">
<tr>
  <th>JAN</th>
  <th>商品名</th>
  <th>カテゴリ</th>
  <th>数量</th>
  <th>期限</th>
  <th>操作</th>
</tr>

<?php foreach ($items as $item): ?>
<tr class="expired">
  <td><?= htmlspecialchars($item['jan_code']) ?></td>
  <td><?= htmlspecialchars($item['item_name']) ?></td>
  <td><?= htmlspecialchars($item['category_label_ja']) ?></td>
  <td><?= (int)$item['quantity'] ?></td>
  <td><?= htmlspecialchars($item['expire_date']) ?></td>
  <td>
    <form method="post"
          action="haiki_execute.php"
          onsubmit="return confirm('この在庫を廃棄しますか？');">
      <input type="hidden" name="stock_id" value="<?= $item['stock_id'] ?>">
      <button type="submit" class="discard-btn">廃棄確定</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>

<?php if (empty($items)): ?>
<tr><td colspan="6">廃棄対象はありません</td></tr>
<?php endif; ?>

</table>
</div>
</body>
</html>
