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
  <link rel="stylesheet" href="../assets/css/hacchu.css">
</head>

<!-- ★このページ専用のUI調整を当てるため class を追加 -->
<body class="hacchu-list-page">

  <!-- ★左上固定：発注画面に戻る -->
  <a href="hacchu_form.php" class="btn ui-fixed ui-fixed--top-left">戻る</a>

  <div class="container">
    <h1>発注履歴</h1>

    <!-- ★テーブル（CSS側で見た目・余白を管理） -->
    <table class="orders-table">
      <tr>
        <th>ID</th>
        <th>JAN</th>
        <th>商品名</th>
        <th>数量</th>
        <th>発注日</th>
        <th>状態</th>
        <th>操作</th>
      </tr>

      <?php if (empty($orders)): ?>
        <tr><td colspan="7">発注履歴がありません</td></tr>
      <?php else: ?>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><?= htmlspecialchars((string)$o['id'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($o['jan_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($o['item_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)$o['order_quantity'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)$o['order_date'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars(statusLabel($o['status']), ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?php if ((int)$o['status'] === 0): ?>
                <a href="nyuka_form.php?order_id=<?= (int)$o['id'] ?>">入荷</a>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </table>
  </div>

  <!-- ★右下固定：発注画面へ（今のボタンを固定化） -->
  <button class="btn ui-fixed ui-fixed--bottom-right" onclick="location.href='hacchu_form.php'">
    発注画面へ
  </button>

</body>
</html>