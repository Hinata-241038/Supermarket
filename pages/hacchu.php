<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  exit('不正なアクセスです');
}

$item_id        = (int)($_POST['item_id'] ?? 0);
$jan            = preg_replace('/\D/', '', ($_POST['jan'] ?? ''));
$order_quantity = (int)($_POST['order_quantity'] ?? 0);
$order_date     = $_POST['order_date'] ?? date('Y-m-d');
$is_limited     = (int)($_POST['is_limited'] ?? 0);

if ($order_quantity <= 0) {
  exit('入力値が不正です（数量が不正です）');
}

/* item_idが無い場合はJANで救済 */
if ($item_id <= 0) {
  if ($jan === '') exit('入力値が不正です（商品が特定できません：JAN未入力）');
  $st = $pdo->prepare("SELECT id FROM items WHERE jan_code = :jan LIMIT 1");
  $st->execute([':jan' => $jan]);
  $item_id = (int)($st->fetchColumn() ?: 0);
}
if ($item_id <= 0) exit('入力値が不正です（商品が特定できません）');

/* 商品存在チェック */
$check = $pdo->prepare("SELECT id FROM items WHERE id = :id");
$check->execute([':id' => $item_id]);
if (!$check->fetchColumn()) exit('商品が存在しません');

/* 期間限定（任意）を items に反映 */
$is_limited = ($is_limited === 1) ? 1 : 0;
$up = $pdo->prepare("UPDATE items SET is_limited = :v WHERE id = :id");
$up->execute([':v' => $is_limited, ':id' => $item_id]);

/* 発注登録 */
$sql = "
INSERT INTO orders
(item_id, order_quantity, order_date, status, created_at, updated_at)
VALUES
(:item_id, :order_quantity, :order_date, 0, NOW(), NOW())
";
$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':item_id' => $item_id,
  ':order_quantity' => $order_quantity,
  ':order_date' => $order_date,
]);

header('Location: hacchu_list.php');
exit;