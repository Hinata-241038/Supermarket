<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

/* 権限 */
if (!isset($_SESSION['role'])) {
  header('Location: logu.php');
  exit;
}
$role = $_SESSION['role'];
if (!in_array($role, ['mng','fte'], true)) {
  exit('権限がありません');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  exit('不正なアクセス');
}

$stockIds = $_POST['stock_ids'] ?? [];
if (!is_array($stockIds) || count($stockIds) === 0) {
  $_SESSION['flash'] = '廃棄対象が選択されていません。';
  header('Location: zaiko.php');
  exit;
}

/* 数値だけにする & 重複排除 */
$stockIds = array_values(array_unique(array_map('intval', $stockIds)));
$stockIds = array_filter($stockIds, fn($v)=>$v>0);
if (!$stockIds) {
  $_SESSION['flash'] = '廃棄対象が不正です。';
  header('Location: zaiko.php');
  exit;
}

try {
  $pdo->beginTransaction();

  // 1件ずつロックして処理（安全優先）
  $sel = $pdo->prepare("
    SELECT
      s.id AS stock_id,
      s.item_id,
      s.quantity,
      COALESCE(s.consume_date, s.best_before_date, s.expire_date) AS expire_common
    FROM stock s
    WHERE s.id = :id
    FOR UPDATE
  ");

  $ins = $pdo->prepare("
    INSERT INTO disposal
      (stock_id, expire_date, item_id, disposal_quantity, reason, disposal_date, created_at)
    VALUES
      (:stock_id, :expire_date, :item_id, :qty, :reason, CURDATE(), CURDATE())
  ");

  $del = $pdo->prepare("DELETE FROM stock WHERE id = :id");

  $reason = '手動廃棄'; // 入力させない（固定）

  $done = 0;

  foreach ($stockIds as $sid) {
    $sel->execute([':id' => $sid]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      // 既に誰かが消した等 → スキップ（全体は止めない）
      continue;
    }

    $qty = (int)($row['quantity'] ?? 0);
    // 数量0以下を廃棄してもいいが、通常は0はスキップ（好み）
    if ($qty <= 0) {
      // 0は履歴不要ならスキップ。必要なら消す。
      // continue;
    }

    $ins->execute([
      ':stock_id'   => (int)$row['stock_id'],
      ':expire_date'=> $row['expire_common'] ?: null,
      ':item_id'    => (int)$row['item_id'],
      ':qty'        => $qty,
      ':reason'     => $reason,
    ]);

    $del->execute([':id' => (int)$row['stock_id']]);

    $done++;
  }

  $pdo->commit();

  $_SESSION['flash'] = "廃棄処理が完了しました（{$done}件）。";
  header('Location: zaiko.php');
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  exit('廃棄処理でエラー：' . $e->getMessage());
}
