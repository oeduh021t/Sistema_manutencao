<?php
// compras_status.php
session_start();
include_once 'includes/db.php';

if (!isset($_SESSION['usuario_id'])) { die("Acesso negado."); }

$id = $_GET['id'] ?? null;
$acao = $_GET['acao'] ?? null;
$usuario_id = $_SESSION['usuario_id'];

if (!$id || !$acao) {
    header("Location: index.php?p=compras_lista");
    exit;
}

try {
    switch ($acao) {
        case 'financeiro':
            // Financeiro dá ciência e manda para a Diretoria
            $sql = "UPDATE solicitacoes_compra SET 
                    status = 'Financeiro', 
                    user_financeiro_id = ?, 
                    data_financeiro = NOW() 
                    WHERE id = ?";
            $params = [$usuario_id, $id];
            break;

        case 'diretoria':
            // Diretoria autoriza a compra
            $sql = "UPDATE solicitacoes_compra SET 
                    status = 'Diretoria', 
                    user_diretoria_id = ?, 
                    data_diretoria = NOW() 
                    WHERE id = ?";
            $params = [$usuario_id, $id];
            break;

        case 'comprado':
            // Finaliza o processo
            $sql = "UPDATE solicitacoes_compra SET status = 'Comprado' WHERE id = ?";
            $params = [$id];
            break;

        case 'negado':
            // Cancela a solicitação
            $sql = "UPDATE solicitacoes_compra SET status = 'Negado' WHERE id = ?";
            $params = [$id];
            break;

        default:
            die("Ação inválida.");
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo "<script>
            alert('Status atualizado com sucesso!');
            window.location.href='index.php?p=compras_detalhes&id=$id';
          </script>";

} catch (PDOException $e) {
    die("Erro ao atualizar status: " . $e->getMessage());
}
