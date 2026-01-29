<?php
include_once 'includes/db.php';

// Pega o tipo de impressão: 'equipamentos' ou 'setores'
$tipo = $_GET['tipo'] ?? 'equipamentos';

function gerarLinkQR($id, $tipo_link) {
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $dominio = $_SERVER['HTTP_HOST'];
    $caminho = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
    
    $p = ($tipo_link == 'setores') ? 'chamados&setor_id=' : 'ver_chamado&id=';
    $url = "{$protocolo}://{$dominio}{$caminho}/index.php?p={$p}{$id}";
    
    return "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($url);
}

if ($tipo == 'setores') {
    $dados = $pdo->query("SELECT id, nome as titulo, 'SETOR' as subtitulo FROM setores ORDER BY nome ASC")->fetchAll();
} else {
    $dados = $pdo->query("SELECT id, nome as titulo, patrimonio as subtitulo FROM equipamentos WHERE status = 'Ativo' ORDER BY nome ASC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Impressão de Etiquetas em Massa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; }
        }
        .etiqueta {
            width: 4.5cm;
            height: 6cm;
            border: 1px solid #ddd;
            margin: 5px;
            padding: 10px;
            display: inline-block;
            text-align: center;
            vertical-align: top;
            page-break-inside: avoid;
        }
        .qr-img { width: 120px; height: 120px; }
        .hospital-nome { font-size: 8px; font-weight: bold; border-bottom: 1px solid #000; margin-bottom: 5px; }
        .patrimonio { font-size: 14px; font-weight: bold; margin-top: 5px; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4 no-print text-center">
    <div class="alert alert-info">Prepare as etiquetas para o Hospital Domingos Lourenço</div>
    <button onclick="window.print()" class="btn btn-primary btn-lg"><i class="bi bi-printer"></i> IMPRIMIR AGORA</button>
    <a href="index.php?p=equipamentos" class="btn btn-secondary btn-lg">VOLTAR</a>
</div>

<div class="d-flex flex-wrap justify-content-center mt-3">
    <?php foreach ($dados as $item): ?>
        <div class="etiqueta bg-white">
            <div class="hospital-nome">HOSPITAL DOMINGOS LOURENÇO</div>
            <img src="<?= gerarLinkQR($item['id'], $tipo) ?>" class="qr-img">
            <div class="patrimonio"><?= $item['subtitulo'] ?></div>
            <div style="font-size: 9px;"><?= mb_strimwidth($item['titulo'], 0, 25, "...") ?></div>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>
