<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

/* ========= Ajax: 廃棄確定（OK） ========= */
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

        // 現在在庫取得
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

        // 在庫を0に
        $stmt = $pdo->prepare("UPDATE stock SET quantity = 0, updated_at = CURDATE() WHERE item_id = :item_id");
        $stmt->execute([':item_id' => $itemId]);

        $pdo->commit();
        echo json_encode(['ok' => true, 'message' => '廃棄しました', 'removed_item_id' => $itemId]);
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'message' => 'DBエラー: ' . $e->getMessage()]);
        exit;
    }
}

/* ========= 通常表示 ========= */
$type = $_GET['type'] ?? 'use';

$sql = "
SELECT
    i.id AS item_id,
    i.jan_code,
    i.item_name,
    s.quantity,
    s.expire_date
FROM stock s
JOIN items i ON s.item_id = i.id
WHERE IFNULL(s.quantity,0) > 0
ORDER BY s.expire_date
";
$stmt = $pdo->query($sql);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = new DateTime('today');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>廃棄管理</title>
    <link rel="stylesheet" href="../assets/css/haiki.css">

    <!-- モーダル用（CSSファイルを触らずに追加） -->
    <style>
      .modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;}
      .modal{display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:min(520px,92vw);background:#fff;border-radius:12px;z-index:1000;padding:18px 18px 14px;box-shadow:0 10px 30px rgba(0,0,0,.25);}
      .modal h3{margin:0 0 10px;font-size:18px;}
      .modal .desc{margin:0 0 14px;color:#333;line-height:1.6;}
      .modal .btns{display:flex;gap:10px;justify-content:flex-end;}
      .modal button{padding:10px 14px;border-radius:10px;border:1px solid #ccc;background:#fff;cursor:pointer;}
      .modal button.primary{border:none;background:#1976d2;color:#fff;}
      .modal button:disabled{opacity:.6;cursor:not-allowed;}
    </style>
</head>
<body>

<button class="back-btn" onclick="location.href='home.php'">戻る</button>

<div class="container">
    <h1>廃棄管理</h1>

    <form method="get" class="switch-area">
        <label>
            <input type="radio" name="type" value="use"
                <?= $type === 'use' ? 'checked' : '' ?>
                onchange="this.form.submit()">
            消費期限
        </label>

        <label>
            <input type="radio" name="type" value="best"
                <?= $type === 'best' ? 'checked' : '' ?>
                onchange="this.form.submit()">
            賞味期限
        </label>
    </form>

    <table class="item-table" id="haikiTable">
        <tr>
            <th>JAN</th>
            <th>商品名</th>
            <th>数量</th>
            <th><?= $type === 'best' ? '賞味期限' : '消費期限' ?></th>
            <th>判定</th>
            <th>操作</th>
        </tr>

        <?php if (empty($list)): ?>
            <tr>
                <td colspan="6">表示するデータがありません</td>
            </tr>
        <?php else: ?>
            <?php foreach ($list as $row): ?>
                <?php
                    $expireStr = $row['expire_date'] ?? '';
                    $rowClass  = '';
                    $judgeText = '不明';

                    if ($expireStr !== '') {
                        $expire = new DateTime($expireStr);
                        if ($expire < $today) {
                            $rowClass = 'expired';
                            $judgeText = '期限切れ';
                        } else {
                            $diffDays = (int)$today->diff($expire)->days;
                            if ($diffDays <= 3) {
                                $rowClass = 'warning';
                                $judgeText = '期限間近';
                            } else {
                                $judgeText = 'OK';
                            }
                        }
                    }
                ?>
                <tr class="<?= htmlspecialchars($rowClass, ENT_QUOTES, 'UTF-8') ?>"
                    data-item-id="<?= htmlspecialchars($row['item_id'], ENT_QUOTES, 'UTF-8') ?>">
                    <td><?= htmlspecialchars($row['jan_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($row['quantity'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['expire_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($judgeText, ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <button type="button"
                                class="discard-btn js-open-dispose"
                                data-item-id="<?= htmlspecialchars($row['item_id'], ENT_QUOTES, 'UTF-8') ?>"
                                data-item-name="<?= htmlspecialchars($row['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-qty="<?= htmlspecialchars((string)($row['quantity'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            廃棄
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</div>

<!-- モーダル -->
<div class="modal-backdrop" id="modalBackdrop"></div>
<div class="modal" id="disposeModal" aria-hidden="true">
  <h3>廃棄しますか？</h3>
  <p class="desc" id="modalDesc">---</p>
  <div class="btns">
    <button type="button" id="btnNo">NO</button>
    <button type="button" class="primary" id="btnOk">OK</button>
  </div>
</div>

<script>
(() => {
  const backdrop = document.getElementById('modalBackdrop');
  const modal = document.getElementById('disposeModal');
  const desc = document.getElementById('modalDesc');
  const btnNo = document.getElementById('btnNo');
  const btnOk = document.getElementById('btnOk');

  let currentItemId = null;
  let currentRowEl = null;

  function openModal({itemId, itemName, qty, rowEl}) {
    currentItemId = itemId;
    currentRowEl = rowEl;
    desc.textContent = `「${itemName}」を ${qty} 個 廃棄します。よろしいですか？`;
    backdrop.style.display = 'block';
    modal.style.display = 'block';
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeModal() {
    backdrop.style.display = 'none';
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    btnOk.disabled = false;
    btnNo.disabled = false;
    currentItemId = null;
    currentRowEl = null;
  }

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-open-dispose');
    if (!btn) return;

    const itemId = btn.dataset.itemId;
    const itemName = btn.dataset.itemName || '';
    const qty = btn.dataset.qty || '';
    const rowEl = btn.closest('tr');

    openModal({itemId, itemName, qty, rowEl});
  });

  backdrop.addEventListener('click', closeModal);
  btnNo.addEventListener('click', closeModal);

  btnOk.addEventListener('click', async () => {
    if (!currentItemId) return;

    btnOk.disabled = true;
    btnNo.disabled = true;

    try {
      const body = new URLSearchParams();
      body.append('action', 'dispose');
      body.append('item_id', currentItemId);

      const res = await fetch('haiki.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
        body
      });

      const data = await res.json();

      if (!data.ok) {
        alert(data.message || '廃棄に失敗しました');
        closeModal();
        return;
      }

      // 画面から行を消す
      if (currentRowEl) currentRowEl.remove();
      closeModal();

    } catch (err) {
      alert('通信エラーが発生しました');
      closeModal();
    }
  });
})();
</script>

</body>
</html>
