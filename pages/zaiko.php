<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

$keyword = $_GET['keyword'] ?? '';

$sql = "
SELECT
  i.id AS item_id,
  i.jan_code,
  i.item_name,
  i.unit,
  i.reorder_point,
  c.category_label_ja,
  IFNULL(s.quantity, 0) AS stock_quantity,
  s.expire_date
FROM items i
LEFT JOIN categories c ON i.category_id = c.id
LEFT JOIN stock s ON i.id = s.item_id
WHERE
  i.item_name LIKE :keyword
  OR c.category_label_ja LIKE :keyword
ORDER BY i.item_name, s.expire_date
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':keyword' => "%{$keyword}%"]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = new DateTime('today');
$soon = (new DateTime('today'))->modify('+7 days');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>在庫</title>
<link rel="stylesheet" href="../assets/css/zaiko.css">
</head>
<body>

<button class="back-btn" onclick="location.href='home.php'">戻る</button>
<h1 class="title">在庫</h1>

<form method="get" class="search-area">
  <input type="text" name="keyword" class="search-box"
         placeholder="商品名またはカテゴリで検索"
         value="<?= htmlspecialchars($keyword) ?>">
  <button class="search-btn">🔍</button>
  <button type="button" class="order-btn"
          onclick="location.href='hacchu_form.php'">発注</button>
</form>

<table class="item-table">
<tr>
  <th>JAN</th>
  <th>商品名</th>
  <th>カテゴリ</th>
  <th>単位</th>
  <th>期限</th>
  <th>在庫</th>
  <th>操作</th>
</tr>

<?php foreach ($items as $item): ?>
<?php
  $qty = (int)$item['stock_quantity'];
  $reorder = (int)$item['reorder_point'];

  $isLowStock = ($qty <= $reorder);
  $isZero = ($qty === 0);

  $expireLabel = '-';
  $expireClass = '';

  if (!empty($item['expire_date'])) {
    $exp = new DateTime($item['expire_date']);
    if ($exp < $today) {
      $expireClass = 'expire-over';
      $expireLabel = '⚠ 期限切れ';
    } elseif ($exp <= $soon) {
      $expireClass = 'expire-soon';
      $expireLabel = '⚠ 期限間近';
    } else {
      $expireLabel = $exp->format('Y-m-d');
    }
  }
?>
<tr class="<?= $isLowStock ? 'row-low-stock' : '' ?>">
  <td><?= htmlspecialchars($item['jan_code']) ?></td>
  <td><?= htmlspecialchars($item['item_name']) ?></td>
  <td><?= htmlspecialchars($item['category_label_ja']) ?></td>
  <td><?= htmlspecialchars($item['unit']) ?></td>

  <td class="<?= $expireClass ?>">
    <?= htmlspecialchars($expireLabel) ?>
  </td>

  <td class="
    <?= $isZero ? 'stock-zero' : '' ?>
    <?= $isLowStock ? 'low-stock' : '' ?>
  ">
    <?= $qty ?>
    <?php if ($isLowStock): ?>
      <span class="low-stock-label">要発注</span>
    <?php endif; ?>
  </td>

  <td>
    <?php if ($isLowStock): ?>
      <a href="hacchu_form.php?jan=<?= urlencode($item['jan_code']) ?>"
         class="order-suggest-btn">
        発注
      </a>
    <?php else: ?>
      -
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</table>

</body>
</html>
