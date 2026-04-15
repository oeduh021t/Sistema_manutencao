<?php
include_once 'includes/db.php';

$id_check = $_GET['id_check'] ?? null;

if (!$id_check) {
    die("ID do checklist não fornecido.");
}

// Busca os dados do checklist e as informações do equipamento vinculado
$stmt = $pdo->prepare("
    SELECT c.*, e.nome as eq_nome, e.patrimonio, e.modelo, e.num_serie, s.nome as setor_nome
    FROM checklist_exaustao c
    JOIN equipamentos e ON c.equipamento_id = e.id
    JOIN setores s ON e.setor_id = s.id
    WHERE c.id = ?
");
$stmt->execute([$id_check]);
$dados = $stmt->fetch();

if (!$dados) {
    die("Registro não encontrado.");
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Laudo de Manutenção - Exaustor - <?= $dados['patrimonio'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; font-size: 12px; }
        .folha { background: white; width: 210mm; margin: 20px auto; padding: 15mm; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header-laudo { border-bottom: 2px solid #0dcaf0; margin-bottom: 20px; }
        .secao-titulo { background: #6c757d; color: white; padding: 5px 10px; font-weight: bold; margin-top: 15px; border-radius: 3px; }
        .signature-img { max-width: 200px; height: auto; border-bottom: 1px solid #000; }
        @media print {
            body { background: white; }
            .folha { margin: 0; box-shadow: none; width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="container no-print mt-3 text-center">
    <button onclick="window.print()" class="btn btn-info fw-bold text-white"><i class="bi bi-printer"></i> IMPRIMIR LAUDO</button>
</div>

<div class="folha">
    <div class="header-laudo d-flex justify-content-between align-items-center pb-2">
        <div>
            <h4 class="mb-1 text-info fw-bold">Relatório de Manutenção Preventiva de Sistema de Exaustão e Coifas</h4>
            <p class="mb-0 text-muted" style="font-size: 0.85rem;">
                (Em conformidade com ANVISA RDC nº 50/2002 e ABNT NBR 14.518)
            </p>
        </div>
        <div class="text-end text-muted small">
            <span class="fw-bold text-dark">HMDL - Hospital Domingos Lourenço</span><br>
            ID do Laudo: #EX-<?= $dados['id'] ?><br>
            Data: <?= date('d/m/Y H:i', strtotime($dados['data_manutencao'])) ?>
        </div>
    </div>

    <div class="secao-titulo">1. DADOS DE IDENTIFICAÇÃO</div>
    <div class="row mt-2 border-bottom pb-2 text-dark">
        <div class="col-6">
            <strong>Equipamento:</strong> <?= htmlspecialchars($dados['eq_nome']) ?><br>
            <strong>Marca/Modelo:</strong> <?= htmlspecialchars($dados['modelo']) ?><br>
            <strong>Série:</strong> <?= htmlspecialchars($dados['num_serie'] ?: '---') ?>
        </div>
        <div class="col-6 text-end">
            <strong>Setor:</strong> <?= htmlspecialchars($dados['setor_nome']) ?><br>
            <strong>Patrimônio:</strong> <span class="badge bg-dark"><?= $dados['patrimonio'] ?></span>
        </div>
    </div>

    <div class="secao-titulo">2. TIPO DE MANUTENÇÃO</div>
    <div class="p-2 border-bottom text-dark">
        <strong>Periodicidade da Manutenção:</strong> <?= htmlspecialchars($dados['tipo_periodicidade']) ?>
    </div>

    <div class="secao-titulo">3. CHECKLIST DE EXECUÇÃO</div>
    <h6 class="fw-bold bg-secondary text-white p-2 small mt-2 rounded">A) LIMPEZA DE TELA E TELA METÁLICA (Frequência: Mensal ou conforme criticidade)</h6>
    <table class="table table-bordered table-sm mt-2 text-dark">
        <thead class="table-light text-center">
            <tr><th>Item</th><th width="100">Status</th><th>Observações</th></tr>
        </thead>
        <tbody>
            <tr><td>1. Retirada e inspeção visual da tela / filtros metálicos</td><td class="text-center"><?= $dados['tela_inspecao'] ?></td><td></td></tr>
            <tr><td>2. Limpeza/lavagem da tela / filtros metálicos</td><td class="text-center"><?= $dados['tela_lavagem'] ?></td><td></td></tr>
            <tr><td>3. Montagem tela / filtros metálicos</td><td class="text-center"><?= $dados['tela_montagem'] ?></td><td></td></tr>
            <tr><td>4. Necessidade de substituição?</td><td class="text-center"><?= $dados['tela_substituicao'] ?></td><td><?= $dados['justificativa_tela'] ?></td></tr>
        </tbody>
    </table>

    <h6 class="fw-bold bg-secondary text-white p-2 small rounded">B) INSPEÇÃO E LIMPEZA SEMESTRAL</h6>
    <table class="table table-bordered table-sm mt-2 text-dark">
        <thead class="table-light text-center">
            <tr><th>Item</th><th width="100">Status</th><th>Observações</th></tr>
        </thead>
        <tbody>
            <tr><td>1. Verificar danos e limpar duto</td><td class="text-center"><?= $dados['duto_limpeza'] ?></td><td></td></tr>
            <tr><td>2. Verificar/eliminar corrosão</td><td class="text-center"><?= $dados['corrosao'] ?></td><td></td></tr>
            <tr><td>3. Verificar vibrações e ruídos</td><td class="text-center"><?= $dados['vibracao'] ?></td><td></td></tr>
            <tr><td>4. Acúmulo de gordura no duto</td><td class="text-center"><?= $dados['gordura_duto'] ?></td><td></td></tr>
            <tr><td>5. Limpeza de coifa e calha</td><td class="text-center"><?= $dados['limpeza_coifa'] ?></td><td></td></tr>
            <tr><td>6. Integridade mecânica/suportes</td><td class="text-center"><?= $dados['suportes'] ?></td><td></td></tr>
            <tr><td>7. Tensão e Corrente Elétrica</td><td class="text-center"><?= $dados['eletrica'] ?></td><td></td></tr>
        </tbody>
    </table>

    <div class="secao-titulo">4. OBSERVAÇÕES E RECOMENDAÇÕES</div>
    <div class="p-3 border mt-2 min-height-50 text-dark">
        <?= nl2br(htmlspecialchars($dados['observacoes_tecnicas'])) ?>
    </div>

    <div class="secao-titulo">5. CONCLUSÃO DO SERVIÇO</div>
    <div class="mt-2 p-2 border text-center fw-bold bg-light text-dark">
        STATUS FINAL DO EQUIPAMENTO: <span class="<?= ($dados['status_final'] == 'Operando Normalmente') ? 'text-success' : 'text-danger' ?> text-uppercase">
            <?= $dados['status_final'] ?>
        </span>
    </div>

    <div class="row mt-5 text-center text-dark">
        <div class="col-6">
            <?php if ($dados['assinatura_tecnico']): ?>
                <img src="<?= $dados['assinatura_tecnico'] ?>" class="signature-img"><br>
            <?php endif; ?>
            <small><strong>TÉCNICO RESPONSÁVEL</strong><br><?= $dados['tecnico_nome'] ?></small>
        </div>
        <div class="col-6">
            <?php if ($dados['assinatura_responsavel']): ?>
                <img src="<?= $dados['assinatura_responsavel'] ?>" class="signature-img"><br>
            <?php endif; ?>
            <small><strong>RESPONSÁVEL PELO SETOR</strong><br><?= $dados['responsavel_setor'] ?></small>
        </div>
    </div>
</div>

</body>
</html>
