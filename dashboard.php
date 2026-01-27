<?php
include_once 'includes/db.php';

// 1. Contadores de Totais
$total_equipamentos = $pdo->query("SELECT COUNT(*) FROM equipamentos")->fetchColumn();
$chamados_abertos = $pdo->query("SELECT COUNT(*) FROM chamados WHERE status = 'Aberto'")->fetchColumn();
$chamados_em_andamento = $pdo->query("SELECT COUNT(*) FROM chamados WHERE status = 'Em Atendimento'")->fetchColumn();
$chamados_concluidos = $pdo->query("SELECT COUNT(*) FROM chamados WHERE status = 'Concluído'")->fetchColumn();

// Contador de Itens Emprestados
$total_emprestimos = $pdo->query("SELECT COUNT(*) FROM emprestimos WHERE status = 'Emprestado'")->fetchColumn() ?: 0;

// Lógica de Preventivas Atrasadas
$hoje = date('Y-m-d');
$sql_preventivas = "
    SELECT COUNT(*) 
    FROM equipamentos 
    WHERE periodicidade_preventiva > 0 
    AND data_ultima_preventiva IS NOT NULL 
    AND DATE_ADD(data_ultima_preventiva, INTERVAL periodicidade_preventiva DAY) <= '$hoje'
";
$preventivas_vencidas = $pdo->query($sql_preventivas)->fetchColumn();

