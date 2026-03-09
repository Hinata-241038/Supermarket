<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

if (!isset($_SESSION['role'])) {
  header('Location: logu.php');
  exit;
}
$role = $_SESSION['role'];
if (!($role === 'mng' || $role === 'fte')) exit('権限がありません');

function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
  $st->execute([':c' => $column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

/**
 * 廃棄履歴の自動削除
 */
function cleanupDisposalHistory(PDO $pdo, int $retentionDays = 90): int {
  $hasCreatedAt    = hasColumn($pdo, 'disposal', 'created_at');
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

$hasConsume = hasColumn($pdo,'stock','consume_date');
$hasBest    = hasColumn($pdo,'stock','best_before_date');
$hasLegacy  = hasColumn($pdo,'stock','expire_date');

$hasDisposalExpire   = hasColumn($pdo,'disposal','expire_date');
$hasDisposalCreated  = hasColumn($pdo,'disposal','created_at');

$view = $_POST['view'] ?? 'best';
$reasonBase = trim($_POST['reason'] ?? '廃棄処理');

/* 互換：単発 stock_id と 複数 stock_ids[] の両対応 */
$stockIds = [];
if (!empty($_POST['stock_ids']) && is_array($_POST['stock_ids'])) {
  $stockIds = $_POST['stock_ids'];
} elseif (!empty($_POST['stock_id'])) {
  $stockIds = [$_POST['stock_id']];
}

$stockIds = array_values(array_unique(array_filter(array_map('intval', $stockIds), fn($v) => $v > 0)));
if (!$stockIds) exit('廃棄対象が選択されていません');

$viewLabel = ($view === 'consume') ? '消費' : (($view === 'limited') ? '限定' : '賞味');

/* expireを理由に埋め込む用（50文字制限のため短く） */
$expireExpr = "COALESCE(" .
  ($hasConsume ? "s.consume_date," : "") .
  ($hasBest    ? "s.best_before_date," : "") .
  ($hasLegacy  ? "s.expire_date" : "NULL") .
")";

if ($view === 'consume') $expireExpr = $hasConsume ? "s.consume_date" : "NULL";
if ($view === 'limited') $expireExpr = "i.limited_end";
if ($view === 'best')    $expireExpr = $hasBest ? "COALESCE(s.best_before_date, s.expire_date)" : "s.expire_date";

$retentionDays = 90; // ← 保持期間

try {
  $pdo->beginTransaction();

  /* 先に古い履歴を掃除 */
  cleanupDisposalHistory($pdo, $retentionDays);

  foreach ($stockIds as $id) {
    /* ロットをロックして取得 */
    $st = $pdo->prepare("
      SELECT
        s.id,
        s.item_id,
        s.quantity,
        {$expireExpr} AS expire_view
      FROM stock s
      LEFT JOIN items i ON i.id = s.item_id
      WHERE s.id = :id
      FOR UPDATE
    ");
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) continue;

    $qty = (int)$row['quantity'];
    if ($qty <= 0) continue;

    $exp = $row['expire_view'] ? date('Y-m-d', strtotime($row['expire_view'])) : '';
    $reason = $reasonBase;
    if ($exp !== '') {
      $reason = "{$reasonBase}({$viewLabel}:{$exp})";
    }
    if (mb_strlen($reason) > 50) {
      $reason = mb_substr($reason, 0, 50);
    }

    /* disposal INSERT文を可変対応 */
    $insertColumns = ['item_id', 'disposal_quantity', 'reason', 'disposal_date'];
    $insertValues  = [':item_id', ':qty', ':reason', 'CURDATE()'];

    $params = [
      ':item_id' => (int)$row['item_id'],
      ':qty'     => $qty,
      ':reason'  => $reason
    ];

    if ($hasDisposalExpire) {
      $insertColumns[] = 'expire_date';
      $insertValues[]  = ':expire_date';
      $params[':expire_date'] = ($exp !== '') ? $exp : null;
    }

    if ($hasDisposalCreated) {
      $insertColumns[] = 'created_at';
      $insertValues[]  = 'NOW()';
    }

    $insSql = "
      INSERT INTO disposal
        (" . implode(', ', $insertColumns) . ")
      VALUES
        (" . implode(', ', $insertValues) . ")
    ";

    $ins = $pdo->prepare($insSql);
    $ins->execute($params);

    /* 在庫から削除（ロット行ごと） */
    $del = $pdo->prepare("DELETE FROM stock WHERE id = :id");
    $del->execute([':id' => $id]);
  }

  $pdo->commit();
  header('Location: haiki.php?done=1');
  exit;

} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  exit('廃棄処理でエラー: ' . $e->getMessage());
}