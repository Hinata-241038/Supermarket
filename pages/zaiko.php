<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

/* =========================
   権限チェック
========================= */
if (!isset($_SESSION['role'])) {
    header('Location: logu.php');
    exit;
}
$role = $_SESSION['role'];

/* =========================
   共通関数
========================= */
function h($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fmtDate($d){
  if (!$d) return '';
  return date('Y-m-d', strtotime($d));
}

function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
  $st->execute([':c'=>$column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

function remainingDays($end){
  if(!$end) return null;
  $d1 = new DateTime();
  $d2 = new DateTime($end);
  return (int)$d1->diff($d2)->format('%r%a');
}

/* =========================
   カラム確認（ロット安全）
========================= */
$hasConsume = hasColumn($pdo,'stock','consume_date');
$hasBest    = hasColumn($pdo,'stock','best_before_date');
$hasLegacy  = hasColumn($pdo,'stock','expire_date');

/* =========================
   表示モード取得
========================= */
$view = $_GET['view'] ?? 'best'; // best / consume / limited

$expireMode = 'best';
$limitedOnly = false;
$expireColumnLabel = '期限';   // ← デフォルト

if ($view === 'consume') {
    $expireMode = 'consume';
    $expireColumnLabel = '消費期限';
}
elseif ($view === 'limited') {
    $limitedOnly = true;
    $expireColumnLabel = '販売終了日';
}
else {
    $expireColumnLabel = '賞味期限';
}

/* =========================
   検索 AND/OR
========================= */
$keyword    = trim($_GET['keyword'] ?? '');
$searchMode = (($_GET['mode'] ?? 'or') === 'and') ? 'and' : 'or';

$where = [];
$params = [];

if ($keyword !== '') {
  $terms = preg_split('/\s+/', $keyword);
  $pieces = [];
  foreach ($terms as $i => $t) {
    $p = ":t{$i}";
    $params[$p] = "%{$t}%";
    $pieces[] = "(i.jan_code LIKE {$p} OR i.item_name LIKE {$p} OR i.supplier LIKE {$p} OR c.category_label_ja LIKE {$p})";
  }
  $glue = ($searchMode==='and') ? ' AND ' : ' OR ';
  $where[] = '(' . implode($glue, $pieces) . ')';
}

/* 期間限定モード */
if ($limitedOnly) {
  $where[] = "i.is_limited = 1 AND (i.limited_end IS NULL OR i.limited_end >= CURDATE())";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* =========================
   期限表示式
========================= */
if ($limitedOnly) {
  $expireExpr = "i.limited_end";
}
else {
  $expireExpr = $expireMode==='consume' && $hasConsume
    ? "MIN(s.consume_date)"
    : ($hasBest ? "MIN(s.best_before_date)" : "MIN(s.expire_date)");
}

$sql = "
SELECT
  i.id AS item_id,
  i.jan_code,
  i.item_name,
  i.unit,
  i.supplier,
  c.category_label_ja,
  i.is_limited,
  i.limited_end,
  IFNULL(SUM(s.quantity),0) AS stock_qty,
  {$expireExpr} AS expire_view
FROM items i
LEFT JOIN categories c ON c.id = i.category_id
LEFT JOIN stock s ON s.item_id = i.id
{$whereSql}
GROUP BY i.id
ORDER BY i.id DESC
";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
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

<div class="search-area">
  <form method="get" class="search-form">
    <input class="search-box" type="text" name="keyword"
      placeholder="JAN / 商品名 / 発注先 / カテゴリ で検索"
      value="<?= h($keyword) ?>">

    <button class="search-btn" type="submit">🔍</button>

    <div class="search-mode">
      <label><input type="radio" name="mode" value="and" <?= $searchMode==='and'?'checked':'' ?>> AND</label>
      <label><input type="radio" name="mode" value="or" <?= $searchMode==='or'?'checked':'' ?>> OR</label>
    </div>

    <!-- プルダウン -->
    <select name="view" class="view-select" onchange="this.form.submit()">
      <option value="best" <?= $view==='best'?'selected':'' ?>>賞味期限</option>
      <option value="consume" <?= $view==='consume'?'selected':'' ?>>消費期限</option>
      <option value="limited" <?= $view==='limited'?'selected':'' ?>>期間限定</option>
    </select>


  </form>
</div>

<div class="right-actions">
  <?php if ($role === 'mng' || $role === 'fte'): ?>
    <a href="haiki.php">廃棄処理</a>
  <?php endif; ?>
</div>

<div class="table-wrap">
  <table class="item-table">
    <thead>
      <tr>
        <th>JAN</th>
        <th>商品名</th>
        <th>カテゴリ</th>
        <th>単位</th>
        <th>発注先</th>
        <th><?= h($expireColumnLabel) ?></th>
        <th>在庫</th>
        <th class="op-col">操作</th>
      </tr>
    </thead>
    <tbody>
      <?php if(!$rows): ?>
        <tr><td colspan="8" style="padding:18px;">該当するデータがありません</td></tr>
      <?php else: ?>
        <?php foreach($rows as $r): ?>
          <?php
            $qty = (int)$r['stock_qty'];
            $expire = fmtDate($r['expire_view'] ?? '');
            $qtyClass = ($qty <= 0) ? 'stock-zero' : '';
            $rowClass = '';
            if ($r['is_limited'] && (!$r['limited_end'] || $r['limited_end'] >= date('Y-m-d'))) {
                $rowClass = 'limited-row';
            }

          ?>
          <tr class="<?= $rowClass ?>">
            <td><?= h($r['jan_code']) ?></td>
            <td>
              <?= h($r['item_name']) ?>
              <?php if($r['is_limited']): ?>
                <?php
                  $days = remainingDays($r['limited_end']);
                  $badgeClass = 'badge-limited';
                  if($days !== null && $days <= 3) $badgeClass='badge-danger';
                  elseif($days !== null && $days <= 7) $badgeClass='badge-warning';
                ?>
                <span class="badge <?= $badgeClass ?>">
                  期間限定<?= ($days!==null ? " 残り{$days}日" : '') ?>
                </span>
              <?php endif; ?>
            </td>
            <td><?= h($r['category_label_ja']) ?></td>
            <td><?= h($r['unit']) ?></td>
            <td><?= h($r['supplier']) ?></td>
            <td><?= h($expire) ?></td>
            <td class="<?= $qtyClass ?>"><?= $qty ?></td>
            <td class="op-col">
              <div class="op-buttons">
                <?php if ($role === 'mng' || $role === 'fte'): ?>
                  <a class="btn-edit" href="zaiko_edit.php?item_id=<?= (int)$r['item_id'] ?>">編集</a>
                  <a class="btn-order" href="hacchu_form.php?jan=<?= urlencode($r['jan_code']) ?>">発注</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

</body>
</html>
