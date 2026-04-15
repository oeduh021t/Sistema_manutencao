<?php
// compras_processar.php
session_start();
include_once 'includes/db.php';

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acesso negado.");
}

$solicitante_id = $_SESSION['usuario_id'];
$urgencia       = $_POST['urgencia'] ?? 'Média';
$motivo         = $_POST['motivo'] ?? '';

// IDs vinculados
$equipamento_id = !empty($_POST['equipamento_id']) ? $_POST['equipamento_id'] : null;
$setor_id       = !empty($_POST['setor_id']) ? $_POST['setor_id'] : null;

$status_inicial = 'Pendente';

try {
    $pdo->beginTransaction(); // Garantia de que tudo ou nada será salvo

    // 1. Inserir a "Capa" da Solicitação
    // Removidos item_nome, quantidade e valor_estimado desta tabela pois agora ficam na tabela de itens
    $sql = "INSERT INTO solicitacoes_compra (
                equipamento_id, setor_id, solicitante_id, 
                urgencia, motivo, status, data_solicitacao
            ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $equipamento_id, $setor_id, $solicitante_id, 
        $urgencia, $motivo, $status_inicial
    ]);

    $solicitacao_id = $pdo->lastInsertId(); // ID necessário para vincular itens e anexos

    // 2. Inserir os Itens da Solicitação (Loop nos arrays do formulário)
    if (isset($_POST['item_nome']) && is_array($_POST['item_nome'])) {
        $sql_item = "INSERT INTO solicitacoes_compra_itens (solicitacao_id, descricao, quantidade, valor_estimado) VALUES (?, ?, ?, ?)";
        $stmt_item = $pdo->prepare($sql_item);

        foreach ($_POST['item_nome'] as $key => $nome_item) {
            if (!empty($nome_item)) {
                $qtd   = $_POST['item_qtd'][$key] ?? 1;
                $valor = $_POST['item_valor'][$key] ?? 0;
                // Trata a vírgula para ponto decimal
                $valor_limpo = str_replace(',', '.', $valor);

                $stmt_item->execute([
                    $solicitacao_id, 
                    $nome_item, 
                    $qtd, 
                    $valor_limpo
                ]);
            }
        }
    }

    // 3. Processar múltiplos anexos (Fotos/Orçamentos)
    if (!empty($_FILES['anexos']['name'][0])) {
        $diretorio = "uploads/compras/";
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0777, true);
        }

        foreach ($_FILES['anexos']['name'] as $key => $name) {
            $error = $_FILES['anexos']['error'][$key];
            if ($error === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['anexos']['tmp_name'][$key];
                $extensao = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $novo_nome = "compra_" . $solicitacao_id . "_" . uniqid() . "." . $extensao;

                if (move_uploaded_file($tmp_name, $diretorio . $novo_nome)) {
                    $sql_anexo = "INSERT INTO solicitacoes_compra_anexos (solicitacao_id, arquivo_nome) VALUES (?, ?)";
                    $pdo->prepare($sql_anexo)->execute([$solicitacao_id, $novo_nome]);
                }
            }
        }
    }

    $pdo->commit(); 

    echo "<script>
            alert('Solicitação múltipla enviada com sucesso!');
            window.location.href='index.php?p=compras_lista';
          </script>";

} catch (PDOException $e) {
    $pdo->rollBack();
    die("Erro ao salvar no banco de dados: " . $e->getMessage());
} catch (Exception $e) {
    $pdo->rollBack();
    die("Erro geral: " . $e->getMessage());
}
