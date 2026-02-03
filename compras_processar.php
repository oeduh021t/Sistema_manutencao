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
$urgencia       = $_POST['urgencia'] ?? 'Média';

// Tratando a vírgula do valor para ponto decimal
$valor_estimado = (!empty($_POST['valor_estimado'])) ? str_replace(',', '.', $_POST['valor_estimado']) : 0.00;
$motivo         = $_POST['motivo'] ?? '';

// IDs vinculados
$equipamento_id = !empty($_POST['equipamento_id']) ? $_POST['equipamento_id'] : null;
$setor_id       = !empty($_POST['setor_id']) ? $_POST['setor_id'] : null;

$status_inicial = 'Pendente';

try {
    $pdo->beginTransaction(); // Iniciamos uma transação para garantir que tudo salve ou nada salve

    // 1. Inserir a Solicitação Principal
    $sql = "INSERT INTO solicitacoes_compra (
                equipamento_id, setor_id, solicitante_id, item_nome, 
                quantidade, urgencia, valor_estimado, motivo, 
                status, data_solicitacao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $equipamento_id, $setor_id, $solicitante_id, $item_nome,
        $quantidade, $urgencia, $valor_estimado, $motivo, $status_inicial
    ]);

    $solicitacao_id = $pdo->lastInsertId(); // Pegamos o ID gerado para vincular os anexos

    // 2. Processar múltiplos anexos
    if (!empty($_FILES['anexos']['name'][0])) {
        $diretorio = "uploads/compras/";
        
        // Cria a pasta se não existir
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0777, true);
        }

        foreach ($_FILES['anexos']['name'] as $key => $name) {
            $error = $_FILES['anexos']['error'][$key];
            
            if ($error === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['anexos']['tmp_name'][$key];
                $extensao = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                
                // Geramos um nome único para o arquivo não ser sobrescrito
                $novo_nome = "compra_" . $solicitacao_id . "_" . uniqid() . "." . $extensao;

                if (move_uploaded_file($tmp_name, $diretorio . $novo_nome)) {
                    // Salva a referência na tabela de anexos
                    $sql_anexo = "INSERT INTO solicitacoes_compra_anexos (solicitacao_id, arquivo_nome) VALUES (?, ?)";
                    $pdo->prepare($sql_anexo)->execute([$solicitacao_id, $novo_nome]);
                }
            }
        }
    }

    $pdo->commit(); // Finaliza a transação com sucesso

    echo "<script>
            alert('Solicitação de compra e anexos enviados com sucesso!');
            window.location.href='index.php?p=compras_lista';
          </script>";

} catch (PDOException $e) {
    $pdo->rollBack(); // Se der erro, desfaz o que foi inserido no banco
    die("Erro ao salvar no banco de dados: " . $e->getMessage());
} catch (Exception $e) {
    $pdo->rollBack();
    die("Erro geral: " . $e->getMessage());
}
