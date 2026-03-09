<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

if (!isset($_SESSION['role'])) {
  header('Location: logu.php');
  exit;
}

function h($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
  $st->execute([':c' => $column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

/**
 * 廃棄履歴の自動削除
 * $retentionDays 日より古いデータを削除
 */
function cleanupDisposalHistory(PDO $pdo, int $retentionDays = 90): int {
  $hasCreatedAt   = hasColumn($pdo, 'disposal', 'created_at');
  $hasDisposalDate = hasColumn($pdo, 'disposal', 'disposal_date');

  if (!$hasCreatedAt && !$hasDisposalDate) {
    return 0;
  }

  $baseColumn = $hasCreatedAt ? 'created_at' : 'disposal_date';

  $sql = "
    DELETE FROM disposal
    WHERE {$baseColumn} IS NOT NULL
      AND DATE({$baseColumn}) < (CURDATE() - INTERVAL :days DAY)
  ";

  $st = $pdo->prepare($sql);
  $st->bindValue(':days', $retentionDays, PDO::PARAM_INT);
  $st->execute();

  return $st->rowCount();
}

/* =========================
   設定
========================= */
$retentionDays = 90; // ← ここを変えれば保持期間を調整可能

/* =========================
   古い廃棄履歴を自動削除
========================= */
$deletedCount = 0;
try {
  $deletedCount = cleanupDisposalHistory($pdo, $retentionDays);
} catch (Exception $e) {
  // 履歴表示自体は止めない
  $deletedCount = 0;
}

/* =========================
   廃棄履歴を取得（disposal）
========================= */
$hasExpireDate = hasColumn($pdo, 'disposal', 'expire_date');

$expireSelect = $hasExpireDate ? "d.expire_date" : "NULL AS expire_date";

$sql = "
SELECT
  d.id,
  d.disposal_date,
  d.disposal_quantity,
  d.reason,
  {$expireSelect},
  i.jan_code,
  i.item_name,
  c.category_label_ja
FROM disposal d
LEFT JOIN items i ON i.id = d.item_id
LEFT JOIN categories c ON c.id = i.category_id
ORDER BY d.disposal_date DESC, d.id DESC
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$doneMessage = '';
if (isset($_GET['done']) && $_GET['done'] === '1') {
  $doneMessage = '廃棄処理が完了しました。';
}

$cleanupMessage = '';
if ($deletedCount > 0) {
  $cleanupMessage = "保存期間を過ぎた廃棄履歴を {$deletedCount} 件自動削除しました。";
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>廃棄履歴</title>
<link rel="stylesheet" href="../assets/css/haiki.css">
</head>
<body>

<button class="back-btn" onclick="location.href='home.php'">戻る</button>

<div class="container">
  <h1>廃棄履歴</h1>

  <?php if ($doneMessage !== ''): ?>
    <div class="notice success"><?= h($doneMessage) ?></div>
  <?php endif; ?>

  <?php if ($cleanupMessage !== ''): ?>
    <div class="notice info"><?= h($cleanupMessage) ?></div>
  <?php endif; ?>

  <div class="history-meta">
    <p>保存期間：<?= (int)$retentionDays ?>日</p>
    <p>件数：<?= count($rows) ?>件</p>
  </div>

  <div class="table-card">
    <div class="table-scroll">
      <table class="item-table">
        <thead>
          <tr>
            <th>廃棄日</th>
            <th>JAN</th>
            <th>商品名</th>
            <th>カテゴリ</th>
            <th>数量</th>
            <th>期限</th>
            <th>理由</th>
          </tr>
        </thead>
        <tbody>

        <?php if ($rows): ?>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= h($r['disposal_date']) ?></td>
            <td><?= h($r['jan_code']) ?></td>
            <td><?= h($r['item_name']) ?></td>
            <td><?= h($r['category_label_ja']) ?></td>
            <td><?= (int)$r['disposal_quantity'] ?></td>
            <td><?= h($r['expire_date']) ?></td>
            <td><?= h($r['reason']) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" class="no-data">廃棄履歴はありません</td>
          </tr>
        <?php endif; ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

</body>
</html>