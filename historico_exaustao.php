<?php
include_once 'includes/db.php';

// Busca todas as preventivas de exaustão realizadas
$query = "SELECT c.id, c.data_manutencao, e.nome as equipamento, e.patrimonio, s.nome as setor, c.status_final, c.tecnico_nome 
          FROM checklist_exaustao c
          JOIN equipamentos e ON c.equipamento_id = e.id
          JOIN setores s ON e.setor_id = s.id
          ORDER BY c.data_manutencao DESC";

$historico = $pdo->query($query)->fetchAll();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark"><i class="bi bi-clock-history text-info me-2"></i>Histórico de Preventivas - Exaustão</h2>
        <a href="index.php?p=chamados" class="btn btn-primary fw-bold shadow-sm">
            <i class="bi bi-plus-lg"></i> Novo Checklist
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Data/Hora</th>
                            <th>Equipamento</th>
                            <th>Setor</th>
                            <th>Técnico</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($historico) > 0): ?>
                            <?php foreach ($historico as $h): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-bold"><?= date('d/m/Y', strtotime($h['data_manutencao'])) ?></span><br>
                                    <small class="text-muted"><?= date('H:i', strtotime($h['data_manutencao'])) ?></small>
                                </td>
                                <td>
                                    <span class="text-dark fw-bold"><?= htmlspecialchars($h['equipamento']) ?></span><br>
                                    <small class="badge bg-secondary">Pat: <?= $h['patrimonio'] ?></small>
                                </td>
                                <td><?= htmlspecialchars($h['setor']) ?></td>
                                <td><?= htmlspecialchars($h['tecnico_nome']) ?></td>
                                <td class="text-center">
                                    <?php if ($h['status_final'] == 'Operando Normalmente'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success">OK</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger">COM PENDÊNCIA</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="visualizar_exaustor.php?id_check=<?= $h['id'] ?>" target="_blank" class="btn btn-sm btn-outline-info shadow-sm" title="Ver Laudo">
                                        <i class="bi bi-file-earmark-pdf-fill"></i> Visualizar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">Nenhum checklist de exaustão encontrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
