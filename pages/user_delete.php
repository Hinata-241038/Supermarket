<?php
session_start();
require_once __DIR__ . '/../dbconnect.php';

if (!empty($_POST['delete_ids'])) {

    $ids = $_POST['delete_ids'];

    /* mngユーザーが含まれているか確認 */
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'mng' AND id IN ($placeholders)");
    $stmt->execute($ids);

    if ($stmt->fetchColumn() > 0) {
        echo "<script>
                alert('店長アカウントは削除できません');
                location.href='user_management.php';
              </script>";
        exit;
    }

    /* 削除処理 */
    $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
    $stmt->execute($ids);
}

header("Location: user_management.php");
exit;
