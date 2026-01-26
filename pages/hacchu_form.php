<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

$stmt = $pdo->query("SELECT id, category_label_ja FROM categories ORDER BY category_group, id");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$prefillJan = $_GET['jan'] ?? '';
$prefillJan = preg_replace('/\D/', '', $prefillJan);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>発注</title>
  <link rel="stylesheet" href="../assets/css/hacchu.css?v=3">
</head>
<body>

<!-- ✅ 右上：商品追加 & 発注履歴 -->
<a href="item_register.php" class="register-btn-fixed">商品追加</a>
<a href="hacchu_list.php" class="register-btn-fixed second-btn">発注履歴</a>

<div class="container">
  <h1>発注</h1>

  <form class="order-form" method="post" action="/Supermarket/pages/hacchu.php" id="orderForm">
    <div class="form-row">
      <label for="supplier">発注先</label>
      <input type="text" id="supplier" name="supplier" required>
    </div>

    <div class="form-row">
      <label for="order_date">発注日</label>
      <input type="date" id="order_date" name="order_date" required>
    </div>

    <div class="form-row">
      <label for="jan_code">JAN</label>
      <input type="text" id="jan_code" name="jan_code"
             value="<?= htmlspecialchars($prefillJan, ENT_QUOTES, 'UTF-8') ?>"
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
      <label for="order_quantity">個数（点）</label>
      <input type="number" id="order_quantity" name="order_quantity" min="1" step="1" required>
    </div>

    <div class="form-row">
      <label for="price">単価（円）</label>
      <input type="number" id="price" name="price" min="0" step="1" required>
    </div>

    <div class="form-row">
      <label for="total_amount">合計（円）</label>
      <input type="number" id="total_amount" name="total_amount" readonly>
    </div>

    <input type="hidden" id="item_id" name="item_id" value="">
    <p class="hint" id="janHint" aria-live="polite"></p>

    <div class="buttons">
      <button type="button" class="back-btn" onclick="location.href='home.php'">戻る</button>
      <button type="submit" class="hacchu-btn">発注</button>
    </div>
  </form>
</div>

<script src="hacchu_form.js"></script>
</body>
</html>
