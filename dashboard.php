<?php
include_once 'includes/db.php';

// 1. Contadores de Totais
$total_equipamentos = $pdo->query("SELECT COUNT(*) FROM equipamentos")->fetchColumn();
$chamados_abertos = $pdo->query("SELECT COUNT(*) FROM chamados WHERE status = 'Aberto'")->fetchColumn();
$chamados_em_andamento = $pdo->query("SELECT COUNT(*) FROM chamados WHERE status = 'Em Atendimento'")->fetchColumn();
$chamados_concluidos = $pdo->query("SELECT COUNT(*) FROM chamados WHERE status = 'Concluído'")->fetchColumn();

// --- NOVO: Lógica de Preventivas Atrasadas ---
// Busca equipamentos onde (data_ultima + periodicidade) <= hoje
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

// 3. Dados para o Gráfico: Chamados por Setor
$setores_stats = $pdo->query("
    SELECT s.nome, COUNT(c.id) as total
    FROM setores s
    LEFT JOIN equipamentos e ON e.setor_id = s.id
    LEFT JOIN chamados c ON c.equipamento_id = e.id
    GROUP BY s.id
    HAVING total > 0
")->fetchAll();

$labels_setores = [];
$valores_setores = [];
foreach($setores_stats as $stat) {
    $labels_setores[] = $stat['nome'];
    $valores_setores[] = $stat['total'];
}

// 4. Dados para o Gráfico: Chamados por Técnico
$tecnicos_stats = $pdo->query("
    SELECT tecnico_responsavel, COUNT(*) as total 
    FROM chamados 
    WHERE tecnico_responsavel IS NOT NULL AND tecnico_responsavel != ''
    GROUP BY tecnico_responsavel
")->fetchAll();

$labels_tecnicos = [];
$valores_tecnicos = [];
foreach($tecnicos_stats as $tstat) {
    $labels_tecnicos[] = $tstat['tecnico_responsavel'];
    $valores_tecnicos[] = $tstat['total'];
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
        <h2><i class="bi bi-speedometer2"></i> Painel de Controle</h2>
        <span class="text-muted small">Atualizado em: <?= date('d/m/Y H:i') ?></span>
    </div>

    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white shadow-sm border-0 h-100">
                <div class="card-body py-3">
                    <h6 class="small">Total Ativos</h6>
                    <h2 class="mb-0 fw-bold"><?= $total_equipamentos ?></h2>
                </div>
            </div>
        </div>
       <div class="col-md-3">
        <a href="index.php?p=preventivas" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 <?= $preventivas_vencidas > 0 ? 'bg-dark text-warning' : 'bg-light' ?>">
            <div class="card-body">
                <small class="<?= $preventivas_vencidas > 0 ? 'text-warning' : 'text-muted' ?>">Preventivas Vencidas</small>
                <h2 class="fw-bold mb-0">
                    <i class="bi bi-calendar-x me-2"></i><?= $preventivas_vencidas ?>
                </h2>
                <small class="text-muted" style="font-size: 0.6rem;">Clique para ver a lista</small>
            </div>
         </div>
        </a>
        </div>



        <div class="col-md-2">
            <div class="card bg-danger text-white shadow-sm border-0 h-100">
                <div class="card-body py-3">
                    <h6 class="small">Chamados Abertos</h6>
                    <h2 class="mb-0 fw-bold"><?= $chamados_abertos ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-dark shadow-sm border-0 h-100">
                <div class="card-body py-3">
                    <h6 class="small">Em Atendimento</h6>
                    <h2 class="mb-0 fw-bold"><?= $chamados_em_andamento ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white shadow-sm border-0 h-100">
                <div class="card-body py-3">
                    <h6 class="small">Chamados Concluídos</h6>
                    <h2 class="mb-0 fw-bold"><?= $chamados_concluidos ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-box-seam me-2 text-primary"></i>Reserva Técnica (Prontos para Substituição)
                </div>
                <div class="card-body">
                    <?php if (empty($estoque_reserva)): ?>
                        <p class="text-muted mb-0 small">Nenhum equipamento em 'Reserva'.</p>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($estoque_reserva as $res): ?>
                                <div class="p-3 border rounded shadow-sm bg-light d-flex align-items-center" style="min-width: 160px;">
                                    <div class="flex-grow-1">
                                        <small class="text-muted text-uppercase d-block" style="font-size: 0.6rem;"><?= htmlspecialchars($res['tipo']) ?></small>
                                        <h4 class="mb-0 fw-bold"><?= $res['total'] ?></h4>
                                    </div>
                                    <i class="bi bi-hdd-fill fs-3 text-secondary opacity-50"></i>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold">Incidentes por Setor</div>
                <div class="card-body">
                    <canvas id="chartSetores"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold">Desempenho da Equipe</div>
                <div class="card-body">
                    <canvas id="chartTecnicos"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold">Últimas Atualizações</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php
                        $recentes = $pdo->query("SELECT titulo, status, data_abertura FROM chamados ORDER BY id DESC LIMIT 6")->fetchAll();
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
                                    <span class="text-truncate d-inline-block fw-medium" style="max-width: 180px; font-size: 0.9rem;"><?= $r['titulo'] ?></span>
                                </div>
                                <span class="badge rounded-pill bg-light <?= $cor ?> border"><?= $r['status'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-footer bg-white text-center">
                    <a href="index.php?p=chamados" class="btn btn-sm btn-link text-decoration-none">Ver todos os chamados</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Gráfico de Setores
    const ctxSetores = document.getElementById('chartSetores');
    new Chart(ctxSetores, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($labels_setores) ?>,
            datasets: [{
                data: <?= json_encode($valores_setores) ?>,
                backgroundColor: ['#0d6efd', '#dc3545', '#ffc107', '#198754', '#0dcaf0', '#6610f2'],
                hoverOffset: 4
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } 
        }
    });

    // Gráfico de Técnicos
    const ctxTecnicos = document.getElementById('chartTecnicos');
    new Chart(ctxTecnicos, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels_tecnicos) ?>,
            datasets: [{
                label: 'Chamados Atendidos',
                data: <?= json_encode($valores_tecnicos) ?>,
                backgroundColor: 'rgba(13, 202, 240, 0.7)',
                borderColor: '#0dcaf0',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: { 
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, grid: { display: false } }, y: { grid: { display: false } } }
        }
    });
</script>
