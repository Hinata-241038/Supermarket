<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

/* 表示タイプ（デフォルト：消費期限） */
$type = $_GET['type'] ?? 'use';

/*
  期限は一旦 stock.expire_date を使用。
  JOIN列が環境で違う場合：
    - itemsの主キーが item_id なら  ON s.item_id = i.item_id
    - stock側が items_id なら      ON s.items_id = i.id
*/
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

$today = new DateTime('today');
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
    <table class="item-table">
        <tr>
            <th>商品名</th>
            <th>数量</th>
            <th><?= $type === 'best' ? '賞味期限' : '消費期限' ?></th>
            <th>判定</th>
            <th>操作</th>
        </tr>

        <?php if (empty($list)): ?>
            <tr>
                <td colspan="5">表示するデータがありません（在庫未登録 or JOIN不一致の可能性）</td>
            </tr>
        <?php else: ?>
            <?php foreach ($list as $row): ?>
                <?php
                    $expireStr = $row['expire_date'] ?? '';
                    $rowClass  = '';
                    $judgeText = '不明';

                    if ($expireStr !== '') {
                        $expire = new DateTime($expireStr);
                        if ($expire < $today) {
                            $rowClass = 'expired';
                            $judgeText = '期限切れ';
                        } else {
                            $diffDays = (int)$today->diff($expire)->days;
                            if ($diffDays <= 3) {
                                $rowClass = 'warning';
                                $judgeText = '期限間近';
                            } else {
                                $judgeText = 'OK';
                            }
                        }
                    }
                ?>
                <tr class="<?= htmlspecialchars($rowClass) ?>">
                    <td><?= htmlspecialchars($row['item_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['quantity'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['expire_date'] ?? '') ?></td>
                    <td><?= htmlspecialchars($judgeText) ?></td>
                    <td>
                        <a class="discard-btn" href="disposal_form.php">廃棄</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</div>

</body>
</html>