// 2. Dados de Reserva por Tipo
$estoque_reserva = $pdo->query("
    SELECT t.nome as tipo, COUNT(e.id) as total 
    FROM equipamentos e
    JOIN tipos_equipamentos t ON e.tipo_id = t.id
    WHERE e.status = 'Reserva'
    GROUP BY t.id
    ORDER BY t.nome ASC
")->fetchAll();

// 3. CORREÇÃO: Dados para o Gráfico: Chamados por Setor
// Buscamos o nome do setor diretamente do chamado ou via equipamento
$setores_stats = $pdo->query("
    SELECT s.nome, COUNT(c.id) as total
    FROM chamados c
    JOIN setores s ON c.setor_id = s.id
    GROUP BY s.id
    HAVING total > 0
    ORDER BY total DESC
")->fetchAll();

$labels_setores = []; $valores_setores = [];
foreach($setores_stats as $stat) {
    $labels_setores[] = $stat['nome'];
    $valores_setores[] = (int)$stat['total'];
}

// 4. Dados para o Gráfico: Chamados por Técnico
$tecnicos_stats = $pdo->query("
    SELECT tecnico_responsavel, COUNT(*) as total 
    FROM chamados 
    WHERE tecnico_responsavel IS NOT NULL AND tecnico_responsavel != ''
    GROUP BY tecnico_responsavel
    ORDER BY total DESC
")->fetchAll();

$labels_tecnicos = []; $valores_tecnicos = [];
foreach($tecnicos_stats as $tstat) {
    $labels_tecnicos[] = $tstat['tecnico_responsavel'];
    $valores_tecnicos[] = (int)$tstat['total'];
}
?>

<style>
    .card-link { transition: all 0.2s ease-in-out; text-decoration: none !important; }
    .card-link:hover { transform: translateY(-4px); box-shadow: 0 8px 15px rgba(0,0,0,0.1) !important; }
    .card-link .card { border: none; }
    #chartSetores, #chartTecnicos { min-height: 250px; }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
        <h2 class="text-dark"><i class="bi bi-speedometer2 text-primary"></i> Painel de Controle</h2>
        <span class="text-muted small">Atualizado em: <?= date('d/m/Y H:i') ?></span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <a href="index.php?p=equipamentos" class="card-link">
                <div class="card bg-primary text-white shadow-sm h-100">
                    <div class="card-body py-3 text-center">
                        <h6 class="small opacity-75 text-uppercase">Total Ativos</h6>
                        <h2 class="mb-0 fw-bold"><?= $total_equipamentos ?></h2>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-2">
            <a href="index.php?p=preventivas" class="card-link">
                <div class="card shadow-sm h-100 <?= $preventivas_vencidas > 0 ? 'bg-dark text-warning border-start border-warning border-4' : 'bg-light' ?>">
                    <div class="card-body py-3 text-center">
                        <h6 class="small text-uppercase">Preventivas</h6>
                        <h2 class="mb-0 fw-bold"><i class="bi bi-calendar-x me-1"></i><?= $preventivas_vencidas ?></h2>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-2">
            <a href="index.php?p=chamados&status=Aberto" class="card-link">
                <div class="card bg-danger text-white shadow-sm h-100">
                    <div class="card-body py-3 text-center">
                        <h6 class="small opacity-75 text-uppercase">Abertos</h6>
                        <h2 class="mb-0 fw-bold"><?= $chamados_abertos ?></h2>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-2">
            <a href="index.php?p=chamados&status=Em Atendimento" class="card-link">
                <div class="card bg-warning text-dark shadow-sm h-100">
                    <div class="card-body py-3 text-center">
                        <h6 class="small opacity-75 text-uppercase">Em Atendimento</h6>
                        <h2 class="mb-0 fw-bold"><?= $chamados_em_andamento ?></h2>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-2">
            <a href="index.php?p=chamados&status=Concluído" class="card-link">
                <div class="card bg-success text-white shadow-sm h-100">
                    <div class="card-body py-3 text-center">
                        <h6 class="small opacity-75 text-uppercase">Concluídos</h6>
                        <h2 class="mb-0 fw-bold"><?= $chamados_concluidos ?></h2>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-2">
            <a href="index.php?p=relatorio_emprestimos" class="card-link">
                <div class="card bg-info text-white shadow-sm h-100">
                    <div class="card-body py-3 text-center">
                        <h6 class="small opacity-75 text-uppercase">Emprestados</h6>
                        <h2 class="mb-0 fw-bold"><i class="bi bi-arrow-left-right me-1"></i><?= $total_emprestimos ?></h2>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100 text-dark">
                <div class="card-header bg-white fw-bold border-bottom">
                    <i class="bi bi-pie-chart-fill me-2 text-primary"></i>Incidentes por Setor
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <?php if (empty($labels_setores)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-graph-down fs-1 d-block mb-2"></i>
                            <p class="small mb-0">Nenhum dado de setor disponível.</p>
                        </div>
                    <?php else: ?>
                        <canvas id="chartSetores"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100 text-dark">
                <div class="card-header bg-white fw-bold border-bottom">
                    <i class="bi bi-bar-chart-fill me-2 text-info"></i>Desempenho da Equipe
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <?php if (empty($labels_tecnicos)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-person-x fs-1 d-block mb-2"></i>
                            <p class="small mb-0">Nenhum chamado atribuído.</p>
                        </div>
                    <?php else: ?>
                        <canvas id="chartTecnicos"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4 text-dark">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold border-bottom">
                    <i class="bi bi-clock-history me-2 text-warning"></i>Últimas Atualizações
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php
                        $recentes = $pdo->query("SELECT id, titulo, status, data_abertura FROM chamados ORDER BY id DESC LIMIT 6")->fetchAll();
                        foreach($recentes as $r):
                            $cor = match($r['status']) {
                                'Aberto' => 'text-danger',
                                'Concluído' => 'text-success',
                                default => 'text-primary'
                            };
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <small class="d-block text-muted" style="font-size: 0.7rem;"><?= date('d/m H:i', strtotime($r['data_abertura'])) ?></small>
                                    <a href="index.php?p=tratar_chamado&id=<?= $r['id'] ?>" class="text-decoration-none text-dark fw-medium" style="font-size: 0.85rem;"><?= htmlspecialchars($r['titulo']) ?></a>
                                </div>
                                <span class="badge rounded-pill bg-light <?= $cor ?> border" style="font-size: 0.65rem;"><?= $r['status'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Configurações Globais do Chart.js
    Chart.defaults.font.family = 'Segoe UI, system-ui, -apple-system';
    
    <?php if (!empty($labels_setores)): ?>
    const ctxSetores = document.getElementById('chartSetores');
    new Chart(ctxSetores, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($labels_setores) ?>,
            datasets: [{
                data: <?= json_encode($valores_setores) ?>,
                backgroundColor: ['#0d6efd', '#dc3545', '#ffc107', '#198754', '#0dcaf0', '#6610f2', '#fd7e14'],
                hoverOffset: 10,
                borderWidth: 2
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: { 
                legend: { position: 'bottom', labels: { padding: 20, boxWidth: 12, font: { size: 11 } } } 
            }
        }
    });
    <?php endif; ?>

    <?php if (!empty($labels_tecnicos)): ?>
    const ctxTecnicos = document.getElementById('chartTecnicos');
    new Chart(ctxTecnicos, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels_tecnicos) ?>,
            datasets: [{
                label: 'Chamados',
                data: <?= json_encode($valores_tecnicos) ?>,
                backgroundColor: 'rgba(13, 202, 240, 0.7)',
                borderColor: '#0dcaf0',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: { 
            indexAxis: 'y', 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { 
                x: { beginAtZero: true, grid: { display: false } }, 
                y: { grid: { display: false } } 
            }
        }
    });
    <?php endif; ?>
</script>
