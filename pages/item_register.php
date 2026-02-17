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

  <form class="order-form" method="post" action="item_register_save.php" novalidate>

    <div class="form-row">
      <label for="jan_code">JANコード（13桁）</label>

      <!-- ✅ ここがポイント：JAN入力 + ステータス表示 -->
      <div class="jan-wrap">
        <input
          type="text"
          id="jan_code"
          name="jan_code"
          inputmode="numeric"
          maxlength="13"
          placeholder="12桁または13桁"
          value="<?= htmlspecialchars($jan, ENT_QUOTES, 'UTF-8') ?>"
          autocomplete="off"
        >
        <div id="jan_status" class="jan-status jan-status--idle">12桁で入力すると自動で13桁に補完します</div>
      </div>
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
          <option value="<?= (int)$c['id'] ?>">
            <?= htmlspecialchars($c['category_label_ja'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="price">価格</label>
      <input type="number" id="price" name="price" min="0" step="1" required>
    </div>

    <div class="form-row">
      <label for="unit">単位</label>
      <input type="text" id="unit" name="unit" placeholder="例：個 / 本 / 袋" required>
    </div>

    <div class="form-row">
      <label for="supplier">仕入先</label>
      <input type="text" id="supplier" name="supplier" placeholder="例：トライアル / ダイソー" required>
    </div>

    <button type="submit" class="primary-btn" id="submitBtn">商品追加</button>
  </form>
</div>

<script>
/* =========================================================
  JAN13: チェックデジット計算（JS版）
  - EAN-13仕様：1桁目から見て偶数位置(2,4,6,...)を×3
========================================================= */
function calcJanCheckDigit(jan12) {
  let sum = 0;
  for (let i = 0; i < 12; i++) {
    const d = parseInt(jan12[i], 10);
    sum += (i % 2 === 0) ? d : d * 3;
  }
  return (10 - (sum % 10)) % 10;
}

function isValidJan13(jan13) {
  if (!/^\d{13}$/.test(jan13)) return false;
  const check = calcJanCheckDigit(jan13.slice(0, 12));
  return parseInt(jan13[12], 10) === check;
}

/* =========================================================
  UI制御（色付き）
========================================================= */
const janInput  = document.getElementById('jan_code');
const janStatus = document.getElementById('jan_status');
const submitBtn = document.getElementById('submitBtn');

function setState(state, message) {
  janStatus.classList.remove(
    'jan-status--idle',
    'jan-status--typing',
    'jan-status--ok',
    'jan-status--ng'
  );
  janStatus.classList.add(state);
  janStatus.textContent = message;

  // NGの時は送信ボタンを無効化（プロ仕様）
  if (state === 'jan-status--ng') {
    submitBtn.disabled = true;
    submitBtn.classList.add('is-disabled');
  } else {
    submitBtn.disabled = false;
    submitBtn.classList.remove('is-disabled');
  }
}

function normalizeDigits(value) {
  return value.replace(/\D/g, '');
}

function refreshJanUI() {
  let v = normalizeDigits(janInput.value);
  janInput.value = v;

  if (v.length === 0) {
    janInput.classList.remove('input-ok', 'input-ng', 'input-typing');
    setState('jan-status--idle', '12桁で入力すると自動で13桁に補完します');
    return;
  }

  if (v.length < 12) {
    janInput.classList.remove('input-ok', 'input-ng');
    janInput.classList.add('input-typing');
    setState('jan-status--typing', `あと ${12 - v.length} 桁で自動補完できます`);
    return;
  }

  if (v.length === 12) {
    const check = calcJanCheckDigit(v);
    janInput.classList.remove('input-ok', 'input-ng');
    janInput.classList.add('input-typing');
    setState('jan-status--typing', `自動補完予定 → ${v}${check}`);
    return;
  }

  if (v.length === 13) {
    janInput.classList.remove('input-typing');
    if (isValidJan13(v)) {
      janInput.classList.add('input-ok');
      janInput.classList.remove('input-ng');
      setState('jan-status--ok', '✓ 有効なJANです');
    } else {
      janInput.classList.add('input-ng');
      janInput.classList.remove('input-ok');
      setState('jan-status--ng', '✕ 無効なJANです（チェックデジット不一致）');
    }
    return;
  }

  // 14桁以上はNG
  janInput.classList.remove('input-ok', 'input-typing');
  janInput.classList.add('input-ng');
  setState('jan-status--ng', '✕ 13桁までです');
}

/* =========================================================
  12桁 → blur（フォーカス外れ）で自動13桁化
========================================================= */
janInput.addEventListener('input', refreshJanUI);

janInput.addEventListener('blur', () => {
  let v = normalizeDigits(janInput.value);

  // 12桁なら確定時に13桁へ
  if (/^\d{12}$/.test(v)) {
    const check = calcJanCheckDigit(v);
    janInput.value = v + String(check);
  }
  refreshJanUI();
});

// 初期表示（GET janがある場合にも対応）
refreshJanUI();
</script>

</body>
</html>
