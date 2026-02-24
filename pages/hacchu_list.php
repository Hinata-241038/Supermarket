<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

$sql = "
  SELECT
    o.id,
    o.item_id,
    i.jan_code,
    i.item_name,
    o.order_quantity,
    o.order_date,
    o.status
  FROM orders o
  LEFT JOIN items i ON i.id = o.item_id
  ORDER BY o.order_date DESC, o.id DESC
";
$stmt = $pdo->query($sql);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function statusLabel($status)
{
  switch ((int)$status) {
    case 0: return '未入荷';
    case 1: return '入荷済';
    default: return '不明';
  }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>発注履歴一覧</title>

  <!-- ★キャッシュ対策：更新しても反映されない時の保険 -->
  <link rel="stylesheet" href="../assets/css/hacchu.css?v=1">
</head>

<body class="hacchu-list-page">

  <!-- 左上固定：発注画面に戻る -->
  <a href="hacchu_form.php" class="btn ui-fixed ui-fixed--top-left">戻る</a>

  <div class="container">
    <h1>発注履歴</h1>

    <div class="table-card">
      <table class="orders-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>JAN</th>
            <th>商品名</th>
            <th>数量</th>
            <th>発注日</th>
            <th>状態</th>
            <th>操作</th>
          </tr>
        </thead>

        <tbody>
        <?php if (empty($orders)): ?>
          <tr><td colspan="7" class="empty-row">発注履歴がありません</td></tr>
        <?php else: ?>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><?= h($o['id']) ?></td>
              <td><?= h($o['jan_code'] ?? '') ?></td>
              <td class="td-name"><?= h($o['item_name'] ?? '') ?></td>
              <td><?= h($o['order_quantity']) ?></td>
              <td><?= h($o['order_date']) ?></td>

              <?php $st = (int)$o['status']; ?>
              <td>
                <span class="status-badge <?= $st === 0 ? 'is-wait' : 'is-done' ?>">
                  <?= h(statusLabel($st)) ?>
                </span>
              </td>

              <td>
                <?php if ($st === 0): ?>
                  <a class="btn btn-small" href="nyuka_form.php?order_id=<?= (int)$o['id'] ?>">入荷</a>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 右下固定：発注画面へ -->
  <button class="btn ui-fixed ui-fixed--bottom-right" onclick="location.href='hacchu_form.php'">
    発注画面へ
  </button>

</body>
</html>