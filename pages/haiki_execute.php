<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  exit('不正なアクセス');
}

$stockId = (int)($_POST['stock_id'] ?? 0);
if ($stockId <= 0) {
  exit('パラメータ不正');
}

try {
  $pdo->beginTransaction();

  // ① 在庫ロットをロックして取得
  $stmt = $pdo->prepare("
    SELECT item_id, quantity
    FROM stock
    WHERE id = :id
    FOR UPDATE
  ");
  $stmt->execute([':id' => $stockId]);
  $stock = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$stock) {
    throw new Exception('在庫が存在しません');
  }

  // ② 廃棄履歴へ記録
  $stmt = $pdo->prepare("
    INSERT INTO disposal
      (item_id, disposal_quantity, reason, disposal_date, created_at)
    VALUES
      (:item_id, :qty, '期限切れ', CURDATE(), CURDATE())
  ");
  $stmt->execute([
    ':item_id' => $stock['item_id'],
    ':qty'     => $stock['quantity'],
  ]);

  // ③ 在庫から削除
  $stmt = $pdo->prepare("
    DELETE FROM stock
    WHERE id = :id
  ");
  $stmt->execute([':id' => $stockId]);

  // ④ 完了
  $pdo->commit();

} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  exit('廃棄処理に失敗しました：' . $e->getMessage());
}

header('Location: haiki.php');
exit;
