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
  $st->execute([':c' => $column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

$hasConsume   = hasColumn($pdo, 'stock', 'consume_date');
$hasBest      = hasColumn($pdo, 'stock', 'best_before_date');
$hasLegacy    = hasColumn($pdo, 'stock', 'expire_date');

$hasCategory  = hasColumn($pdo, 'items', 'category_id');
$hasSupplier  = hasColumn($pdo, 'items', 'supplier');
$hasLimited   = hasColumn($pdo, 'items', 'is_limited');

$stock_id = (int)($_POST['stock_id'] ?? 0);
$item_id  = (int)($_POST['item_id'] ?? 0);

if ($stock_id <= 0 || $item_id <= 0) {
  exit('不正なアクセス');
}

/* ===== 入力値 ===== */
$quantity   = (int)($_POST['quantity'] ?? 0);
$categoryId = $hasCategory ? (int)($_POST['category_id'] ?? 0) : 0;
$supplier   = $hasSupplier ? trim($_POST['supplier'] ?? '') : '';
$isLimited  = $hasLimited ? (isset($_POST['is_limited']) ? 1 : 0) : 0;

$consume  = $hasConsume ? trim($_POST['consume_date'] ?? '') : '';
$best     = $hasBest ? trim($_POST['best_before_date'] ?? '') : '';
$legacyIn = (!$hasBest && $hasLegacy) ? trim($_POST['expire_date'] ?? '') : '';

$consume  = ($consume !== '') ? $consume : null;
$best     = ($best !== '') ? $best : null;
$legacyIn = ($legacyIn !== '') ? $legacyIn : null;

if ($quantity < 0) {
  exit('在庫数は0以上で入力してください');
}

try {
  $pdo->beginTransaction();

  /* ===== stock確認・ロック ===== */
  $st = $pdo->prepare("
    SELECT s.id, s.item_id, s.expire_date
    FROM stock s
    WHERE s.id = :id
    FOR UPDATE
  ");
  $st->execute([':id' => $stock_id]);
  $cur = $st->fetch(PDO::FETCH_ASSOC);

  if (!$cur) {
    throw new Exception('在庫ロットが存在しません');
  }

  if ((int)$cur['item_id'] !== $item_id) {
    throw new Exception('商品情報の整合性が取れません');
  }

  $currentExpire = $cur['expire_date'] ?? null;

  /* ===== expire_dateは後方互換のため維持 =====
     優先順位：賞味(best) → 消費(consume) → legacy入力 → 現在値
  */
  $newExpire = $best ?? $consume ?? $legacyIn ?? $currentExpire;
  if (!$newExpire) {
    throw new Exception('期限日が必須です');
  }

  /* ===== stock更新 ===== */
  $stockSets = ["quantity = :qty"];
  $stockParams = [
    ':qty' => $quantity,
    ':sid' => $stock_id
  ];

  if ($hasConsume) {
    $stockSets[] = "consume_date = :consume";
    $stockParams[':consume'] = $consume;
  }
  if ($hasBest) {
    $stockSets[] = "best_before_date = :best";
    $stockParams[':best'] = $best;
  }
  if ($hasLegacy) {
    $stockSets[] = "expire_date = :expire";
    $stockParams[':expire'] = $newExpire;
  }

  $sqlStock = "UPDATE stock SET " . implode(', ', $stockSets) . " WHERE id = :sid";
  $upStock = $pdo->prepare($sqlStock);
  $upStock->execute($stockParams);

  /* ===== items更新（カテゴリ・発注先・期間限定） ===== */
  $itemSets = [];
  $itemParams = [':iid' => $item_id];

  if ($hasCategory) {
    $itemSets[] = "category_id = :category_id";
    $itemParams[':category_id'] = ($categoryId > 0) ? $categoryId : null;
  }

  if ($hasSupplier) {
    $itemSets[] = "supplier = :supplier";
    $itemParams[':supplier'] = ($supplier !== '') ? $supplier : null;
  }

  if ($hasLimited) {
    $itemSets[] = "is_limited = :is_limited";
    $itemParams[':is_limited'] = $isLimited;
  }

  if (!empty($itemSets)) {
    $sqlItem = "UPDATE items SET " . implode(', ', $itemSets) . " WHERE id = :iid";
    $upItem = $pdo->prepare($sqlItem);
    $upItem->execute($itemParams);
  }

  $pdo->commit();

  header('Location: zaiko.php');
  exit;

} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  exit('保存エラー: ' . $e->getMessage());
}