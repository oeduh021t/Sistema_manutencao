<?php
include_once 'includes/db.php';

$ids = $_POST['selecionados'] ?? [];
$data_ini = $_POST['data_inicio'] . " 00:00:00";
$data_fim = $_POST['data_fim'] . " 23:59:59";

if (empty($ids)) { die("Erro: Nenhum equipamento foi selecionado para o relatório."); }

$ids_string = implode(',', array_map('intval', $ids));

// Busca dados dos equipamentos
$sql_eq = "SELECT e.*, s.nome as setor, t.nome as tipo 
           FROM equipamentos e 
           LEFT JOIN setores s ON e.setor_id = s.id 
           LEFT JOIN tipos_equipamentos t ON e.tipo_id = t.id 
           WHERE e.id IN ($ids_string) ORDER BY s.nome, e.nome";
$equipamentos = $pdo->query($sql_eq)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Auditoria Técnica</title>
    <style>
        @page { margin: 1cm; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #000; }
        .no-print { display: none; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; word-wrap: break-word; }
        .header-section { background: #f0f0f0; font-weight: bold; font-size: 11px; }
        .row-item { background: #e0e0e0; font-weight: bold; }
        .text-center { text-align: center; }
        .footer { margin-top: 30px; font-size: 9px; text-align: right; }
    </style>
</head>
<body onload="window.print()">

    <div style="text-align: center; margin-bottom: 20px;">
        <h2 style="margin:0;">AUDITORIA DE MANUTENÇÃO E RASTREABILIDADE</h2>
        <p style="margin:5px;">Hospital Domingos Lourenço | Período: <?= date('d/m/Y', strtotime($data_ini)) ?> até <?= date('d/m/Y', strtotime($data_fim)) ?></p>
    </div>

    <table>
        <thead>
            <tr class="header-section text-center">
                <th width="12%">Data/Hora</th>
                <th width="10%">Patr./Série</th>
                <th width="63%">Equipamento / Descrição da Intervenção Técnica</th>
                <th width="15%">Responsável</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($equipamentos as $eq): ?>
                <tr class="row-item">
                    <td colspan="2">ATÍVO: <?= $eq['patrimonio'] ?></td>
                    <td colspan="1"><?= mb_strtoupper($eq['nome']) ?> (<?= $eq['marca'] ?> / <?= $eq['modelo'] ?>)</td>
                    <td>SETOR: <?= $eq['setor'] ?></td>
                </tr>

                <?php
                // Busca histórico filtrado por DATA
                $stmt_h = $pdo->prepare("
                    (SELECT h.data_registro as data, h.texto_historico as descr, h.tecnico_nome as tec, c.tipo_manutencao as t_man
                     FROM chamados_historico h 
                     JOIN chamados c ON h.chamado_id = c.id 
                     WHERE c.equipamento_id = ? AND h.data_registro BETWEEN ? AND ?)
                    UNION 
                    (SELECT m.data_movimentacao, m.descricao_log, m.tecnico_nome, 'LOGÍSTICA'
                     FROM equipamentos_historico m 
                     WHERE m.equipamento_id = ? AND m.data_movimentacao BETWEEN ? AND ?)
                    ORDER BY data DESC
                ");
                $stmt_h->execute([$eq['id'], $data_ini, $data_fim, $eq['id'], $data_ini, $data_fim]);
                $historico = $stmt_h->fetchAll();

                if (empty($historico)): ?>
                    <tr>
                        <td colspan="4" class="text-center" style="color: #666;">Nenhuma atividade registrada neste período.</td>
                    </tr>
                <?php else: 
                    foreach ($historico as $h): ?>
                    <tr>
                        <td class="text-center"><?= date('d/m/Y H:i', strtotime($h['data'])) ?></td>
                        <td><?= $eq['num_serie'] ?></td>
                        <td><strong>[<?= $h['t_man'] ?>]</strong> - <?= htmlspecialchars($h['descr']) ?></td>
                        <td><?= $h['tec'] ?></td>
                    </tr>
                    <?php endforeach; 
                endif; ?>
                <tr><td colspan="4" style="border:none; height:10px;"></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        Documento emitido em <?= date('d/m/Y H:i:s') ?> por Sistema MNT - Engenharia Clínica.
    </div>

</body>
</html>
