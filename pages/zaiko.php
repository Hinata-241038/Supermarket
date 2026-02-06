<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

/* =========================================================
<<<<<<< HEAD
  共通：カラム存在チェック（列が無くても落ちないようにする）
=======
  共通：カラム存在チェック
>>>>>>> add-stockpage
========================================================= */
function hasColumn(PDO $pdo, string $table, string $column): bool
{
  $sql = "SHOW COLUMNS FROM {$table} LIKE :col";
  $st = $pdo->prepare($sql);
  $st->execute([':col' => $column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}
<<<<<<< HEAD

$hasConsume = hasColumn($pdo, 'stock', 'consume_date');
$hasBest    = hasColumn($pdo, 'stock', 'best_before_date');
$hasLegacy  = hasColumn($pdo, 'stock', 'expire_date'); // 互換

/* =========================================================
  1) GET（検索 / AND-OR / 期限モード）
========================================================= */
$keyword    = trim($_GET['keyword'] ?? '');
$searchMode = ($_GET['mode'] ?? 'or') === 'and' ? 'and' : 'or';         // and / or
$expiryMode = ($_GET['expiry'] ?? 'best') === 'consume' ? 'consume' : 'best'; // consume / best

// 賞味期限モード：best_before_date があればそれ、なければ expire_date
$bestExpr = $hasBest
  ? ($hasLegacy ? "COALESCE(s.best_before_date, s.expire_date)" : "s.best_before_date")
  : ($hasLegacy ? "s.expire_date" : "NULL");

// 消費期限モード：consume_date（無ければNULL）
$consumeExpr = $hasConsume ? "s.consume_date" : "NULL";

// 表示用の期限（モードで切替）
$dateExprForView = ($expiryMode === 'consume') ? $consumeExpr : $bestExpr;

// 期限切れ判定：消費/賞味のどちらかが切れていたら「期限切れ扱い」(安全運用)
// ★ 期限切れ判定（安全版：consume_date を一切使わない）
$expiredExpr = "
(
  ({$bestExpr} IS NOT NULL AND {$bestExpr} < CURDATE())
)
";


/* =========================================================
  2) 検索入力の許可文字（サーバ側）
  ひらがな/カタカナ/漢字/英数字/空白のみ許可
========================================================= */
$allowPattern = '/^[0-9A-Za-zぁ-ゖァ-ヶー一-龯々\s]*$/u';
$inputError = '';
if ($keyword !== '' && !preg_match($allowPattern, $keyword)) {
  $inputError = '検索欄には「ひらがな・カタカナ・漢字・英数字・空白」だけ入力できます。';
}

/* =========================================================
  3) 廃棄処理（ボタン押下時だけ）
  - 期限切れ：在庫>0 を disposalへ（同日同理由の二重登録防止）
  - 在庫切れ：在庫<=0 を disposalへ（同日同理由の二重登録防止）
  - 最後に stock から削除
========================================================= */
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_disposal'])) {
  try {
    $pdo->beginTransaction();

    // 期限切れ（商品単位で集計して1回だけINSERT）
    $sqlExpire = "
      INSERT INTO disposal (item_id, disposal_quantity, reason, disposal_date, created_at)
      SELECT
        s.item_id,
        SUM(CASE WHEN s.quantity > 0 THEN s.quantity ELSE 0 END) AS disposal_quantity,
        '期限切れ' AS reason,
        CURDATE() AS disposal_date,
        CURDATE() AS created_at
      FROM stock s
      WHERE {$expiredExpr}
      GROUP BY s.item_id
      HAVING disposal_quantity > 0
         AND NOT EXISTS (
           SELECT 1 FROM disposal d
           WHERE d.item_id = s.item_id
             AND d.reason = '期限切れ'
             AND d.disposal_date = CURDATE()
         )
    ";
    $pdo->exec($sqlExpire);

    // 在庫切れ（商品単位で1回だけINSERT）
    $sqlZero = "
      INSERT INTO disposal (item_id, disposal_quantity, reason, disposal_date, created_at)
      SELECT
        s.item_id,
        0 AS disposal_quantity,
        '在庫切れ' AS reason,
        CURDATE() AS disposal_date,
        CURDATE() AS created_at
      FROM stock s
      WHERE s.quantity <= 0
      GROUP BY s.item_id
      HAVING NOT EXISTS (
        SELECT 1 FROM disposal d
        WHERE d.item_id = s.item_id
          AND d.reason = '在庫切れ'
          AND d.disposal_date = CURDATE()
      )
    ";
    $pdo->exec($sqlZero);

    // stock から削除（期限切れ or 在庫切れ）
    $sqlDelete = "
      DELETE FROM stock
      WHERE quantity <= 0
         OR {$expiredExpr}
    ";
    $pdo->exec($sqlDelete);

    $pdo->commit();

    // 二重送信防止：GETに戻す
    header('Location: zaiko.php?keyword=' . urlencode($keyword) . '&mode=' . urlencode($searchMode) . '&expiry=' . urlencode($expiryMode));
    exit;
  } catch (Throwable $e) {
    $pdo->rollBack();
    $errorMsg = '廃棄処理でエラー: ' . $e->getMessage();
  }
}

/* =========================================================
  4) AND/OR検索（空白区切り）
  対象：商品名/カテゴリ/JAN/発注先
========================================================= */
$params = [];
$whereSql = '1=1';

if ($inputError !== '') {
  $whereSql = '0=1'; // 不正入力なら全件表示しない
} else {
  $tokens = [];
  if ($keyword !== '') {
    $tokens = preg_split('/\s+/', $keyword);
    $tokens = array_values(array_filter($tokens, fn($t) => $t !== ''));
  }

  if (!empty($tokens)) {
    $parts = [];
    foreach ($tokens as $i => $t) {
      $ph = ":t{$i}";
      $params[$ph] = "%{$t}%";
      $parts[] = "(
        i.item_name LIKE {$ph}
        OR c.category_label_ja LIKE {$ph}
        OR i.jan_code LIKE {$ph}
        OR i.supplier LIKE {$ph}
      )";
    }
    $glue = ($searchMode === 'and') ? ' AND ' : ' OR ';
    $whereSql = '(' . implode($glue, $parts) . ')';
  }
}

