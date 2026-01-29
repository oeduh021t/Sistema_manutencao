<?php
include_once 'includes/db.php';

// Ativa exibição de erros para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

$id = $_GET['id'] ?? null;
if (!$id) { die("<div class='alert alert-danger mt-3'>ID do Ativo não especificado.</div>"); }

// --- 1. LÓGICA DE PROCESSAMENTO DE EMPRÉSTIMOS ---
if (isset($_POST['realizar_emprestimo'])) {
    $item = $_POST['item_acessorio'];
    $solicitante = $_POST['solicitante'];
    $obs = $_POST['observacao'];
    $tecnico = $_SESSION['usuario_nome'] ?? 'Sistema';
    $stmt = $pdo->prepare("INSERT INTO emprestimos (equipamento_id, item_acessorio, solicitante, observacao, status) VALUES (?, ?, ?, ?, 'Emprestado')");
    $stmt->execute([$id, $item, $solicitante, $obs]);
    $msg_log = "⚠️ EMPRÉSTIMO: [$item] entregue para [$solicitante]. Obs: $obs";
    $pdo->prepare("INSERT INTO equipamentos_historico (equipamento_id, descricao_log, tecnico_nome, status_novo, data_movimentacao) VALUES (?, ?, ?, 'Emprestado', NOW())")->execute([$id, $msg_log, $tecnico]);
    echo "<script>window.location.href='index.php?p=historico_equipamento&id=$id';</script>";
    exit;
}

if (isset($_GET['devolver_id'])) {
    $emp_id = $_GET['devolver_id'];
    $tecnico = $_SESSION['usuario_nome'] ?? 'Sistema';
    $stmt = $pdo->prepare("UPDATE emprestimos SET status = 'Devolvido', data_devolucao = NOW() WHERE id = ?");
    $stmt->execute([$emp_id]);
    $pdo->prepare("INSERT INTO equipamentos_historico (equipamento_id, descricao_log, tecnico_nome, status_novo, data_movimentacao) VALUES (?, '✅ EMPRÉSTIMO FINALIZADO: Item retornado ao setor.', ?, 'Ativo', NOW())")->execute([$id, $tecnico]);
    echo "<script>window.location.href='index.php?p=historico_equipamento&id=$id';</script>";
    exit;
}

// --- 2. BUSCA DADOS E CUSTOS ---
$stmt = $pdo->prepare("SELECT e.*, s.nome as setor_nome, t.nome as tipo_nome FROM equipamentos e LEFT JOIN setores s ON e.setor_id = s.id LEFT JOIN tipos_equipamentos t ON e.tipo_id = t.id WHERE e.id = ?");
$stmt->execute([$id]);
$eq = $stmt->fetch();
if (!$eq) { die("<div class='alert alert-warning mt-3'>Ativo não localizado.</div>"); }

$stmt_custo = $pdo->prepare("SELECT (SELECT IFNULL(SUM(custo_servico), 0) FROM chamados WHERE equipamento_id = ? AND status = 'Concluído') + (SELECT IFNULL(SUM(ci.quantidade * ci.valor_unitario_na_epoca), 0) FROM chamados_itens ci JOIN chamados c ON ci.chamado_id = c.id WHERE c.equipamento_id = ? AND c.status = 'Concluído') as total");
$stmt_custo->execute([$id, $id]);
$custo_total = $stmt_custo->fetchColumn() ?: 0;

$stmt_emp = $pdo->prepare("SELECT id, item_acessorio, solicitante, observacao, `data_empréstimo` as data_e FROM emprestimos WHERE equipamento_id = ? AND status = 'Emprestado'");
$stmt_emp->execute([$id]);
$pendencias = $stmt_emp->fetchAll();

// --- 3. BUSCA TIMELINE ---
$stmt_hist = $pdo->prepare("SELECT h.data_registro as data, h.texto_historico as evento, h.status_momento as status, h.tecnico_nome as tecnico, 'chamado' as tipo, c.id as ref_id, c.tipo_manutencao FROM chamados_historico h JOIN chamados c ON h.chamado_id = c.id WHERE c.equipamento_id = ?");
$stmt_hist->execute([$id]);
$res_atendimentos = $stmt_hist->fetchAll();
$stmt_mov = $pdo->prepare("SELECT m.data_movimentacao as data, m.descricao_log as evento, m.status_novo as status, m.tecnico_nome as tecnico, 'troca' as tipo, 0 as ref_id, 'Logística' as tipo_manutencao FROM equipamentos_historico m WHERE m.equipamento_id = ?");
$stmt_mov->execute([$id]);
$res_movs = $stmt_mov->fetchAll();
$timeline = array_merge($res_atendimentos, $res_movs);
usort($timeline, function($a, $b) { return strtotime($b['data']) - strtotime($a['data']); });
?>

