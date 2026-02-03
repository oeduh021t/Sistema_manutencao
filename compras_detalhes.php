<?php
// compras_detalhes.php
if (!isset($_SESSION['usuario_id'])) { die("Acesso negado."); }

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php?p=compras_lista"); exit; }

// Busca os detalhes da compra com os nomes relacionados
$sql = "SELECT c.*, e.nome as nome_equipamento, e.patrimonio, 
               u_sol.nome as solicitante,
               u_fin.nome as aprovador_financeiro,
               u_dir.nome as aprovador_diretoria
        FROM solicitacoes_compra c
        LEFT JOIN equipamentos e ON c.equipamento_id = e.id
        JOIN usuarios u_sol ON c.solicitante_id = u_sol.id
        LEFT JOIN usuarios u_fin ON c.user_financeiro_id = u_fin.id
        LEFT JOIN usuarios u_dir ON c.user_diretoria_id = u_dir.id
        WHERE c.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$compra = $stmt->fetch();

if (!$compra) { die("Solicitação não encontrada."); }

// Cores por status
$cores = [
    'Pendente' => 'secondary', 'Financeiro' => 'primary', 
    'Diretoria' => 'info', 'Aprovado' => 'success', 
    'Negado' => 'danger', 'Comprado' => 'dark'
];
?>

<style>
/* --- ESTILO DE IMPRESSÃO AVANÇADO --- */
@media print {
    /* 1. Esconde absolutamente tudo o que for do layout do painel */
    header, footer, nav, .sidebar, .navbar, .d-print-none, #sidebarMenu, .menu-lateral, .breadcrumb {
        display: none !important;
    }

    /* 2. Reseta o posicionamento para o conteúdo começar no topo da folha */
    body {
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
        visibility: hidden; /* Esconde o corpo todo */
    }

    /* 3. Torna apenas o container da solicitação visível e o joga para o topo */
    #imprimir-conteudo {
        visibility: visible;
        position: absolute;
        left: 0;
        top: 0;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* 4. Ajustes finos no card */
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
    
    .card-header {
        border-bottom: 2px solid #000 !important;
        background-color: #f8f9fa !important;
        -webkit-print-color-adjust: exact;
    }

    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
        background: transparent !important;
    }
}
</style>

<div class="container mt-4" id="imprimir-conteudo">
    
    <div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
        <a href="index.php?p=compras_lista" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar para Lista
        </a>
        <button onclick="window.print();" class="btn btn-dark">
            <i class="bi bi-printer"></i> Imprimir Solicitação
        </button>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0">Solicitação de Compra #<?= $compra['id'] ?></h5>
                    <span class="badge bg-<?= $cores[$compra['status']] ?> fs-6"><?= $compra['status'] ?></span>
                </div>
                <div class="card-body">
                    
                    <div class="row mb-4">
                        <div class="col-6">
                            <label class="text-muted small d-block">Item / Serviço</label>
                            <span class="fw-bold fs-5"><?= htmlspecialchars($compra['item_nome']) ?></span>
                        </div>
                        <div class="col-3 text-center">
                            <label class="text-muted small d-block">Qtd.</label>
                            <span class="fw-bold fs-5"><?= $compra['quantidade'] ?></span>
                        </div>
                        <div class="col-3 text-end">
                            <label class="text-muted small d-block">Valor Estimado</label>
                            <span class="fw-bold text-success fs-5">R$ <?= number_format($compra['valor_estimado'], 2, ',', '.') ?></span>
                        </div>
                    </div>

                    <hr>

                    <div class="row mb-4">
                        <div class="col-md-7">
                            <label class="text-muted small d-block mb-1">Equipamento Relacionado</label>
                            <?php if ($compra['equipamento_id']): ?>
                                <div class="p-2 border rounded bg-light">
                                    <i class="bi bi-cpu text-primary"></i> 
                                    <strong><?= htmlspecialchars($compra['nome_equipamento']) ?></strong>
                                    <span class="text-muted small ms-2">(Pat: <?= htmlspecialchars($compra['patrimonio']) ?>)</span>
                                </div>
                            <?php else: ?>
                                <span class="text-muted italic">Uso Geral / Sem vínculo</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-5 text-md-end">
                            <label class="text-muted small d-block">Data da Solicitação</label>
                            <span class="fw-bold"><?= date('d/m/Y H:i', strtotime($compra['data_solicitacao'])) ?></span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="text-muted small d-block">Justificativa da Necessidade</label>
                        <div class="p-3 border rounded bg-light" style="min-height: 80px;">
                            <?= nl2br(htmlspecialchars($compra['motivo'])) ?>
                        </div>
                    </div>

                    <div class="d-none d-print-block mt-5 pt-4">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="border-top border-dark mx-2 pt-1">
                                    <small>Solicitante</small><br>
                                    <span class="small fw-bold"><?= $compra['solicitante'] ?></span>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border-top border-dark mx-2 pt-1">
                                    <small>Financeiro / Compras</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border-top border-dark mx-2 pt-1">
                                    <small>Diretoria / Aprovação</small>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-4 d-print-none">
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light fw-bold text-uppercase small">Status e Aprovações</div>
                <div class="card-body">
                    
                    <?php if ($compra['status'] == 'Pendente'): ?>
                        <a href="compras_status.php?id=<?= $id ?>&acao=financeiro" class="btn btn-primary w-100 mb-2">Ciência do Financeiro</a>
                    <?php elseif ($compra['status'] == 'Financeiro'): ?>
                        <a href="compras_status.php?id=<?= $id ?>&acao=diretoria" class="btn btn-info w-100 mb-2 text-white">Autorizar (Diretoria)</a>
                    <?php elseif ($compra['status'] == 'Diretoria'): ?>
                        <a href="compras_status.php?id=<?= $id ?>&acao=comprado" class="btn btn-success w-100 mb-2">Confirmar Compra</a>
                    <?php endif; ?>

                    <?php if (in_array($compra['status'], ['Pendente', 'Financeiro', 'Diretoria'])): ?>
                        <hr>
                        <a href="compras_status.php?id=<?= $id ?>&acao=negado" 
                           class="btn btn-outline-danger btn-sm w-100" 
                           onclick="return confirm('Negar esta solicitação?')">Negar Solicitação</a>
                    <?php endif; ?>

                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light fw-bold text-uppercase small">Logs do Fluxo</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item"><strong>Solicitante:</strong> <?= $compra['solicitante'] ?></li>
                        <?php if ($compra['aprovador_financeiro']): ?>
                            <li class="list-group-item"><strong>Finan:</strong> <?= $compra['aprovador_financeiro'] ?></li>
                        <?php endif; ?>
                        <?php if ($compra['aprovador_diretoria']): ?>
                            <li class="list-group-item"><strong>Diret:</strong> <?= $compra['aprovador_diretoria'] ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>
