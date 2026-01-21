<?php
include_once 'includes/db.php';

// --- 1. CONFIGURAÇÃO DE HIERARQUIA DE SETORES ---
$setores_mapa = $pdo->query("SELECT id, nome, setor_pai_id FROM setores")->fetchAll(PDO::FETCH_UNIQUE);

function getNomeHierarquico($id, $mapa) {
    if (!isset($mapa[$id])) return "Não identificado";
    $setor = $mapa[$id];
    if (!empty($setor['setor_pai_id']) && isset($mapa[$setor['setor_pai_id']])) {
        return getNomeHierarquico($setor['setor_pai_id'], $mapa) . " > " . $setor['nome'];
    }
    return $setor['nome'];
}

$setores_select = [];
foreach ($setores_mapa as $id_s => $dados_s) {
    $setores_select[] = [
        'id' => $id_s,
        'nome_completo' => getNomeHierarquico($id_s, $setores_mapa)
    ];
}
usort($setores_select, function($a, $b) {
    return strcmp($a['nome_completo'], $b['nome_completo']);
});

// --- 2. CAPTURAR FILTROS (DATA E SETOR) ---
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$setor_filtro = $_GET['setor_id'] ?? null;

// --- 3. MÉTRICAS GERAIS (ESTADO ATUAL) ---
$sql_base_eq = "SELECT COUNT(*) FROM equipamentos WHERE 1=1";
if ($setor_filtro) $sql_base_eq .= " AND setor_id = " . intval($setor_filtro);

$total = $pdo->query($sql_base_eq)->fetchColumn() ?: 1;
$ativos = $pdo->query($sql_base_eq . " AND status = 'Ativo'")->fetchColumn();
$manutencao = $pdo->query($sql_base_eq . " AND status = 'Em Manutenção'")->fetchColumn();
$reserva = $pdo->query($sql_base_eq . " AND status = 'Reserva'")->fetchColumn();
$perc_disponivel = round(($ativos / $total) * 100, 1);

// --- 4. CUSTOS FILTRADOS POR DATA E SETOR ---
$params = [$data_inicio . ' 00:00:00', $data_fim . ' 23:59:59'];
$where_setor = "";
if ($setor_filtro) {
    $where_setor = " AND setor_id = ?";
    $params[] = $setor_filtro;
}

// Gasto com Ativos
$stmt_ativos = $pdo->prepare("SELECT SUM(custo_servico) FROM chamados WHERE equipamento_id > 0 AND data_abertura BETWEEN ? AND ?" . $where_setor);
$stmt_ativos->execute($params);
$custo_ativos = $stmt_ativos->fetchColumn() ?: 0;

// Gasto com Infraestrutura (Setor direto)
$stmt_infra = $pdo->prepare("SELECT SUM(custo_servico) FROM chamados WHERE (equipamento_id IS NULL OR equipamento_id = 0) AND data_abertura BETWEEN ? AND ?" . $where_setor);
$stmt_infra->execute($params);
$custo_setores_direto = $stmt_infra->fetchColumn() ?: 0;

$investimento_geral = $custo_ativos + $custo_setores_direto;

// MTTR
$stmt_mttr = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, data_abertura, data_conclusao)) FROM chamados WHERE status = 'Concluído' AND data_abertura BETWEEN ? AND ?" . $where_setor);
$stmt_mttr->execute($params);
$tempo_medio = $stmt_mttr->fetchColumn() ?: 0;

