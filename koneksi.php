<?php
$host = 'localhost';
$db   = 'db_santrify';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("<h3 style='color:red;'>Koneksi Database Gagal: " . $e->getMessage() . "</h3>");
}
?>