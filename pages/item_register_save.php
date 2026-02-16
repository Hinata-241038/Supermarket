<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  exit('不正なアクセス');
}

/* =========================================================
   1. 入力取得
========================================================= */
$jan_code    = preg_replace('/\D/', '', $_POST['jan_code'] ?? '');
$item_name   = trim($_POST['item_name'] ?? '');
$category_id = (int)($_POST['category_id'] ?? 0);
$price       = (int)($_POST['price'] ?? 0);
$unit        = trim($_POST['unit'] ?? '');
$supplier    = trim($_POST['supplier'] ?? '');

/* =========================================================
   2. JANチェックデジット補完関数
========================================================= */
function appendJanCheckDigit(string $jan12): string {
  $sum = 0;
  for ($i = 0; $i < 12; $i++) {
    $d = (int)$jan12[$i];
    $sum += ($i % 2 === 0) ? $d : $d * 3;
  }
  $check = (10 - ($sum % 10)) % 10;
  return $jan12 . $check;
}

/* =========================================================
   3. 12桁なら自動補完
========================================================= */
if (preg_match('/^\d{12}$/', $jan_code)) {
  $jan_code = appendJanCheckDigit($jan_code);
}

/* =========================================================
   4. 13桁チェック
========================================================= */
if (!preg_match('/^\d{13}$/', $jan_code)) {
  exit('JANコードは12桁または13桁の数字で入力してください');
}

/* =========================================================
   5. チェックデジット検証
========================================================= */
function isValidJan13(string $jan): bool {
  $sum = 0;
  for ($i = 0; $i < 12; $i++) {
    $d = (int)$jan[$i];
    $sum += ($i % 2 === 0) ? $d : $d * 3;
  }
  $check = (10 - ($sum % 10)) % 10;
  return $check === (int)$jan[12];
}

if (!isValidJan13($jan_code)) {
  exit('無効なJANコードです');
}

/* =========================================================
   6. 必須チェック
========================================================= */
if ($item_name === '' || $category_id <= 0) {
  exit('入力不足');
}

/* =========================================================
   7. DB登録（UPSERT）
========================================================= */
$sql = "
INSERT INTO items
(jan_code, item_name, category_id, price, unit, supplier, created_at, updated_at)
VALUES
(:jan, :name, :cat, :price, :unit, :supplier, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  item_name   = VALUES(item_name),
  category_id = VALUES(category_id),
  price       = VALUES(price),
  unit        = VALUES(unit),
  supplier    = VALUES(supplier),
  updated_at  = NOW()
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':jan'      => $jan_code,
  ':name'     => $item_name,
  ':cat'      => $category_id,
  ':price'    => $price,
  ':unit'     => $unit,
  ':supplier' => $supplier,
]);

/* =========================================================
   8. リダイレクト
========================================================= */
header('Location: hacchu_form.php?jan=' . urlencode($jan_code));
exit;
