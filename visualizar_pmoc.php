<?php
include_once 'includes/db.php';

$id_check = $_GET['id_check'] ?? null;

if (!$id_check) { die("Checklist não encontrado."); }

// Busca os dados do Checklist e do Equipamento vinculado
$stmt = $pdo->prepare("
    SELECT c.*, e.nome, e.modelo, e.patrimonio, e.num_serie, s.nome as setor_nome 
    FROM checklist_climatizacao c
    JOIN equipamentos e ON c.equipamento_id = e.id
    JOIN setores s ON e.setor_id = s.id
    WHERE c.id = ?
");
$stmt->execute([$id_check]);
$d = $stmt->fetch();

if (!$d) { die("Dados não localizados."); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>PMOC - HMDL - <?= $d['patrimonio'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f4f4; font-size: 12px; }
        .folha-a4 { background: white; width: 210mm; margin: 20px auto; padding: 15mm; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header-pmoc { border: 2px solid #000; padding: 10px; margin-bottom: 20px; }
        .table-check td { padding: 4px !important; }
        .signature-box { border-bottom: 1px solid #000; height: 80px; width: 250px; margin: 0 auto; }
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .folha-a4 { margin: 0; box-shadow: none; width: 100%; }
        }
    </style>
</head>
<body>

<div class="container no-print mt-3 text-center">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Imprimir / Salvar PDF</button>
    <button onclick="window.close()" class="btn btn-secondary">Fechar</button>
</div>

<div class="folha-a4">
    <div class="header-pmoc text-center">
        <h4 class="mb-0">HMDL - HOSPITAL DOMINGOS LOURENÇO</h4>
        <h5 class="mb-0 text-muted">Relatório de Manutenção Preventiva de Climatização (PMOC)</h5>
    </div>

    <table class="table table-bordered border-dark mb-4">
        <tr>
            <td colspan="2"><strong>Estabelecimento:</strong> HMDL</td>
            <td colspan="2"><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($d['data_manutencao'])) ?></td>
        </tr>
        <tr>
            <td><strong>Setor:</strong> <?= $d['setor_nome'] ?></td>
            <td><strong>Marca/Modelo:</strong> <?= $d['nome'] ?> / <?= $d['modelo'] ?></td>
            <td><strong>Patrimônio:</strong> <?= $d['patrimonio'] ?></td>
            <td><strong>Nº Série:</strong> <?= $d['num_serie'] ?></td>
        </tr>
        <tr>
            <td><strong>Capacidade:</strong> <?= $d['capacidade_btu'] ?></td>
            <td><strong>Gás:</strong> <?= $d['tipo_gas'] ?></td>
            <td colspan="2"><strong>Periodicidade:</strong> <?= $d['tipo_periodicidade'] ?></td>
        </tr>
    </table>

    <h6>3. CHECKLIST DE EXECUÇÃO</h6>
    <table class="table table-sm table-bordered border-dark table-check mb-4">
        <tr class="table-light"><th colspan="3">A) LIMPEZA DE FILTROS</th></tr>
        <tr><td>1. Inspeção visual</td><td class="text-center fw-bold"><?= $d['filtro_inspecao'] ?></td><td><?= $d['obs_filtro_inspecao'] ?></td></tr>
        <tr><td>2. Lavagem/Limpeza</td><td class="text-center fw-bold"><?= $d['filtro_limpeza'] ?></td><td><?= $d['obs_filtro_lavagem'] ?></td></tr>
        <tr><td>3. Reinstalação</td><td class="text-center fw-bold"><?= $d['filtro_reinstalacao'] ?></td><td><?= $d['obs_filtro_reinstalacao'] ?></td></tr>
        <tr><td>4. Substituição do filtro</td><td class="text-center fw-bold"><?= $d['filtro_substituicao'] ?></td><td><?= $d['justificativa_filtro'] ?></td></tr>
        
        <tr class="table-light"><th colspan="3">B) INSPEÇÃO MENSAL</th></tr>
        <tr><td>1. Limpeza Bandeja</td><td class="text-center fw-bold"><?= $d['limpeza_bandeja'] ?></td><td><?= $d['obs_bandeja'] ?></td></tr>
        <tr><td>2. Limpeza Dreno</td><td class="text-center fw-bold"><?= $d['limpeza_dreno'] ?></td><td><?= $d['obs_dreno'] ?></td></tr>
        <tr><td>3. Evaporadora</td><td class="text-center fw-bold"><?= $d['limpeza_evaporadora'] ?></td><td><?= $d['obs_evaporadora'] ?></td></tr>
        <tr><td>4. Condensadora</td><td class="text-center fw-bold"><?= $d['limpeza_condensadora'] ?></td><td><?= $d['obs_condensadora'] ?></td></tr>
        <tr><td>5. Conexões Elétricas</td><td class="text-center fw-bold"><?= $d['conexoes_eletricas'] ?></td><td><?= $d['obs_eletrica'] ?></td></tr>
    </table>

    <div class="border border-dark p-2 mb-4">
        <strong>4. OBSERVAÇÕES E RECOMENDAÇÕES:</strong><br>
        <?= nl2br($d['observacoes_tecnicas']) ?>
    </div>

    <div class="p-2 border border-dark bg-light mb-4 text-center">
        <strong>5. CONCLUSÃO DO SERVIÇO:</strong> <span class="badge bg-dark fs-6"><?= $d['status_final'] ?></span>
    </div>

    <div class="row text-center mt-5">
        <div class="col-6">
            <div class="signature-box">
                <img src="<?= $d['assinatura_tecnico'] ?>" style="height: 60px;">
            </div>
            <p class="mb-0">Técnico: <?= $d['tecnico_nome'] ?></p>
        </div>
        <div class="col-6">
            <div class="signature-box">
                <img src="<?= $d['assinatura_responsavel'] ?>" style="height: 60px;">
            </div>
            <p class="mb-0">Responsável: <?= $d['responsavel_setor'] ?></p>
        </div>
    </div>
</div>

</body>
</html>
