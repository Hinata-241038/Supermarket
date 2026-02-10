<?php
$dsn  = 'mysql:host=localhost;dbname=supermarketmanager;charset=utf8';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    exit('DB接続エラー: ' . $e->getMessage());
}
