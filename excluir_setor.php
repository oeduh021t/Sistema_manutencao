<?php
session_start();
include_once 'includes/db.php';

// 1. Segurança: Verifica se é admin
if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: index.php?p=setores&erro=Acesso negado");
    exit;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        $pdo->beginTransaction();

        // 2. Verificar se tem filhos (Sub-setores)
        $stmt_filhos = $pdo->prepare("SELECT COUNT(*) FROM setores WHERE setor_pai_id = ?");
        $stmt_filhos->execute([$id]);
        if ($stmt_filhos->fetchColumn() > 0) {
            throw new Exception("Não é possível excluir: Este setor possui sub-setores vinculados.");
        }

        // 3. Verificar se tem equipamentos vinculados
        $stmt_equip = $pdo->prepare("SELECT COUNT(*) FROM equipamentos WHERE setor_id = ?");
        $stmt_equip->execute([$id]);
        if ($stmt_equip->fetchColumn() > 0) {
            throw new Exception("Não é possível excluir: Existem equipamentos cadastrados neste local.");
        }

        // 4. Executa a exclusão
        $stmt = $pdo->prepare("DELETE FROM setores WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        header("Location: index.php?p=setores");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: index.php?p=setores&erro=" . urlencode($e->getMessage()));
        exit;
    }
}
