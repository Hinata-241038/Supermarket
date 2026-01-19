<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです');
}

// 入力取得
$item_id    = (int)($_POST['item_id'] ?? 0);
$jan_code   = trim((string)($_POST['jan_code'] ?? ''));
$item_name  = trim((string)($_POST['item_name'] ?? ''));
$category_id= (int)($_POST['category_id'] ?? 0);
$unit       = trim((string)($_POST['unit'] ?? ''));

// バリデーション（必要最低限 + 実務的）
$errors = [];

if ($jan_code === '') $errors[] = 'JANが未入力です';
if ($item_name === '') $errors[] = '商品名が未入力です';
if ($category_id <= 0) $errors[] = 'カテゴリを選択してください';

// JANは数字のみ（必要ならハイフン許容に変更可）
if ($jan_code !== '' && !preg_match('/^\d{8,14}$/', $jan_code)) {
    $errors[] = 'JANは8〜14桁の数字で入力してください';
}

if (mb_strlen($item_name) > 100) $errors[] = '商品名が長すぎます（最大100文字目安）';
if (mb_strlen($unit) > 20) $errors[] = '単位が長すぎます（最大20文字目安）';

if ($errors) {
    // 簡易的にGET msgで返す（本格運用はセッション推奨）
    $msg = implode(' / ', $errors);
    $back = ($item_id > 0) ? "shouhintuika.php?item_id={$item_id}&msg=" . urlencode($msg)
                           : "shouhintuika.php?msg=" . urlencode($msg);
    header("Location: {$back}");
    exit;
}

try {
    $pdo->beginTransaction();

    // JAN重複チェック
    if ($item_id > 0) {
        // 編集：自分以外で同一JANが存在しないか
        $dupSql = "SELECT id FROM items WHERE jan_code = :jan_code AND id <> :id LIMIT 1";
        $dupStmt = $pdo->prepare($dupSql);
        $dupStmt->execute([
            ':jan_code' => $jan_code,
            ':id' => $item_id
        ]);
    } else {
        // 新規：同一JANが存在しないか
        $dupSql = "SELECT id FROM items WHERE jan_code = :jan_code LIMIT 1";
        $dupStmt = $pdo->prepare($dupSql);
        $dupStmt->execute([
            ':jan_code' => $jan_code
        ]);
    }

    $dup = $dupStmt->fetch(PDO::FETCH_ASSOC);
    if ($dup) {
        $pdo->rollBack();
        $msg = '同じJANの商品が既に登録されています';
        $back = ($item_id > 0) ? "shouhintuika.php?item_id={$item_id}&msg=" . urlencode($msg)
                               : "shouhintuika.php?msg=" . urlencode($msg);
        header("Location: {$back}");
        exit;
    }

    if ($item_id > 0) {
        // UPDATE（編集）
        $sql = "
            UPDATE items
            SET
                jan_code = :jan_code,
                item_name = :item_name,
                category_id = :category_id,
                unit = :unit,
                updated_at = NOW()
            WHERE id = :id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':jan_code' => $jan_code,
            ':item_name' => $item_name,
            ':category_id' => $category_id,
            ':unit' => $unit,
            ':id' => $item_id
        ]);

        $pdo->commit();
        header("Location: shouhintuika.php?item_id={$item_id}&msg=" . urlencode('更新しました'));
        exit;

    } else {
        // INSERT（新規）
        $sql = "
            INSERT INTO items
                (jan_code, item_name, category_id, unit, created_at, updated_at)
            VALUES
                (:jan_code, :item_name, :category_id, :unit, NOW(), NOW())
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':jan_code' => $jan_code,
            ':item_name' => $item_name,
            ':category_id' => $category_id,
            ':unit' => $unit
        ]);

        $newId = (int)$pdo->lastInsertId();

        $pdo->commit();
        header("Location: shouhintuika.php?item_id={$newId}&msg=" . urlencode('登録しました'));
        exit;
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    exit('保存に失敗しました：' . $e->getMessage());
}
