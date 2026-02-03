<?php
session_start();
include_once 'includes/db.php';

if (!isset($_SESSION['usuario_id'])) {
    die("Acesso negado.");
}

$id = $_POST['id'] ?? $_GET['id'] ?? null;
$tecnico = $_SESSION['usuario_nome'] ?? 'Técnico Externo';

if ($id) {
    try {
        $pdo->beginTransaction();

        // 1. Atualiza a data da última preventiva no cadastro principal do ativo
        $stmt = $pdo->prepare("UPDATE equipamentos SET data_ultima_preventiva = CURRENT_DATE WHERE id = ?");
        $stmt->execute([$id]);

        // 2. Verifica qual tipo de checklist salvar
        if (isset($_POST['capacidade_btu'])) {
            // --- FLUXO AR CONDICIONADO (CLIMATIZAÇÃO) ---
            $stmt_check = $pdo->prepare("
                INSERT INTO checklist_climatizacao (
                    equipamento_id, capacidade_btu, tipo_gas, tipo_periodicidade,
                    filtro_inspecao, obs_filtro_inspecao,
                    filtro_limpeza, obs_filtro_lavagem,
                    filtro_reinstalacao, obs_filtro_reinstalacao,
                    filtro_substituicao, justificativa_filtro,
                    limpeza_bandeja, obs_bandeja,
                    limpeza_dreno, obs_dreno,
                    limpeza_evaporadora, obs_evaporadora,
                    limpeza_condensadora, obs_condensadora,
                    conexoes_eletricas, obs_eletrica,
                    teste_controle, obs_controle,
                    ruidos_vibracoes, obs_ruidos,
                    observacoes_tecnicas, status_final, 
                    tecnico_nome, responsavel_setor,
                    assinatura_tecnico, assinatura_responsavel
                ) VALUES (
                    ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, ?
                )
            ");

            $stmt_check->execute([
                $id,
                $_POST['capacidade_btu'],
                $_POST['tipo_gas'],
                $_POST['periodicidade'],
                $_POST['filtro_inspecao'], $_POST['obs_filtro_inspecao'],
                $_POST['filtro_lavagem'], $_POST['obs_filtro_lavagem'],
                $_POST['filtro_reinstalacao'], $_POST['obs_filtro_reinstalacao'],
                $_POST['filtro_substituicao'], $_POST['justificativa_filtro'],
                $_POST['limpeza_bandeja'], $_POST['obs_bandeja'],
                $_POST['limpeza_dreno'], $_POST['obs_dreno'],
                $_POST['limpeza_evaporadora'], $_POST['obs_evaporadora'],
                $_POST['limpeza_condensadora'], $_POST['obs_condensadora'],
                $_POST['conexoes_eletricas'], $_POST['obs_eletrica'],
                $_POST['teste_controle'], $_POST['obs_controle'],
                $_POST['ruidos_vibracoes'], $_POST['obs_ruidos'],
                $_POST['obs_tecnicas'],
                $_POST['status_final'],
                $tecnico,
                $_POST['responsavel_setor'] ?? 'Não informado',
                $_POST['assinatura_tecnico'],
                $_POST['assinatura_responsavel']
            ]);

            $msg_log = "✅ PMOC/PREVENTIVA REALIZADA - Status: " . $_POST['status_final'];

        } elseif (isset($_POST['ex_tela_inspecao'])) {
            // --- FLUXO EXAUSTÃO ---
            // Mapeando os nomes do seu HTML para as colunas do Banco
            $stmt_exaustao = $pdo->prepare("
                INSERT INTO checklist_exaustao (
                    equipamento_id, tipo_periodicidade, tela_inspecao, tela_lavagem,
                    tela_montagem, tela_substituicao, justificativa_tela, duto_limpeza,
                    corrosao, vibracao, gordura_duto, limpeza_coifa, suportes,
                    eletrica, observacoes_tecnicas, status_final, tecnico_nome,
                    responsavel_setor, assinatura_tecnico, assinatura_responsavel
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt_exaustao->execute([
                $id,
                $_POST['periodicidade'] ?? 'Mensal',
                $_POST['ex_tela_inspecao'],      // vindo do seu HTML name="ex_tela_inspecao"
                $_POST['ex_tela_lavagem'],       // vindo do seu HTML name="ex_tela_lavagem"
                $_POST['ex_tela_montagem'],      // vindo do seu HTML name="ex_tela_montagem"
                $_POST['ex_tela_substituicao'],  // vindo do seu HTML name="ex_tela_substituicao"
                $_POST['justificativa_ex_tela'], // vindo do seu HTML name="justificativa_ex_tela"
                $_POST['duto_limpeza'] ?? 'Ok',  // Parte B (complete no HTML se necessário)
                $_POST['corrosao'] ?? 'Ok',      // Parte B
                $_POST['vibracao'] ?? 'Ok',      // Parte B
                $_POST['gordura_duto'] ?? 'Ok',  // Parte B
                $_POST['limpeza_coifa'] ?? 'Ok',  // Parte B
                $_POST['suportes'] ?? 'Ok',      // Parte B
                $_POST['eletrica'] ?? 'Ok',      // Parte B
                $_POST['obs_tecnicas'],
                $_POST['status_final'],
                $tecnico,
                $_POST['responsavel_setor'] ?? 'Não informado',
                $_POST['assinatura_tecnico'],
                $_POST['assinatura_responsavel']
            ]);

            $msg_log = "✅ PREVENTIVA EXAUSTÃO REALIZADA - Status: " . $_POST['status_final'];

        } else {
            // Caso seja uma preventiva genérica (outros ativos)
            $msg_log = "MANUTENÇÃO PREVENTIVA REALIZADA (PADRÃO)";
        }

        // 3. Registra na Timeline Geral (equipamentos_historico)
        $stmt_log = $pdo->prepare("
            INSERT INTO equipamentos_historico (equipamento_id, data_movimentacao, descricao_log, status_novo, tecnico_nome)
            VALUES (?, CURRENT_TIMESTAMP, ?, 'Ativo', ?)
        ");
        $stmt_log->execute([$id, $msg_log, $tecnico]);

        $pdo->commit();

        echo "<script>alert('Checklist salvo com sucesso!'); window.location.href='index.php?p=historico_equipamento&id=$id';</script>";

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao salvar no banco: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
}
exit;
