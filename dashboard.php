<?php
include_once 'includes/db.php';

// 1. Contador de Totais
$total_equipamentos = $pdo->query("SELECT COUNT(*) FROM equipamentos")->fetchColumn();
$chamados_abertos = $pdo->query("SELECT COUNT(*) FROM chamados WHERE status = 'Aberto'")->fetchColumn();
$chamados_em_andamento = $pdo->query("SELECT COUNT(*) FROM chamados WHERE status = 'Em Atendimento'")->fetchColumn();
$chamados_concluidos = $pdo->query("SELECT COUNT(*) FROM chamados WHERE status = 'Concluído'")->fetchColumn();

// 2. Dados de Reserva por Tipo (NOVO)
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
    <h2 class="mb-4"><i class="bi bi-speedometer2"></i> Painel de Controle</h2>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white shadow-sm border-0">
                <div class="card-body">
                    <h6>Total Equipamentos</h6>
                    <h2 class="mb-0"><?= $total_equipamentos ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white shadow-sm border-0">
                <div class="card-body">
                    <h6>Aguardando Técnico</h6>
                    <h2 class="mb-0"><?= $chamados_abertos ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark shadow-sm border-0">
                <div class="card-body">
                    <h6>Em Manutenção</h6>
                    <h2 class="mb-0"><?= $chamados_em_andamento ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white shadow-sm border-0">
                <div class="card-body">
                    <h6>Resolvidos</h6>
                    <h2 class="mb-0"><?= $chamados_concluidos ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-box-seam me-2 text-primary"></i>Reserva Técnica (Estoque de Contingência)
                </div>
                <div class="card-body">
                    <?php if (empty($estoque_reserva)): ?>
                        <p class="text-muted mb-0 small">Nenhum equipamento em status de 'Reserva' no momento.</p>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($estoque_reserva as $res): ?>
                                <div class="p-3 border rounded shadow-sm bg-light d-flex align-items-center" style="min-width: 180px;">
                                    <div class="flex-grow-1">
                                        <small class="text-muted text-uppercase d-block" style="font-size: 0.65rem;"><?= htmlspecialchars($res['tipo']) ?></small>
                                        <h4 class="mb-0 fw-bold text-dark"><?= $res['total'] ?> <small class="text-muted fw-normal fs-6">unid.</small></h4>
                                    </div>
                                    <div class="ms-3">
                                        <i class="bi bi-pc-display-horizontal fs-3 text-secondary"></i>
                                    </div>
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
                <div class="card-header bg-white fw-bold">Chamados por Setor</div>
                <div class="card-body">
                    <canvas id="chartSetores"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold">Chamados por Técnico</div>
                <div class="card-body">
                    <canvas id="chartTecnicos"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold">Atividades Recentes</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php
                        $recentes = $pdo->query("SELECT titulo, status, data_abertura FROM chamados ORDER BY id DESC LIMIT 5")->fetchAll();
                        foreach($recentes as $r):
                            $cor = $r['status'] == 'Aberto' ? 'text-danger' : ($r['status'] == 'Concluído' ? 'text-success' : 'text-primary');
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="d-block text-muted"><?= date('d/m H:i', strtotime($r['data_abertura'])) ?></small>
                                    <span class="text-truncate d-inline-block" style="max-width: 150px;"><?= $r['titulo'] ?></span>
                                </div>
                                <span class="small fw-bold <?= $cor ?>"><?= $r['status'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-footer bg-white text-center">
                    <a href="index.php?p=chamados" class="btn btn-sm btn-link text-decoration-none">Ver todos</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Gráfico de Setores (Doughnut)
    const ctxSetores = document.getElementById('chartSetores');
    new Chart(ctxSetores, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($labels_setores) ?>,
            datasets: [{
                data: <?= json_encode($valores_setores) ?>,
                backgroundColor: ['#0d6efd', '#dc3545', '#ffc107', '#198754', '#6610f2'],
                borderWidth: 1
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    // Gráfico de Técnicos (Barra Horizontal)
    const ctxTecnicos = document.getElementById('chartTecnicos');
    new Chart(ctxTecnicos, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels_tecnicos) ?>,
            datasets: [{
                label: 'Chamados',
                data: <?= json_encode($valores_tecnicos) ?>,
                backgroundColor: '#0dcaf0',
                borderRadius: 5
            }]
        },
        options: { 
            indexAxis: 'y',
            responsive: true, 
            plugins: { legend: { display: false } } 
        }
    });
</script>
