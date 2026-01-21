<?php
include_once 'includes/db.php';

// 1. DEFINIÇÃO DO ID
$id = $_GET['id'] ?? null;

if (!$id) {
    die("<div class='alert alert-danger mt-3'>ID do Equipamento não especificado.</div>");
}

// 2. FUNÇÃO PARA GERAR O QR CODE (QR Server)
function gerarLinkQRCodeLocal($id) {
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $dominio = $_SERVER['HTTP_HOST'];
    $caminho_projeto = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
    if ($caminho_projeto == '/') $caminho_projeto = '';
    $url_destino = "{$protocolo}://{$dominio}{$caminho_projeto}/qrcode.php?id={$id}";
    return "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($url_destino);
}

// --- LÓGICA PARA REGISTRAR NOVO EMPRÉSTIMO ---
if (isset($_POST['registrar_emprestimo'])) {
    $item = $_POST['item_acessorio'];
    $solicitante = $_POST['solicitante'];
    $obs = $_POST['observacao'];
    
    $stmt_ins = $pdo->prepare("INSERT INTO emprestimos (equipamento_id, item_acessorio, solicitante, observacao, status) VALUES (?, ?, ?, ?, 'Emprestado')");
    $stmt_ins->execute([$id, $item, $solicitante, $obs]);
    echo "<div class='alert alert-success shadow-sm'>Empréstimo registrado com sucesso!</div>";
}

