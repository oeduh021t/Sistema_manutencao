<?php
include_once 'includes/db.php';

$hoje = date('Y-m-d');
$filtro_tipo = $_GET['tipo_id'] ?? '';
$filtro_setor = $_GET['setor_id'] ?? '';

// --- LÓGICA DE PROCESSAMENTO DA BAIXA ---
if (isset($_POST['registrar_baixa'])) {
    $id_equipamento = $_POST['id_equipamento'];
    $relatorio_tecnico = $_POST['relatorio_tecnico'];
    $tecnico = $_SESSION['usuario_nome'] ?? 'Técnico';
    $data_atual = date('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();

        // 1. Atualiza a data da última preventiva no equipamento
        $stmt_eq = $pdo->prepare("UPDATE equipamentos SET data_ultima_preventiva = ? WHERE id = ?");
        $stmt_eq->execute([$hoje, $id_equipamento]);

        // 2. Cria um registro no histórico para auditoria e relatórios
        // Se você tiver uma tabela 'chamados', o ideal é criar um registro tipo 'Preventiva' concluído
        $stmt_hist = $pdo->prepare("INSERT INTO chamados_historico (chamado_id, tecnico_nome, texto_historico, status_momento, data_registro) 
                                   VALUES (?, ?, ?, 'Preventiva Realizada', ?)");
        // Nota: Se não houver um ID de chamado vinculado, podemos usar 0 ou adaptar conforme sua estrutura
        $stmt_hist->execute([0, $tecnico, "RELATÓRIO DE PREVENTIVA: " . $relatorio_tecnico, $data_atual]);

        $pdo->commit();
        echo "<script>alert('Baixa de preventiva registrada com sucesso!'); window.location.href='index.php?p=preventivas';</script>";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Erro ao registrar baixa: " . $e->getMessage() . "');</script>";
    }
}

// 1. Função para montar o nome hierárquico
function getCaminhoSetor($id, $mapa) {
    if (!isset($mapa[$id])) return "Setor não encontrado";
    $setor = $mapa[$id];
    if (!empty($setor['setor_pai_id']) && isset($mapa[$setor['setor_pai_id']])) {
        return getCaminhoSetor($setor['setor_pai_id'], $mapa) . " > " . $setor['nome'];
    }
    return $setor['nome'];
}

$setores_query = $pdo->query("SELECT id, nome, setor_pai_id FROM setores")->fetchAll(PDO::FETCH_ASSOC);
$setores_mapa = [];
foreach ($setores_query as $s) { $setores_mapa[$s['id']] = $s; }

// 3. Query principal
$sql = "SELECT e.*, t.nome as tipo_nome,
        DATE_ADD(e.data_ultima_preventiva, INTERVAL e.periodicidade_preventiva DAY) as data_vencimento,
        DATEDIFF(DATE_ADD(e.data_ultima_preventiva, INTERVAL e.periodicidade_preventiva DAY), '$hoje') as dias_restantes
        FROM equipamentos e
        JOIN tipos_equipamentos t ON e.tipo_id = t.id
        WHERE e.periodicidade_preventiva > 0 AND e.data_ultima_preventiva IS NOT NULL";

if ($filtro_tipo) $sql .= " AND e.tipo_id = " . (int)$filtro_tipo;
if ($filtro_setor) $sql .= " AND e.setor_id = " . (int)$filtro_setor;

$sql .= " ORDER BY data_vencimento ASC";
$preventivas = $pdo->query($sql)->fetchAll();
?>

<div class="container-fluid">
    <h2 class="mb-4 mt-3"><i class="bi bi-calendar-check text-primary"></i> Gestão de Preventivas</h2>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body bg-light">
            <form method="GET" class="row g-2">
                <input type="hidden" name="p" value="preventivas">
                <div class="col-md-4">
                    <label class="small fw-bold text-muted">Tipo</label>
                    <select name="tipo_id" class="form-select">
                        <option value="">Todos os Tipos</option>
                        <?php
                        $tipos = $pdo->query("SELECT * FROM tipos_equipamentos ORDER BY nome ASC")->fetchAll();
                        foreach($tipos as $t) echo "<option value='{$t['id']}' ".($filtro_tipo == $t['id'] ? 'selected' : '').">{$t['nome']}</option>";
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="small fw-bold text-muted">Setor</label>
                    <select name="setor_id" class="form-select">
                        <option value="">Todos os Setores</option>
                        <?php
                        $lista_setores_select = [];
                        foreach ($setores_mapa as $id_s => $dados_s) { $lista_setores_select[$id_s] = getCaminhoSetor($id_s, $setores_mapa); }
                        asort($lista_setores_select);
                        foreach($lista_setores_select as $id_s => $caminho) echo "<option value='{$id_s}' ".($filtro_setor == $id_s ? 'selected' : '').">{$caminho}</option>";
                        ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-dark w-50 me-2">Filtrar</button>
                    <a href="index.php?p=preventivas" class="btn btn-outline-secondary w-50">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Equipamento</th>
                        <th>Localização</th>
                        <th>Vencimento</th>
                        <th>Situação</th>
                        <th class="text-end pe-4">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preventivas as $p):
                        $dias = $p['dias_restantes'];
                        $classe = ($dias < 0) ? "bg-danger" : (($dias <= 7) ? "bg-warning text-dark" : "bg-success");
                        $caminho_completo = getCaminhoSetor($p['setor_id'], $setores_mapa);
                    ?>
                    <tr>
                        <td class="ps-3">
                            <span class="fw-bold"><?= htmlspecialchars($p['nome']) ?></span><br>
                            <small class="badge bg-light text-dark border">Patr: <?= $p['patrimonio'] ?></small>
                        </td>
                        <td><small class="text-primary fw-bold"><?= htmlspecialchars($caminho_completo) ?></small></td>
                        <td>
                            <div class="small"><?= date('d/m/Y', strtotime($p['data_vencimento'])) ?></div>
                            <small class="text-muted">Última: <?= date('d/m/Y', strtotime($p['data_ultima_preventiva'])) ?></small>
                        </td>
                        <td><span class="badge <?= $classe ?> shadow-sm"><?= ($dias < 0) ? "Atrasada: ".abs($dias)." dias" : "Vence em $dias dias" ?></span></td>
                        <td class="text-end pe-3">
                            <button class="btn btn-sm btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalBaixa<?= $p['id'] ?>">
                                <i class="bi bi-check-circle"></i> Baixa
                            </button>
                            <a href="index.php?p=historico_equipamento&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>

                    <div class="modal fade" id="modalBaixa<?= $p['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" class="modal-content border-0 shadow">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title fw-bold">Baixa Técnica: <?= htmlspecialchars($p['nome']) ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="id_equipamento" value="<?= $p['id'] ?>">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Relatório Técnico de Execução</label>
                                        <textarea name="relatorio_tecnico" class="form-control" rows="4" placeholder="Descreva os testes, limpezas e calibrações realizadas..." required></textarea>
                                    </div>
                                    <div class="alert alert-info py-2 small">
                                        <i class="bi bi-info-circle me-1"></i> Esta ação atualizará a data da última preventiva para hoje (<?= date('d/m/Y') ?>) e registrará o log no histórico.
                                    </div>
                                </div>
                                <div class="modal-footer bg-light text-dark">
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" name="registrar_baixa" class="btn btn-success btn-sm fw-bold">Confirmar Execução</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
