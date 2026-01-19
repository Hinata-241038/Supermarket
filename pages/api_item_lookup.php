<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

header('Content-Type: application/json; charset=UTF-8');

$jan = $_GET['jan'] ?? '';
$jan = preg_replace('/\D/', '', $jan);

if ($jan === '' || strlen($jan) < 8) {
  echo json_encode([
    'found' => false,
    'message' => 'JANが不正です'
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$sql = "SELECT id, jan_code, item_name, category_id, price, supplier
        FROM items
        WHERE jan_code = :jan
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':jan' => $jan]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  echo json_encode([
    'found' => false,
    'message' => '商品が未登録です'
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode([
  'found' => true,
  'id' => (int)$row['id'],
  'jan_code' => $row['jan_code'],
  'item_name' => $row['item_name'],
  'category_id' => (int)$row['category_id'],
  'price' => (int)$row['price'],
  'supplier' => $row['supplier'],
], JSON_UNESCAPED_UNICODE);
