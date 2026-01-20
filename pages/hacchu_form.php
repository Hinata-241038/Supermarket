<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

// カテゴリ一覧
$stmt = $pdo->query("SELECT id, category_label_ja FROM categories ORDER BY category_group, id");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 商品登録から戻ってきたときのJAN（?jan=）
$prefillJan = $_GET['jan'] ?? '';
$prefillJan = preg_replace('/\D/', '', $prefillJan);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>発注</title>
  <link rel="stylesheet" href="../assets/css/hacchu.css?v=2">
</head>
<body>

<a href="item_register.php" class="register-btn-fixed">登録</a>

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
      <input type="text"
             id="jan_code"
             name="jan_code"
             value="<?= htmlspecialchars($prefillJan, ENT_QUOTES, 'UTF-8') ?>"
             inputmode="numeric"
             autocomplete="off"
             required>
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

<!-- ★ モーダルウィンドウ（商品登録） -->
<div id="registerModal" style="display:none; position:fixed; top:10%; left:50%; transform:translateX(-50%);
  background:#fff; padding:20px; border-radius:10px; box-shadow:0 0 10px #aaa; z-index:9999; width:90%; max-width:500px;">
  <h2>商品登録</h2>
  <form id="modalRegisterForm">
    <input type="hidden" id="modal_jan_code" name="jan_code">

    <div class="form-row">
      <label for="modal_item_name">商品名</label>
      <input type="text" id="modal_item_name" name="item_name" required>
    </div>

    <div class="form-row">
      <label for="modal_category_id">カテゴリ</label>
      <select id="modal_category_id" name="category_id" required>
        <option value="">選択してください</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= htmlspecialchars((string)$c['id'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars((string)$c['category_label_ja'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="modal_price">単価（円）</label>
      <input type="number" id="modal_price" name="price" min="0" step="1" required>
    </div>

    <div class="form-row">
      <label for="modal_unit">単位（例：個、袋）</label>
      <input type="text" id="modal_unit" name="unit">
    </div>

    <div class="form-row">
      <label for="modal_supplier">仕入れ先（任意）</label>
      <input type="text" id="modal_supplier" name="supplier">
    </div>

    <div class="buttons">
      <button type="button" class="back-btn" onclick="closeRegisterModal()">閉じる</button>
      <button type="submit" class="hacchu-btn">登録</button>
    </div>
  </form>
</div>

<script>
(() => {
  const janEl = document.getElementById("jan_code");
  const nameEl = document.getElementById("item_name");
  const categoryEl = document.getElementById("category_id");
  const qtyEl = document.getElementById("order_quantity");
  const priceEl = document.getElementById("price");
  const totalEl = document.getElementById("total_amount");
  const supplierEl = document.getElementById("supplier");
  const itemIdEl = document.getElementById("item_id");
  const hintEl = document.getElementById("janHint");

  const onlyDigits = (s) => (s || "").replace(/\D/g, "");

  const calcTotal = () => {
    const qty = Number(qtyEl.value || 0);
    const price = Number(priceEl.value || 0);
    const total = qty * price;
    totalEl.value = Number.isFinite(total) ? total : 0;
  };
  qtyEl.addEventListener("input", calcTotal);
  priceEl.addEventListener("input", calcTotal);

  const lockFields = (locked) => {
    nameEl.disabled = locked;
    categoryEl.disabled = locked;
    priceEl.disabled = locked;

    if (locked) {
      nameEl.classList.add("is-locked");
      categoryEl.classList.add("is-locked");
      priceEl.classList.add("is-locked");
    } else {
      nameEl.classList.remove("is-locked");
      categoryEl.classList.remove("is-locked");
      priceEl.classList.remove("is-locked");
    }
  };

  const clearItemUI = () => {
    itemIdEl.value = "";
    nameEl.value = "";
    categoryEl.value = "";
    priceEl.value = "";
    calcTotal();
  };

  // ★ モーダル表示処理
  const registerModal = document.getElementById("registerModal");
  const modalForm = document.getElementById("modalRegisterForm");

  function openRegisterModal(jan) {
    document.getElementById("modal_jan_code").value = jan;
    registerModal.style.display = "block";
  }

  function closeRegisterModal() {
    registerModal.style.display = "none";
  }

  const showRegister = (jan) => {
    hintEl.textContent = "商品が未登録です。登録してください。";
    openRegisterModal(jan);
    lockFields(true);
  };

  const hideRegister = () => {
    closeRegisterModal();
  };

  let timer = null;

  const lookupByJan = async () => {
    hintEl.textContent = "";
    clearTimeout(timer);

    const jan = onlyDigits(janEl.value);
    if (jan.length < 8) {
      clearItemUI();
      lockFields(true);
      return;
    }

    clearItemUI();
    lockFields(true);

    timer = setTimeout(async () => {
      try {
        const res = await fetch(`/Supermarket/pages/api_item_lookup.php?jan=${encodeURIComponent(jan)}`);
        if (!res.ok) {
          hintEl.textContent = `JAN検索に失敗しました（HTTP ${res.status}）`;
          showRegister(jan);
          return;
        }
        const data = await res.json();

        if (!data.found) {
          showRegister(jan);
          return;
        }

        lockFields(false);
        itemIdEl.value = String(data.id);
        nameEl.value = data.item_name ?? "";
        categoryEl.value = String(data.category_id ?? "");
        priceEl.value = String(data.price ?? "");
        if (supplierEl.value.trim() === "" && data.supplier) {
          supplierEl.value = data.supplier;
        }

        hintEl.textContent = "JANから商品情報を補完しました。";
        calcTotal();
      } catch (e) {
        console.error(e);
        hintEl.textContent = "JAN検索でエラーが発生しました。";
        showRegister(jan);
      }
    }, 250);
  };

  janEl.addEventListener("input", lookupByJan);
  janEl.addEventListener("change", lookupByJan);

  document.getElementById("orderForm").addEventListener("submit", (e) => {
    calcTotal();
    if (!itemIdEl.value) {
      e.preventDefault();
      const jan = onlyDigits(janEl.value);
      if (jan.length >= 8) showRegister(jan);
      else hintEl.textContent = "JANを入力してください。";
      janEl.focus();
    }
  });

  // ★ モーダル内の登録処理
  modalForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const formData = new FormData(modalForm);
    const data = Object.fromEntries(formData.entries());

    try {
      const res = await fetch("item_register_save.php", {
        method: "POST",
        body: formData,
      });

      if (res.redirected) {
        closeRegisterModal();
        janEl.value = data.jan_code;
        lookupByJan();
      } else {
        alert("登録に失敗しました");
      }
    } catch (err) {
      console.error(err);
      alert("通信エラーが発生しました");
    }
  });

  // 初期処理
  window.addEventListener("DOMContentLoaded", () => {
    calcTotal();
    const jan = onlyDigits(janEl.value);
    if (jan.length >= 8) {
      lookupByJan();
    } else {
      lockFields(true);
    }
  });

  // グローバル関数にする（HTML内から呼び出し）
  window.closeRegisterModal = closeRegisterModal;
})();
</script>
</body>
</html>
