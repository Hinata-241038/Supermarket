<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

/**
 * GETで item_id があれば編集、なければ新規
 */
$item_id = (int)($_GET['item_id'] ?? 0);

$item = [
    'id' => '',
    'jan_code' => '',
    'item_name' => '',
    'category_id' => '',
    'unit' => '',
];

// カテゴリ一覧取得（プルダウン用）
$catSql = "SELECT id, category_label_ja FROM categories ORDER BY category_label_ja";
$catStmt = $pdo->query($catSql);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// 編集の場合：itemsから取得してフォームに反映
if ($item_id > 0) {
    $sql = "SELECT id, jan_code, item_name, category_id, unit FROM items WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $item_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        exit('指定された商品が見つかりません');
    }
    $item = $row;
}

// 画面表示用メッセージ（任意）
$msg = $_GET['msg'] ?? '';
$isEdit = ($item_id > 0);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= $isEdit ? '商品編集' : '商品追加' ?></title>
    <link rel="stylesheet" href="../assets/css/shouhintuika.css">
</head>
<body>
<div class="container">

    <header>
        <button class="back-btn" onclick="location.href='zaiko.php'">戻る</button>
        <h1><?= $isEdit ? '商品編集' : '商品追加' ?></h1>
    </header>

    <?php if ($msg !== ''): ?>
        <p style="margin-top:16px; color:#333;">
            <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form class="add-form" method="post" action="shouhintuika_save.php">
        <!-- 編集判定用：編集時のみ値が入る -->
        <input type="hidden" name="item_id" value="<?= htmlspecialchars((string)$item['id'], ENT_QUOTES, 'UTF-8') ?>">

        <label>JAN</label>
        <input type="text" name="jan_code" required
               value="<?= htmlspecialchars($item['jan_code'], ENT_QUOTES, 'UTF-8') ?>"
               placeholder="例）4901234567890">

        <label>商品名</label>
        <input type="text" name="item_name" required
               value="<?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?>"
               placeholder="例）牛乳 1L">

        <label>カテゴリ</label>
        <select name="category_id" required style="width:100%; height:42px; background:#dcdcdc; border:none; border-radius:4px; margin-top:6px; padding:5px; font-size:16px;">
            <option value="">選択してください</option>
            <?php foreach ($categories as $c): ?>
                <?php
                    $selected = ((string)$item['category_id'] === (string)$c['id']) ? 'selected' : '';
                ?>
                <option value="<?= htmlspecialchars((string)$c['id'], ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                    <?= htmlspecialchars($c['category_label_ja'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>単位</label>
        <input type="text" name="unit"
               value="<?= htmlspecialchars($item['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               placeholder="例）個 / 本 / 袋 / g など">

        <div class="buttons">
            <button type="button" class="back-btn" onclick="location.href='zaiko.php'">戻る</button>
            <button type="submit" class="submit-btn"><?= $isEdit ? '更新' : '登録' ?></button>
        </div>
    </form>

</div>
</body>
</html>
