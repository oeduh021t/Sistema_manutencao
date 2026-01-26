<?php
include_once 'includes/db.php';

// SQL atualizado para incluir a soma dos custos de cada equipamento
$sql = "SELECT e.*, 
               IFNULL(s.nome, 'Sem Setor') as setor_nome, 
               IFNULL(t.nome, 'Sem Tipo') as tipo_nome,
               IFNULL(SUM(c.custo_servico), 0) as total_gasto
        FROM equipamentos e 
        LEFT JOIN setores s ON e.setor_id = s.id 
        LEFT JOIN tipos_equipamentos t ON e.tipo_equipamento_id = t.id 
        LEFT JOIN chamados c ON e.id = c.equipamento_id AND c.status = 'Concluído'
        GROUP BY e.id
        ORDER BY s.nome ASC, e.nome ASC";

$stmt = $pdo->query($sql);
$equipamentos = $stmt->fetchAll();

$total_itens = count($equipamentos);
$investimento_total_geral = array_sum(array_column($equipamentos, 'total_gasto'));
?>

<div class="container-fluid py-3 text-dark" id="area-impressao">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h3 class="fw-bold mb-0 text-dark">
                <i class="bi bi-printer-fill d-print-none text-primary me-2"></i>Inventário Patrimonial Geral
            </h3>
            <small class="text-muted text-uppercase">Hospital Domingos Lourenço - Engenharia Clínica</small>
        </div>
        <div class="text-end">
            <button onclick="window.print()" class="btn btn-dark d-print-none fw-bold shadow-sm mb-2">
                <i class="bi bi-printer me-2"></i>IMPRIMIR RELATÓRIO
            </button>
            <div class="badge bg-danger px-3 py-2 d-block shadow-sm" style="font-size: 0.85rem;">
                Investimento Total: R$ <?= number_format($investimento_total_geral, 2, ',', '.') ?>
            </div>
        </div>
    </div>

    <div class="row mb-3 d-none d-print-flex">
        <div class="col-6">
            <strong>Data de Emissão:</strong> <?= date('d/m/Y H:i') ?>
        </div>
        <div class="col-6 text-end">
            <strong>Total de Ativos:</strong> <?= $total_itens ?> itens
        </div>
    </div>

    <?php if ($total_itens == 0): ?>
        <div class="alert alert-warning border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> 
            Nenhum equipamento encontrado no banco de dados.
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase fw-bold">
                            <tr>
                                <th class="ps-4" style="width: 15%">Patrimônio</th>
                                <th style="width: 35%">Equipamento / Marca</th>
                                <th style="width: 25%">Localização</th>
                                <th class="text-end" style="width: 15%">Custo Acumulado</th>
                                <th class="text-center d-print-none" style="width: 10%">Status</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php foreach($equipamentos as $e): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($e['patrimonio'] ?? 'N/A') ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($e['nome']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($e['marca'] ?? '-') ?> / <?= htmlspecialchars($e['modelo'] ?? '-') ?></div>
                                </td>
                                <td>
                                    <span class="text-secondary small"><i class="bi bi-geo-alt me-1 d-print-none"></i><?= htmlspecialchars($e['setor_nome']) ?></span>
                                </td>
                                <td class="text-end fw-bold text-danger">
                                    R$ <?= number_format($e['total_gasto'], 2, ',', '.') ?>
                                </td>
                                <td class="text-center d-print-none">
                                    <?php 
                                        $status_class = ($e['status'] == 'Ativo') ? 'bg-success' : (($e['status'] == 'Em Manutenção') ? 'bg-danger' : 'bg-warning');
                                    ?>
                                    <span class="badge <?= $status_class ?> shadow-sm">
                                        <?= htmlspecialchars($e['status']) ?>
                                    </span>
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
                    <div style="border-top: 1px solid #000; padding-top: 5px;">Responsável Engenharia</div>
                </div>
                <div class="col-4 offset-4">
                    <div style="border-top: 1px solid #000; padding-top: 5px;">Direção Administrativa</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* CONFIGURAÇÕES DE IMPRESSÃO */
@media print {
    /* Esconde o menu lateral do sistema e o botão de imprimir */
    #sidebar, .d-print-none, .btn-dark, .navbar { 
        display: none !important; 
    }
    
    /* Faz o conteúdo ocupar a tela inteira */
    body, #content, .container-fluid { 
        width: 100% !important; 
        margin: 0 !important; 
        padding: 0 !important; 
        background-color: white !important;
    }

    /* Ajustes na Tabela */
    .table { 
        border: 1px solid #000 !important; 
    }
    .table thead th { 
        background-color: #eee !important; 
        color: #000 !important; 
        border: 1px solid #000 !important;
    }
    .table td { 
        border: 1px solid #eee !important; 
    }

    /* Força cores no PDF */
    .text-primary { color: #0d6efd !important; }
    .text-danger { color: #dc3545 !important; }
    
    .card { border: none !important; }
}
</style>
