<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'fte') {
    header('Location: ../../logu.php');
    exit;
}
?>
<h1>正社員ページ</h1>

