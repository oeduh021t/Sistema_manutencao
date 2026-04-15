<?php
require_once 'includes/db.php';

if (isset($_POST['salvar_nota'])) {
    $id = $_POST['computador_id'];
    $nota = $_POST['texto_nota'];
    $autor = "Eduardo Nascimento"; // Aqui você pode pegar do $_SESSION['usuario']

    $stmt = $pdo->prepare("INSERT INTO ti_inventario_notas (computador_id, texto_nota, usuario_autor) VALUES (?, ?, ?)");
    $stmt->execute([$id, $nota, $autor]);

      header("Location: index.php?p=ti_detalhes&id=$id");
}
