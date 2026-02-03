<?php
// Define o fuso horário para o PHP (Brasília)
date_default_timezone_set('America/Sao_Paulo');

$host = 'localhost'; 
$db   = 'sistema_manutencao';
$user = 'manutencao';
$pass = '@TiHmdl#2007$';
$sgbd = 'mysql';

try {
    $pdo = new PDO("$sgbd:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Sincroniza o horário do MySQL com o fuso horário de Brasília
    $pdo->exec("SET time_zone = '-03:00'");
    
} catch (PDOException $e) {
    die("Erro ao conectar: " . $e->getMessage());
}
?>
