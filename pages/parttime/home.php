<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ptj') {
    header('Location: ../logu.php');
    exit;
}
?>
<h1>アルバイトページ</h1>
