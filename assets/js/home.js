// 商品情報ボタン
document.getElementById("shouhinBtn").addEventListener("click", function (e) {
    e.preventDefault();
    window.location.href = "shouhin.html"; // 必要に応じて変更
});

// 在庫情報ボタン
document.getElementById("zaikoBtn").addEventListener("click", function (e) {
    e.preventDefault();
    window.location.href = "zaiko.html";
});
