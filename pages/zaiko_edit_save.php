<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit('不正なアクセス');

function hasColumn(PDO $pdo, string $table, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
  $st->execute([':c'=>$column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

$hasConsume = hasColumn($pdo,'stock','consume_date');
$hasBest    = hasColumn($pdo,'stock','best_before_date');
$hasLegacy  = hasColumn($pdo,'stock','expire_date');

$item_id    = (int)($_POST['item_id'] ?? 0);
$item_name  = trim($_POST['item_name'] ?? '');
$category_id= (int)($_POST['category_id'] ?? 0);
$unit       = trim($_POST['unit'] ?? '');
$supplier   = trim($_POST['supplier'] ?? '');
$price      = (int)($_POST['price'] ?? 0);
$quantity   = (int)($_POST['quantity'] ?? 0);

$consume_date = $_POST['consume_date'] ?? null;
$best_before  = $_POST['best_before_date'] ?? null;

if ($item_id<=0 || $item_name==='' || $category_id<=0 || $unit==='' || $supplier==='' || $price<0) {
  exit('入力値が不正です');
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

  // stock：重複防止のため一度削除→1行で作り直す
  $pdo->prepare("DELETE FROM stock WHERE item_id=:id")->execute([':id'=>$item_id]);

  // stock INSERT（存在する列だけ入れる）
  $cols = ['item_id','quantity','created_at','updated_at'];
  $vals = [':item_id',':qty','CURDATE()','CURDATE()'];
  $bind = [
    ':item_id'=>$item_id,
    ':qty'=>$quantity,
  ];

  if ($hasConsume) {
    $cols[]='consume_date';
    $vals[]=':consume';
    $bind[':consume'] = ($consume_date !== '' ? $consume_date : null);
  }
  if ($hasBest) {
    $cols[]='best_before_date';
    $vals[]=':best';
    $bind[':best'] = ($best_before !== '' ? $best_before : null);
  }
  if ($hasLegacy) {
    // 互換：best_before_date を expire_date にも入れておく（旧画面があっても壊れない）
    $cols[]='expire_date';
    $vals[]=':legacy';
    $bind[':legacy'] = ($best_before !== '' ? $best_before : null);
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
