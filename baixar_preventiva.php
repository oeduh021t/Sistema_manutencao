<?php
// baixar_preventiva.php
session_start(); // OBRIGATÓRIO para ler o nome do técnico logado
include_once 'includes/db.php';

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    die("Acesso negado. Você precisa estar logado.");
}

$id = $_GET['id'] ?? null;
$tecnico = $_SESSION['usuario_nome'] ?? 'Técnico Externo';

if ($id) {
    try {
        // Iniciamos uma transação para garantir que ou faz as duas coisas ou não faz nada
        $pdo->beginTransaction();

        // 1. Atualiza a data da última preventiva para HOJE no cadastro do equipamento
        $stmt = $pdo->prepare("UPDATE equipamentos SET data_ultima_preventiva = CURRENT_DATE WHERE id = ?");
        $stmt->execute([$id]);

        // 2. Registra no histórico/timeline que uma preventiva foi realizada
        // Adicionamos o nome do técnico que veio da sessão
        $stmt_log = $pdo->prepare("
            INSERT INTO equipamentos_historico (equipamento_id, data_movimentacao, descricao_log, status_novo, tecnico_nome) 
            VALUES (?, CURRENT_TIMESTAMP, 'MANUTENÇÃO PREVENTIVA REALIZADA', 'Ativo', ?)
        ");
        $stmt_log->execute([$id, $tecnico]);

        $pdo->commit();

        echo "<script>alert('Manutenção Preventiva registrada com sucesso!'); window.history.back();</script>";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Erro ao registrar: " . $e->getMessage() . "'); window.history.back();</script>";
    }
} else {
    header("Location: index.php");
}
exit;
