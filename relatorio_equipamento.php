<?php
include_once 'includes/db.php';

$id_equipamento = $_GET['id'] ?? null;
if (!$id_equipamento) { die("Equipamento não especificado."); }

// 1. Busca dados do Equipamento e a localização atual
$stmt_eq = $pdo->prepare("
    SELECT e.*, s.nome as nome_setor 
    FROM equipamentos e 
    LEFT JOIN setores s ON e.setor_id = s.id 
    WHERE e.id = ?
");
$stmt_eq->execute([$id_equipamento]);
$eq = $stmt_eq->fetch();

if (!$eq) { die("Equipamento não encontrado."); }

// 2. Busca Manutenções (Chamados)
$stmt_chamados = $pdo->prepare("
    SELECT data_conclusao as data, id as doc_id, titulo as descricao, 
           tecnico_responsavel as responsavel, custo_servico, 'MANUTENÇÃO' as tipo, descricao_solucao as detalhes
    FROM chamados WHERE equipamento_id = ? AND status = 'Concluído'
");
$stmt_chamados->execute([$id_equipamento]);
$res_chamados = $stmt_chamados->fetchAll();

// 3. Busca Movimentações (Trocas/Estoque)
$stmt_mov = $pdo->prepare("
    SELECT m.data_movimentacao as data, m.id as doc_id, m.descricao_log as descricao, 
           m.tecnico_nome as responsavel, 0 as custo_servico, 'LOGÍSTICA' as tipo, 
           CONCAT('Origem: ', s1.nome, ' -> Destino: ', s2.nome) as detalhes
    FROM equipamentos_historico m
    LEFT JOIN setores s1 ON m.setor_origem_id = s1.id
    LEFT JOIN setores s2 ON m.setor_destino_id = s2.id
    WHERE m.equipamento_id = ?
");
$stmt_mov->execute([$id_equipamento]);
$res_movs = $stmt_mov->fetchAll();

// 4. Unifica e ordena por data (Recente para antigo)
$historico_unificado = array_merge($res_chamados, $res_movs);
usort($historico_unificado, function($a, $b) {
    return strtotime($b['data']) - strtotime($a['data']);
});

// 5. Cálculo de Custo Acumulado
$custo_total = array_sum(array_column($res_chamados, 'custo_servico'));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório Técnico - <?= htmlspecialchars($eq['nome']) ?></title>
    <style>
        body { font-family: sans-serif; font-size: 11px; margin: 30px; color: #333; line-height: 1.4; }
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        .ficha-tecnica { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .ficha-tecnica td { padding: 6px; border: 1px solid #ccc; vertical-align: middle; }
        .bg-cinza { background: #f4f4f4; font-weight: bold; width: 120px; text-transform: uppercase; font-size: 10px; }
        .foto-eq { width: 150px; text-align: center; }
        .foto-eq img { max-width: 140px; max-height: 100px; border: 1px solid #ddd; }
        .titulo-secao { background: #000; color: #fff; padding: 5px 10px; font-weight: bold; margin-top: 20px; text-transform: uppercase; }
        .tabela-historico { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .tabela-historico th { background: #eee; border: 1px solid #ccc; padding: 8px; text-align: left; font-size: 10px; }
        .tabela-historico td { border: 1px solid #ccc; padding: 8px; vertical-align: top; }
        .tipo-tag { font-size: 9px; font-weight: bold; padding: 2px 4px; border-radius: 3px; color: #fff; }
        .tag-manutencao { background: #0056b3; }
        .tag-logistica { background: #6c757d; }
        .resumo-financeiro { margin-top: 20px; text-align: right; font-size: 13px; border-top: 2px solid #000; padding-top: 10px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="no-print" style="margin-bottom: 20px; text-align: right;">
    <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; font-weight: bold;">🖨️ Imprimir Prontuário</button>
</div>

<div class="header">
    <div>
        <h1 style="margin:0; font-size: 20px;">PRONTUÁRIO TÉCNICO DE ATIVO</h1>
        <span>Hospital Domingos Lourenço - Engenharia Clínica</span>
    </div>
    <div style="text-align: right;">
        <strong>Patrimônio:</strong> <span style="font-size: 16px;"><?= htmlspecialchars($eq['patrimonio']) ?></span><br>
        <strong>Data Emissão:</strong> <?= date('d/m/Y H:i') ?>
    </div>
</div>

<div class="titulo-secao">Dados de Identificação</div>
<table class="ficha-tecnica">
    <tr>
        <td class="bg-cinza">Ativo:</td>
        <td><strong><?= htmlspecialchars($eq['nome']) ?></strong></td>
        <td rowspan="4" class="foto-eq">
            <?php if ($eq['foto_equipamento']): ?>
                <img src="uploads/<?= $eq['foto_equipamento'] ?>" alt="Foto do Equipamento">
            <?php else: ?>
                <small style="color:#999;">Sem Foto</small>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <td class="bg-cinza">Tipo/Modelo:</td>
        <td><?= htmlspecialchars($eq['tipo_nome'] ?? 'N/A') ?></td>
    </tr>
    <tr>
        <td class="bg-cinza">Nº de Série:</td>
        <td><?= htmlspecialchars($eq['num_serie']) ?: '---' ?></td>
    </tr>
    <tr>
        <td class="bg-cinza">Patrimônio:</td>
        <td><strong><?= htmlspecialchars($eq['patrimonio']) ?></strong></td>
    </tr>
    <tr>
        <td class="bg-cinza">Local Atual:</td>
        <td colspan="2"><?= htmlspecialchars($eq['nome_setor']) ?> | <strong>Status:</strong> <?= $eq['status'] ?></td>
    </tr>
</table>

<div class="titulo-secao">Linha do Tempo (Manutenções e Movimentações)</div>
<table class="tabela-historico">
    <thead>
        <tr>
            <th style="width: 12%;">Data</th>
            <th style="width: 12%;">Categoria</th>
            <th>Descrição do Evento / Detalhes Técnicos</th>
            <th style="width: 20%;">Técnico / Resp.</th>
            <th style="width: 12%;">Custo (R$)</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($historico_unificado)): ?>
            <tr><td colspan="5" style="text-align:center; padding: 20px;">Nenhum registro encontrado para este ativo.</td></tr>
        <?php else: ?>
            <?php foreach ($historico_unificado as $item): 
                $tag_class = ($item['tipo'] == 'MANUTENÇÃO') ? 'tag-manutencao' : 'tag-logistica';
            ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($item['data'])) ?></td>
                    <td><span class="tipo-tag <?= $tag_class ?>"><?= $item['tipo'] ?></span></td>
                    <td>
                        <strong><?= htmlspecialchars($item['descricao']) ?></strong><br>
                        <small style="color: #666;"><?= nl2br(htmlspecialchars($item['detalhes'])) ?></small>
                    </td>
                    <td><?= htmlspecialchars($item['responsavel']) ?></td>
                    <td><?= $item['custo_servico'] > 0 ? number_format($item['custo_servico'], 2, ',', '.') : '---' ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="resumo-financeiro">
    <strong>TOTAL INVESTIDO EM MANUTENÇÃO:</strong> 
    <span style="color: #d32f2f; font-size: 16px; margin-left: 15px;">
        R$ <?= number_format($custo_total, 2, ',', '.') ?>
    </span>
</div>

<div style="margin-top: 50px; font-size: 9px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 10px;">
    Este documento é um registro histórico oficial do ativo patrimonial. Gerado pelo Sistema MNT.
</div>

</body>
</html>
