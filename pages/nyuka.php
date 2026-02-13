<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit('不正なアクセス');

/* ========= 共通：カラム存在チェック ========= */
function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
  $st->execute([':c' => $column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

$hasConsume = hasColumn($pdo, 'stock', 'consume_date');
$hasBest    = hasColumn($pdo, 'stock', 'best_before_date');
$hasLegacy  = hasColumn($pdo, 'stock', 'expire_date'); // 旧互換

/* ========= POST（後方互換あり） ========= */
$order_id = (int)($_POST['order_id'] ?? 0);
$item_id  = (int)($_POST['item_id'] ?? 0);

// 新フォーム：nyuka_quantity / 旧フォーム：quantity
$quantity = (int)($_POST['nyuka_quantity'] ?? ($_POST['quantity'] ?? 0));

// 新フォーム：expire_type / 旧フォーム：なし（旧は expire_date を expire_date に入れていた想定）
$expireType = ($_POST['expire_type'] ?? 'consume') === 'best' ? 'best' : 'consume';

$expireDate = trim((string)($_POST['expire_date'] ?? ''));
$expireDate = ($expireDate === '') ? null : $expireDate;

if ($order_id <= 0 || $item_id <= 0 || $quantity <= 0) exit('入力値エラー');
if (!$expireDate) exit('期限（日付）が未入力です');

/* ========= サーバ側：今日より前禁止（最重要） ========= */
$today = new DateTime('today');
try {
  $inputDate = new DateTime($expireDate);
} catch (Exception $e) {
  exit('日付形式が不正です');
}
$inputDate->setTime(0,0,0);

if ($inputDate < $today) {
  exit('期限は今日以降の日付を指定してください');
}

/* ========= 期限の振り分け（テーブルに合わせて） ========= */
$consume = null;
$best    = null;
$legacy  = null;

if ($hasConsume || $hasBest) {
  if ($expireType === 'consume') $consume = $expireDate;
  else                          $best    = $expireDate;
} else {
  // stock に consume/best が無い → 旧 expire_date に入れる
  if ($hasLegacy) $legacy = $expireDate;
  else {
    // どの期限カラムも無いなら、ここで落とす（DB設計上あり得ない）
    exit('stockテーブルに期限カラムが見つかりません');
  }
}

/* ========= トランザクション強化 =========
   - orders を FOR UPDATE ロック
   - status=0 であることをトラン内で再確認
   - item_id も一致確認（改ざん/誤送信対策）
====================================== */
try {
  $pdo->beginTransaction();

  // 1) 発注行ロック & 検証
  $st = $pdo->prepare("
    SELECT id, item_id, order_quantity, status
    FROM orders
    WHERE id = :id
    FOR UPDATE
  ");
  $st->execute([':id' => $order_id]);
  $ord = $st->fetch(PDO::FETCH_ASSOC);

  if (!$ord) {
    $pdo->rollBack();
    exit('発注が見つかりません');
  }
  if ((int)$ord['status'] !== 0) {
    $pdo->rollBack();
    exit('すでに入荷済の発注です（重複処理を防止しました）');
  }
  if ((int)$ord['item_id'] !== $item_id) {
    $pdo->rollBack();
    exit('商品情報が一致しません（改ざん/誤送信の可能性）');
  }

  // 2) 在庫更新（存在すれば加算、期限は「より早い期限」を維持）
  if ($hasConsume || $hasBest) {
    $sql = "
      INSERT INTO stock (item_id, quantity, consume_date, best_before_date, created_at, updated_at)
      VALUES (:item_id, :qty, :consume, :best, CURDATE(), CURDATE())
      ON DUPLICATE KEY UPDATE
        quantity = quantity + :qty,
        consume_date = CASE
          WHEN :consume IS NULL THEN consume_date
          WHEN consume_date IS NULL THEN :consume
          ELSE LEAST(consume_date, :consume)
        END,
        best_before_date = CASE
          WHEN :best IS NULL THEN best_before_date
          WHEN best_before_date IS NULL THEN :best
          ELSE LEAST(best_before_date, :best)
        END,
        updated_at = CURDATE()
    ";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':item_id'  => $item_id,
      ':qty'      => $quantity,
      ':consume'  => $consume,
      ':best'     => $best,
    ]);
  } else {
    // 旧互換：expire_date
    $sql = "
      INSERT INTO stock (item_id, quantity, expire_date, created_at, updated_at)
      VALUES (:item_id, :qty, :expire, CURDATE(), CURDATE())
      ON DUPLICATE KEY UPDATE
        quantity = quantity + :qty,
        expire_date = CASE
          WHEN :expire IS NULL THEN expire_date
          WHEN expire_date IS NULL THEN :expire
          ELSE LEAST(expire_date, :expire)
        END,
        updated_at = CURDATE()
    ";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':item_id' => $item_id,
      ':qty'     => $quantity,
      ':expire'  => $legacy,
    ]);
  }

  // 3) 発注ステータス更新
  $st = $pdo->prepare("UPDATE orders SET status = 1, updated_at = CURDATE() WHERE id = :id");
  $st->execute([':id' => $order_id]);

  $pdo->commit();

} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  exit('入荷処理に失敗しました');
}

header('Location: hacchu_list.php');
exit;
