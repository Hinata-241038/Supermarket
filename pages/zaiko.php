<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

/* =========================================================
   1) GETパラメータ（検索 / AND-OR / 期限モード）
   ========================================================= */
$keyword = trim($_GET['keyword'] ?? '');
$searchMode = ($_GET['mode'] ?? 'or') === 'and' ? 'and' : 'or';       // and / or
$expiryMode = ($_GET['expiry'] ?? 'best') === 'consume' ? 'consume' : 'best'; // consume / best

// 表示用：期限モードごとに参照する日付列
// best: best_before_date があればそれ、なければ expire_date（互換）
// consume: consume_date
$dateExprForView = ($expiryMode === 'consume')
  ? "s.consume_date"
  : "COALESCE(s.best_before_date, s.expire_date)";

// 「期限切れ判定」はモードに依存させず、どちらかが切れてたら切れ扱いにする（実務で安全）
$dateExprExpired = "
  (
    (s.consume_date IS NOT NULL AND s.consume_date < CURDATE())
    OR
    (COALESCE(s.best_before_date, s.expire_date) IS NOT NULL
     AND COALESCE(s.best_before_date, s.expire_date) < CURDATE())
  )
";

/* =========================================================
   2) 検索欄の入力制限（サーバ側）
   ひらがな/カタカナ/漢字/英数字/空白のみ許可
   ========================================================= */
$allowPattern = '/^[0-9A-Za-zぁ-ゖァ-ヶー一-龯々\s]*$/u';
$inputError = '';
if ($keyword !== '' && !preg_match($allowPattern, $keyword)) {
  $inputError = '検索欄には「ひらがな・カタカナ・漢字・英数字・空白」だけ入力できます。';
}

/* =========================================================
   3) 廃棄処理（ボタンを押したときだけ）
   - 期限切れ：quantity>0 を disposal に移す（同日同理由の二重登録防止）
   - 在庫切れ：quantity<=0 を disposal に移す（同日同理由の二重登録防止）
   - その後 stock から該当行を削除
   ※ stock は1商品1行運用でも、複数行でも壊れないように "集計してINSERT" する
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
      WHERE {$dateExprExpired}
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

    // stock から削除（期限切れ OR 在庫切れ）
    $sqlDelete = "
      DELETE FROM stock
      WHERE quantity <= 0
         OR {$dateExprExpired}
    ";
    $pdo->exec($sqlDelete);

    $pdo->commit();

    // 二重送信防止
    header('Location: zaiko.php?keyword=' . urlencode($keyword) . '&mode=' . urlencode($searchMode) . '&expiry=' . urlencode($expiryMode));
    exit;
  } catch (Throwable $e) {
    $pdo->rollBack();
    $errorMsg = '廃棄処理でエラー: ' . $e->getMessage();
  }
}

/* =========================================================
   4) 検索条件（空白区切りトークン + AND/OR 切替）
   対象：商品名 / カテゴリ / JAN / 発注先
   ========================================================= */
$params = [];
$whereSql = '1=1';

if ($inputError !== '') {
  // 入力が不正なら “検索結果なし” にする（意図せず全件表示しない）
  $whereSql = '0=1';
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
   - stock は SUM(quantity) で合算
   - 期限は MIN(期限) で最も近い日付を表示
   - 発注ボタンは stock_quantity>0 の時のみ
   ========================================================= */
$sql = "
  SELECT
    i.id AS item_id,
    i.jan_code,
    i.item_name,
    i.unit,
    i.supplier,
    c.category_label_ja,
    IFNULL(SUM(s.quantity), 0) AS stock_quantity,
    MIN({$dateExprForView}) AS nearest_expire
  FROM items i
  LEFT JOIN categories c ON i.category_id = c.id
  LEFT JOIN stock s ON i.id = s.item_id
  WHERE {$whereSql}
  GROUP BY
    i.id, i.jan_code, i.item_name, i.unit, i.supplier, c.category_label_ja
  ORDER BY i.item_name
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 表示用：期限モードの見出し＆切替リンク */
$expireTitle = ($expiryMode === 'consume') ? '消費期限' : '賞味期限';
$nextExpiryMode = ($expiryMode === 'consume') ? 'best' : 'consume';
$nextExpiryLabel = ($expiryMode === 'consume') ? '賞味期限に切替' : '消費期限に切替';

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

<!-- 検索（入力制限 + AND/OR + 期限モード維持） -->
<form method="get" class="search-area" id="searchForm">
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

<!-- 期限モード切替（完全別モード） -->
<div class="toggle-area">
  <a class="toggle-expire-btn"
     href="?keyword=<?= urlencode($keyword) ?>&mode=<?= urlencode($searchMode) ?>&expiry=<?= urlencode($nextExpiryMode) ?>">
     <?= htmlspecialchars($nextExpiryLabel, ENT_QUOTES, 'UTF-8') ?>
  </a>
</div>

<!-- 廃棄処理（押した時だけ移動） -->
<form method="post" class="dispose-area">
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

  // 期限表示（モードで nearest_expire が変わる）
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
    <?php if ($qty > 0): ?>
      <!-- 在庫が0より大きい時だけ表示 -->
      <a class="order-suggest-btn"
         href="hacchu_form.php?jan=<?= urlencode($item['jan_code']) ?>">
         発注
      </a>
    <?php else: ?>
      &nbsp;
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</table>

<script>
/* =========================================================
   検索入力の禁止文字をリアルタイム除去（クライアント側）
   - ひらがな/カタカナ/漢字/英数字/空白のみ許可
   ========================================================= */
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
