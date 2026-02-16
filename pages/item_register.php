<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

function isValidJan13(string $jan): bool {
  if (!preg_match('/^\d{13}$/', $jan)) return false;
  $sum = 0;
  for ($i = 0; $i < 12; $i++) {
    $d = (int)$jan[$i];
    $sum += ($i % 2 === 0) ? $d : $d * 3;
  }
  $check = (10 - ($sum % 10)) % 10;
  return $check === (int)$jan[12];
}

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

<form method="post" action="item_register_save.php">

<div class="form-row">
<label>JANコード（13桁）</label>
<input type="text" name="jan_code"
       id="jan_code"
       maxlength="13"
       pattern="\d{13}"
       inputmode="numeric"
       required>

<p id="janError" style="color:red; font-size:14px;"></p>
</div>


<div class="form-row">
<label>商品名</label>
<input type="text" name="item_name" required>
</div>

<div class="form-row">
<label>カテゴリ</label>
<select name="category_id" required>
<option value="">選択</option>
<?php foreach($categories as $c): ?>
<option value="<?= (int)$c['id'] ?>">
<?= htmlspecialchars($c['category_label_ja'], ENT_QUOTES, 'UTF-8') ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="form-row">
<label>価格</label>
<input type="number" name="price" min="0">
</div>

<div class="form-row">
<label>単位</label>
<input type="text" name="unit">
</div>

<div class="form-row">
<label>仕入先</label>
<input type="text" name="supplier">
</div>

<button type="submit">商品追加</button>
</form>
</div>

<script>
const janInput = document.getElementById('jan_code');
const janError = document.getElementById('janError');

if (janInput) {
  janInput.addEventListener('input', function(){
    this.value = this.value.replace(/\D/g,'').slice(0,13);

    if (this.value.length === 0) {
      janError.textContent = '';
    } else if (this.value.length < 13) {
      janError.textContent = 'JANコードは13桁で入力してください';
    } else {
      janError.textContent = '';
    }
  });
}
</script>



</body>
</html>
