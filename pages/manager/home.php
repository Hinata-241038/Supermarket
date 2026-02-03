<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'mng') {
    header('Location: ../logu.php');
    exit;
}
?>
<h1>店長ページ</h1>
