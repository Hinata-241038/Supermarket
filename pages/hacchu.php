<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  exit('不正なアクセスです');
}

$item_id        = (int)($_POST['item_id'] ?? 0);
$order_quantity = (int)($_POST['order_quantity'] ?? 0);
$order_date     = $_POST['order_date'] ?? date('Y-m-d');

if ($item_id <= 0 || $order_quantity <= 0) {
  exit('入力値が不正です（JANから商品が特定できません）');
}

// items存在確認
$check = $pdo->prepare("SELECT id FROM items WHERE id = :id");
$check->execute([':id' => $item_id]);
if (!$check->fetchColumn()) {
  exit('商品が存在しません（itemsを確認してください）');
}

$sql = "
INSERT INTO orders (item_id, order_quantity, order_date, status, created_at, updated_at)
VALUES (:item_id, :order_quantity, :order_date, 0, CURDATE(), CURDATE())
";
$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':item_id' => $item_id,
  ':order_quantity' => $order_quantity,
  ':order_date' => $order_date,
]);

header('Location: hacchu_list.php');
exit;