/* =========================================================
  5) 表示データ（重複防止：1商品=1行）
  - stock 合計 SUM(quantity)
  - 表示期限は MIN(期限)
  - 期限切れフラグは MAX(期限切れ判定)
========================================================= */
$sql = "
  SELECT
    i.id AS item_id,
    i.jan_code,
    i.item_name,
    i.unit,
    i.supplier,
    c.category_label_ja,
    i.price,
    IFNULL(SUM(s.quantity), 0) AS stock_quantity,
    MIN({$dateExprForView}) AS nearest_expire,
    MAX(CASE WHEN {$expiredExpr} THEN 1 ELSE 0 END) AS is_expired
  FROM items i
  LEFT JOIN categories c ON i.category_id = c.id
  LEFT JOIN stock s ON i.id = s.item_id
  WHERE {$whereSql}
  GROUP BY i.id, i.jan_code, i.item_name, i.unit, i.supplier, c.category_label_ja, i.price
  ORDER BY i.item_name
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 期限切替ボタン */
$expireTitle     = ($expiryMode === 'consume') ? '消費期限' : '賞味期限';
$nextExpiryMode  = ($expiryMode === 'consume') ? 'best' : 'consume';
$nextExpiryLabel = ($expiryMode === 'consume') ? '賞味期限に切替' : '消費期限に切替';

$today = new DateTime('today');
$soon  = (new DateTime('today'))->modify('+7 days');
=======

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
>>>>>>> add-stockpage
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

<<<<<<< HEAD
<!-- 検索 -->
<form method="get" class="search-area">
  <input
    type="text"
    name="keyword"
    class="search-box"
    id="keywordInput"
    placeholder="商品名 / カテゴリ / JAN / 発注先（空白区切り可）"
    value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"
  >

  <div class="search-mode">
    <label><input type="radio" name="mode" value="and" <?= $searchMode === 'and' ? 'checked' : '' ?>> AND</label>
    <label><input type="radio" name="mode" value="or"  <?= $searchMode === 'or'  ? 'checked' : '' ?>> OR</label>
  </div>

  <input type="hidden" name="expiry" value="<?= htmlspecialchars($expiryMode, ENT_QUOTES, 'UTF-8') ?>">
  <button class="search-btn" type="submit">🔍</button>