<div class="container-fluid text-dark">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <h2 class="fw-bold"><i class="bi bi-journal-medical text-primary"></i> Prontuário do Ativo</h2>
        <div class="d-flex gap-2">
            <a href="index.php?p=chamados&equipamento_id=<?= $id ?>&setor_id=<?= $eq['setor_id'] ?>" class="btn btn-warning shadow-sm fw-bold"><i class="bi bi-plus-circle"></i> Abrir Chamado</a>
            <button class="btn btn-info shadow-sm fw-bold text-white" data-bs-toggle="modal" data-bs-target="#modalEmprestimo"><i class="bi bi-arrow-left-right"></i> Emprestar</button>
            <button class="btn btn-success shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalBaixaPreventiva"><i class="bi bi-calendar-check"></i> Preventiva</button>
            <a href="relatorio_equipamento.php?id=<?= $id ?>" target="_blank" class="btn btn-danger shadow-sm fw-bold"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            <a href="index.php?p=equipamentos" class="btn btn-secondary shadow-sm">Voltar</a>
        </div>
    </div>

    <?php foreach($pendencias as $p): ?>
        <div class="alert alert-warning border-start border-4 border-warning shadow-sm mb-3 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold mb-1"><i class="bi bi-info-circle-fill me-2"></i>ITEM VINCULADO / EMPRESTADO</h6>
                <span>O item <strong><?= htmlspecialchars($p['item_acessorio']) ?></strong> está com <strong><?= htmlspecialchars($p['solicitante']) ?></strong>.</span>
            </div>
            <a href="index.php?p=historico_equipamento&id=<?= $id ?>&devolver_id=<?= $p['id'] ?>" class="btn btn-sm btn-dark fw-bold">DAR BAIXA</a>
        </div>
    <?php endforeach; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4 overflow-hidden text-center">
                <div class="card-body bg-white">
                    <?php if ($eq['foto_equipamento']): ?>
                        <img src="uploads/<?= $eq['foto_equipamento'] ?>" class="img-fluid rounded border shadow-sm mb-3" style="max-height: 280px; width: 100%; object-fit: contain;">
                    <?php else: ?>
                        <div class="bg-light py-5 rounded mb-3 text-muted border text-center"><i class="bi bi-pc-display" style="font-size: 5rem;"></i></div>
                    <?php endif; ?>
                    <h4 class="fw-bold mb-3"><?= htmlspecialchars($eq['nome']) ?></h4>
                </div>
                <div class="list-group list-group-flush border-top text-start">
                    <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span class="text-muted small fw-bold">LOCAL</span>
                        <span class="fw-bold text-primary"><?= htmlspecialchars($eq['setor_nome']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 bg-light">
                        <span class="text-muted small fw-bold text-danger">CUSTO ACUMULADO</span>
                        <span class="fw-bold text-danger fs-5">R$ <?= number_format($custo_total, 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4 text-dark">
                <div class="card-header bg-dark text-white fw-bold small text-center uppercase">Inteligência de Mercado</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 small">
                        <tbody id="corpo-cotacao">
                            <tr><td class="text-center py-3 text-muted small">Pronto para analisar.</td></tr>
                        </tbody>
                    </table>
                    <div class="p-2">
                        <button id="btn-cotar" class="btn btn-sm btn-dark w-100 fw-bold" onclick="buscarCotacaoIA('<?= htmlspecialchars($eq['nome'] . " " . $eq['modelo']) ?>')">BUSCAR PREÇOS REAIS</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0 text-dark mb-4">
                <div class="card-header bg-white fw-bold py-3 border-bottom"><i class="bi bi-clock-history me-2 text-primary"></i>Cronologia de Intervenções</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th>Data</th><th>Evento</th><th>Tipo</th><th>Responsável</th><th class="text-end pe-3">Ação</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($timeline as $item): 
                                    $is_troca = ($item['tipo'] == 'troca');
                                    $badge = $is_troca ? 'bg-secondary' : ($item['tipo_manutencao'] == 'Preventiva' ? 'bg-success' : 'bg-primary');
                                ?>
                                <tr>
                                    <td class="ps-3 fw-bold"><?= date('d/m/Y H:i', strtotime($item['data'])) ?></td>
                                    <td><?= htmlspecialchars($item['evento']) ?></td>
                                    <td><span class="badge <?= $badge ?>"><?= $is_troca ? 'Logística' : $item['tipo_manutencao'] ?></span></td>
                                    <td><?= htmlspecialchars($item['tecnico']) ?></td>
                                    <td class="text-end pe-3">
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

            <div class="card shadow-sm border-0 text-dark">
                <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-shield-check"></i> Histórico Técnico de PMOCs</span>
                    <span class="badge bg-white text-primary">HMDL Climatização</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th class="ps-3">Data</th><th>Periodicidade</th><th>Técnico</th><th>Status Final</th><th class="text-end pe-3">Laudo</th></tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt_pm = $pdo->prepare("SELECT id, data_manutencao, tipo_periodicidade, tecnico_nome, status_final FROM checklist_climatizacao WHERE equipamento_id = ? ORDER BY data_manutencao DESC");
                                $stmt_pm->execute([$id]);
                                $pmocs = $stmt_pm->fetchAll();
                                if ($pmocs): foreach ($pmocs as $p): 
                                    $cor = ($p['status_final'] == 'Operando Normalmente') ? 'text-success' : 'text-danger';
                                ?>
                                    <tr>
                                        <td class="ps-3 fw-bold"><?= date('d/m/Y H:i', strtotime($p['data_manutencao'])) ?></td>
                                        <td><?= $p['tipo_periodicidade'] ?></td>
                                        <td><?= htmlspecialchars($p['tecnico_nome']) ?></td>
                                        <td class="<?= $cor ?> fw-bold"><?= $p['status_final'] ?></td>
                                        <td class="text-end pe-3"><a href="visualizar_pmoc.php?id_check=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-dark py-0">LAUDO PDF</a></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Nenhum PMOC registrado para este ar-condicionado.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalBaixaPreventiva" tabindex="-1">
    <div class="modal-dialog modal-lg text-dark">
        <form class="modal-content border-0 shadow" action="baixar_preventiva.php" method="POST">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">Manutenção Preventiva / PMOC</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (strpos(strtolower($eq['tipo_nome']), 'ar condicionado') !== false): ?>
                

<div class="p-3 bg-light border rounded mb-3 text-dark">
    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-info-circle"></i> 1 - DADOS DE IDENTIFICAÇÃO</h6>
    <div class="row">
        <div class="col-md-6 border-end">
            <div class="mb-2">
                <label class="fw-bold small text-muted d-block">Nome do estabelecimento:</label>
                <span class="fw-bold">HMDL (Hospital Domingos Lourenço)</span>
            </div>
            <div class="mb-2">
                <label class="fw-bold small text-muted d-block">Setor/Área do equipamento:</label>
                <span class="text-primary fw-bold"><?= htmlspecialchars($eq['setor_nome']) ?></span>
            </div>
            <div class="mb-2">
                <label class="fw-bold small text-muted d-block">Marca/Modelo:</label>
                <span class="fw-bold"><?= htmlspecialchars($eq['nome']) ?> / <?= htmlspecialchars($eq['modelo']) ?></span>
            </div>
            <div class="mb-2">
                <label class="fw-bold small text-muted d-block text-danger">Capacidade de refrigeração:</label>
                <input type="text" name="capacidade_btu" class="form-control form-control-sm" placeholder="Ex: 12.000 BTUs" required>
            </div>
        </div>

 <div class="col-md-6 ps-3">
            <div class="mb-2">
                <label class="fw-bold small text-muted d-block">Data da manutenção:</label>
                <span class="fw-bold text-dark"><?= date('d/m/Y') ?></span>
            </div>
            <div class="mb-2">
                <label class="fw-bold small text-muted d-block">Identificação do equipamento (Patrimônio):</label>
                <span class="badge bg-dark fs-6"><?= htmlspecialchars($eq['patrimonio']) ?></span>
            </div>
            <div class="mb-2">
                <label class="fw-bold small text-muted d-block">Nº de série:</label>
                <span class="fw-bold"><?= htmlspecialchars($eq['num_serie'] ?: '---') ?></span>
            </div>
            <div class="mb-2">
                <label class="fw-bold small text-muted d-block text-danger">Tipo de gás refrigerante:</label>
                <input type="text" name="tipo_gas" class="form-control form-control-sm" placeholder="Ex: R-410A ou R-22" required>
            </div>
        </div>
    </div>
</div>

<div class="p-3 bg-white border rounded mb-3 text-dark shadow-sm">
    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-calendar-event"></i> 2 - TIPO DE MANUTENÇÃO</h6>
    <div class="d-flex flex-wrap gap-4 justify-content-start">
        <div class="form-check">
            <input class="form-check-input border-primary" type="radio" name="periodicidade" id="per1" value="Semanal/Diária" checked>
            <label class="form-check-label fw-bold" for="per1">Semanal/Diária</label>
        </div>
        <div class="form-check">
            <input class="form-check-input border-primary" type="radio" name="periodicidade" id="per2" value="Mensal">
            <label class="form-check-label fw-bold" for="per2">Mensal</label>
        </div>
        <div class="form-check">
            <input class="form-check-input border-primary" type="radio" name="periodicidade" id="per3" value="Trimestral">
            <label class="form-check-label fw-bold" for="per3">Trimestral (Áreas Críticas)</label>
        </div>
        <div class="form-check">
            <input class="form-check-input border-primary" type="radio" name="periodicidade" id="per4" value="Semestral">
            <label class="form-check-label fw-bold" for="per4">Semestral (Geral)</label>
        </div>
    </div>
</div>

<div class="p-3 bg-white border rounded mb-3 text-dark shadow-sm">
    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-card-checklist"></i> 3 - CHECKLIST DE EXECUÇÃO</h6>

    <h6 class="fw-bold bg-secondary text-white p-2 small mt-2 rounded">A) LIMPEZA DE FILTROS DE AR (Frequência: Semanal ou conforme criticidade)</h6>
    <table class="table table-sm small mb-4 text-dark align-middle">
        <thead>
            <tr class="table-light">
                <th style="width: 50%;">Item</th>
                <th style="width: 20%;">Status</th>
                <th style="width: 30%;">Observações</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1. Retirada e inspeção visual dos filtros</td>
                <td><select name="filtro_inspecao" class="form-select form-select-sm"><option value="Ok">Ok</option><option value="Não Conforme">N/C</option></select></td>
                <td><input type="text" name="obs_filtro_inspecao" class="form-control form-control-sm"></td>
            </tr>
            <tr>
                <td>2. Limpeza/lavagem dos filtros</td>
                <td><select name="filtro_lavagem" class="form-select form-select-sm"><option value="Ok">Ok</option><option value="Não Conforme">N/C</option></select></td>
                <td><input type="text" name="obs_filtro_lavagem" class="form-control form-control-sm"></td>
            </tr>
            <tr>
                <td>3. Reinstalação dos filtros</td>
                <td><select name="filtro_reinstalacao" class="form-select form-select-sm"><option value="Ok">Ok</option><option value="Não Conforme">N/C</option></select></td>
                <td><input type="text" name="obs_filtro_reinstalacao" class="form-control form-control-sm"></td>
            </tr>
            <tr>
                <td>4. Necessidade de substituição dos filtros?</td>
                <td><select name="filtro_substituicao" class="form-select form-select-sm"><option value="Não">Não</option><option value="Sim">Sim</option></select></td>
                <td><input type="text" name="justificativa_filtro" class="form-control form-control-sm" placeholder="Justifique se sim"></td>
            </tr>
        </tbody>
    </table>
 <h6 class="fw-bold bg-secondary text-white p-2 small rounded">B) INSPEÇÃO E LIMPEZA MENSAL</h6>
    <table class="table table-sm small mb-0 text-dark align-middle">
        <thead>
            <tr class="table-light">
                <th style="width: 50%;">Item</th>
                <th style="width: 20%;">Status</th>
                <th style="width: 30%;">Observações</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1. Limpeza da bandeja de condensado</td>
                <td><select name="limpeza_bandeja" class="form-select form-select-sm"><option value="Ok">Ok</option><option value="Não Conforme">N/C</option></select></td>
                <td><input type="text" name="obs_bandeja" class="form-control form-control-sm"></td>
            </tr>
            <tr>
                <td>2. Limpeza e desobstrução do dreno</td>
                <td><select name="limpeza_dreno" class="form-select form-select-sm"><option value="Ok">Ok</option><option value="Não Conforme">N/C</option></select></td>
                <td><input type="text" name="obs_dreno" class="form-control form-control-sm"></td>
            </tr>
            <tr>
                <td>3. Inspeção e limpeza da serpentina (Evaporadora)</td>
                <td><select name="limpeza_evaporadora" class="form-select form-select-sm"><option value="Ok">Ok</option><option value="Não Conforme">N/C</option></select></td>
                <td><input type="text" name="obs_evaporadora" class="form-control form-control-sm"></td>
            </tr>
            <tr>
                <td>4. Inspeção e limpeza da serpentina (Condensadora)</td>
                <td><select name="limpeza_condensadora" class="form-select form-select-sm"><option value="Ok">Ok</option><option value="Não Conforme">N/C</option></select></td>
                <td><input type="text" name="obs_condensadora" class="form-control form-control-sm"></td>
            </tr>
            <tr>
                <td>5. Verificação das conexões elétricas (aperto de bornes)</td>
                <td><select name="conexoes_eletricas" class="form-select form-select-sm"><option value="Ok">Ok</option><option value="Não Conforme">N/C</option></select></td>
                <td><input type="text" name="obs_eletrica" class="form-control form-control-sm"></td>
            </tr>
            <tr>
                <td>6. Teste de funcionamento do controle remoto/termostato</td>
                <td><select name="teste_controle" class="form-select form-select-sm"><option value="Ok">Ok</option><option value="Não Conforme">N/C</option></select></td>
                <td><input type="text" name="obs_controle" class="form-control form-control-sm"></td>
            </tr>
            <tr>
                <td>7. Verificação de ruídos ou vibrações anormais</td>
                <td><select name="ruidos_vibracoes" class="form-select form-select-sm"><option value="Ok">Ok</option><option value="Não Conforme">N/C</option></select></td>
                <td><input type="text" name="obs_ruidos" class="form-control form-control-sm"></td>
            </tr>
        </tbody>
    </table>
