<?php
include_once 'includes/db.php';

$id_setor = $_GET['id'] ?? null;
if (!$id_setor) { die("Setor não especificado."); }

// Filtros: Datas e Equipamentos
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01'); 
$data_fim    = $_GET['data_fim']    ?? date('Y-m-t');  
$incluir_equipamentos = isset($_GET['incluir_equipamentos']) && $_GET['incluir_equipamentos'] == '1';

// 1. Mapa de Hierarquia
$setores_mapa = $pdo->query("SELECT id, nome, setor_pai_id FROM setores")->fetchAll(PDO::FETCH_UNIQUE);
if (!isset($setores_mapa[$id_setor])) { die("Setor não encontrado."); }

// 2. Funções de Hierarquia
function getCaminhoCompletoRelatorio($id, $mapa) {
    if (!isset($mapa[$id])) return "";
    $setor = $mapa[$id];
    if (!empty($setor['setor_pai_id']) && isset($mapa[$setor['setor_pai_id']])) {
        return getCaminhoCompletoRelatorio($setor['setor_pai_id'], $mapa) . " > " . $setor['nome'];
    }
    return $setor['nome'];
}

function getTodosFilhos($pai_id, $mapa) {
    $ids = [$pai_id];
    foreach ($mapa as $id => $setor) {
        if ($setor['setor_pai_id'] == $pai_id) {
            $ids = array_merge($ids, getTodosFilhos($id, $mapa));
        }
    }
    return $ids;
}

$ids_alvo = getTodosFilhos($id_setor, $setores_mapa);
$placeholders = implode(',', array_fill(0, count($ids_alvo), '?'));

// 3. Busca Chamados Prediais (Infra)
$sql = "SELECT c.*, s.nome as nome_setor_especifico
        FROM chamados c 
        LEFT JOIN setores s ON c.setor_id = s.id
        WHERE c.setor_id IN ($placeholders) 
        AND c.equipamento_id IS NULL 
        AND DATE(c.data_abertura) BETWEEN ? AND ?
        ORDER BY c.data_abertura DESC";

$params_infra = array_merge($ids_alvo, [$data_inicio, $data_fim]);
$stmt = $pdo->prepare($sql);
$stmt->execute($params_infra);
$chamados_infra = $stmt->fetchAll();

// 4. Busca Detalhada de Equipamentos e Custos Individuais
$equipamentos_detalhe = [];
$manutencoes_equipamentos = [];
$total_custo_equipamentos = 0;

if ($incluir_equipamentos) {
    // Lista os equipamentos atuais do setor (Com Foto e Setor)
    $sql_eq = "SELECT e.*, t.nome as tipo_nome, s.nome as nome_setor_eq
               FROM equipamentos e 
               LEFT JOIN tipos_equipamentos t ON e.tipo_id = t.id
               LEFT JOIN setores s ON e.setor_id = s.id
               WHERE e.setor_id IN ($placeholders)
               ORDER BY e.nome ASC";
    $stmt_eq = $pdo->prepare($sql_eq);
    $stmt_eq->execute($ids_alvo);
    $equipamentos_detalhe = $stmt_eq->fetchAll();

    if (!empty($ids_alvo)) {
        // Busca chamados finalizados com o nome do setor onde o chamado foi aberto
        $sql_detalhe_custo = "
            SELECT c.*, e.nome as nome_equipamento, e.patrimonio, s.nome as nome_setor_chamado
            FROM chamados c
            JOIN equipamentos e ON c.equipamento_id = e.id
            LEFT JOIN setores s ON c.setor_id = s.id
            WHERE e.setor_id IN ($placeholders) 
            AND c.status = 'Concluído' 
            AND c.custo_servico > 0
            AND DATE(c.data_conclusao) BETWEEN ? AND ?
            ORDER BY c.data_conclusao DESC";
        
        $params_eq = array_merge($ids_alvo, [$data_inicio, $data_fim]);
        $stmt_detalhe = $pdo->prepare($sql_detalhe_custo);
        $stmt_detalhe->execute($params_eq);
        $manutencoes_equipamentos = $stmt_detalhe->fetchAll();
        
        foreach($manutencoes_equipamentos as $m) { $total_custo_equipamentos += $m['custo_servico']; }
    }
}

