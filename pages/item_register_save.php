<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  exit('不正なアクセス');
}

$jan_code    = preg_replace('/\D/', '', $_POST['jan_code'] ?? '');
$item_name   = trim($_POST['item_name'] ?? '');
$category_id = (int)($_POST['category_id'] ?? 0);
$price       = (int)($_POST['price'] ?? 0);
$unit        = trim($_POST['unit'] ?? '');
$supplier    = trim($_POST['supplier'] ?? '');

// ✅ 期間限定（任意）: チェックされていれば1、それ以外0
$is_limited  = isset($_POST['is_limited']) ? 1 : 0;

/* =========================================================
  12桁→13桁補完（サーバー側保険）
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

if (preg_match('/^\d{12}$/', $jan_code)) {
  $jan_code = appendJanCheckDigit($jan_code);
}

/* =========================================================
  13桁形式チェック
========================================================= */
if (!preg_match('/^\d{13}$/', $jan_code)) {
  exit('JANコードは12桁または13桁の数字で入力してください');
}

/* =========================================================
  チェックデジット検証（サーバー側確定）
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
  ✅ 入力制限（サーバー側・すり抜け防止の本丸）
  - 「ひらがな」「カタカナ」「漢字」「アルファベット」「数字」だけ
========================================================= */
function onlyAllowedChars(string $s): bool {
  return preg_match('/\A[ぁ-んァ-ヶー一-龥A-Za-z0-9]+\z/u', $s) === 1;
}

if ($item_name === '' || $unit === '' || $supplier === '' || $category_id <= 0) {
  exit('入力不足です');
}
if (!onlyAllowedChars($item_name)) exit('商品名に使用できない文字が含まれています');
if (!onlyAllowedChars($unit))      exit('単位に使用できない文字が含まれています');
if (!onlyAllowedChars($supplier))  exit('仕入先に使用できない文字が含まれています');

/* =========================================================
  INSERT / UPDATE
  - is_limited を追加（既存機能を壊さない：ON DUPLICATE思想維持）
========================================================= */
$sql = "
INSERT INTO items
(jan_code, item_name, category_id, price, unit, supplier, is_limited, created_at, updated_at)
VALUES
(:jan, :name, :cat, :price, :unit, :supplier, :is_limited, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  item_name   = VALUES(item_name),
  category_id = VALUES(category_id),
  price       = VALUES(price),
  unit        = VALUES(unit),
  supplier    = VALUES(supplier),
  is_limited  = VALUES(is_limited),
  updated_at  = NOW()
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':jan'        => $jan_code,
  ':name'       => $item_name,
  ':cat'        => $category_id,
  ':price'      => $price,
  ':unit'       => $unit,
  ':supplier'   => $supplier,
  ':is_limited' => $is_limited,
]);

// 発注画面に戻す（JANを引き継ぐ）
header('Location: hacchu_form.php?jan=' . urlencode($jan_code));
exit;