// --- LÓGICA PARA DEVOLUÇÃO ---
if (isset($_GET['devolver_item'])) {
    $emp_id = $_GET['devolver_item'];
    $stmt_dev = $pdo->prepare("UPDATE emprestimos SET status = 'Devolvido', data_devolucao = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt_dev->execute([$emp_id]);
    echo "<script>window.location.href='index.php?p=historico_equipamento&id=$id';</script>";
}

// 3. Busca os dados do equipamento
$stmt = $pdo->prepare("
    SELECT e.*, s.nome as setor_nome, t.nome as tipo_nome
    FROM equipamentos e
    LEFT JOIN setores s ON e.setor_id = s.id
    LEFT JOIN tipos_equipamentos t ON e.tipo_id = t.id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$eq = $stmt->fetch();

if (!$eq) {
    die("<div class='alert alert-warning mt-3'>Equipamento não encontrado no banco de dados.</div>");
}

// 4. Busca Chamados (Manutenções)
$stmt_chamados = $pdo->prepare("SELECT id, data_abertura as data, titulo as evento, status, tecnico_responsavel as tecnico, 'chamado' as tipo FROM chamados WHERE equipamento_id = ?");
$stmt_chamados->execute([$id]);
$res_chamados = $stmt_chamados->fetchAll();

// 5. Busca Movimentações (Trocas de Setor)
$stmt_mov = $pdo->prepare("
    SELECT m.id, m.data_movimentacao as data, m.descricao_log as evento, m.status_novo as status, m.tecnico_nome as tecnico, 'troca' as tipo, s1.nome as de, s2.nome as para
    FROM equipamentos_historico m
    LEFT JOIN setores s1 ON m.setor_origem_id = s1.id
    LEFT JOIN setores s2 ON m.setor_destino_id = s2.id
    WHERE m.equipamento_id = ?
");
$stmt_mov->execute([$id]);
$res_movs = $stmt_mov->fetchAll();

// 5.1 Busca Histórico de Empréstimos (Para a Timeline)
$stmt_emp_hist = $pdo->prepare("SELECT id, data_empréstimo as data, CONCAT('EMPRÉSTIMO: ', item_acessorio, ' para ', solicitante) as evento, status, '' as tecnico, 'emprestimo' as tipo FROM emprestimos WHERE equipamento_id = ?");
$stmt_emp_hist->execute([$id]);
$res_emps = $stmt_emp_hist->fetchAll();

// 6. Une e Ordena por data
$timeline = array_merge($res_chamados, $res_movs, $res_emps);
usort($timeline, function($a, $b) {
    return strtotime($b['data']) - strtotime($a['data']);
});

// 7. Cálculo do Custo Acumulado
$stmt_custo = $pdo->prepare("SELECT SUM(custo_servico) FROM chamados WHERE equipamento_id = ?");
$stmt_custo->execute([$id]);
$custo_total = $stmt_custo->fetchColumn() ?: 0;

// 8. Busca Empréstimos ATIVOS (Para o Alerta)
$stmt_ativos = $pdo->prepare("SELECT * FROM emprestimos WHERE equipamento_id = ? AND status = 'Emprestado'");
$stmt_ativos->execute([$id]);
$emprestimos_ativos = $stmt_ativos->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <h2><i class="bi bi-journal-medical text-primary"></i> Prontuário do Ativo</h2>
        <div>
            <button class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalEmprestimo">
                <i class="bi bi-plug"></i> Emprestar Acessório
            </button>
            <a href="relatorio_equipamento.php?id=<?= $id ?>" target="_blank" class="btn btn-danger shadow-sm">
                <i class="bi bi-file-earmark-pdf"></i> PDF
            </a>
            <a href="index.php?p=equipamentos" class="btn btn-secondary shadow-sm">Voltar</a>
        </div>
    </div>

    <?php foreach ($emprestimos_ativos as $ativo): ?>
        <div class="alert alert-warning border-warning shadow-sm d-flex justify-content-between align-items-center animate__animated animate__headShake">
            <div>
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>ATENÇÃO:</strong> O item <b><?= $ativo['item_acessorio'] ?></b> está emprestado para <b><?= $ativo['solicitante'] ?></b> desde <?= date('d/m/H:i', strtotime($ativo['data_empréstimo'])) ?>.
            </div>
            <a href="index.php?p=historico_equipamento&id=<?= $id ?>&devolver_item=<?= $ativo['id'] ?>" class="btn btn-sm btn-dark">Confirmar Devolução</a>
        </div>
    <?php endforeach; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body text-center">
                    <?php if ($eq['foto_equipamento']): ?>
                        <img src="uploads/<?= $eq['foto_equipamento'] ?>" class="img-fluid rounded border mb-3 shadow-sm" style="max-height: 200px;">
                    <?php else: ?>
                        <div class="bg-light py-5 rounded mb-3">
                            <i class="bi bi-pc-display text-muted" style="font-size: 5rem;"></i>
                        </div>
                    <?php endif; ?>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($eq['nome']) ?></h4>
                    <span class="badge bg-dark mb-2">ID #<?= $eq['id'] ?></span>

                    <div class="d-block mt-2">
                        <img src="<?= gerarLinkQRCodeLocal($id) ?>" width="100" class="img-thumbnail shadow-sm border-primary">
                        <small class="d-block text-muted" style="font-size: 0.7rem;">QR Code de Identificação</small>
                    </div>
                </div>

                <div class="list-group list-group-flush border-top">
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="text-muted small">Tipo:</span>
                        <span class="fw-bold"><?= htmlspecialchars($eq['tipo_nome']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between text-truncate">
                        <span class="text-muted small">Local:</span>
                        <span class="fw-bold text-primary"><?= htmlspecialchars($eq['setor_nome']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <span class="text-muted small">Patrimônio:</span>
                        <span class="fw-bold font-monospace"><?= htmlspecialchars($eq['patrimonio']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between bg-light">
                        <span class="text-muted small">Custo Total:</span>
                        <span class="fw-bold text-danger">R$ <?= number_format($custo_total, 2, ',', '.') ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between border-bottom">
                        <?php $status_color = ($eq['status'] == 'Ativo') ? 'success' : (($eq['status'] == 'Reserva') ? 'info' : 'warning'); ?>
                        <span class="text-muted small">Status:</span>
                        <span class="badge bg-<?= $status_color ?>"><?= $eq['status'] ?></span>
                    </div>
                </div>

                <div class="card-footer bg-white border-0 p-3">
                    <a href="index.php?p=chamados&equipamento_id=<?= $id ?>&setor_id=<?= $eq['setor_id'] ?>" class="btn btn-warning w-100 fw-bold shadow-sm">
                        <i class="bi bi-plus-lg"></i> ABRIR CHAMADO
                    </a>
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
                        <p class="text-center text-muted py-5">Nenhum evento registrado.</p>
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
                                        $tipo = $item['tipo'];
                                        $cor_badge = ($tipo == 'chamado') ? 'primary' : (($tipo == 'troca') ? 'secondary' : 'warning text-dark');
                                    ?>
                                        <tr>
                                            <td class="small fw-bold"><?= date('d/m/Y H:i', strtotime($item['data'])) ?></td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($item['evento']) ?></div>
                                                <?php if ($tipo == 'troca'): ?>
                                                    <small class="text-muted">Movimentação: <b><?= $item['de'] ?></b> <i class="bi bi-arrow-right"></i> <b><?= $item['para'] ?></b></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-<?= $cor_badge ?> shadow-sm"><?= ucfirst($tipo) ?></span></td>
                                            <td class="small"><?= htmlspecialchars($item['tecnico'] ?: 'Sistema') ?></td>
                                            <td class="text-end">
                                                <?php if($tipo == 'chamado'): ?>
                                                    <a href="index.php?p=tratar_chamado&id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">Ver</a>
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

<div class="modal fade" id="modalEmprestimo" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow" method="POST">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plug"></i> Empréstimo de Acessório</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Item / Acessório</label>
                    <input type="text" name="item_acessorio" class="form-control" placeholder="Ex: Cabo de Força, Sensor de O2" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Solicitante (Nome/Setor)</label>
                    <input type="text" name="solicitante" class="form-control" placeholder="Quem está pegando?" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Observações</label>
                    <textarea name="observacao" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="submit" name="registrar_emprestimo" class="btn btn-primary w-100">Registrar Saída</button>
            </div>
        </form>
    </div>
</div>