</div>
<div class="p-3 bg-white border rounded mb-3 text-dark shadow-sm">
    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-chat-left-text"></i> 4 - OBSERVAÇÕES E RECOMENDAÇÕES TÉCNICAS</h6>
    <div class="mb-2">
        <label class="small fw-bold text-muted mb-1">Descreva detalhadamente o estado do equipamento ou necessidades de reparos futuros:</label>
        <textarea name="obs_tecnicas" class="form-control" rows="4" placeholder="Ex: Identificado desgaste na isolação térmica da tubulação. Recomendada substituição na próxima preventiva."></textarea>
    </div>
</div>

<div class="p-3 bg-white border rounded mb-3 text-dark shadow-sm">
    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-check-circle"></i> 5 - CONCLUSÃO DO SERVIÇO</h6>
    <div class="p-3 rounded border border-info bg-light">
        <label class="fw-bold d-block mb-3 text-dark">Status Final do Equipamento:</label>
        <div class="d-flex flex-wrap gap-4">
            <div class="form-check">
                <input class="form-check-input border-success" type="radio" name="status_final" id="st1" value="Operando Normalmente" checked>
                <label class="form-check-label fw-bold text-success" for="st1">
                    <i class="bi bi-check-all"></i> Operando Normalmente
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input border-warning" type="radio" name="status_final" id="st2" value="Restrições">
                <label class="form-check-label fw-bold text-warning" for="st2">
                    <i class="bi bi-exclamation-triangle"></i> Operando com restrições (ver obs.)
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input border-danger" type="radio" name="status_final" id="st3" value="Inoperante">
                <label class="form-check-label fw-bold text-danger" for="st3">
                    <i class="bi bi-x-circle"></i> Inoperante / Aguardando Peças
                </label>
            </div>
        </div>
    </div>
