<?php
session_start();
include_once 'includes/db.php';

// Segurança: Permite admin ou Coordenador (ajustado para ser case-insensitive)
$nivel = isset($_SESSION['usuario_nivel']) ? strtolower($_SESSION['usuario_nivel']) : '';
$niveis_permitidos = ['admin', 'coordenador'];

if (!in_array($nivel, $niveis_permitidos)) {
    header("Location: index.php?p=setores&erro=Acesso negado");
    exit;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $pdo->beginTransaction();

        // Verificações de sub-setores e equipamentos (seus códigos originais)
        $stmt_filhos = $pdo->prepare("SELECT COUNT(*) FROM setores WHERE setor_pai_id = ?");
        $stmt_filhos->execute([$id]);
        if ($stmt_filhos->fetchColumn() > 0) throw new Exception("Possui sub-setores vinculados.");

        $stmt_equip = $pdo->prepare("SELECT COUNT(*) FROM equipamentos WHERE setor_id = ?");
        $stmt_equip->execute([$id]);
        if ($stmt_equip->fetchColumn() > 0) throw new Exception("Existem equipamentos neste local.");

        $stmt = $pdo->prepare("DELETE FROM setores WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        header("Location: index.php?p=setores&msg=excluido");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: index.php?p=setores&erro=" . urlencode($e->getMessage()));
        exit;
    }
}
