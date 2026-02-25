<?php
session_start();
require_once __DIR__ . '/../dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: user_management.php");
    exit;
}

if (empty($_POST['id']) || empty($_POST['login_id']) || empty($_POST['role'])) {
    exit("入力が不足しています");
}

$id = $_POST['id'];
$login_id = trim($_POST['login_id']);
$role = $_POST['role'];

// 更新処理
$stmt = $pdo->prepare("UPDATE users SET login_id = ?, role = ? WHERE id = ?");
$stmt->execute([$login_id, $role, $id]);

header("Location: user_management.php");
exit;