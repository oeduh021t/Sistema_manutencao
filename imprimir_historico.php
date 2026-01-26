<?php
include_once 'includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) { die("Equipamento não encontrado."); }

// 1. Busca dados do equipamento com LEFT JOIN (para aceitar se o setor for nulo por algum motivo)
$eq_stmt = $pdo->prepare("SELECT e.*, s.nome as setor_nome FROM equipamentos e LEFT JOIN setores s ON e.setor_id = s.id WHERE e.id = ?");
$eq_stmt->execute([$id]);
$e = $eq_stmt->fetch();

if (!$e) { die("Equipamento inexistente."); }

// 2. Busca Manutenções (Chamados)
$stmt_chamados = $pdo->prepare("
    SELECT id, data_abertura as data, titulo as evento, status, tecnico_responsavel as tecnico, 
           descricao_solucao as detalhes, custo_servico, 'chamado' as tipo 
    FROM chamados WHERE equipamento_id = ?
");
$stmt_chamados->execute([$id]);
$res_chamados = $stmt_chamados->fetchAll();

// 3. Busca Movimentações (Histórico de Trocas/Estoque)
$stmt_mov = $pdo->prepare("
    SELECT m.id, m.data_movimentacao as data, m.descricao_log as evento, m.status_novo as status, 
           m.tecnico_nome as tecnico, '' as detalhes, 0 as custo_servico, 'troca' as tipo, 
           s1.nome as de, s2.nome as para
    FROM equipamentos_historico m
    LEFT JOIN setores s1 ON m.setor_origem_id = s1.id
    LEFT JOIN setores s2 ON m.setor_destino_id = s2.id
    WHERE m.equipamento_id = ?
");
$stmt_mov->execute([$id]);
$res_movs = $stmt_mov->fetchAll();

// 4. Une as listas e ordena pela data mais recente no topo
$logs = array_merge($res_chamados, $res_movs);
usort($logs, function($a, $b) {
    return strtotime($b['data']) - strtotime($a['data']);
});

// 5. Cálculo do custo total acumulado
$custo_total = array_sum(array_column($res_chamados, 'custo_servico'));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório Técnico - <?= $e['patrimonio'] ?></title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: 'Helvetica', Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
        .page { width: 100%; }
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .ficha-tecnica { width: 100%; margin-bottom: 20px; border: 1px solid #000; }
        .ficha-tecnica td { padding: 5px 8px; border: 1px solid #ccc; }
        .label { background: #f0f0f0; font-weight: bold; width: 15%; text-transform: uppercase; font-size: 9px; }
        
        table.dados { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.dados th { background: #333; color: #fff; padding: 8px; text-align: left; font-size: 10px; text-transform: uppercase; }
        table.dados td { border-bottom: 1px solid #eee; padding: 10px 8px; vertical-align: top; }
        
        .tipo-badge { font-size: 9px; font-weight: bold; padding: 2px 5px; border-radius: 3px; color: #fff; }
        .bg-manutencao { background: #0056b3; }
        .bg-logistica { background: #6c757d; }
        
        .footer-custos { margin-top: 20px; text-align: right; font-size: 14px; border-top: 2px solid #000; padding-top: 10px; }
        .img-eq { max-height: 100px; border: 1px solid #ddd; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 10px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 15px; cursor: pointer; font-weight: bold;">🖨️ IMPRIMIR RELATÓRIO</button>
    </div>

    <div class="page">
        <div class="header">
            <table style="width: 100%;">
                <tr>
                    <td>
                        <h2 style="margin: 0;">PRONTUÁRIO DE VIDA DO ATIVO</h2>
                        <span style="font-size: 14px;">Hospital Domingos Lourenço - Setor de Manutenção</span>
                    </td>
                    <td style="text-align: right;">
                        <?php if($e['foto_equipamento']): ?>
                            <img src="uploads/<?= $e['foto_equipamento'] ?>" class="img-eq">
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <table class="ficha-tecnica">
            <tr>
                <td class="label">Equipamento</td><td><?= htmlspecialchars($e['nome']) ?></td>
                <td class="label">Patrimônio</td><td style="font-size: 13px; font-weight: bold;"><?= $e['patrimonio'] ?></td>
            </tr>
            <tr>
                <td class="label">Nº de Série</td><td><?= $e['num_serie'] ?: '---' ?></td>
                <td class="label">Local Atual</td><td><?= $e['setor_nome'] ?></td>
            </tr>
            <tr>
                <td class="label">Status Atual</td><td colspan="3"><?= strtoupper($e['status']) ?></td>
            </tr>
        </table>

        <table class="dados">
            <thead>
                <tr>
                    <th style="width: 12%;">Data</th>
                    <th style="width: 15%;">Categoria</th>
                    <th>Descrição do Evento / Intervenção</th>
                    <th style="width: 18%;">Responsável</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($logs as $l): 
                    $is_chamado = ($l['tipo'] == 'chamado');
                ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($l['data'])) ?></td>
                    <td>
                        <span class="tipo-badge <?= $is_chamado ? 'bg-manutencao' : 'bg-logistica' ?>">
                            <?= $is_chamado ? 'MANUTENÇÃO' : 'LOGÍSTICA' ?>
                        </span>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($l['evento']) ?></strong>
                        <?php if ($is_chamado): ?>
                            <div style="margin-top: 5px; color: #555; font-size: 10px;">
                                <?= nl2br(htmlspecialchars($l['detalhes'])) ?>
                                <?php if($l['custo_servico'] > 0): ?>
                                    <br><strong style="color: #000;">Custo: R$ <?= number_format($l['custo_servico'], 2, ',', '.') ?></strong>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 5px; color: #666; font-size: 10px;">
                                Movimentação de Setor: <?= $l['de'] ?> ➔ <?= $l['para'] ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($l['tecnico']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="footer-custos">
            <strong>INVESTIMENTO TOTAL EM MANUTENÇÃO:</strong> 
            <span style="color: #d32f2f; margin-left: 10px;">R$ <?= number_format($custo_total, 2, ',', '.') ?></span>
        </div>

        <div style="margin-top: 30px; font-size: 9px; color: #888; text-align: center; border-top: 1px solid #eee; padding-top: 5px;">
            Relatório gerado em <?= date('d/m/Y H:i') ?> | Sistema MNT Hospitalar
        </div>
    </div>
</body>
</html>
