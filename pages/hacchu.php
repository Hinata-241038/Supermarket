<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  exit('不正なアクセスです');
}

/* =========================
  入力受け取り
========================= */
$item_id        = (int)($_POST['item_id'] ?? 0);
$jan_raw        = $_POST['jan'] ?? ($_POST['jan_code'] ?? '');
$jan            = preg_replace('/\D/', '', $jan_raw);

$order_quantity = (int)($_POST['order_quantity'] ?? 0);
$order_date     = $_POST['order_date'] ?? date('Y-m-d');

/* =========================
  バリデーション
========================= */
if ($order_quantity <= 0) {
  exit('入力値が不正です（数量が不正です）');
}

/* =========================
  item_id救済（JAN検索）
========================= */
if ($item_id <= 0) {
  if ($jan === '') {
    exit('入力値が不正です（商品特定不可）');
  }

  $st = $pdo->prepare("SELECT id FROM items WHERE jan_code = :jan LIMIT 1");
  $st->execute([':jan' => $jan]);
  $item_id = (int)($st->fetchColumn() ?: 0);

  if ($item_id <= 0) {
    $msg = rawurlencode('このJANは未登録です。商品追加をしてください。');
    header("Location: item_register.php?jan={$jan}&from=hacchu_form&msg={$msg}");
    exit;
  }
}

/* =========================
  商品存在確認
========================= */
$check = $pdo->prepare("
  SELECT id
  FROM items
  WHERE id = :id
  LIMIT 1
");
$check->execute([':id' => $item_id]);

if (!$check->fetch()) {
  exit('商品が存在しません');
}

/* =========================
  発注登録（itemsは更新しない）
========================= */
$sql = "
INSERT INTO orders
(item_id, order_quantity, order_date, status, created_at, updated_at)
VALUES
(:item_id, :order_quantity, :order_date, 0, NOW(), NOW())
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':item_id'       => $item_id,
  ':order_quantity'=> $order_quantity,
  ':order_date'    => $order_date,
]);

header('Location: hacchu_list.php');
exit;