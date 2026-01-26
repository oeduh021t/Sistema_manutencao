<?php
session_start();
include_once 'includes/db.php';

// 1. Segurança: Verifica se é admin
if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: index.php?p=tipos_equipamentos&erro=Acesso negado");
    exit;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        $pdo->beginTransaction();

        // 2. Verificar se existem equipamentos vinculados a este tipo
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM equipamentos WHERE tipo_id = ?");
        $stmt_check->execute([$id]);
        
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("Não é possível excluir: Existem equipamentos cadastrados com este tipo.");
        }

        // 3. Executa a exclusão
        $stmt = $pdo->prepare("DELETE FROM tipos_equipamentos WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        header("Location: index.php?p=tipos_equipamentos&msg=excluido");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: index.php?p=tipos_equipamentos&erro=" . urlencode($e->getMessage()));
        exit;
    }
}
