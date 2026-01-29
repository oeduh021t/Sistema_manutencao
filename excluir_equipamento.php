<?php
// Removemos o session_start daqui porque o log mostrou que o index.php já inicia a sessão.
include_once 'includes/db.php';

// 1. Segurança: Verifica se é admin (A sessão já vem do index)
if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] !== 'admin') {
    echo "<script>window.location.href='index.php?p=equipamentos';</script>";
    exit;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        $pdo->beginTransaction();

        // 2. Buscar e apagar a foto física
        $stmt = $pdo->prepare("SELECT foto_equipamento FROM equipamentos WHERE id = ?");
        $stmt->execute([$id]);
        $equip = $stmt->fetch();
        
        if ($equip && $equip['foto_equipamento']) {
            $caminho_foto = "uploads/" . $equip['foto_equipamento'];
            if (file_exists($caminho_foto)) {
                @unlink($caminho_foto);
            }
        }

        // 3. LIMPEZA DE DEPENDÊNCIAS
        $pdo->prepare("DELETE FROM chamados_historico WHERE chamado_id IN (SELECT id FROM chamados WHERE equipamento_id = ?)")->execute([$id]);
        $pdo->prepare("DELETE FROM chamados WHERE equipamento_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM equipamentos_historico WHERE equipamento_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM emprestimos WHERE equipamento_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM checklist_climatizacao WHERE equipamento_id = ?")->execute([$id]);

        // 4. Exclui o equipamento principal
        $stmt = $pdo->prepare("DELETE FROM equipamentos WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit(); 

        // REDIRECIONAMENTO VIA JAVASCRIPT (Solução para o erro de Headers)
        echo "<script>window.location.href='index.php?p=equipamentos&msg=excluido';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack(); 
        echo "<script>alert('Erro ao excluir: " . addslashes($e->getMessage()) . "'); window.location.href='index.php?p=equipamentos';</script>";
        exit;
    }
} else {
    echo "<script>window.location.href='index.php?p=equipamentos';</script>";
    exit;
}
