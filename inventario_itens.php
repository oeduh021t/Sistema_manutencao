<?php
include_once 'includes/db.php';

// SQL para listar os itens e calcular o valor total por linha (Qtd x Valor Unitário)
$sql = "SELECT *, 
               (quantidade * valor_unitario) as subtotal_valor
        FROM itens_estoque 
        ORDER BY nome ASC";

$stmt = $pdo->query($sql);
$itens = $stmt->fetchAll();

$total_tipos_itens = count($itens);
$quantidade_total_pecas = array_sum(array_column($itens, 'quantidade'));
$investimento_estoque_total = array_sum(array_column($itens, 'subtotal_valor'));
?>

<div class="container-fluid py-3 text-dark" id="area-impressao">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h3 class="fw-bold mb-0 text-dark">
                <i class="bi bi-box-seam-fill d-print-none text-primary me-2"></i>Inventário Geral de Itens (Estoque)
            </h3>
            <small class="text-muted text-uppercase">Hospital Domingos Lourenço - Gestão de Materiais</small>
        </div>
        <div class="text-end">
            <button onclick="window.print()" class="btn btn-dark d-print-none fw-bold shadow-sm mb-2">
                <i class="bi bi-printer me-2"></i>IMPRIMIR RELATÓRIO
            </button>
            <div class="badge bg-success px-3 py-2 d-block shadow-sm" style="font-size: 0.85rem;">
                Valor Total em Estoque: R$ <?= number_format($investimento_estoque_total, 2, ',', '.') ?>
            </div>
        </div>
    </div>

    <div class="row mb-3 d-none d-print-flex">
        <div class="col-6">
            <strong>Data de Emissão:</strong> <?= date('d/m/Y H:i') ?>
        </div>
        <div class="col-6 text-end">
            <strong>Total de Peças:</strong> <?= $quantidade_total_pecas ?> unidades
        </div>
    </div>

    <?php if ($total_tipos_itens == 0): ?>
        <div class="alert alert-warning border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> 
            Nenhum item cadastrado no estoque.
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase fw-bold">
                            <tr>
                                <th class="ps-4" style="width: 10%">ID</th>
                                <th style="width: 40%">Descrição do Item</th>
                                <th class="text-center" style="width: 15%">Quantidade</th>
                                <th class="text-end" style="width: 15%">Valor Unit.</th>
                                <th class="text-end" style="width: 20%">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php foreach($itens as $i): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted">#<?= $i['id'] ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($i['nome']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($i['descricao'] ?? '-') ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold <?= ($i['quantidade'] <= 0) ? 'text-danger' : 'text-dark' ?>">
                                        <?= $i['quantidade'] ?> un
                                    </span>
                                </td>
                                <td class="text-end text-muted">
                                    R$ <?= number_format($i['valor_unitario'], 2, ',', '.') ?>
                                </td>
                                <td class="text-end fw-bold text-primary">
                                    R$ <?= number_format($i['subtotal_valor'], 2, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="mt-5 d-none d-print-block">
            <div class="row text-center mt-5">
                <div class="col-4">
                    <div style="border-top: 1px solid #000; padding-top: 5px;">Almoxarifado / Estoque</div>
                </div>
                <div class="col-4 offset-4">
                    <div style="border-top: 1px solid #000; padding-top: 5px;">Direção Administrativa</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
@media print {
    #sidebar, .d-print-none, .btn-dark, .navbar { 
        display: none !important; 
    }
    body, #content, .container-fluid { 
        width: 100% !important; 
        margin: 0 !important; 
        padding: 0 !important; 
        background-color: white !important;
    }
    .table { border: 1px solid #000 !important; }
    .table thead th { 
        background-color: #f8f9fa !important; 
        color: #000 !important; 
        border: 1px solid #000 !important;
    }
    .table td { border: 1px solid #eee !important; }
    .text-primary { color: #0d6efd !important; }
    .card { border: none !important; }
}
</style>
