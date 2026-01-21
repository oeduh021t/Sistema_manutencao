<?php
include_once 'includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) { die("<div class='alert alert-danger mt-3'>ID do Equipamento não especificado.</div>"); }

// 1. BUSCA DADOS CONSOLIDADOS DO EQUIPAMENTO
$stmt = $pdo->prepare("
    SELECT e.*, s.nome as setor_nome, t.nome as tipo_nome 
    FROM equipamentos e
    LEFT JOIN setores s ON e.setor_id = s.id
    LEFT JOIN tipos_equipamentos t ON e.tipo_id = t.id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$eq = $stmt->fetch();

if (!$eq) { die("<div class='alert alert-warning mt-3'>Ativo não localizado.</div>"); }

// 2. CÁLCULO DO CUSTO TOTAL ACUMULADO
$stmt_custo = $pdo->prepare("SELECT SUM(custo_servico) FROM chamados WHERE equipamento_id = ? AND status = 'Concluído'");
$stmt_custo->execute([$id]);
$custo_total = $stmt_custo->fetchColumn() ?: 0;

// 3. BUSCA A LINHA DO TEMPO (CHAMADOS + TROCAS)
$stmt_hist = $pdo->prepare("
    SELECT h.data_registro as data, h.texto_historico as evento, h.status_momento as status, 
           h.tecnico_nome as tecnico, 'chamado' as tipo, c.id as ref_id, c.tipo_manutencao
    FROM chamados_historico h
    JOIN chamados c ON h.chamado_id = c.id
    WHERE c.equipamento_id = ?
");
$stmt_hist->execute([$id]);
$res_atendimentos = $stmt_hist->fetchAll();

$stmt_mov = $pdo->prepare("
    SELECT m.data_movimentacao as data, m.descricao_log as evento, m.status_novo as status, 
           m.tecnico_nome as tecnico, 'troca' as tipo, 0 as ref_id, 'Logística' as tipo_manutencao
    FROM equipamentos_historico m
    WHERE m.equipamento_id = ?
");
$stmt_mov->execute([$id]);
$res_movs = $stmt_mov->fetchAll();

$timeline = array_merge($res_atendimentos, $res_movs);
usort($timeline, function($a, $b) { return strtotime($b['data']) - strtotime($a['data']); });
?>

<div class="container-fluid text-dark">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <h2 class="fw-bold"><i class="bi bi-journal-medical text-primary"></i> Prontuário do Ativo</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-success shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalBaixaPreventiva">
                <i class="bi bi-calendar-check"></i> Baixar Preventiva
            </button>
            <a href="relatorio_equipamento.php?id=<?= $id ?>" target="_blank" class="btn btn-danger shadow-sm fw-bold">
                <i class="bi bi-file-earmark-pdf"></i> PDF
            </a>
            <a href="index.php?p=equipamentos" class="btn btn-secondary shadow-sm">Voltar</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4 overflow-hidden">
                <div class="card-body text-center bg-white">
                    <?php if ($eq['foto_equipamento']): ?>
                        <img src="uploads/<?= $eq['foto_equipamento'] ?>" class="img-fluid rounded border shadow-sm mb-3" style="max-height: 280px; width: 100%; object-fit: contain;">
                    <?php else: ?>
                        <div class="bg-light py-5 rounded mb-3 text-muted border">
                            <i class="bi bi-pc-display" style="font-size: 5rem;"></i>
                            <p class="small mb-0">Sem foto cadastrada</p>
                        </div>
                    <?php endif; ?>
                    
                    <h4 class="fw-bold text-dark mb-3"><?= htmlspecialchars($eq['nome']) ?></h4>
                </div>

                <div class="list-group list-group-flush border-top">
                    <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <span class="text-muted fw-bold small text-uppercase">Tipo</span>
                        <span class="fw-bold text-dark"><?= htmlspecialchars($eq['tipo_nome']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <span class="text-muted fw-bold small text-uppercase">Local</span>
                        <span class="fw-bold text-primary"><?= htmlspecialchars($eq['setor_nome']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <span class="text-muted fw-bold small text-uppercase">Patrimônio</span>
                        <span class="badge bg-dark px-3 py-2 fs-6"><?= htmlspecialchars($eq['patrimonio']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center py-3 bg-light">
                        <span class="text-muted fw-bold small text-uppercase">Custo Total</span>
                        <span class="fw-bold text-danger fs-5">R$ <?= number_format($custo_total, 2, ',', '.') ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <span class="text-muted fw-bold small text-uppercase">Status</span>
                        <?php 
                            $status_class = ($eq['status'] == 'Ativo') ? 'bg-success' : (($eq['status'] == 'Em Manutenção') ? 'bg-danger' : 'bg-warning text-dark');
                        ?>
                        <span class="badge <?= $status_class ?> px-3 py-2 text-uppercase"><?= $eq['status'] ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3 text-dark border-bottom">
                    <i class="bi bi-clock-history me-2 text-primary"></i>Cronologia de Intervenções
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-dark">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">Data</th>
                                    <th>Descrição / Tratativa</th>
                                    <th>Tipo</th>
                                    <th>Responsável</th>
                                    <th class="text-end pe-4">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php foreach ($timeline as $item): 
                                    $is_troca = ($item['tipo'] == 'troca');
                                    $badge_class = $is_troca ? 'bg-secondary' : ($item['tipo_manutencao'] == 'Preventiva' ? 'bg-success' : 'bg-primary');
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><?= date('d/m/Y H:i', strtotime($item['data'])) ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($item['evento']) ?></div>
                                    </td>
                                    <td><span class="badge <?= $badge_class ?>"><?= $is_troca ? 'Logística' : $item['tipo_manutencao'] ?></span></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($item['tecnico']) ?></div>
                                        <span class="text-muted small text-uppercase" style="font-size: 0.65rem;"><?= $item['status'] ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if(!$is_troca): ?>
                                            <a href="index.php?p=tratar_chamado&id=<?= $item['ref_id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="bi bi-search"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
