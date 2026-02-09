<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

/* =========================================================
  共通：カラム存在チェック
========================================================= */
function hasColumn(PDO $pdo, string $table, string $column): bool
{
  $sql = "SHOW COLUMNS FROM `{$table}` LIKE :col";
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
      DELETE FROM stock
      WHERE quantity <= 0
         OR ({$expiredExpr})
    ";
    $pdo->exec($sqlDelete);

    $pdo->commit();

    // POST→GET（連打で同じ処理しない）
    header('Location: zaiko.php?keyword=' . urlencode($keyword) . '&mode=' . urlencode($searchMode) . '&expiry=' . urlencode($expiryMode));
    exit;

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
  $terms = preg_split('/\s+/', $keyword, -1, PREG_SPLIT_NO_EMPTY);
  $conds = [];
  foreach ($terms as $i => $t) {
    $conds[] = "(i.item_name LIKE :kw{$i}
              OR c.category_label_ja LIKE :kw{$i}
              OR i.jan_code LIKE :kw{$i}
              OR i.supplier LIKE :kw{$i})";
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
    i.supplier,
    s.quantity,
    {$bestExpr} AS best_date,
    {$consumeExpr} AS consume_date
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

// 表示する期限名
$expiryLabel = ($expiryMode === 'consume') ? '消費期限' : '賞味期限';
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

<!-- =======================================================
  検索エリア（並び：検索欄 → 🔍 → AND/OR）
  - 既存機能を壊さないため、expiryはhiddenで維持
======================================================= -->
<div class="search-area">
  <form method="get" class="search-form">
    <input
      type="text"
      name="keyword"
      class="search-box"
      value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"
      placeholder="商品名 / カテゴリ / JAN / 発注先（空白区切り可）"
    >

    <!-- 期限モードを検索時も維持する -->
    <input type="hidden" name="expiry" value="<?= htmlspecialchars($expiryMode, ENT_QUOTES, 'UTF-8') ?>">

    <!-- 🔍（検索実行） -->
    <button type="submit" class="search-btn" aria-label="検索">🔍</button>

    <!-- AND/OR -->
    <div class="search-mode">
      <label class="radio">
        <input type="radio" name="mode" value="and" <?= $searchMode === 'and' ? 'checked' : '' ?>>
        AND
      </label>
      <label class="radio">
        <input type="radio" name="mode" value="or" <?= $searchMode === 'or' ? 'checked' : '' ?>>
        OR
      </label>
    </div>
  </form>
</div>

<?php if ($disposeError): ?>
  <div class="error-msg"><?= htmlspecialchars($disposeError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<!-- 右側の操作（期限切替・廃棄処理）は維持 -->
<div class="right-actions">
  <a class="toggle-expire-btn"
     href="zaiko.php?keyword=<?= urlencode($keyword) ?>&mode=<?= urlencode($searchMode) ?>&expiry=<?= ($expiryMode === 'best') ? 'consume' : 'best' ?>">
    <?= ($expiryMode === 'best') ? '消費期限に切替' : '賞味期限に切替' ?>
  </a>

  <form method="post" class="dispose-form">
    <button type="submit" name="dispose" class="dispose-btn">廃棄処理</button>
  </form>
</div>

<table class="item-table">
  <thead>
    <tr>
      <th>JAN</th>
      <th>商品名</th>
      <th>カテゴリ</th>
      <th>単位</th>
      <th>発注先</th>
      <th><?= htmlspecialchars($expiryLabel, ENT_QUOTES, 'UTF-8') ?></th>
      <th>在庫</th>
      <th>操作</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($stocks as $row): ?>
    <?php
      $today = date('Y-m-d');
      $dateToShow = ($expiryMode === 'consume') ? ($row['consume_date'] ?? null) : ($row['best_date'] ?? null);
      $expired = ($dateToShow && $dateToShow < $today);
    ?>
    <tr class="<?= $expired ? 'row-expire-over' : '' ?>">
      <td><?= htmlspecialchars($row['jan_code'], ENT_QUOTES, 'UTF-8') ?></td>
      <td><?= htmlspecialchars($row['item_name'], ENT_QUOTES, 'UTF-8') ?></td>
      <td><?= htmlspecialchars($row['category_label_ja'], ENT_QUOTES, 'UTF-8') ?></td>
      <td><?= htmlspecialchars($row['unit'], ENT_QUOTES, 'UTF-8') ?></td>
      <td><?= htmlspecialchars($row['supplier'], ENT_QUOTES, 'UTF-8') ?></td>
      <td>
        <span class="<?= $expired ? 'expire-over' : '' ?>">
          <?= $dateToShow ? htmlspecialchars($dateToShow, ENT_QUOTES, 'UTF-8') : '-' ?>
          <?= $expired ? '（期限切れ）' : '' ?>
        </span>
      </td>
      <td class="<?= ((int)$row['quantity'] <= 0) ? 'stock-zero' : '' ?>">
        <?= (int)$row['quantity'] ?>
      </td>
      <td class="op-buttons">
        <a href="zaiko_edit.php?item_id=<?= (int)$row['id'] ?>" class="btn-edit">編集</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

</body>
</html>