</form>

<?php if ($inputError !== ''): ?>
  <p class="error-msg"><?= htmlspecialchars($inputError, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($errorMsg !== ''): ?>
  <p class="error-msg"><?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<!-- 右側ボタン群 -->
<div class="right-actions">
  <a class="toggle-expire-btn"
     href="?keyword=<?= urlencode($keyword) ?>&mode=<?= urlencode($searchMode) ?>&expiry=<?= urlencode($nextExpiryMode) ?>">
     <?= htmlspecialchars($nextExpiryLabel, ENT_QUOTES, 'UTF-8') ?>
  </a>

  <form method="post" class="dispose-form">
    <button
      type="submit"
      name="do_disposal"
      value="1"
      class="dispose-btn"
      onclick="return confirm('期限切れ・在庫切れ商品を廃棄処理します。よろしいですか？')"
    >
      廃棄処理
    </button>
  </form>
</div>

<table class="item-table">
<tr>
  <th>JAN</th>
  <th>商品名</th>
  <th>カテゴリ</th>
  <th>単位</th>
  <th>発注先</th>
  <th><?= htmlspecialchars($expireTitle, ENT_QUOTES, 'UTF-8') ?></th>
  <th>在庫</th>
  <th>操作</th>
</tr>

<?php foreach ($items as $item): ?>
<?php
  $qty = (int)$item['stock_quantity'];
  $isExpired = ((int)$item['is_expired'] === 1);

  // 期限表示（モードの列で表示）
  $expireLabel = '-';
  $expireClass = '';
  $rowClass = '';

  if (!empty($item['nearest_expire'])) {
    $exp = new DateTime($item['nearest_expire']);
    if ($exp < $today) {
      $expireLabel = '⚠ 期限切れ';
      $expireClass = 'expire-over';
      $rowClass = 'row-expire-over';
    } elseif ($exp <= $soon) {
      $expireLabel = '⚠ 期限間近';
      $expireClass = 'expire-soon';
      $rowClass = 'row-expire-soon';
    } else {
      $expireLabel = $exp->format('Y-m-d');
    }
  }

  // 発注ボタン表示条件（要件優先：0のとき空白）
  $showOrder = ($qty > 0); // 期限切れでも在庫>0なら表示される
?>
<tr class="<?= $rowClass ?>">
  <td><?= htmlspecialchars($item['jan_code'], ENT_QUOTES, 'UTF-8') ?></td>
  <td><?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?></td>
  <td><?= htmlspecialchars($item['category_label_ja'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
  <td><?= htmlspecialchars($item['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
  <td><?= htmlspecialchars($item['supplier'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>

  <td class="<?= $expireClass ?>"><?= htmlspecialchars($expireLabel, ENT_QUOTES, 'UTF-8') ?></td>

  <td class="<?= $qty <= 0 ? 'stock-zero' : '' ?>"><?= $qty ?></td>

  <td>
    <div class="op-buttons">
      <?php if ($showOrder): ?>
        <!-- 発注：発注画面へ（JAN渡す → 発注画面で item_id/単価/カテゴリ/単位/商品名/発注先 を自動反映） -->
        <a class="btn-order" href="hacchu_form.php?jan=<?= urlencode($item['jan_code']) ?>">発注</a>
      <?php else: ?>
        <span class="btn-blank">&nbsp;</span>
      <?php endif; ?>

      <!-- 編集：在庫・商品情報を編集 -->
      <a class="btn-edit" href="zaiko_edit.php?item_id=<?= (int)$item['item_id'] ?>">編集</a>
    </div>
  </td>
</tr>
<?php endforeach; ?>
=======
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
>>>>>>> add-stockpage
</table>

<script>
/* 検索欄：許可文字以外をリアルタイム除去 */
(function(){
  const input = document.getElementById('keywordInput');
  if (!input) return;

  const allow = /[0-9A-Za-zぁ-ゖァ-ヶー一-龯々\s]/u;

  input.addEventListener('input', () => {
    const s = input.value;
    let out = '';
    for (const ch of s) {
      if (allow.test(ch)) out += ch;
    }
    if (out !== s) input.value = out;
  });
})();
</script>

</body>
</html>
