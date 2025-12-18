<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

/* 表示タイプ（デフォルト：消費期限） */
$type = $_GET['type'] ?? 'use';

/* SQL（今回は stock.expire_date を共通で使用） */
$sql = "
SELECT
    i.item_name,
    s.quantity,
    s.expire_date
FROM stock s
JOIN items i ON s.item_id = i.id
ORDER BY s.expire_date
";

$stmt = $pdo->query($sql);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>廃棄管理</title>
    <link rel="stylesheet" href="../assets/css/haiki.css">
</head>
<body>

<!-- 戻る（左下固定） -->
<button class="back-btn" onclick="location.href='home.php'">戻る</button>

<div class="container">
    <h1>廃棄管理</h1>

    <!-- ラジオボタン切替 -->
    <form method="get" class="switch-area">
        <label>
            <input type="radio" name="type" value="use"
                <?= $type === 'use' ? 'checked' : '' ?>
                onchange="this.form.submit()">
            消費期限
        </label>

        <label>
            <input type="radio" name="type" value="best"
                <?= $type === 'best' ? 'checked' : '' ?>
                onchange="this.form.submit()">
            賞味期限
        </label>
    </form>

    <!-- 一覧テーブル -->
    <table>
        <tr>
            <th>商品名</th>
            <th>数量</th>
            <th><?= $type === 'best' ? '賞味期限' : '消費期限' ?></th>
            <th>判定</th>
            <th>操作</th>
        </tr>

        <?php foreach ($list as $row): ?>
            <?php
                $expired = ($row['expire_date'] < $today);
            ?>
            <tr class="<?= $expired ? 'expired' : '' ?>">
                <td><?= htmlspecialchars($row['item_name']) ?></td>
                <td><?= htmlspecialchars($row['quantity']) ?></td>
                <td><?= htmlspecialchars($row['expire_date']) ?></td>
                <td><?= $expired ? '期限切れ' : 'OK' ?></td>
                <td>
                    <a href="disposal_form.php">廃棄</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

</body>
</html>
