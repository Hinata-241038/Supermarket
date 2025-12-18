<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

/* 検索キーワード */
$keyword = $_GET['keyword'] ?? '';

/* 一覧取得 */
$sql = "
SELECT
    i.jan_code,
    i.item_name,
    c.category_label_ja,
    IFNULL(s.quantity, 0) AS stock_quantity,
    s.expire_date,
    i.unit
FROM items i
LEFT JOIN categories c ON i.category_id = c.id
LEFT JOIN stock s ON i.id = s.item_id
WHERE
    i.item_name LIKE :keyword
    OR c.category_label_ja LIKE :keyword
ORDER BY i.item_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':keyword' => '%' . $keyword . '%'
]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 期限判定用（日付は "Y-m-d" で統一） */
$today = new DateTime('today');
$soonLimit = (new DateTime('today'))->modify('+7 days'); // 期限間近の基準（7日以内）
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>在庫</title>
    <link rel="stylesheet" href="../assets/css/zaiko.css">
</head>
<body>

<button class="back-btn" onclick="location.href='home.php'">戻る</button>

<h1 class="title">在庫</h1>

<form method="get" class="search-area">
    <input
        type="text"
        name="keyword"
        class="search-box"
        placeholder="商品名またはカテゴリで検索"
        value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"
    >
    <button type="submit" class="search-btn">🔍</button>

    <!-- 重要：submitにならないように type="button" -->
    <button type="button" class="order-btn" onclick="location.href='nyuka_form.php'">追加</button>
</form>

<table class="item-table">
    <tr>
        <th>JAN</th>
        <th>商品名</th>
        <th>カテゴリ</th>
        <th>単位</th>
        <th>期限</th>
        <th>在庫</th>
        <th>編集</th>
    </tr>

    <?php foreach ($items as $item): ?>
        <?php
            // 在庫0判定
            $isZeroStock = ((int)$item['stock_quantity'] === 0);

            // 期限判定
            $expireClass = '';
            $expireLabel = $item['expire_date'] ?? null;

            if (!empty($expireLabel)) {
                $expireDate = DateTime::createFromFormat('Y-m-d', $expireLabel);

                if ($expireDate instanceof DateTime) {
                    if ($expireDate < $today) {
                        // 期限切れ
                        $expireClass = 'expire-over';
                        $expireLabel = '⚠ 期限切れ（' . $expireDate->format('Y-m-d') . '）';
                    } elseif ($expireDate <= $soonLimit) {
                        // 期限間近
                        $expireClass = 'expire-soon';
                        $expireLabel = '⚠ 期限間近（' . $expireDate->format('Y-m-d') . '）';
                    } else {
                        // 通常
                        $expireLabel = $expireDate->format('Y-m-d');
                    }
                }
            } else {
                $expireLabel = '-';
            }

            // 行の強調（期限切れ/間近があれば行にも薄く色を付ける）
            $rowClass = '';
            if ($expireClass === 'expire-over') $rowClass = 'row-expire-over';
            if ($expireClass === 'expire-soon') $rowClass = 'row-expire-soon';
        ?>

        <tr class="<?= htmlspecialchars($rowClass, ENT_QUOTES, 'UTF-8') ?>">
            <td><?= htmlspecialchars($item['jan_code'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($item['category_label_ja'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($item['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>

            <!-- 期限セル（期限切れ/期限間近は色付き） -->
            <td class="<?= htmlspecialchars($expireClass, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($expireLabel, ENT_QUOTES, 'UTF-8') ?>
            </td>

            <!-- 在庫セル（在庫0は赤） -->
            <td class="<?= $isZeroStock ? 'stock-zero' : '' ?>">
                <?= htmlspecialchars((string)$item['stock_quantity'], ENT_QUOTES, 'UTF-8') ?>
            </td>

            <td>
                <a href="nyuka_form.php?jan=<?= urlencode($item['jan_code']) ?>">入荷</a>
                /
                <a href="disposal_form.php?jan=<?= urlencode($item['jan_code']) ?>">廃棄</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