</div>

<div class="p-3 bg-white border rounded mb-3 text-dark shadow-sm">
    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-pencil-fill"></i> 6 - ASSINATURAS DIGITAIS</h6>

    <div class="row">
        <div class="col-md-6 text-center border-end">
            <label class="fw-bold small d-block mb-2">Técnico Responsável</label>
            <div class="border rounded bg-light mb-2" style="width: 100%; height: 150px;">
                <canvas id="pad-tecnico" width="400" height="150" style="touch-action: none; cursor: crosshair; width: 100%; height: 100%;"></canvas>
            </div>
            <input type="hidden" name="assinatura_tecnico" id="input-tecnico">
            <button type="button" class="btn btn-xs btn-outline-danger py-0" onclick="limparPad('tecnico')">Limpar</button>
            <div class="mt-2 small fw-bold"><?= $_SESSION['usuario_nome'] ?></div>
        </div>

        <div class="col-md-6 text-center">
            <label class="fw-bold small d-block mb-2">Responsável pelo Setor (Hospital)</label>
            <div class="border rounded bg-light mb-2" style="width: 100%; height: 150px;">
                <canvas id="pad-responsavel" width="400" height="150" style="touch-action: none; cursor: crosshair; width: 100%; height: 100%;"></canvas>
            </div>
            <input type="hidden" name="assinatura_responsavel" id="input-responsavel">
            <button type="button" class="btn btn-xs btn-outline-danger py-0" onclick="limparPad('responsavel')">Limpar</button>
            <input type="text" name="responsavel_setor" class="form-control form-control-sm mt-2" placeholder="Nome do Responsável">
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
    // Inicializa os quadros de assinatura
    const canvasT = document.querySelector("#pad-tecnico");
    const canvasR = document.querySelector("#pad-responsavel");

    const padTecnico = new SignaturePad(canvasT);
    const padResponsavel = new SignaturePad(canvasR);

    // Função para limpar
    function limparPad(quem) {
        if(quem === 'tecnico') padTecnico.clear();
        else padResponsavel.clear();
    }

    // Antes de enviar o formulário, converte os desenhos em imagem
    document.querySelector("form").onsubmit = function() {
        if (!padTecnico.isEmpty()) {
            document.querySelector("#input-tecnico").value = padTecnico.toDataURL();
        }
        if (!padResponsavel.isEmpty()) {
            document.querySelector("#input-responsavel").value = padResponsavel.toDataURL();
        }
    };
