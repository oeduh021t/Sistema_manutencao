<?php
$host = 'localhost'; // Ou o IP do servidor se for externo
$db   = 'sistema_manutencao';
$user = 'admin';
$pass = '123';
$sgbd = 'mysql';

try {
    $pdo = new PDO("$sgbd:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao conectar: " . $e->getMessage());
}
?>