// --- 5. BUSCA CAUSA RAIZ ---
$stmt_causas = $pdo->prepare("SELECT causa_raiz, SUM(custo_servico) as total FROM chamados WHERE data_abertura BETWEEN ? AND ? AND custo_servico > 0" . $where_setor . " GROUP BY causa_raiz ORDER BY total DESC");
$stmt_causas->execute($params);
$causas_data = $stmt_causas->fetchAll();
$labels_causas = []; $valores_causas = [];
foreach($causas_data as $cd) {
    $labels_causas[] = $cd['causa_raiz'];
    $valores_causas[] = (float)$cd['total'];
}
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center border-bottom pb-3">
        <div class="col-md-4">
            <h2 class="fw-bold mb-0 text-dark">BI - Business Intelligence</h2>
            <small class="text-primary fw-bold text-uppercase">Gestão Estratégica de Manutenção</small>
        </div>
        <div class="col-md-8 d-print-none">
            <form method="GET" class="row g-2 justify-content-end">
                <input type="hidden" name="p" value="relatorios"> 
                <div class="col-md-5">
                    <select name="setor_id" class="form-select form-select-sm border-primary">
                        <option value="">-- Todos os Setores (Geral) --</option>
                        <?php foreach($setores_select as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($setor_filtro == $s['id']) ? 'selected' : '' ?>><?= $s['nome_completo'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <input type="date" name="data_inicio" class="form-control form-control-sm" value="<?= $data_inicio ?>">
                </div>
                <div class="col-auto">
                    <input type="date" name="data_fim" class="form-control form-control-sm" value="<?= $data_fim ?>">
                </div>
                <div class="col-auto text-end">
                    <button type="submit" class="btn btn-primary btn-sm px-3 fw-bold">Filtrar</button>
                    <button type="button" onclick="window.print()" class="btn btn-dark btn-sm"><i class="bi bi-printer"></i></button>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4 align-items-stretch">
        <div class="col-md-3">
            <div class="card border-0 border-start border-4 border-primary shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase fw-bold">Investimento</h6>
                    <h2 class="fw-bold text-dark">R$ <?= number_format($investimento_geral, 2, ',', '.') ?></h2>
                    <div class="small border-top mt-2 pt-2">
                        <div class="d-flex justify-content-between"><span>Ativos:</span> <b>R$ <?= number_format($custo_ativos, 0, ',', '.') ?></b></div>
                        <div class="d-flex justify-content-between"><span>Infra:</span> <b>R$ <?= number_format($custo_setores_direto, 0, ',', '.') ?></b></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body py-3 d-flex flex-column align-items-center justify-content-center text-center text-dark">
                    <div style="width: 100%; height: 200px;">
                        <canvas id="chartStatus"></canvas>
                    </div>
                    <div class="mt-2 fw-bold text-success text-uppercase"><?= $perc_disponivel ?>% Operacional</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 border-start border-4 border-info shadow-sm h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <h6 class="text-muted small text-uppercase fw-bold">MTTR (Resposta)</h6>
                    <h1 class="fw-bold text-dark mb-0"><?= round($tempo_medio, 1) ?>h</h1>
                    <small class="text-muted">Média de conclusão</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12 text-dark">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 fw-bold text-uppercase small text-primary border-bottom">
                    <i class="bi bi-shield-exclamation me-2"></i>Custos por Causa Raiz Identificada
                </div>
                <div class="card-body">
                    <div style="height: 280px;">
                        <canvas id="chartCausaRaiz"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <?php if(!$setor_filtro): ?>
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom fw-bold text-uppercase small text-dark">Maiores Custos por Setor</div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr><th class="ps-3 border-0">Setor (Hierarquia Completa)</th><th class="text-end pe-3 border-0">Total</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt_top_setores = $pdo->prepare("SELECT s.id, SUM(c.custo_servico) as total FROM chamados c JOIN setores s ON c.setor_id = s.id WHERE c.custo_servico > 0 AND c.data_abertura BETWEEN ? AND ? GROUP BY s.id ORDER BY total DESC LIMIT 10");
                            $stmt_top_setores->execute([$params[0], $params[1]]);
                            foreach ($stmt_top_setores->fetchAll() as $sd): 
                                $caminho = getNomeHierarquico($sd['id'], $setores_mapa);
                            ?>
                            <tr><td class="ps-3 small fw-bold text-secondary"><?= htmlspecialchars($caminho) ?></td><td class="text-end pe-3 fw-bold">R$ <?= number_format($sd['total'], 2, ',', '.') ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="<?= $setor_filtro ? 'col-md-12' : 'col-md-6' ?> mb-4 text-center">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom fw-bold text-uppercase small text-dark text-start">Equipamentos mais Custosos</div>
                <div class="card-body p-0 text-start">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr><th class="ps-3 border-0">Equipamento</th><th class="text-end pe-3 border-0">Gasto</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt_rank = $pdo->prepare("SELECT e.nome, SUM(c.custo_servico) as total FROM chamados c JOIN equipamentos e ON c.equipamento_id = e.id WHERE c.custo_servico > 0 AND c.data_abertura BETWEEN ? AND ?" . $where_setor . " GROUP BY e.id ORDER BY total DESC LIMIT 10");
                            $stmt_rank->execute($params);
                            foreach ($stmt_rank->fetchAll() as $ar): ?>
                            <tr><td class="ps-3 small fw-bold text-secondary"><?= htmlspecialchars($ar['nome']) ?></td><td class="text-end pe-3 fw-bold text-danger">R$ <?= number_format($ar['total'], 2, ',', '.') ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Gráfico de Frota
    new Chart(document.getElementById('chartStatus'), {
        type: 'doughnut',
        data: {
            labels: ['Operacionais - <?= $ativos ?>', 'Manutenção - <?= $manutencao ?>', 'Reserva - <?= $reserva ?>'],
            datasets: [{
                data: [<?= $ativos ?>, <?= $manutencao ?>, <?= $reserva ?>],
                backgroundColor: ['#28a745', '#dc3545', '#17a2b8'],
                borderWidth: 2, borderColor: '#ffffff'
            }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 15, font: { weight: 'bold', size: 12 } } } } }
    });

    // Gráfico de Causa Raiz Multi-cores
    new Chart(document.getElementById('chartCausaRaiz'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels_causas) ?>,
            datasets: [{
                label: 'Custo (R$)',
                data: <?= json_encode($valores_causas) ?>,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.8)', 'rgba(255, 99, 132, 0.8)', 'rgba(255, 206, 86, 0.8)', 
                    'rgba(75, 192, 192, 0.8)', 'rgba(153, 102, 255, 0.8)', 'rgba(255, 159, 64, 0.8)', 
                    'rgba(199, 199, 199, 0.8)', 'rgba(83, 102, 255, 0.8)', 'rgba(40, 167, 69, 0.8)', 'rgba(220, 53, 69, 0.8)'
                ],
                borderRadius: 5
            }]
        },
        options: { 
            indexAxis: 'y', 
            maintainAspectRatio: false, 
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                y: { grid: { display: false } }
            }
        }
    });
</script>

<style>
body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
.card { border-radius: 12px; }
.border-primary { border-color: #0d6efd !important; }
.border-info { border-color: #0dcaf0 !important; }
@media print { .d-print-none { display: none !important; } body { background-color: white; } .card { box-shadow: none !important; border: 1px solid #ddd !important; } }
</style>
