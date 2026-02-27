<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

/* 権限（保存もmng/fteのみ） */
if (!isset($_SESSION['role'])) {
  header('Location: logu.php');
  exit;
}
$role = $_SESSION['role'];
if (!in_array($role, ['mng','fte'], true)) {
  exit('権限がありません');
}

function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
  $st->execute([':c'=>$column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

$hasConsume = hasColumn($pdo,'stock','consume_date');
$hasBest    = hasColumn($pdo,'stock','best_before_date');
$hasLegacy  = hasColumn($pdo,'stock','expire_date');

$stock_id = (int)($_POST['stock_id'] ?? 0);
if ($stock_id <= 0) exit('不正なアクセス');

$quantity = (int)($_POST['quantity'] ?? 0);
$consume  = $hasConsume ? trim($_POST['consume_date'] ?? '') : '';
$best     = $hasBest    ? trim($_POST['best_before_date'] ?? '') : '';
$legacyIn = (!$hasBest && $hasLegacy) ? trim($_POST['expire_date'] ?? '') : '';

$consume  = ($consume !== '') ? $consume : null;
$best     = ($best !== '') ? $best : null;
$legacyIn = ($legacyIn !== '') ? $legacyIn : null;

try {
  $pdo->beginTransaction();

  $st = $pdo->prepare("SELECT expire_date FROM stock WHERE id = :id FOR UPDATE");
  $st->execute([':id'=>$stock_id]);
  $cur = $st->fetch(PDO::FETCH_ASSOC);
  if(!$cur) throw new Exception('在庫ロットが存在しません');

  $currentExpire = $cur['expire_date'] ?? null;

  // expire_dateはNOT NULL：優先順位「賞味(best)→消費(consume)→入力legacy→現在値」
  $newExpire = $best ?? $consume ?? $legacyIn ?? $currentExpire;
  if (!$newExpire) throw new Exception('期限日が必須です');

  $sets = ["quantity = :qty"];
  $params = [':qty'=>$quantity, ':id'=>$stock_id];

  if ($hasConsume) { $sets[] = "consume_date = :consume"; $params[':consume'] = $consume; }
  if ($hasBest)    { $sets[] = "best_before_date = :best"; $params[':best'] = $best; }
  if ($hasLegacy)  { $sets[] = "expire_date = :expire"; $params[':expire'] = $newExpire; }

  $sql = "UPDATE stock SET " . implode(', ', $sets) . " WHERE id = :id";
  $up = $pdo->prepare($sql);
  $up->execute($params);

  $pdo->commit();

  header('Location: zaiko.php');
  exit;

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  exit('保存エラー: ' . $e->getMessage());
}