<?php
include_once 'includes/db.php';
$id = $_GET['id'] ?? null;

if (!$id) { die("OS não encontrada."); }

// 1. Busca dados principais
$stmt = $pdo->prepare("
    SELECT c.*, e.patrimonio, e.num_serie, e.nome as eq_nome, e.foto_equipamento, s.nome as setor_nome
    FROM chamados c
    JOIN equipamentos e ON c.equipamento_id = e.id
    JOIN setores s ON e.setor_id = s.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$c = $stmt->fetch();

// 2. Busca fotos do histórico
$stmt_fotos = $pdo->prepare("SELECT foto_historico, status_momento FROM chamados_historico WHERE chamado_id = ? AND foto_historico IS NOT NULL");
$stmt_fotos->execute([$id]);
$fotos_historico = $stmt_fotos->fetchAll();

$fotos_abertura_extras = [];
$fotos_conclusao_extras = [];

foreach ($fotos_historico as $f) {
    // TRAVA DE DUPLICIDADE: 
    // Só adicionamos ao array extra se a foto NÃO for igual à foto principal de abertura ou conclusão
    if ($f['status_momento'] === 'Aberto') {
        if ($f['foto_historico'] !== $c['foto_abertura']) {
            $fotos_abertura_extras[] = $f;
        }
    } elseif ($f['status_momento'] === 'Concluído') {
        if ($f['foto_historico'] !== $c['foto_conclusao']) {
            $fotos_conclusao_extras[] = $f;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4; margin: 10mm; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #333; }
        .page { width: 190mm; margin: auto; }
        .header { text-align: center; border: 2px solid #000; padding: 10px; background: #f8f9fa; margin-bottom: 10px; }
        .section-title { background: #333; color: #fff; padding: 5px 10px; font-weight: bold; margin-top: 15px; text-transform: uppercase; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info-table td { border: 1px solid #000; padding: 8px; vertical-align: top; }
        
        .gallery-container { display: flex; flex-wrap: wrap; gap: 10px; border: 1px solid #ccc; padding: 10px; background: #fdfdfd; min-height: 50px; }
        .photo-card { width: 30%; border: 1px solid #ddd; padding: 5px; text-align: center; background: #fff; }
        .photo-card img { max-width: 100%; max-height: 120px; object-fit: contain; }
        .photo-card span { display: block; font-size: 8px; color: #666; margin-top: 5px; }

        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="no-print" style="text-align:center; margin-bottom: 20px;">
    <button onclick="window.print()" style="padding: 10px 20px; cursor:pointer; font-weight:bold;">IMPRIMIR RELATÓRIO TÉCNICO (OS)</button>
</div>

<div class="page">
    <div class="header">
        <h3 style="margin:0; text-transform: uppercase;">ORDEM DE SERVIÇO № <?= str_pad($c['id'], 6, "0", STR_PAD_LEFT) ?></h3>
        <span>Hospital Domingos Lourenço - Departamento de Manutenção</span>
    </div>

    <table class="info-table">
        <tr>
            <td colspan="2"><strong>EQUIPAMENTO:</strong> <?= htmlspecialchars($c['eq_nome']) ?></td>
            <td rowspan="3" style="width: 120px; text-align:center; vertical-align: middle;">
                <?php if($c['foto_equipamento']): ?>
                    <img src="uploads/<?= $c['foto_equipamento'] ?>" style="max-width: 100px; max-height: 80px;">
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td><strong>PATRIMÔNIO:</strong> <?= htmlspecialchars($c['patrimonio']) ?></td>
            <td><strong>Nº SÉRIE:</strong> <?= htmlspecialchars($c['num_serie']) ?></td>
        </tr>
        <tr>
            <td><strong>LOCALIZAÇÃO:</strong> <?= htmlspecialchars($c['setor_nome']) ?></td>
            <td><strong>TÉCNICO:</strong> <?= htmlspecialchars($c['tecnico_responsavel']) ?></td>
        </tr>
    </table>

    <div class="section-title">1. Evidências de Entrada (Início do Chamado)</div>
    <div style="border: 1px solid #000; padding: 10px; margin-bottom: 5px;">
        <strong>Relato do Problema:</strong> <?= nl2br(htmlspecialchars($c['descricao_problema'])) ?>
    </div>
    <div class="gallery-container">
        <?php if($c['foto_abertura']): ?>
            <div class="photo-card"><img src="uploads/<?= $c['foto_abertura'] ?>"><span>Foto Inicial</span></div>
        <?php endif; ?>

        <?php foreach($fotos_abertura_extras as $f): ?>
            <div class="photo-card"><img src="uploads/<?= $f['foto_historico'] ?>"><span>Evidência Abertura</span></div>
        <?php endforeach; ?>
    </div>

    <div class="section-title">2. Evidências de Saída (Conclusão e Testes)</div>
    <div class="gallery-container">
        <?php if($c['foto_conclusao']): ?>
            <div class="photo-card" style="border-color: #28a745;"><img src="uploads/<?= $c['foto_conclusao'] ?>"><span>Foto Entrega</span></div>
        <?php endif; ?>

        <?php foreach($fotos_conclusao_extras as $f): ?>
            <div class="photo-card" style="border-color: #28a745;"><img src="uploads/<?= $f['foto_historico'] ?>"><span>Evidência Conclusão</span></div>
        <?php endforeach; ?>
    </div>

    <div class="section-title">3. Relatório de Execução e Solução</div>
    <div style="border: 1px solid #000; padding: 10px; margin-bottom: 5px; min-height: 100px;">
        <strong>Solução Técnica Aplicada:</strong><br>
        <?= nl2br(htmlspecialchars($c['descricao_solucao'])) ?>
        
        <?php if($c['empresa_terceirizada']): ?>
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ccc;">
                <strong>Empresa:</strong> <?= htmlspecialchars($c['empresa_terceirizada']) ?> | 
                <strong>NF:</strong> <?= htmlspecialchars($c['nf_referencia']) ?> | 
                <strong>Custo:</strong> R$ <?= number_format($c['custo_servico'], 2, ',', '.') ?>
            </div>
        <?php endif; ?>
    </div>

    <div style="margin-top: 60px; display: flex; justify-content: space-around; text-align: center;">
        <div style="border-top: 1px solid #000; width: 30%;">Responsável Técnico</div>
        <div style="border-top: 1px solid #000; width: 30%;">Prestador / Empresa</div>
        <div style="border-top: 1px solid #000; width: 30%;">Aceite do Setor</div>
    </div>
</div>
</body>
</html>
