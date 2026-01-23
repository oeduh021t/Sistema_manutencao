<?php
include_once 'includes/db.php';

// Busca apenas o que ainda NÃO foi devolvido
// Unimos com a tabela de equipamentos e setores para saber ONDE o item está
$sql = "SELECT e.*, 
               eq.nome as equipamento_nome, 
               eq.patrimonio as eq_patrimonio,
               s.nome as setor_nome
        FROM emprestimos e
        JOIN equipamentos eq ON e.equipamento_id = eq.id
        JOIN setores s ON eq.setor_id = s.id
        WHERE e.status = 'Emprestado'
        ORDER BY s.nome ASC, e.data_empréstimo DESC";

$stmt = $pdo->query($sql);
$emprestimos = $stmt->fetchAll();

$total_pendente = count($emprestimos);
?>

<div class="container-fluid py-3 text-dark">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h3 class="fw-bold mb-0 text-dark">
                <i class="bi bi-arrow-left-right d-print-none text-info me-2"></i>Itens em Empréstimo / Pendentes
            </h3>
            <small class="text-muted text-uppercase">Hospital Domingos Lourenço - Controle de Ativos</small>
        </div>
        <div class="text-end d-print-none">
            <button onclick="window.print()" class="btn btn-dark fw-bold shadow-sm">
                <i class="bi bi-printer me-2"></i>IMPRIMIR MAPA
            </button>
        </div>
    </div>

    <?php if ($total_pendente == 0): ?>
        <div class="alert alert-success border-0 shadow-sm text-center py-4">
            <i class="bi bi-check-circle-fill fs-1 d-block mb-2"></i>
            <span class="fw-bold">Tudo em ordem! Não há itens emprestados no momento.</span>
        </div>
    <?php else: ?>
        <div class="alert alert-info d-print-none shadow-sm border-0">
            <i class="bi bi-info-circle-fill me-2"></i> 
            Existem <strong><?= $total_pendente ?></strong> itens aguardando devolução no hospital.
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase fw-bold">
                            <tr>
                                <th class="ps-4">Data Saída</th>
                                <th>Item / Acessório</th>
                                <th>Vinculado ao Ativo</th>
                                <th>Localização</th>
                                <th>Solicitante / Obs</th>
                                <th class="text-center d-print-none">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="small text-dark">
                            <?php foreach($emprestimos as $emp): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?= date('d/m/Y', strtotime($emp['data_empréstimo'])) ?></div>
                                    <small class="text-muted"><?= date('H:i', strtotime($emp['data_empréstimo'])) ?></small>
                                </td>
                                <td><span class="badge bg-info text-white fs-6"><?= htmlspecialchars($emp['item_acessorio']) ?></span></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($emp['equipamento_nome']) ?></div>
                                    <small class="text-muted">Pat: <?= htmlspecialchars($emp['eq_patrimonio']) ?></small>
                                </td>
                                <td><i class="bi bi-geo-alt me-1 text-danger"></i><?= htmlspecialchars($emp['setor_nome']) ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($emp['solicitante']) ?></div>
                                    <small class="text-muted italic"><?= htmlspecialchars($emp['observacao']) ?></small>
                                </td>
                                <td class="text-center d-print-none">
                                    <a href="index.php?p=historico_equipamento&id=<?= $emp['equipamento_id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Ver Prontuário
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
@media print {
    #sidebar, .d-print-none, .navbar { display: none !important; }
    body, #content, .container-fluid { width: 100% !important; margin: 0 !important; padding: 0 !important; background: white; }
    .table { border: 1px solid #000 !important; }
    .table thead th { background-color: #f0f0f0 !important; border: 1px solid #000 !important; color: #000 !important; }
    .badge { border: 1px solid #000 !important; color: #000 !important; background: none !important; }
}
</style>
