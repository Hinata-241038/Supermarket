<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  exit('不正なアクセスです');
}

$jan_code    = $_POST['jan_code'] ?? '';
$item_name   = $_POST['item_name'] ?? '';
$category_id = (int)($_POST['category_id'] ?? 0);
$price       = (int)($_POST['price'] ?? 0);
$unit        = $_POST['unit'] ?? '';
$supplier    = $_POST['supplier'] ?? '';

$jan_code = preg_replace('/\D/', '', $jan_code);

if ($jan_code === '' || trim($item_name) === '' || $category_id <= 0) {
  exit('入力が不足しています');
}

/*
  前提：items.jan_code に UNIQUE が付いている（uq_items_jan_code）
  同JANがある場合は更新
*/
$sql = "
INSERT INTO items (jan_code, item_name, category_id, price, unit, supplier, created_at, updated_at)
VALUES (:jan_code, :item_name, :category_id, :price, :unit, :supplier, CURDATE(), CURDATE())
ON DUPLICATE KEY UPDATE
  item_name  = VALUES(item_name),
  category_id = VALUES(category_id),
  price      = VALUES(price),
  unit       = VALUES(unit),
  supplier   = VALUES(supplier),
  updated_at = CURDATE()
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':jan_code'    => $jan_code,
  ':item_name'   => $item_name,
  ':category_id' => $category_id,
  ':price'       => $price,
  ':unit'        => $unit,
  ':supplier'    => $supplier,
]);

header('Location: hacchu_form.php?jan=' . urlencode($jan_code));
exit;
