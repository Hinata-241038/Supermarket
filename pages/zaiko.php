<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

/* =========================
   権限
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
   互換列存在
========================= */
$hasConsume = hasColumn($pdo,'stock','consume_date');
$hasBest    = hasColumn($pdo,'stock','best_before_date');
$hasLegacy  = hasColumn($pdo,'stock','expire_date'); // NOT NULL

/* =========================
   表示モード（4種）: セッション保持
   consume / best / limited / expired
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_view'])) {
  $v = (string)($_POST['view_mode'] ?? 'best');
  $allowed = ['consume','best','limited','expired'];
  $_SESSION['view_mode'] = in_array($v, $allowed, true) ? $v : 'best';

  // 検索条件を保持して戻る
  $q = $_SERVER['QUERY_STRING'] ? ('?'.$_SERVER['QUERY_STRING']) : '';
  header('Location: zaiko.php'.$q);
  exit;
}
$viewMode = $_SESSION['view_mode'] ?? 'best';

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

/* 検索条件（items/categories側） */
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

/* =========================
   4種フィルタ（データそのものを絞る）
========================= */
$today = (new DateTime('today'))->format('Y-m-d');

switch ($viewMode) {
  case 'consume':
    // 消費期限だけ（consume_dateがあるロットのみ）
    if ($hasConsume) {
      $where[] = "s.consume_date IS NOT NULL";
    } else {
      // カラムが無いなら何も出さない（安全）
      $where[] = "1=0";
    }
    break;

  case 'best':
    // 賞味期限だけ（best_before_dateがあるロットのみ）
    if ($hasBest) {
      $where[] = "s.best_before_date IS NOT NULL";
    } else {
      $where[] = "1=0";
    }
    break;

  case 'limited':
    // 期間限定商品だけ（items.is_limited）
    $where[] = "i.is_limited = 1";
    // 期間内だけに絞りたいなら（任意）
    // $where[] = "(i.limited_start IS NULL OR i.limited_start <= :today) AND (i.limited_end IS NULL OR i.limited_end >= :today)";
    // $params[':today'] = $today;
    break;

  case 'expired':
    // 期限切れだけ：consume/bestがあればそれを優先、無ければ互換expire_date
    // COALESCEで「そのロットの期限」を作る
    $where[] = "COALESCE(s.consume_date, s.best_before_date, s.expire_date) < :today";
    $params[':today'] = $today;
    break;
}

/* WHERE組み立て */
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* =========================
   期限表示（列）
   - consume: consume_date
   - best:    best_before_date
   - limited: COALESCE(consume,best,expire)
   - expired: COALESCE(consume,best,expire)
========================= */
$expireExprCommon = "COALESCE(" .
  ($hasConsume ? "s.consume_date" : "NULL") . ", " .
  ($hasBest    ? "s.best_before_date" : "NULL") . ", " .
  ($hasLegacy  ? "s.expire_date" : "NULL") .
")";

if ($viewMode === 'consume') {
  $expireViewExpr = $hasConsume ? "s.consume_date" : "NULL";
} elseif ($viewMode === 'best') {
  $expireViewExpr = $hasBest ? "s.best_before_date" : "NULL";
} else {
  $expireViewExpr = $expireExprCommon;
}

/* =========================
   ロット（stock.id）単位で一覧取得
========================= */
$sql = "
  SELECT
    s.id AS stock_id,
    s.item_id,
    i.jan_code,
    i.item_name,
    c.category_label_ja,
    i.unit,
    i.supplier,
    i.is_limited,
    i.limited_start,
    i.limited_end,
    s.quantity,
    s.consume_date,
    s.best_before_date,
    s.expire_date,
    {$expireViewExpr} AS expire_view,
    {$expireExprCommon} AS expire_common
  FROM stock s
  JOIN items i ON i.id = s.item_id
  LEFT JOIN categories c ON c.id = i.category_id
  {$whereSql}
  ORDER BY i.id DESC, {$expireExprCommon} ASC, s.id DESC
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* フラッシュメッセージ */
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

/* 廃棄可能権限 */
$canDispose = ($role === 'mng' || $role === 'fte');
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

<?php if ($flash): ?>
  <div class="flash"><?= h($flash) ?></div>
<?php endif; ?>

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
    <span class="expire-label">
      <?php
        echo match($viewMode){
          'consume' => '消費期限',
          'best'    => '賞味期限',
          'limited' => '期間限定',
          'expired' => '期限切れ',
          default   => '賞味期限'
        };
      ?>
      表示
    </span>
  </div>

  <!-- 4種切替 -->
  <form method="post" class="view-switch">
    <input type="hidden" name="set_view" value="1">
    <button class="view-btn <?= $viewMode==='consume'?'is-active':'' ?>" type="submit" name="view_mode" value="consume">消費期限</button>
    <button class="view-btn <?= $viewMode==='best'?'is-active':'' ?>" type="submit" name="view_mode" value="best">賞味期限</button>
    <button class="view-btn <?= $viewMode==='limited'?'is-active':'' ?>" type="submit" name="view_mode" value="limited">期間限定</button>
    <button class="view-btn <?= $viewMode==='expired'?'is-active':'' ?>" type="submit" name="view_mode" value="expired">期限切れ</button>
  </form>

  <?php if ($canDispose): ?>
    <!-- ここは「画面遷移」ではなく、チェック→二段階OK→実行 -->
    <button type="button" class="dispose-link" id="btnDispose">廃棄処理</button>
  <?php endif; ?>
