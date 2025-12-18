<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

/* POST以外は拒否 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです');
}

/* 値取得 */
$item_id        = (int)($_POST['item_id'] ?? 0);
$order_quantity = (int)($_POST['order_quantity'] ?? 0);
$order_date     = $_POST['order_date'] ?? date('Y-m-d');

/* バリデーション */
if ($item_id <= 0 || $order_quantity <= 0) {
    exit('入力値が不正です');
}

/* DB登録 */
$sql = "
    INSERT INTO orders
        (item_id, order_quantity, order_date, status, created_at, updated_at)
    VALUES
        (:item_id, :order_quantity, :order_date, 0, CURDATE(), CURDATE())
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':item_id'        => $item_id,
    ':order_quantity' => $order_quantity,
    ':order_date'     => $order_date
]);

/* 完了画面へ */
header('Location: hacchu_list.php');
exit;
