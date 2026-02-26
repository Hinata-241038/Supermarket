<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

$jan = $_GET['jan'] ?? '';
$jan = preg_replace('/\D/', '', $jan);

// カテゴリ一覧
$stmt = $pdo->query("SELECT id, category_label_ja FROM categories ORDER BY category_group, id");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>商品登録</title>

  <!-- 共通CSS -->
  <link rel="stylesheet" href="../assets/css/hacchu.css">
  <!-- 商品登録専用CSS（hacchu.cssを壊さず上書き） -->
  <link rel="stylesheet" href="../assets/css/item_register.css">
</head>

<body class="item-register-page">

<a href="hacchu_form.php" class="back-btn">戻る</a>

<div class="container">
  <h1>商品登録</h1>

  <form class="order-form" method="post" action="item_register_save.php" novalidate>

    <!-- JAN -->
    <div class="form-row">
      <label for="jan_code">JANコード（13桁）</label>

      <div class="jan-wrap">
        <input
          type="text"
          id="jan_code"
          name="jan_code"
          inputmode="numeric"
          maxlength="13"
          placeholder="12桁または13桁"
          value="<?= h($jan) ?>"
          autocomplete="off"
        >
        <div id="jan_status" class="jan-status jan-status--idle">
          12桁で入力すると自動で13桁に補完します
        </div>
      </div>
    </div>

    <!-- 商品名 -->
    <div class="form-row">
      <label for="item_name">商品名</label>
      <input type="text" id="item_name" name="item_name" required>
    </div>

    <!-- カテゴリ -->
    <div class="form-row">
      <label for="category_id">カテゴリ</label>
      <select id="category_id" name="category_id" required>
        <option value="">選択してください</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>">
            <?= h($c['category_label_ja']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- 価格 -->
    <div class="form-row">
      <label for="price">価格</label>
      <input type="number" id="price" name="price" min="0" step="1" required>
    </div>

    <!-- 単位 -->
    <div class="form-row">
      <label for="unit">単位</label>
      <input type="text" id="unit" name="unit" placeholder="例：個 / 本 / 袋" required>
    </div>

    <!-- 仕入先 -->
    <div class="form-row">
      <label for="supplier">仕入先</label>
      <input type="text" id="supplier" name="supplier" placeholder="例：トライアル / ダイソー" required>
    </div>

    <!-- 期間限定（任意） -->
    <div class="form-row">
      <label>期間限定</label>
      <div class="limited-field">
        <label class="toggle">
          <input type="checkbox" id="is_limited" name="is_limited" value="1">
          <span class="toggle-ui" aria-hidden="true"></span>
          <span class="toggle-text">期間限定商品として登録する</span>
        </label>
        <div class="field-help">※任意。通常商品はオフのままでOK</div>
      </div>
    </div>

    <!-- エラー表示 -->
    <div id="form_errors" class="form-errors" style="display:none;"></div>

    <!-- 送信ボタン（見た目/配置はCSSで“発注ボタン風”にする） -->
    <div class="form-actions">
      <button type="submit" class="primary-btn" id="submitBtn">商品追加</button>
    </div>
  </form>
</div>

<script>
/* =========================================================
  JAN13: チェックデジット計算（EAN-13）
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
  JAN UI制御
========================================================= */
const janInput  = document.getElementById('jan_code');
const janStatus = document.getElementById('jan_status');
const submitBtn = document.getElementById('submitBtn');

function setJanState(state, message) {
  janStatus.classList.remove(
    'jan-status--idle','jan-status--typing','jan-status--ok','jan-status--ng'
  );
  janStatus.classList.add(state);
  janStatus.textContent = message;
}
function normalizeDigits(value) {
  return value.replace(/\D/g, '');
}
function refreshJanUI() {
  let v = normalizeDigits(janInput.value);
  janInput.value = v;

  if (v.length === 0) {
    janInput.classList.remove('input-ok', 'input-ng', 'input-typing');
    setJanState('jan-status--idle', '12桁で入力すると自動で13桁に補完します');
    return;
  }
  if (v.length < 12) {
    janInput.classList.remove('input-ok', 'input-ng');
    janInput.classList.add('input-typing');
    setJanState('jan-status--typing', `あと ${12 - v.length} 桁で自動補完できます`);
    return;
  }
  if (v.length === 12) {
    const check = calcJanCheckDigit(v);
    janInput.classList.remove('input-ok', 'input-ng');
    janInput.classList.add('input-typing');
    setJanState('jan-status--typing', `自動補完予定 → ${v}${check}`);
    return;
  }
  if (v.length === 13) {
    janInput.classList.remove('input-typing');
    if (isValidJan13(v)) {
      janInput.classList.add('input-ok');
      janInput.classList.remove('input-ng');
      setJanState('jan-status--ok', '✓ 有効なJANです');
    } else {
      janInput.classList.add('input-ng');
      janInput.classList.remove('input-ok');
      setJanState('jan-status--ng', '✕ 無効なJANです（チェックデジット不一致）');
    }
    return;
  }
  janInput.classList.remove('input-ok', 'input-typing');
  janInput.classList.add('input-ng');
  setJanState('jan-status--ng', '✕ 13桁までです');
}
janInput.addEventListener('input', refreshJanUI);
janInput.addEventListener('blur', () => {
  let v = normalizeDigits(janInput.value);
  if (/^\d{12}$/.test(v)) {
    const check = calcJanCheckDigit(v);
    janInput.value = v + String(check);
  }
  refreshJanUI();
});
refreshJanUI();

/* =========================================================
  入力制限（商品名/単位/仕入れ先）
========================================================= */
const itemName   = document.getElementById('item_name');
const unit       = document.getElementById('unit');
const supplier   = document.getElementById('supplier');
const formErrors = document.getElementById('form_errors');
const allowedRe = /^[ぁ-んァ-ヶー一-龥A-Za-z0-9]+$/;

let showErrors = false;

function validateTextField(el, label) {
  const v = (el.value ?? '').trim();
  if (v.length === 0) return { ok:false, msg:`${label}を入力してください` };
  if (!allowedRe.test(v)) {
    return { ok:false, msg:`${label}は「ひらがな・カタカナ・漢字・アルファベット・数字」のみ入力できます（スペース/記号は不可）` };
  }
  return { ok:true, msg:'' };
}

function renderErrors(messages) {
  if (!showErrors) {
    formErrors.style.display = 'none';
    formErrors.innerHTML = '';
    return;
  }
  if (messages.length === 0) {
    formErrors.style.display = 'none';
    formErrors.innerHTML = '';
    return;
  }
  formErrors.style.display = 'block';
  formErrors.innerHTML = '<ul>' + messages.map(m => `<li>${m}</li>`).join('') + '</ul>';
}

function refreshFormValidation() {
  const messages = [];

  const r1 = validateTextField(itemName, '商品名');
  if (!r1.ok) messages.push(r1.msg);

  const r2 = validateTextField(unit, '単位');
  if (!r2.ok) messages.push(r2.msg);

  const r3 = validateTextField(supplier, '仕入先');
  if (!r3.ok) messages.push(r3.msg);

  const janOk = /^\d{13}$/.test(janInput.value) && isValidJan13(janInput.value);
  if (!janOk) messages.push('JANコードが有効ではありません');

  renderErrors(messages);

  if (messages.length > 0) {
    submitBtn.disabled = true;
    submitBtn.classList.add('is-disabled');
  } else {
    submitBtn.disabled = false;
    submitBtn.classList.remove('is-disabled');
  }
  return messages;
}

[itemName, unit, supplier, janInput].forEach(el => {
  el.addEventListener('input', () => refreshFormValidation());
  el.addEventListener('blur',  () => refreshFormValidation());
});

document.querySelector('form').addEventListener('submit', (e) => {
  showErrors = true;
  const messages = refreshFormValidation();
  if (messages.length > 0) e.preventDefault();
});

refreshFormValidation();
</script>

</body>
</html>