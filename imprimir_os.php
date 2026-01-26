<?php
include_once 'includes/db.php';
$id = $_GET['id'] ?? null;

if (!$id) { die("OS não encontrada."); }

// 1. Busca dados principais (incluindo os novos campos de tipo e nota)
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
    if ($f['status_momento'] === 'Aberto' && $f['foto_historico'] !== $c['foto_abertura']) {
        $fotos_abertura_extras[] = $f;
    } elseif ($f['status_momento'] === 'Concluído' && $f['foto_historico'] !== $c['foto_conclusao']) {
        $fotos_conclusao_extras[] = $f;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4; margin: 10mm; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
        .page { width: 190mm; margin: auto; }
        .header { text-align: center; border: 2px solid #000; padding: 10px; background: #f8f9fa; margin-bottom: 10px; position: relative; }
        .badge-externo { position: absolute; right: 10px; top: 10px; background: #d9534f; color: #fff; padding: 5px; font-weight: bold; font-size: 10px; }
        .section-title { background: #333; color: #fff; padding: 5px 10px; font-weight: bold; margin-top: 15px; text-transform: uppercase; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info-table td { border: 1px solid #000; padding: 8px; vertical-align: top; }

        .gallery-container { display: flex; flex-wrap: wrap; gap: 10px; border: 1px solid #ccc; padding: 10px; background: #fdfdfd; }
        .photo-card { width: 31%; border: 1px solid #ddd; padding: 5px; text-align: center; background: #fff; }
        .photo-card img { max-width: 100%; max-height: 100px; object-fit: contain; }
        
        .termo-juridico { margin-top: 20px; padding: 10px; border: 1px solid #000; background: #f9f9f9; font-size: 9.5px; text-align: justify; }
        .assinaturas { margin-top: 50px; display: flex; justify-content: space-around; text-align: center; }
        .assinaturas div { border-top: 1px solid #000; width: 28%; padding-top: 5px; }

        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="no-print" style="text-align:center; margin: 20px;">
    <button onclick="window.print()" style="padding: 10px 20px; cursor:pointer; font-weight:bold; background: #28a745; color:#fff; border:none; border-radius:5px;">GERAR DOCUMENTO FÍSICO (PDF)</button>
</div>

<div class="page">
    <div class="header">
        <?php if($c['tipo_atendimento'] === 'Externo'): ?>
            <div class="badge-externo text-uppercase">Assistência Externa</div>
        <?php endif; ?>
        <h3 style="margin:0; text-transform: uppercase;">ORDEM DE SERVIÇO № <?= str_pad($c['id'], 6, "0", STR_PAD_LEFT) ?></h3>
        <span style="font-weight: bold;">Hospital Domingos Lourenço - Engenharia Clínica</span>
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
            <td><strong>TÉCNICO RESPONSÁVEL:</strong> <?= htmlspecialchars($c['tecnico_responsavel']) ?></td>
        </tr>
    </table>

    <div class="section-title">1. Descrição do Defeito e Diagnóstico</div>
    <div style="border: 1px solid #000; padding: 10px;">
        <strong>Relato Inicial:</strong> <?= nl2br(htmlspecialchars($c['descricao_problema'])) ?>
    </div>

    <div class="section-title">2. Relatório de Execução Técnica</div>
    <div style="border: 1px solid #000; padding: 10px; min-height: 120px;">
        <strong>Procedimentos realizados:</strong><br>
        <?= nl2br(htmlspecialchars($c['descricao_solucao'])) ?>

        <?php if($c['tipo_atendimento'] === 'Externo'): ?>
            <div style="margin-top: 15px; padding: 8px; background: #eee; border: 1px solid #ccc;">
                <strong>DADOS DO PRESTADOR:</strong> <?= htmlspecialchars($c['empresa_terceirizada']) ?> | 
                <strong>NF:</strong> <?= htmlspecialchars($c['nf_referencia']) ?> | 
                <strong>CUSTO:</strong> R$ <?= number_format($c['custo_servico'], 2, ',', '.') ?> |
                <strong>AVALIAÇÃO:</strong> <?= $c['nota_fornecedor'] ?> Estrela(s)
            </div>
        <?php endif; ?>
    </div>

    <div class="section-title">3. Galeria de Evidências (Antes e Depois)</div>
    <div class="gallery-container">
        <?php if($c['foto_abertura']): ?>
            <div class="photo-card"><img src="uploads/<?= $c['foto_abertura'] ?>"><br>Início</div>
        <?php endif; ?>
        <?php if($c['foto_conclusao']): ?>
            <div class="photo-card" style="border-color: #28a745;"><img src="uploads/<?= $c['foto_conclusao'] ?>"><br>Conclusão</div>
        <?php endif; ?>
        <?php foreach($fotos_conclusao_extras as $f): ?>
            <div class="photo-card"><img src="uploads/<?= $f['foto_historico'] ?>"><br>Teste/Peça</div>
        <?php endforeach; ?>
    </div>

    <?php if($c['tipo_atendimento'] === 'Externo'): ?>
    <div class="termo-juridico">
        <strong>TERMO DE ENTREGA TÉCNICA E RESPONSABILIDADE:</strong><br>
        O prestador acima identificado declara que realizou a manutenção corretiva/preventiva no equipamento descrito, utilizando peças adequadas e seguindo as normas técnicas vigentes. O equipamento foi entregue ao Hospital Domingos Lourenço devidamente testado em todas as suas funções vitais, com calibração verificada (quando aplicável) e em perfeitas condições de uso clínico. O hospital reserva-se o direito de contestar o serviço em caso de vícios ocultos ou falha prematura das peças substituídas dentro do prazo legal de garantia.
    </div>
    <?php else: ?>
    <div class="termo-juridico" style="text-align: center;">
        <strong>DECLARAÇÃO:</strong> O setor de Engenharia Clínica declara que o equipamento foi reparado internamente e testado para retorno imediato ao uso operacional.
    </div>
    <?php endif; ?>

    <div class="assinaturas">
        <div>Engenharia Clínica</div>
        <div><?= ($c['tipo_atendimento'] === 'Externo') ? 'Prestador de Serviço' : 'Técnico Executante' ?></div>
        <div>Aceite do Setor (Enfermagem)</div>
    </div>
</div>

</body>
</html>
