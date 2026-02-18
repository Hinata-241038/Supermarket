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
$canDispose = ($role === 'mng' || $role === 'fte');

/* =========================
   共通関数
========================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
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
  $d1 = new DateTime('today');
  $d2 = new DateTime($end);
  return (int)$d1->diff($d2)->format('%r%a');
}

/* =========================
   カラム確認（落ちないため）
========================= */
$hasConsume = hasColumn($pdo,'stock','consume_date');
$hasBest    = hasColumn($pdo,'stock','best_before_date');
$hasLegacy  = hasColumn($pdo,'stock','expire_date'); // あなたのDBでは必須列

/* =========================
   表示モード（プルダウン）
   best / consume / limited / expired
========================= */
$view = $_GET['view'] ?? 'best';

$expireColumnLabel = '賞味期限';
$expireExpr = '';   // SELECT で expire_view として返す式
$extraWhere = '';   // view固有のWHERE

if ($view === 'consume') {
  $expireColumnLabel = '消費期限';
  if ($hasConsume) {
    $expireExpr = "s.consume_date";
    $extraWhere = " AND s.consume_date IS NOT NULL";
  } else {
    // consume列がないなら何も出さない
    $expireExpr = "NULL";
    $extraWhere = " AND 1=0";
  }
}
elseif ($view === 'limited') {
  $expireColumnLabel = '販売終了日';
  $expireExpr = "i.limited_end";
  $extraWhere = " AND i.is_limited = 1 AND (i.limited_end IS NULL OR i.limited_end >= CURDATE())";
}
elseif ($view === 'expired') {
  $expireColumnLabel = '期限';
  // 期限切れ判定は「消費→賞味→expire」の優先（OKもらった仕様）
  $expireExpr = "COALESCE(" .
      ($hasConsume ? "s.consume_date," : "") .
      ($hasBest    ? "s.best_before_date," : "") .
      ($hasLegacy  ? "s.expire_date" : "NULL") .
    ")";
  $extraWhere = " AND {$expireExpr} < CURDATE()";
}
else { // best
  $expireColumnLabel = '賞味期限';
  // 表示は best_before_date 優先、なければ expire_date
  if ($hasBest) {
    $expireExpr = "COALESCE(s.best_before_date, s.expire_date)";
  } else {
    $expireExpr = ($hasLegacy ? "s.expire_date" : "NULL");
  }
  $extraWhere = " AND {$expireExpr} IS NOT NULL";
}

/* =========================
   検索 AND/OR
========================= */
$keyword    = trim($_GET['keyword'] ?? '');
$searchMode = (($_GET['mode'] ?? 'or') === 'and') ? 'and' : 'or';

$where = [];
$params = [];

/* 在庫がある行だけ出す（必要なら >=0 に変更可） */
$where[] = "s.quantity > 0";

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

$whereSql = 'WHERE ' . implode(' AND ', $where) . $extraWhere;

/* =========================
   SQL（ロット単位表示）
========================= */
$sql = "
SELECT
  s.id AS stock_id,
  i.id AS item_id,
  i.jan_code,
  i.item_name,
  i.unit,
  i.supplier,
  c.category_label_ja,
  i.is_limited,
  i.limited_end,
  s.quantity,
  {$expireExpr} AS expire_view
FROM stock s
LEFT JOIN items i ON s.item_id = i.id
LEFT JOIN categories c ON c.id = i.category_id
{$whereSql}
ORDER BY
  expire_view ASC,
  i.id DESC,
  s.id DESC
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
      <label><input type="radio" name="mode" value="or"  <?= $searchMode==='or'?'checked':'' ?>> OR</label>
    </div>

    <select name="view" class="view-select" onchange="this.form.submit()">
      <option value="best"   <?= $view==='best'?'selected':'' ?>>賞味期限</option>
      <option value="consume"<?= $view==='consume'?'selected':'' ?>>消費期限</option>
      <option value="limited"<?= $view==='limited'?'selected':'' ?>>期間限定</option>
      <option value="expired"<?= $view==='expired'?'selected':'' ?>>期限切れ</option>
    </select>
  </form>
</div>

<?php if ($canDispose): ?>
<form method="post" action="haiki_confirm.php" class="dispose-bar">
  <input type="hidden" name="view" value="<?= h($view) ?>">
  <button type="submit" class="dispose-btn">廃棄処理（選択したロットを廃棄）</button>
  <span class="dispose-hint">※廃棄したい行にチェック → このボタン</span>
<?php endif; ?>

<div class="table-wrap">
  <table class="item-table">
    <thead>
      <tr>
        <?php if ($canDispose): ?><th class="check-col">選択</th><?php endif; ?>
        <th>JAN</th>
        <th>商品名</th>
        <th>カテゴリ</th>
        <th>単位</th>
        <th>発注先</th>
        <th><?= h($expireColumnLabel) ?></th>
        <th>在庫</th>
        <?php if ($role==='mng' || $role==='fte'): ?><th class="op-col">操作</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if(!$rows): ?>
        <tr><td colspan="<?= $canDispose ? 9 : (($role==='mng'||$role==='fte')?8:7) ?>" style="padding:18px;">該当するデータがありません</td></tr>
      <?php else: ?>
        <?php foreach($rows as $r): ?>
          <?php
            $expire = fmtDate($r['expire_view'] ?? '');
            $qty = (int)$r['quantity'];

            $rowClass = '';
            if ($view === 'expired') $rowClass = 'row-expired';

            $badgeHtml = '';
            if ((int)$r['is_limited'] === 1) {
              $days = remainingDays($r['limited_end']);
              $badgeClass = 'badge-limited';
              if($days !== null && $days <= 3) $badgeClass='badge-danger';
              elseif($days !== null && $days <= 7) $badgeClass='badge-warning';
              $badgeHtml = '<span class="badge '.$badgeClass.'">期間限定'.($days!==null ? " 残り{$days}日" : '').'</span>';
            }
          ?>
          <tr class="<?= h($rowClass) ?>">
            <?php if ($canDispose): ?>
              <td class="check-col">
                <input type="checkbox" name="stock_ids[]" value="<?= (int)$r['stock_id'] ?>">
              </td>
            <?php endif; ?>
            <td><?= h($r['jan_code']) ?></td>
            <td><?= h($r['item_name']) ?> <?= $badgeHtml ?></td>
            <td><?= h($r['category_label_ja']) ?></td>
            <td><?= h($r['unit']) ?></td>
            <td><?= h($r['supplier']) ?></td>
            <td><?= h($expire) ?></td>
            <td><?= $qty ?></td>

            <?php if ($role==='mng' || $role==='fte'): ?>
            <td class="op-col">
              <div class="op-buttons">
                <!-- ★編集はロット単位（stock_id） -->
                <a class="btn-edit" href="zaiko_edit.php?stock_id=<?= (int)$r['stock_id'] ?>">編集</a>
                <a class="btn-order" href="hacchu_form.php?jan=<?= urlencode($r['jan_code']) ?>">発注</a>
              </div>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($canDispose): ?>
</form>
<?php endif; ?>

</body>
</html>
