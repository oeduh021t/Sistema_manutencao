<?php
// OBRIGATÓRIO iniciar a sessão para ler o nível do usuário
session_start();
include_once 'includes/db.php';

// 1. Segurança: Verifica se está logado e se é admin
if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: index.php?p=equipamentos");
    exit;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        // Iniciamos uma transação para garantir que tudo seja apagado ou nada seja apagado
        $pdo->beginTransaction();

        // 2. Buscar e apagar a foto física da pasta uploads
        $stmt = $pdo->prepare("SELECT foto_equipamento FROM equipamentos WHERE id = ?");
        $stmt->execute([$id]);
        $equip = $stmt->fetch();
        if ($equip && $equip['foto_equipamento']) {
            $caminho_foto = "uploads/" . $equip['foto_equipamento'];
            if (file_exists($caminho_foto)) {
                @unlink($caminho_foto);
            }
        }

        // 3. LIMPEZA DE DEPENDÊNCIAS (Chave Estrangeira)
        // Primeiro apagamos o histórico dos chamados desse equipamento
        $pdo->prepare("DELETE FROM chamados_historico WHERE chamado_id IN (SELECT id FROM chamados WHERE equipamento_id = ?)")->execute([$id]);
        
        // Depois apagamos os chamados vinculados a esse equipamento
        $pdo->prepare("DELETE FROM chamados WHERE equipamento_id = ?")->execute([$id]);

        // 4. Agora sim, excluímos o equipamento
        $stmt = $pdo->prepare("DELETE FROM equipamentos WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit(); // Confirma todas as exclusões
        
    } catch (Exception $e) {
        $pdo->rollBack(); // Se der erro em qualquer passo, desfaz tudo
        die("Erro ao excluir: " . $e->getMessage());
    }
}

header("Location: index.php?p=equipamentos&msg=excluido");
exit;
