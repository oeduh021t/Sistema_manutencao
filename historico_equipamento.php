<?php
include_once 'includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) { die("Equipamento não encontrado."); }

// 1. Busca os dados do equipamento
$stmt = $pdo->prepare("
    SELECT e.*, s.nome as setor_nome, t.nome as tipo_nome 
    FROM equipamentos e
    LEFT JOIN setores s ON e.setor_id = s.id
    LEFT JOIN tipos_equipamentos t ON e.tipo_id = t.id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$eq = $stmt->fetch();

// 2. Busca Chamados (Manutenções)
$stmt_chamados = $pdo->prepare("SELECT id, data_abertura as data, titulo as evento, status, tecnico_responsavel as tecnico, 'chamado' as tipo FROM chamados WHERE equipamento_id = ?");
$stmt_chamados->execute([$id]);
$res_chamados = $stmt_chamados->fetchAll();

// 3. Busca Movimentações (Trocas de Setor)
$stmt_mov = $pdo->prepare("
    SELECT m.id, m.data_movimentacao as data, m.descricao_log as evento, m.status_novo as status, m.tecnico_nome as tecnico, 'troca' as tipo, s1.nome as de, s2.nome as para
    FROM equipamentos_historico m
    LEFT JOIN setores s1 ON m.setor_origem_id = s1.id
    LEFT JOIN setores s2 ON m.setor_destino_id = s2.id
    WHERE m.equipamento_id = ?
");
$stmt_mov->execute([$id]);
$res_movs = $stmt_mov->fetchAll();

// 4. Une e Ordena por data (mais recente primeiro)
$timeline = array_merge($res_chamados, $res_movs);
usort($timeline, function($a, $b) {
    return strtotime($b['data']) - strtotime($a['data']);
});
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <h2><i class="bi bi-journal-medical text-primary"></i> Prontuário do Ativo</h2>
        <div>
            <a href="relatorio_equipamento.php?id=<?= $id ?>" target="_blank" class="btn btn-danger shadow-sm">
                <i class="bi bi-file-earmark-pdf"></i> Gerar Relatório Técnico
            </a>
            <a href="index.php?p=equipamentos" class="btn btn-secondary shadow-sm">Voltar</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body text-center">
                    <?php if ($eq['foto_equipamento']): ?>
                        <img src="uploads/<?= $eq['foto_equipamento'] ?>" class="img-fluid rounded border mb-3 shadow-sm" style="max-height: 250px;">
                    <?php else: ?>
                        <div class="bg-light py-5 rounded mb-3">
                            <i class="bi bi-pc-display text-muted" style="font-size: 5rem;"></i>
                        </div>
                    <?php endif; ?>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($eq['nome']) ?></h4>
                    <span class="badge bg-dark mb-3">SISTEMA MNT</span>
                </div>
                <div class="list-group list-group-flush border-top">
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="text-muted small">Tipo:</span>
                        <span class="fw-bold"><?= htmlspecialchars($eq['tipo_nome']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="text-muted small">Setor Atual:</span>
                        <span class="fw-bold text-primary"><?= htmlspecialchars($eq['setor_nome']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="text-muted small">Nº de Série:</span>
                        <span class="fw-bold"><?= htmlspecialchars($eq['num_serie']) ?: '---' ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="text-muted small">Patrimônio:</span>
                        <span class="fw-bold"><?= htmlspecialchars($eq['patrimonio']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <?php 
                            $status_color = ($eq['status'] == 'Ativo') ? 'success' : (($eq['status'] == 'Reserva') ? 'info' : 'warning');
                        ?>
                        <span class="text-muted small">Status:</span>
                        <span class="badge bg-<?= $status_color ?>"><?= $eq['status'] ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-clock-history me-2"></i>Linha do Tempo de Intervenções
                </div>
                <div class="card-body">
                    <?php if (empty($timeline)): ?>
                        <p class="text-center text-muted py-5">Nenhum evento registrado para este ativo.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data</th>
                                        <th>Evento / Descrição</th>
                                        <th>Tipo</th>
                                        <th>Responsável</th>
                                        <th class="text-end">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($timeline as $item): 
                                        $is_chamado = ($item['tipo'] == 'chamado');
                                        $cor_badge = $is_chamado ? 'primary' : 'secondary';
                                    ?>
                                        <tr>
                                            <td class="small fw-bold"><?= date('d/m/Y H:i', strtotime($item['data'])) ?></td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($item['evento']) ?></div>
                                                <?php if (!$is_chamado): ?>
                                                    <small class="text-muted">Movimentação: <b><?= $item['de'] ?></b> <i class="bi bi-arrow-right"></i> <b><?= $item['para'] ?></b></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $cor_badge ?> shadow-sm">
                                                    <?= $is_chamado ? 'Manutenção' : 'Logística' ?>
                                                </span>
                                            </td>
                                            <td class="small"><?= $item['tecnico'] ?></td>
                                            <td class="text-end">
                                                <?php if($is_chamado): ?>
                                                    <a href="index.php?p=tratar_chamado&id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        Detalhes <i class="bi bi-chevron-right"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
