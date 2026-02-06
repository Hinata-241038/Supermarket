<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

/* =========================================================
  共通：カラム存在チェック
========================================================= */
function hasColumn(PDO $pdo, string $table, string $column): bool
{
  $sql = "SHOW COLUMNS FROM {$table} LIKE :col";
  $st = $pdo->prepare($sql);
  $st->execute([':col' => $column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

$hasConsume = hasColumn($pdo, 'stock', 'consume_date');
$hasBest    = hasColumn($pdo, 'stock', 'best_before_date');
$hasLegacy  = hasColumn($pdo, 'stock', 'expire_date');

/* =========================================================
  GET（検索条件）
========================================================= */
$keyword    = trim($_GET['keyword'] ?? '');
$searchMode = ($_GET['mode'] ?? 'or') === 'and' ? 'and' : 'or';
$expiryMode = $_GET['expiry'] ?? 'best'; // best / consume

/* =========================================================
  期限判定式（NULL安全）
========================================================= */
$bestExpr = $hasBest
  ? ($hasLegacy ? "COALESCE(s.best_before_date, s.expire_date)" : "s.best_before_date")
  : ($hasLegacy ? "s.expire_date" : "NULL");

$consumeExpr = $hasConsume ? "s.consume_date" : "NULL";

$expiredExpr = $expiryMode === 'consume'
  ? "{$consumeExpr} IS NOT NULL AND {$consumeExpr} < CURDATE()"
  : "{$bestExpr} IS NOT NULL AND {$bestExpr} < CURDATE()";

/* =========================================================
  廃棄処理（POST）
========================================================= */
$disposeError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispose'])) {
  try {
    $pdo->beginTransaction();

    $sqlDelete = "
      DELETE FROM stock s
      WHERE s.quantity <= 0
         OR ({$expiredExpr})
    ";
    $pdo->exec($sqlDelete);

    $pdo->commit();
  } catch (PDOException $e) {
    $pdo->rollBack();
    $disposeError = '廃棄処理でエラー: ' . $e->getMessage();
  }
}

/* =========================================================
  在庫一覧取得
========================================================= */
$where = [];
$params = [];

if ($keyword !== '') {
  $terms = preg_split('/\s+/', $keyword);
  $conds = [];
  foreach ($terms as $i => $t) {
    $conds[] = "(i.item_name LIKE :kw{$i}
              OR c.category_label_ja LIKE :kw{$i}
              OR i.jan_code LIKE :kw{$i})";
    $params[":kw{$i}"] = "%{$t}%";
  }
  $glue = $searchMode === 'and' ? ' AND ' : ' OR ';
  $where[] = '(' . implode($glue, $conds) . ')';
}

$sql = "
  SELECT
    s.id,
    i.jan_code,
    i.item_name,
    c.category_label_ja,
    i.unit,
    i.vendor,
    s.quantity,
    {$bestExpr} AS best_date
  FROM stock s
  LEFT JOIN items i ON i.id = s.item_id
  LEFT JOIN categories c ON c.id = i.category_id
";

if ($where) {
  $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY i.item_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>在庫</title>
<link rel="stylesheet" href="../assets/css/hacchu.css">
</head>
<body>

<a href="home.php" class="back-btn">戻る</a>

<h1>在庫</h1>

<form method="get" class="search-form">
  <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>"
         placeholder="商品名 / カテゴリ / JAN / 発注先（空白区切り可）">
  <label><input type="radio" name="mode" value="and" <?= $searchMode === 'and' ? 'checked' : '' ?>>AND</label>
  <label><input type="radio" name="mode" value="or"  <?= $searchMode === 'or'  ? 'checked' : '' ?>>OR</label>
  <button type="submit">検索</button>
</form>

<?php if ($disposeError): ?>
  <div class="error"><?= htmlspecialchars($disposeError) ?></div>
<?php endif; ?>

<form method="post">
  <button type="submit" name="dispose">廃棄処理</button>
</form>

<table>
  <thead>
    <tr>
      <th>JAN</th>
      <th>商品名</th>
      <th>カテゴリ</th>
      <th>単位</th>
      <th>発注先</th>
      <th>賞味期限</th>
      <th>在庫</th>
      <th>操作</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($stocks as $row): ?>
    <?php
      $expired = $row['best_date'] && $row['best_date'] < date('Y-m-d');
    ?>
    <tr class="<?= $expired ? 'expired' : '' ?>">
      <td><?= htmlspecialchars($row['jan_code']) ?></td>
      <td><?= htmlspecialchars($row['item_name']) ?></td>
      <td><?= htmlspecialchars($row['category_label_ja']) ?></td>
      <td><?= htmlspecialchars($row['unit']) ?></td>
      <td><?= htmlspecialchars($row['vendor']) ?></td>
      <td><?= $row['best_date'] ? htmlspecialchars($row['best_date']) : '-' ?></td>
      <td><?= (int)$row['quantity'] ?></td>
      <td>
        <a href="zaiko_edit.php?id=<?= $row['id'] ?>">編集</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

</body>
</html>
