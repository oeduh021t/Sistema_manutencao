<?php
// compras_processar.php
session_start();
include_once 'includes/db.php';

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acesso negado.");
}

$solicitante_id = $_SESSION['usuario_id'];
$item_nome      = $_POST['item_nome'] ?? '';
$quantidade     = $_POST['quantidade'] ?? 1;
$urgencia        = $_POST['urgencia'] ?? 'Média';

// Tratando a vírgula do valor para ponto decimal (ex: 10,50 vira 10.50)
$valor_estimado = (!empty($_POST['valor_estimado'])) ? str_replace(',', '.', $_POST['valor_estimado']) : 0.00;
$motivo          = $_POST['motivo'] ?? '';

// IDs vinculados
$equipamento_id = !empty($_POST['equipamento_id']) ? $_POST['equipamento_id'] : null;
$setor_id       = !empty($_POST['setor_id']) ? $_POST['setor_id'] : null;

$status_inicial = 'Pendente';

try {
    // Adicionado setor_id na estrutura do INSERT
    $sql = "INSERT INTO solicitacoes_compra (
                equipamento_id, 
                setor_id, 
                solicitante_id, 
                item_nome, 
                quantidade, 
                urgencia, 
                valor_estimado, 
                motivo, 
                status, 
                data_solicitacao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

    $stmt = $pdo->prepare($sql);
    
    // Passando os parâmetros na ordem correta
    $stmt->execute([
        $equipamento_id,
        $setor_id,
        $solicitante_id,
        $item_nome,
        $quantidade,
        $urgencia,
        $valor_estimado,
        $motivo,
        $status_inicial
    ]);

    echo "<script>
            alert('Solicitação de compra enviada com sucesso!');
            window.location.href='index.php?p=compras_lista';
          </script>";

} catch (PDOException $e) {
    // Caso dê erro de coluna não encontrada, certifique-se de ter rodado o ALTER TABLE enviado anteriormente
    die("Erro ao salvar no banco de dados: " . $e->getMessage());
}
