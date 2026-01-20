<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

/* ========= Ajax: 廃棄確定（モーダル内の「廃棄確定」） ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dispose') {
    header('Content-Type: application/json; charset=UTF-8');

    $itemId = $_POST['item_id'] ?? '';
    $itemId = preg_replace('/\D/', '', (string)$itemId);

    if ($itemId === '') {
        echo json_encode(['ok' => false, 'message' => 'item_idが不正です']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 在庫をロックして取得
        $stmt = $pdo->prepare("SELECT quantity FROM stock WHERE item_id = :item_id FOR UPDATE");
        $stmt->execute([':item_id' => $itemId]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'message' => '在庫が見つかりません']);
            exit;
        }

        $qty = (int)$stock['quantity'];
        if ($qty <= 0) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'message' => '在庫が0のため廃棄できません']);
            exit;
        }

        // disposalへ記録
        $stmt = $pdo->prepare("
            INSERT INTO disposal (item_id, disposal_quantity, reason, disposal_date, created_at)
            VALUES (:item_id, :qty, :reason, CURDATE(), CURDATE())
        ");
        $stmt->execute([
            ':item_id' => $itemId,
            ':qty'     => $qty,
            ':reason'  => '廃棄',
        ]);

        // 在庫を0に（在庫一覧から消したいなら quantity=0 を条件に非表示でもOK）
        $stmt = $pdo->prepare("UPDATE stock SET quantity = 0, updated_at = CURDATE() WHERE item_id = :item_id");
        $stmt->execute([':item_id' => $itemId]);

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'message' => '廃棄しました',
            'removed_item_id' => $itemId,
            'new_quantity' => 0
        ]);
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'message' => 'DBエラー: ' . $e->getMessage()]);
        exit;
    }
}

/* ========= 通常表示 ========= */
$keyword = $_GET['keyword'] ?? '';

