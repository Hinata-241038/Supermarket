<?php
$host = "localhost";
$user = "root";
$pass = "";        // XAMPP はパスワード空欄
$dbname = "supermarketmanager";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit("DB接続エラー: " . $e->getMessage());
}
?>