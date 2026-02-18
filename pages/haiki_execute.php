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
  $st->execute([':c'=>$column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

$hasConsume = hasColumn($pdo,'stock','consume_date');
$hasBest    = hasColumn($pdo,'stock','best_before_date');
$hasLegacy  = hasColumn($pdo,'stock','expire_date');

$view = $_POST['view'] ?? 'best';
$reasonBase = trim($_POST['reason'] ?? '廃棄処理');

/* 互換：単発 stock_id と 複数 stock_ids[] の両対応 */
$stockIds = [];
if (!empty($_POST['stock_ids']) && is_array($_POST['stock_ids'])) {
  $stockIds = $_POST['stock_ids'];
} elseif (!empty($_POST['stock_id'])) {
  $stockIds = [$_POST['stock_id']];
}

$stockIds = array_values(array_unique(array_filter(array_map('intval', $stockIds), fn($v)=>$v>0)));
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

try {
  $pdo->beginTransaction();

  foreach ($stockIds as $id) {
    /* ロットをロックして取得 */
    $st = $pdo->prepare("
      SELECT
        s.id, s.item_id, s.quantity,
        {$expireExpr} AS expire_view
      FROM stock s
      LEFT JOIN items i ON i.id = s.item_id
      WHERE s.id = :id
      FOR UPDATE
    ");
    $st->execute([':id'=>$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) continue;

    $qty = (int)$row['quantity'];
    if ($qty <= 0) continue;

    $exp = $row['expire_view'] ? date('Y-m-d', strtotime($row['expire_view'])) : '';
    $reason = $reasonBase;
    if ($exp !== '') $reason = "{$reasonBase}({$viewLabel}:{$exp})";
    if (mb_strlen($reason) > 50) $reason = mb_substr($reason, 0, 50);

    /* 廃棄履歴へ（あなたのdisposal構造に合わせる） */
    $ins = $pdo->prepare("
      INSERT INTO disposal
        (item_id, disposal_quantity, reason, disposal_date, created_at)
      VALUES
        (:item_id, :qty, :reason, CURDATE(), CURDATE())
    ");
    $ins->execute([
      ':item_id' => (int)$row['item_id'],
      ':qty'     => $qty,
      ':reason'  => $reason
    ]);

    /* 在庫から削除（ロット行ごと） */
    $del = $pdo->prepare("DELETE FROM stock WHERE id = :id");
    $del->execute([':id'=>$id]);
  }

  $pdo->commit();
  header('Location: haiki.php?done=1');
  exit;

} catch (Exception $e) {
  $pdo->rollBack();
  exit('廃棄処理でエラー: ' . $e->getMessage());
}