$caminho_identificador = getCaminhoCompletoRelatorio($id_setor, $setores_mapa);
$total_custo_infra = array_sum(array_column($chamados_infra, 'custo_servico'));
$investimento_geral = $total_custo_infra + $total_custo_equipamentos;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório Consolidado - MNT</title>
    <style>
        body { font-family: 'Helvetica', Arial, sans-serif; font-size: 10px; margin: 20px; color: #333; }
        .no-print { background: #f8f9fa; padding: 15px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 6px; }
        .header { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 15px; }
        .titulo-secao { background: #333; color: #fff; padding: 5px 10px; font-weight: bold; margin-top: 20px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f2f2f2; font-size: 8px; }
        .resumo-final { margin-top: 30px; border: 2px solid #198754; background: #f1f8f5; padding: 15px; page-break-inside: avoid; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        .badge-empresa { background: #eee; padding: 2px 4px; border: 1px solid #ccc; border-radius: 3px; font-weight: bold; font-size: 9px; }
        .img-mini { max-width: 40px; max-height: 40px; border-radius: 4px; border: 1px solid #ddd; }
        .col-setor { color: #0d6efd; font-weight: bold; font-size: 9px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="no-print">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
        <input type="hidden" name="id" value="<?= $id_setor ?>">
        <div><label style="display:block; font-size:9px;">Início:</label><input type="date" name="data_inicio" value="<?= $data_inicio ?>"></div>
        <div><label style="display:block; font-size:9px;">Fim:</label><input type="date" name="data_fim" value="<?= $data_fim ?>"></div>
        <label style="cursor: pointer; padding-bottom: 5px;"><input type="checkbox" name="incluir_equipamentos" value="1" <?= $incluir_equipamentos ? 'checked' : '' ?>> Incluir Equipamentos e Fotos</label>
        <button type="submit" style="padding: 5px 15px; background: #333; color: #fff; border: none; cursor: pointer; border-radius: 3px;">Filtrar</button>
        <button type="button" onclick="window.print()" style="padding: 5px 15px; background: #28a745; color: #fff; border: none; cursor: pointer; border-radius: 3px;">Imprimir</button>
    </form>
</div>

<div class="header">
    <table style="border:none; width: 100%;">
        <tr style="border:none;">
            <td style="border:none;">
                <h2 style="margin:0;">RELATÓRIO CONSOLIDADO DE CUSTOS</h2>
                <strong>FILTRO PRINCIPAL:</strong> <?= htmlspecialchars($caminho_identificador) ?><br>
                <strong>PERÍODO:</strong> <?= date('d/m/Y', strtotime($data_inicio)) ?> - <?= date('d/m/Y', strtotime($data_fim)) ?>
            </td>
            <td style="border:none; text-align: right; vertical-align: bottom;">GERADO EM: <?= date('d/m/Y H:i') ?></td>
        </tr>
    </table>
</div>

<div class="titulo-secao">1. Manutenções de Infraestrutura</div>
<table>
    <thead>
        <tr>
            <th style="width: 10%;">Data</th>
            <th style="width: 20%;">Setor Específico</th>
            <th style="width: 30%;">Serviço / OS</th>
            <th style="width: 25%;">Empresa Terceirizada</th>
            <th style="width: 15%;" class="text-right">Custo (R$)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($chamados_infra as $c): ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($c['data_abertura'])) ?></td>
            <td class="col-setor"><?= htmlspecialchars($c['nome_setor_especifico']) ?></td>
            <td><strong><?= htmlspecialchars($c['titulo']) ?></strong><br><small><?= htmlspecialchars($c['descricao_solucao']) ?></small></td>
            <td><span class="badge-empresa"><?= htmlspecialchars($c['empresa_terceirizada'] ?: 'Mão de Obra Interna') ?></span></td>
            <td class="text-right"><?= number_format($c['custo_servico'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($incluir_equipamentos): ?>
    <div class="titulo-secao" style="background: #0056b3;">2. Inventário de Equipamentos do Setor</div>
    <table>
        <thead>
            <tr>
                <th>Foto</th>
                <th>Setor Atual</th>
                <th>Patrimônio</th>
                <th>Equipamento</th>
                <th>Tipo</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($equipamentos_detalhe as $eq): ?>
            <tr>
                <td>
                    <?php if($eq['foto_equipamento']): ?>
                        <img src="uploads/<?= $eq['foto_equipamento'] ?>" class="img-mini">
                    <?php else: ?>
                        <small>-</small>
                    <?php endif; ?>
                </td>
                <td class="col-setor"><?= htmlspecialchars($eq['nome_setor_eq']) ?></td>
                <td><strong><?= $eq['patrimonio'] ?></strong></td>
                <td><?= htmlspecialchars($eq['nome']) ?></td>
                <td><?= htmlspecialchars($eq['tipo_nome']) ?></td>
                <td><?= $eq['status'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="titulo-secao" style="background: #0056b3;">3. Gastos Detalhados por Equipamento</div>
    <table>
        <thead>
            <tr>
                <th style="width: 10%;">Data</th>
                <th style="width: 20%;">Local do Chamado</th>
                <th style="width: 25%;">Equipamento (Patrimônio)</th>
                <th style="width: 20%;">Serviço Executado</th>
                <th style="width: 15%;">Empresa</th>
                <th style="width: 10%;" class="text-right">Custo (R$)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($manutencoes_equipamentos as $m): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($m['data_conclusao'])) ?></td>
                <td class="col-setor"><?= htmlspecialchars($m['nome_setor_chamado']) ?></td>
                <td><?= htmlspecialchars($m['nome_equipamento']) ?> <br><small>(Pat: <?= $m['patrimonio'] ?>)</small></td>
                <td><?= htmlspecialchars($m['titulo']) ?></td>
                <td><span class="badge-empresa"><?= htmlspecialchars($m['empresa_terceirizada'] ?: 'N/D') ?></span></td>
                <td class="text-right"><?= number_format($m['custo_servico'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class="resumo-final">
    <table style="border:none; width: 100%;">
        <tr style="border:none;">
            <td style="border:none; font-size: 11px;">Total em Infraestrutura:</td>
            <td style="border:none;" class="text-right">R$ <?= number_format($total_custo_infra, 2, ',', '.') ?></td>
        </tr>
        <?php if ($incluir_equipamentos): ?>
        <tr style="border:none;">
            <td style="border:none; font-size: 11px;">Total em Manutenção de Equipamentos:</td>
            <td style="border:none;" class="text-right">R$ <?= number_format($total_custo_equipamentos, 2, ',', '.') ?></td>
        </tr>
        <?php endif; ?>
        <tr style="border:none; border-top: 1px solid #198754;">
            <td style="border:none; padding-top: 5px; font-size: 13px;" class="fw-bold">INVESTIMENTO TOTAL NO SETOR:</td>
            <td style="border:none; padding-top: 5px; font-size: 13px;" class="text-right fw-bold">R$ <?= number_format($investimento_geral, 2, ',', '.') ?></td>
        </tr>
    </table>
</div>

</body>
</html>
