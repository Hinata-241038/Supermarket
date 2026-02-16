<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
  $st->execute([':c'=>$column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

$hasConsume = hasColumn($pdo,'stock','consume_date');
$hasBest    = hasColumn($pdo,'stock','best_before_date');
$hasLegacy  = hasColumn($pdo,'stock','expire_date'); // あなたのDBでは必須

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit('不正なアクセス');

$item_id     = (int)($_POST['item_id'] ?? 0);
$item_name   = trim($_POST['item_name'] ?? '');
$category_id = (int)($_POST['category_id'] ?? 0);
$unit        = trim($_POST['unit'] ?? '');
$supplier    = trim($_POST['supplier'] ?? '');
$price       = (int)($_POST['price'] ?? 0);
$quantity    = (int)($_POST['quantity'] ?? 0);

$expire_type = $_POST['expire_type'] ?? 'best';

// 選択された方だけセット
$consume_date = null;
$best_before  = null;

if ($expire_type === 'consume') {
  $consume_date = ($_POST['consume_date'] ?? '');
  $consume_date = ($consume_date !== '') ? $consume_date : null;
} else {
  $best_before = ($_POST['best_before_date'] ?? '');
  $best_before = ($best_before !== '') ? $best_before : null;
}

// ✅ expire_date（NOT NULL）に必ず入れる値
// ルール：選んだ日付を expire_date にコピー
$expire_date = ($expire_type === 'consume') ? $consume_date : $best_before;

// バリデーション（expire_date 必須）
if ($item_id<=0 || $item_name==='' || $category_id<=0 || $unit==='' || $supplier==='' || $price<0 || $quantity<0) {
  exit('入力値が不正です');
}
if (!$expire_date) {
  exit('期限が未入力です（消費期限 or 賞味期限を入力してください）');
}

try{
  $pdo->beginTransaction();

  // items更新
  $sql = "
    UPDATE items
    SET item_name=:name, category_id=:cid, unit=:unit, supplier=:sup, price=:price, updated_at=CURDATE()
    WHERE id=:id
  ";
  $st = $pdo->prepare($sql);
  $st->execute([
    ':name'=>$item_name,
    ':cid'=>$category_id,
    ':unit'=>$unit,
    ':sup'=>$supplier,
    ':price'=>$price,
    ':id'=>$item_id,
  ]);

  // stock：集計方式（1商品=1行）にするため全削除→作り直し
  $pdo->prepare("DELETE FROM stock WHERE item_id=:id")->execute([':id'=>$item_id]);

  // UX：最後に選んだ期限種別をセッションに反映
  $_SESSION['expire_mode'] = ($expire_type==='consume') ? 'consume' : 'best';

  $cols = ['item_id','quantity','created_at','updated_at'];
  $vals = [':item_id',':qty','CURDATE()','CURDATE()'];
  $bind = [
    ':item_id'=>$item_id,
    ':qty'=>$quantity,
  ];

  if ($hasConsume) {
    $cols[]='consume_date';
    $vals[]=':consume';
    $bind[':consume'] = $consume_date; // 選ばれてなければ null
  }
  if ($hasBest) {
    $cols[]='best_before_date';
    $vals[]=':best';
    $bind[':best'] = $best_before; // 選ばれてなければ null
  }

  if ($hasLegacy) {
    // ✅ NOT NULL対策：必ず入れる
    $cols[]='expire_date';
    $vals[]=':expire';
    $bind[':expire'] = $expire_date;
  }

  $sqlIns = "INSERT INTO stock (" . implode(',',$cols) . ") VALUES (" . implode(',',$vals) . ")";
  $st = $pdo->prepare($sqlIns);
  $st->execute($bind);

  $pdo->commit();
  header('Location: zaiko.php');
  exit;

}catch(Throwable $e){
  $pdo->rollBack();
  exit('保存でエラー: ' . $e->getMessage());
}