</script>







                <?php else: ?>
                    <p class="alert alert-info">Aparelho não classificado como Climatização. Checklist técnico não requerido.</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-success fw-bold px-5 shadow">SALVAR PMOC</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEmprestimo" tabindex="-1"><div class="modal-dialog"><form class="modal-content border-0 shadow text-dark" method="POST"><div class="modal-header bg-info text-white"><h5 class="modal-title fw-bold">Registrar Empréstimo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="fw-bold small">Item:</label><input type="text" name="item_acessorio" class="form-control" required></div><div class="mb-3"><label class="fw-bold small">Para:</label><input type="text" name="solicitante" class="form-control" required></div><div class="mb-3"><label class="fw-bold small">Obs:</label><textarea name="observacao" class="form-control"></textarea></div></div><div class="modal-footer"><button type="submit" name="realizar_emprestimo" class="btn btn-info text-white w-100 fw-bold">SALVAR</button></div></form></div></div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
    // Inicializa Signature Pad
    const cvT = document.querySelector("#pad-tecnico");
    const cvR = document.querySelector("#pad-responsavel");
    let padT, padR;
    if(cvT) padT = new SignaturePad(cvT);
    if(cvR) padR = new SignaturePad(cvR);

    // Envio do formulário
    document.querySelector("form[action='baixar_preventiva.php']").onsubmit = function() {
        if(padT && !padT.isEmpty()) document.querySelector("#input-tecnico").value = padT.toDataURL();
        if(padR && !padR.isEmpty()) document.querySelector("#input-responsavel").value = padR.toDataURL();
    };

    // Função de Busca de Preços (IA)
    function buscarCotacaoIA(t) {
        const b = document.getElementById('btn-cotar'); const tb = document.getElementById('corpo-cotacao');
        b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Analisando...'; b.disabled = true;
        fetch(`cotar_ia.php?termo=${encodeURIComponent(t)}`)
        .then(r => r.json())
        .then(data => {
            tb.innerHTML = '';
            if(data.length === 0) { tb.innerHTML = '<tr><td class="text-center py-2">Nada encontrado.</td></tr>'; return; }
            data.forEach(item => {
                let link = item.link || `https://www.google.com/search?q=${encodeURIComponent(t + " " + item.loja)}`;
                tb.innerHTML += `<tr><td class="small">🛒 ${item.loja}</td><td class="small">${item.preco}</td><td><a href="${link}" target="_blank" class="btn btn-xs btn-success py-0">Ver</a></td></tr>`;
            });
        })
        .catch(() => { tb.innerHTML = '<tr><td class="text-center py-2 text-danger">Erro técnico.</td></tr>'; })
        .finally(() => { b.innerHTML = 'BUSCAR PREÇOS REAIS'; b.disabled = false; });
    }
</script>