</div>

<!-- 廃棄フォーム（チェックボックス送信用） -->
<form method="post" action="zaiko_dispose_execute.php" id="disposeForm">
  <input type="hidden" name="confirm" value="1">

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
          <th>期限</th>
          <th>在庫</th>
          <?php if ($role==='mng' || $role==='fte'): ?><th class="op-col">操作</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?>
          <tr>
            <td colspan="<?= $canDispose ? 9 : (($role==='mng'||$role==='fte')?8:7) ?>" style="padding:18px;">
              該当するデータがありません
            </td>
          </tr>
        <?php else: ?>
          <?php foreach($rows as $r): ?>
            <?php
              $qty = (int)($r['quantity'] ?? 0);

              // 表示期限
              $expire = fmtDate($r['expire_view'] ?? '');

              // 期限切れ判定（行色）
              $expireCommon = fmtDate($r['expire_common'] ?? '');
              $isExpired = ($expireCommon !== '' && $expireCommon < $today);

              // 期間限定バッジ（items.is_limited）
              $isLimited = ((int)($r['is_limited'] ?? 0) === 1);

              $rowClass = $isExpired ? 'row-expired' : '';
            ?>
            <tr class="<?= h($rowClass) ?>">
              <?php if ($canDispose): ?>
                <td class="check-col">
                  <input
                    type="checkbox"
                    class="row-check"
                    name="stock_ids[]"
                    value="<?= (int)$r['stock_id'] ?>"
                    data-jan="<?= h($r['jan_code'] ?? '') ?>"
                    data-name="<?= h($r['item_name'] ?? '') ?>"
                    data-expire="<?= h($expire) ?>"
                    data-qty="<?= (int)$qty ?>"
                  >
                </td>
              <?php endif; ?>

              <td><?= h($r['jan_code'] ?? '') ?></td>
              <td>
                <?= h($r['item_name'] ?? '') ?>
                <?php if ($isLimited): ?>
                  <span class="badge badge-limited">期間限定</span>
                <?php endif; ?>
              </td>
              <td><?= h($r['category_label_ja'] ?? '') ?></td>
              <td><?= h($r['unit'] ?? '') ?></td>
              <td><?= h($r['supplier'] ?? '') ?></td>
              <td><?= h($expire) ?></td>
              <td class="<?= $qty<=0 ? 'stock-zero':'' ?>"><?= $qty ?></td>

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
</form>

<!-- 最終確認モーダル -->
<div class="modal" id="confirmModal" aria-hidden="true">
  <div class="modal-backdrop" id="modalBackdrop"></div>
  <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-head">
      <div class="modal-title" id="modalTitle">廃棄の最終確認</div>
      <button type="button" class="modal-close" id="modalClose">×</button>
    </div>

    <div class="modal-body">
      <div class="modal-lead">
        選択したロットを廃棄します。<b>在庫から削除</b>され、廃棄履歴に記録されます。
      </div>

      <div class="modal-table-wrap">
        <table class="modal-table" id="modalTable">
          <thead>
            <tr>
              <th>JAN</th>
              <th>商品名</th>
              <th>期限</th>
              <th>数量</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div class="modal-warn">
        ※ この操作は取り消せません。
      </div>
    </div>

    <div class="modal-actions">
      <button type="button" class="btn-sub" id="modalCancel">キャンセル</button>
      <button type="button" class="btn-danger" id="modalOk">OK（廃棄確定）</button>
    </div>
  </div>
</div>

<script>
(function(){
  const btnDispose = document.getElementById('btnDispose');
  const form = document.getElementById('disposeForm');

  const modal = document.getElementById('confirmModal');
  const backdrop = document.getElementById('modalBackdrop');
  const closeBtn = document.getElementById('modalClose');
  const cancelBtn = document.getElementById('modalCancel');
  const okBtn = document.getElementById('modalOk');

  const tbody = document.querySelector('#modalTable tbody');

  function getChecked(){
    return Array.from(document.querySelectorAll('.row-check:checked'));
  }

  function openModal(){
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden','false');
  }

  function closeModal(){
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden','true');
    tbody.innerHTML = '';
  }

  function buildModalRows(checked){
    tbody.innerHTML = '';
    checked.forEach(ch => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${ch.dataset.jan || ''}</td>
        <td>${ch.dataset.name || ''}</td>
        <td>${ch.dataset.expire || ''}</td>
        <td style="text-align:right;">${ch.dataset.qty || '0'}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  if (btnDispose){
    btnDispose.addEventListener('click', () => {
      const checked = getChecked();
      if (checked.length === 0){
        alert('廃棄したい商品（ロット）を選択してください。');
        return;
      }

      // ①一次OK（要求の「OKを押す」に相当）
      const firstOk = confirm('選択した商品を廃棄しますか？');
      if (!firstOk) return;

      // ②最終確認モーダル
      buildModalRows(checked);
      openModal();
    });
  }

  backdrop.addEventListener('click', closeModal);
  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);

  okBtn.addEventListener('click', () => {
    // 最終OK → サーバへPOST（廃棄確定）
    form.submit();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
  });
})();
</script>

</body>
</html>
