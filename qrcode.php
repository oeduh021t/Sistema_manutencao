<?php
// qrcode.php
session_start();

$id = $_GET['id'] ?? null;

if ($id) {
    // Gravamos o link. Note que usamos o p=... do seu sistema
    $_SESSION['url_redirecionamento'] = "index.php?p=historico_equipamento&id=" . $id;
    session_write_close(); // Força o PHP a gravar a sessão no disco agora
}

header("Location: login.php");
exit;
