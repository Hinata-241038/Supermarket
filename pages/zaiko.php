<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

/* =========================
   権限チェック（最初に実行）
========================= */
if (!isset($_SESSION['role'])) {
  header('Location: logu.php');
  exit;
}
$role = $_SESSION['role'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
  $st->execute([':c'=>$column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

function fmtDate($d){
  if (!$d) return '';
  return date('Y-m-d', strtotime($d));
}

/* =========================
   stockの列存在チェック（NULL耐性）
========================= */
$hasConsume = hasColumn($pdo,'stock','consume_date');
$hasBest    = hasColumn($pdo,'stock','best_before_date');
$hasLegacy  = hasColumn($pdo,'stock','expire_date'); // 互換

/* =========================
   期限モード（セッション保持）
========================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_expire'])) {
  $cur = $_SESSION['expire_mode'] ?? 'best';
  $_SESSION['expire_mode'] = ($cur === 'consume') ? 'best' : 'consume';

  // 検索条件を保持してリロード
  $q = $_SERVER['QUERY_STRING'] ? ('?'.$_SERVER['QUERY_STRING']) : '';
  header('Location: zaiko.php'.$q);
  exit;
}
$expireMode = $_SESSION['expire_mode'] ?? 'best';

/* =========================
   検索 AND/OR
========================= */
$keyword    = trim($_GET['keyword'] ?? '');
$searchMode = (($_GET['mode'] ?? 'or') === 'and') ? 'and' : 'or';

$terms = [];
if ($keyword !== '') {
  $kw = preg_replace('/\s+/u', ' ', $keyword);
  $terms = array_values(array_filter(explode(' ', $kw), fn($v)=>$v!==''));
}

$where = [];
$params = [];
if (!empty($terms)) {
  $pieces = [];
  foreach ($terms as $i => $t) {
    $p = ":t{$i}";
    $params[$p] = "%{$t}%";
    $pieces[] = "(i.jan_code LIKE {$p}
              OR i.item_name LIKE {$p}
              OR i.supplier LIKE {$p}
              OR c.category_label_ja LIKE {$p})";
  }
  $glue = ($searchMode === 'and') ? ' AND ' : ' OR ';
  $where[] = '(' . implode($glue, $pieces) . ')';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* =========================
   期限表示（最短期限 = MIN）
   モードにより表示対象を切替
========================= */
$consumeExpr = $hasConsume ? "MIN(s.consume_date)" : "NULL";
$bestExpr    = $hasBest    ? "MIN(s.best_before_date)" : "NULL";
$legacyExpr  = $hasLegacy  ? "MIN(s.expire_date)" : "NULL";

if ($expireMode === 'consume' && $hasConsume) {
  $expireViewExpr = $consumeExpr;
} else {
  // 賞味期限モード：best_before_date があればそれ、なければ expire_date（互換）
  $expireViewExpr = $hasBest ? $bestExpr : $legacyExpr;
}

/* =========================
   一覧取得
========================= */
$sql = "
  SELECT
    i.id AS item_id,
    i.jan_code,
    i.item_name,
    i.unit,
    i.supplier,
    c.category_label_ja,
    IFNULL(SUM(s.quantity),0) AS stock_qty,
    {$expireViewExpr} AS expire_view
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

    <button class="search-btn" type="submit" aria-label="検索">🔍</button>

    <div class="search-mode">
      <label><input type="radio" name="mode" value="and" <?= $searchMode==='and'?'checked':'' ?>> AND</label>
      <label><input type="radio" name="mode" value="or"  <?= $searchMode==='or'?'checked':''  ?>> OR</label>
    </div>
  </form>
</div>

<div class="right-actions">
  <div class="expire-status">
    現在：
    <span class="expire-label"><?= $expireMode==='consume' ? '消費期限モード' : '賞味期限モード' ?></span>
  </div>

  <form method="post" class="expire-switch-form">
    <button type="submit" name="toggle_expire" value="1" class="toggle-expire-btn">
      <?= $expireMode==='consume' ? '賞味期限に切替' : '消費期限に切替' ?>
    </button>
  </form>

  <?php if ($role === 'mng' || $role === 'fte'): ?>
    <a class="dispose-link" href="haiki.php">廃棄処理</a>
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
        <th>期限</th>
        <th>在庫</th>
        <?php if ($role==='mng' || $role==='fte'): ?>
          <th class="op-col">操作</th>
        <?php endif; ?>
      </tr>
    </thead>

    <tbody>
      <?php if(!$rows): ?>
        <tr>
          <td colspan="<?= ($role==='mng'||$role==='fte') ? 8 : 7 ?>" style="padding:18px;">
            該当するデータがありません
          </td>
        </tr>
      <?php else: ?>

        <?php foreach($rows as $r): ?>
          <?php
            $expire = fmtDate($r['expire_view'] ?? '');
            $qty = (int)($r['stock_qty'] ?? 0);     // ★未定義を根絶
            $qtyClass = ($qty <= 0) ? 'stock-zero' : '';
          ?>
          <tr>
            <td><?= h($r['jan_code'] ?? '') ?></td>
            <td><?= h($r['item_name'] ?? '') ?></td>
            <td><?= h($r['category_label_ja'] ?? '') ?></td>
            <td><?= h($r['unit'] ?? '') ?></td>
            <td><?= h($r['supplier'] ?? '') ?></td>
            <td><?= h($expire) ?></td>
            <td class="<?= h($qtyClass) ?>"><?= $qty ?></td>

            <?php if ($role==='mng' || $role==='fte'): ?>
              <td class="op-col">
                <div class="op-buttons">
                  <a class="btn-edit" href="zaiko_edit.php?item_id=<?= (int)$r['item_id'] ?>">編集</a>
                  <a class="btn-order" href="hacchu_form.php?jan=<?= urlencode((string)($r['jan_code'] ?? '')) ?>">発注</a>
                </div>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>

      <?php endif; ?>
    </tbody>
  </table>
</div>

</body>
</html>
