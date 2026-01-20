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
  const formEl = document.getElementById("orderForm");
  const registerBox = document.getElementById("registerBox");
  const goRegisterBtn = document.getElementById("goRegisterBtn");

  const onlyDigits = (s) => (s || "").replace(/\D/g, "");

  const calcTotal = () => {
    const qty = Number(qtyEl.value || 0);
    const price = Number(priceEl.value || 0);
    const total = qty * price;
    totalEl.value = Number.isFinite(total) ? total : 0;
  };
  qtyEl.addEventListener("input", calcTotal);
  priceEl.addEventListener("input", calcTotal);

  // ★未登録時に入力不可にしたい3項目
  const lockFields = (locked) => {
    nameEl.disabled = locked;
    categoryEl.disabled = locked;
    priceEl.disabled = locked;

    // 見た目も「入力不可」っぽくする（CSSでもOK）
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

  const showRegister = (jan) => {
    hintEl.textContent = "商品が未登録です。商品登録を行ってください。";
    registerBox.style.display = "block";
    goRegisterBtn.href = `item_register.php?jan=${encodeURIComponent(jan)}`;
    lockFields(true); // ★未登録は入力不可
  };

  const hideRegister = () => {
    registerBox.style.display = "none";
    goRegisterBtn.href = "item_register.php";
  };

  // JAN入力開始時に「見た目の値」もクリアして矛盾を防ぐ
  const clearItemUI = () => {
    itemIdEl.value = "";
    nameEl.value = "";
    categoryEl.value = "";
    priceEl.value = "";
    calcTotal();
  };

  let timer = null;

  const lookupByJan = async () => {
    hintEl.textContent = "";
    hideRegister();

    clearTimeout(timer);

    const jan = onlyDigits(janEl.value);

    // まだ短いならロック＆クリア（未登録扱い）
    if (jan.length < 8) {
      clearItemUI();
      lockFields(true);
      return;
    }

    // 入力中は一旦「未確定」扱いにして矛盾を防ぐ
    // （ここでロックにしておくと、手入力で埋められない）
    clearItemUI();
    lockFields(true);

    timer = setTimeout(async () => {
      try {
        const res = await fetch(`/Supermarket/pages/api_item_lookup.php?jan=${encodeURIComponent(jan)}`, {
          headers: { "Accept": "application/json" }
        });

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

        // 登録済み → 解除して補完
        lockFields(false);
        itemIdEl.value = String(data.id);
        nameEl.value = data.item_name ?? "";
        categoryEl.value = String(data.category_id ?? "");
        priceEl.value = String(data.price ?? "");

        // 発注先も未入力なら補完
        if (supplierEl.value.trim() === "" && data.supplier) {
          supplierEl.value = data.supplier;
        }

        hintEl.textContent = "JANから商品情報を補完しました。";
        calcTotal();
      } catch (e) {
        console.error(e);
        hintEl.textContent = "JAN検索でエラーが発生しました（api_item_lookup.php を確認してください）。";
        showRegister(jan);
      }
    }, 250);
  };

  janEl.addEventListener("input", lookupByJan);
  janEl.addEventListener("change", lookupByJan);

  formEl.addEventListener("submit", (e) => {
    calcTotal();
    if (!itemIdEl.value) {
      e.preventDefault();
      const jan = onlyDigits(janEl.value);
      if (jan.length >= 8) showRegister(jan);
      else hintEl.textContent = "JANを入力してください。";
      janEl.focus();
    }
  });

  // 登録画面から戻ってきた（?jan=）時は自動検索
  window.addEventListener("DOMContentLoaded", () => {
    calcTotal();
    const jan = onlyDigits(janEl.value);
    if (jan.length >= 8) {
      lookupByJan();
    } else {
      lockFields(true);
    }
  });
})();
