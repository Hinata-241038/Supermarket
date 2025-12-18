<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

/* 発注履歴取得 */
$sql = "
    SELECT
        id,
        item_id,
        order_quantity,
        order_date,
        status
    FROM orders
    ORDER BY order_date DESC, id DESC
";

$stmt = $pdo->query($sql);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* status 表示用 */
function statusLabel($status)
{
    switch ($status) {
        case 0: return '未入荷';
        case 1: return '入荷済';
        default: return '不明';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>発注履歴一覧</title>
    <link rel="stylesheet" href="../assets/css/hacchu.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }
        th, td {
            border: 1px solid #aaa;
            padding: 8px;
            text-align: center;
        }
        th {
            background: #f0f0f0;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>発注履歴</h1>

    <table>
        <tr>
            <th>ID</th>
            <th>商品ID</th>
            <th>数量</th>
            <th>発注日</th>
            <th>状態</th>
            <th>操作</th>

        </tr>

        <?php if (empty($orders)): ?>
            <tr>
                <td colspan="5">発注履歴がありません</td>
            </tr>
        <?php else: ?>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?= htmlspecialchars($o['id']) ?></td>
                    <td><?= htmlspecialchars($o['item_id']) ?></td>
                    <td><?= htmlspecialchars($o['order_quantity']) ?></td>
                    <td><?= htmlspecialchars($o['order_date']) ?></td>
                    <td><?= statusLabel($o['status']) ?></td>
                    <td>
                        <?php if ($o['status'] == 0): ?>
                            <a href="nyuka_form.php?order_id=<?= $o['id'] ?>">入荷</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>

                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <div class="buttons">
        <button class="back-btn" onclick="location.href='hacchu_form.php'">
            発注画面へ
        </button>
    </div>
</div>

</body>
</html>