$sql = "
SELECT
    i.id AS item_id,
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
$stmt->execute([':keyword' => '%' . $keyword . '%']);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = new DateTime('today');
$soonLimit = (new DateTime('today'))->modify('+7 days');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>在庫</title>
    <link rel="stylesheet" href="../assets/css/zaiko.css">

    <!-- モーダル用（CSSファイルを触らず追加） -->
    <style>
      .modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;}
      .modal{display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:min(820px,94vw);background:#fff;border-radius:12px;z-index:1000;padding:18px 18px 14px;box-shadow:0 10px 30px rgba(0,0,0,.25);}
      .modal h3{margin:0 0 10px;font-size:18px;}
      .modal .desc{margin:0 0 14px;color:#333;line-height:1.6;}
      .modal .btns{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;}
      .modal button, .modal a.btnlink{padding:10px 14px;border-radius:10px;border:1px solid #ccc;background:#fff;cursor:pointer;text-decoration:none;color:#111;display:inline-flex;align-items:center;justify-content:center;}
      .modal button.primary{border:none;background:#1976d2;color:#fff;}
      .modal button.danger{border:none;background:#d32f2f;color:#fff;}
      .modal button:disabled{opacity:.6;cursor:not-allowed;}
      .mini-table{width:100%;border-collapse:collapse;margin-top:10px;}
      .mini-table th,.mini-table td{border:1px solid #ddd;padding:8px;text-align:left;font-size:14px;}
      .step2{display:none;}
    </style>
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
    <button type="button" class="order-btn" onclick="location.href='shouhintuika.php'">追加</button>
</form>

<table class="item-table" id="zaikoTable">
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
            $isZeroStock = ((int)$item['stock_quantity'] === 0);

            $expireClass = '';
            $expireLabel = $item['expire_date'] ?? null;
            $expireRaw   = $item['expire_date'] ?? '';

            if (!empty($expireLabel)) {
                $expireDate = DateTime::createFromFormat('Y-m-d', $expireLabel);
                if ($expireDate instanceof DateTime) {
                    if ($expireDate < $today) {
                        $expireClass = 'expire-over';
                        $expireLabel = '⚠ 期限切れ（' . $expireDate->format('Y-m-d') . '）';
                    } elseif ($expireDate <= $soonLimit) {
                        $expireClass = 'expire-soon';
                        $expireLabel = '⚠ 期限間近（' . $expireDate->format('Y-m-d') . '）';
                    } else {
                        $expireLabel = $expireDate->format('Y-m-d');
                    }
                }
            } else {
                $expireLabel = '-';
            }

            $rowClass = '';
            if ($expireClass === 'expire-over') $rowClass = 'row-expire-over';
            if ($expireClass === 'expire-soon') $rowClass = 'row-expire-soon';
        ?>

        <tr class="<?= htmlspecialchars($rowClass, ENT_QUOTES, 'UTF-8') ?>"
            data-item-id="<?= htmlspecialchars($item['item_id'], ENT_QUOTES, 'UTF-8') ?>">
            <td><?= htmlspecialchars($item['jan_code'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($item['category_label_ja'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($item['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>

            <td class="<?= htmlspecialchars($expireClass, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($expireLabel, ENT_QUOTES, 'UTF-8') ?>
            </td>

            <td class="js-stock-cell <?= $isZeroStock ? 'stock-zero' : '' ?>">
                <?= htmlspecialchars((string)$item['stock_quantity'], ENT_QUOTES, 'UTF-8') ?>
            </td>

            <td>
                <a href="shouhintuika.php?item_id=<?= urlencode($item['item_id']) ?>">編集</a>
                /
                <button type="button"
                        class="js-open-zaiko-dispose"
                        data-item-id="<?= htmlspecialchars($item['item_id'], ENT_QUOTES, 'UTF-8') ?>"
                        data-jan="<?= htmlspecialchars($item['jan_code'], ENT_QUOTES, 'UTF-8') ?>"
                        data-name="<?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?>"
                        data-cat="<?= htmlspecialchars($item['category_label_ja'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        data-unit="<?= htmlspecialchars($item['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        data-expire="<?= htmlspecialchars($expireRaw, ENT_QUOTES, 'UTF-8') ?>"
                        data-stock="<?= htmlspecialchars((string)$item['stock_quantity'], ENT_QUOTES, 'UTF-8') ?>">
                    廃棄
                </button>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<!-- モーダル -->
<div class="modal-backdrop" id="modalBackdrop"></div>
<div class="modal" id="zaikoModal" aria-hidden="true">

  <!-- step1: 確認 -->
  <div class="step1" id="step1">
    <h3>廃棄画面に表示しますか？</h3>
    <p class="desc" id="step1Desc">---</p>
    <div class="btns">
      <button type="button" id="btnNo">NO</button>
      <button type="button" class="primary" id="btnOk">OK</button>
    </div>
  </div>

  <!-- step2: モーダル廃棄画面（テーブル表示＋廃棄確定） -->
  <div class="step2" id="step2">
    <h3>廃棄（モーダル画面）</h3>
    <p class="desc">選択した行を表示しました。廃棄確定しますか？</p>
    <div id="tableArea"></div>

    <div class="btns" style="margin-top:12px;">
      <button type="button" id="btnBack2">戻る</button>
      <button type="button" id="btnClose2">閉じる</button>
      <button type="button" class="danger" id="btnDispose2">廃棄確定</button>
    </div>
  </div>
</div>

<script>
(() => {
  const backdrop = document.getElementById('modalBackdrop');
  const modal = document.getElementById('zaikoModal');

  const step1 = document.getElementById('step1');
  const step2 = document.getElementById('step2');

  const step1Desc = document.getElementById('step1Desc');
  const tableArea = document.getElementById('tableArea');

  const btnNo = document.getElementById('btnNo');
  const btnOk = document.getElementById('btnOk');

  const btnBack2 = document.getElementById('btnBack2');
  const btnClose2 = document.getElementById('btnClose2');
  const btnDispose2 = document.getElementById('btnDispose2');

  let selected = null;
  let selectedRowEl = null;

  function escapeHtml(str) {
    return String(str ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  function openModal(data, rowEl) {
    selected = data;
    selectedRowEl = rowEl;

    // 初期はstep1
    step1.style.display = 'block';
    step2.style.display = 'none';

    step1Desc.textContent = `「${data.name}」を廃棄対象としてモーダルに表示します。よろしいですか？`;

    backdrop.style.display = 'block';
    modal.style.display = 'block';
    modal.setAttribute('aria-hidden', 'false');

    btnOk.disabled = false;
    btnNo.disabled = false;
    btnDispose2.disabled = false;
  }

  function closeModal() {
    backdrop.style.display = 'none';
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');

    selected = null;
    selectedRowEl = null;
  }

  function showStep2() {
    if (!selected) return;

    // テーブルをモーダルに「映す」
    const expire = selected.expire ? selected.expire : '-';
    tableArea.innerHTML = `
      <table class="mini-table">
        <tr>
          <th>JAN</th><th>商品名</th><th>カテゴリ</th><th>単位</th><th>期限</th><th>在庫</th>
        </tr>
        <tr>
          <td>${escapeHtml(selected.jan)}</td>
          <td>${escapeHtml(selected.name)}</td>
          <td>${escapeHtml(selected.cat)}</td>
          <td>${escapeHtml(selected.unit)}</td>
          <td>${escapeHtml(expire)}</td>
          <td>${escapeHtml(selected.stock)}</td>
        </tr>
      </table>
    `;

    step1.style.display = 'none';
    step2.style.display = 'block';
  }

  // 廃棄確定（DB更新）
  async function disposeNow() {
    if (!selected) return;

    btnDispose2.disabled = true;
    btnBack2.disabled = true;
    btnClose2.disabled = true;

    try {
      const body = new URLSearchParams();
      body.append('action', 'dispose');
      body.append('item_id', selected.item_id);

      const res = await fetch('zaiko.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
        body
      });

      const data = await res.json();

      if (!data.ok) {
        alert(data.message || '廃棄に失敗しました');
        btnDispose2.disabled = false;
        btnBack2.disabled = false;
        btnClose2.disabled = false;
        return;
      }

      // 画面反映：行を消す（または在庫0にする）
      if (selectedRowEl) {
        // 「消す」挙動
        selectedRowEl.remove();

        // もし「消さずに在庫0表示」にしたい場合は上をコメントアウトして下を使う
        // const cell = selectedRowEl.querySelector('.js-stock-cell');
        // if (cell) {
        //   cell.textContent = '0';
        //   cell.classList.add('stock-zero');
        // }
      }

      closeModal();

    } catch (e) {
      alert('通信エラーが発生しました');
      btnDispose2.disabled = false;
      btnBack2.disabled = false;
      btnClose2.disabled = false;
    }
  }

  // 廃棄ボタン押下でstep1モーダル
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-open-zaiko-dispose');
    if (!btn) return;

    const rowEl = btn.closest('tr');

    openModal({
      item_id: btn.dataset.itemId,
      jan: btn.dataset.jan,
      name: btn.dataset.name,
      cat: btn.dataset.cat,
      unit: btn.dataset.unit,
      expire: btn.dataset.expire,
      stock: btn.dataset.stock
    }, rowEl);
  });

  // 閉じる系
  backdrop.addEventListener('click', closeModal);
  btnNo.addEventListener('click', closeModal);
  btnClose2.addEventListener('click', closeModal);

  // step遷移
  btnOk.addEventListener('click', showStep2);
  btnBack2.addEventListener('click', () => {
    step2.style.display = 'none';
    step1.style.display = 'block';
  });

  // 廃棄確定
  btnDispose2.addEventListener('click', disposeNow);
})();
</script>

</body>
</html>
