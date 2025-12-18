<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセス');
}

$order_id = (int)$_POST['order_id'];
$item_id  = (int)$_POST['item_id'];
$quantity = (int)$_POST['quantity'];
$expire   = $_POST['expire_date'] ?: null;

if ($order_id <= 0 || $item_id <= 0 || $quantity <= 0) {
    exit('入力値エラー');
}

try {
    $pdo->beginTransaction();

    // 在庫更新（なければ作成）
    $sql = "
        INSERT INTO stock (item_id, quantity, expire_date, created_at, updated_at)
        VALUES (:item_id, :quantity, :expire, CURDATE(), CURDATE())
        ON DUPLICATE KEY UPDATE
            quantity = quantity + :quantity,
            updated_at = CURDATE()
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':item_id' => $item_id,
        ':quantity' => $quantity,
        ':expire' => $expire
    ]);

    // 発注ステータス更新
    $stmt = $pdo->prepare(
        "UPDATE orders SET status = 1, updated_at = CURDATE() WHERE id = :id"
    );
    $stmt->execute([':id' => $order_id]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    exit('入荷処理に失敗しました');
}

header('Location: hacchu_list.php');
exit;
