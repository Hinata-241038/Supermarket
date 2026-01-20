<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

$jan = $_GET['jan'] ?? '';
$jan = preg_replace('/\D/', '', $jan);

// カテゴリ一覧
$stmt = $pdo->query("SELECT id, category_label_ja FROM categories ORDER BY category_group, id");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>商品登録</title>
  <link rel="stylesheet" href="../assets/css/hacchu.css">
</head>
<body>

<div class="container">
  <h1>商品登録</h1>

  <form class="order-form" method="post" action="item_register_save.php">

    <div class="form-row">
      <label for="jan_code">JAN</label>
      <input type="text" id="jan_code" name="jan_code"
             value="<?= htmlspecialchars($jan, ENT_QUOTES, 'UTF-8') ?>"
             inputmode="numeric" autocomplete="off" required>
    </div>

    <div class="form-row">
      <label for="item_name">商品名</label>
      <input type="text" id="item_name" name="item_name" required>
    </div>

    <div class="form-row">
      <label for="category_id">カテゴリ</label>
      <select id="category_id" name="category_id" required>
        <option value="">選択してください</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= htmlspecialchars((string)$c['id'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars((string)$c['category_label_ja'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="price">単価（円）</label>
      <input type="number" id="price" name="price" min="0" step="1" required>
    </div>

    <div class="form-row">
      <label for="unit">単位（例：個、袋）</label>
      <input type="text" id="unit" name="unit" value="">
    </div>

    <div class="form-row">
      <label for="supplier">仕入先（任意）</label>
      <input type="text" id="supplier" name="supplier" value="">
    </div>

    <div class="buttons">
      <button type="button" class="back-btn" onclick="location.href='hacchu_form.php'">戻る</button>
      <button type="submit" class="hacchu-btn">登録</button>
    </div>

  </form>
</div>

</body>
</html>
