<?php
session_start();
require_once __DIR__ . '/../dbconnect.php';

if (!empty($_POST['delete_ids'])) {

    $ids = $_POST['delete_ids'];

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
    $stmt->execute($ids);
}

header("Location: user_management.php");
exit;
