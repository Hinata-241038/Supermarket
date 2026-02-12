<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

/* =========================================================
   1) カラム存在チェック
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
   2) 検索処理（既存機能維持）
========================================================= */
$keyword    = trim($_GET['keyword'] ?? '');
$searchMode = ($_GET['mode'] ?? 'or') === 'and' ? 'and' : 'or';

$where = [];
$params = [];

if ($keyword !== '') {
  $words = preg_split('/\s+/', $keyword);
  $conds = [];

  foreach ($words as $i => $w) {
    $conds[] = "(
      COALESCE(i.item_name,'') LIKE :w{$i}
      OR COALESCE(i.jan_code,'') LIKE :w{$i}
      OR COALESCE(c.category_label_ja,'') LIKE :w{$i}
      OR COALESCE(i.supplier,'') LIKE :w{$i}
    )";
    $params[":w{$i}"] = "%{$w}%";
  }

  $where[] = '(' . implode($searchMode === 'and' ? ' AND ' : ' OR ', $conds) . ')';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* =========================================================
   3) 期限列決定
========================================================= */
$expireCol = $hasConsume ? 's.consume_date'
           : ($hasBest  ? 's.best_before_date'
           : 's.expire_date');

/* =========================================================
   4) データ取得（NULL完全対策版）
========================================================= */
$sql = "
SELECT
  s.id,
  s.item_id,
  COALESCE(i.jan_code,'') AS jan_code,
  COALESCE(i.item_name,'') AS item_name,
  COALESCE(c.category_label_ja,'') AS category_label_ja,
  COALESCE(i.unit,'') AS unit,
  COALESCE(i.supplier,'') AS supplier,
  COALESCE({$expireCol}, '') AS expire_date,
  COALESCE(s.quantity, 0) AS quantity
FROM stock s
LEFT JOIN items i ON i.id = s.item_id
LEFT JOIN categories c ON c.id = i.category_id
{$whereSql}
ORDER BY i.item_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   5) 表示用安全関数（完全string保証）
========================================================= */
function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
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

<!-- 検索 -->
<div class="search-area">
  <form method="get" class="search-form">
    <input type="text" name="keyword" class="search-box"
      value="<?= h($keyword) ?>">
    <button type="submit" class="search-btn">🔍</button>

    <div class="search-mode">
      <label>
        <input type="radio" name="mode" value="and"
          <?= $searchMode==='and'?'checked':'' ?>> AND
      </label>
      <label>
        <input type="radio" name="mode" value="or"
          <?= $searchMode==='or'?'checked':'' ?>> OR
      </label>
    </div>
  </form>
</div>

<div class="right-actions">
  <a href="?expire=consume" class="toggle-expire-btn">消費期限に切替</a>
  <a href="dispose.php" class="dispose-btn">廃棄処理</a>
</div>

<!-- 横スクロール -->
<div class="table-wrap">
<table class="item-table">
<thead>
<tr>
  <th>JAN</th>
  <th>商品名</th>
  <th>カテゴリ</th>
  <th>単位</th>
  <th>発注先</th>
  <th>期限</th>
  <th>在庫</th>
  <th class="op-col">操作</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><?= h($r['jan_code']) ?></td>
  <td><?= h($r['item_name']) ?></td>
  <td><?= h($r['category_label_ja']) ?></td>
  <td><?= h($r['unit']) ?></td>
  <td><?= h($r['supplier']) ?></td>
  <td><?= h($r['expire_date']) ?></td>

  <td class="<?= ((int)$r['quantity'] <= 0) ? 'stock-zero' : '' ?>">
    <?= (int)$r['quantity'] ?>
  </td>

  <td class="op-col">
    <div class="op-buttons">
      <a href="zaiko_edit.php?item_id=<?= $row['id'] ?>">編集</a>

      <a href="hacchu_form.php?jan=<?= urlencode((string)$r['jan_code']) ?>"
         class="btn-order <?= ((int)$r['quantity'] <= 0) ? 'btn-order-alert' : '' ?>">
         発注
      </a>
    </div>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

</body>
</html>
