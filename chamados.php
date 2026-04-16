<?php
include_once 'includes/db.php';

// --- FUNÇÃO TELEGRAM ---
function enviarNotificacaoTelegram($mensagem) {
    $token = "8477438164:AAFz5SkUaN3pdF0X0sP-O-sokNGhK3xHSjU";
    $chat_id = "-4879637458";
    $url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=" . urlencode($mensagem) . "&parse_mode=Markdown";
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    @file_get_contents($url, false, $ctx);
}

$usuario_id_logado = $_SESSION['usuario_id'];
$nivel_logado = $_SESSION['usuario_nivel'];

// --- CAPTURA DE FILTROS ---
$filtro_id      = isset($_GET['f_id']) ? trim($_GET['f_id']) : '';
$filtro_setor   = isset($_GET['f_setor']) ? $_GET['f_setor'] : '';
$filtro_tecnico = isset($_GET['f_tecnico']) ? trim($_GET['f_tecnico']) : '';
$filtro_equip   = isset($_GET['f_equip']) ? trim($_GET['f_equip']) : '';
$status_filtro  = isset($_GET['status_filtro']) ? $_GET['status_filtro'] : 'Todos';

$equip_selecionado_id = isset($_GET['equipamento_id']) ? $_GET['equipamento_id'] : null;
$setor_selecionado_id = isset($_GET['setor_id']) ? $_GET['setor_id'] : null;

function getCaminhoSetor($id, $mapa) {
    if (!isset($mapa[$id])) return "";
    $setor = $mapa[$id];
    if (!empty($setor['setor_pai_id']) && isset($mapa[$setor['setor_pai_id']])) {
        return getCaminhoSetor($setor['setor_pai_id'], $mapa) . " > " . $setor['nome'];
    }
    return $setor['nome'];
}

$setores_raw = $pdo->query("SELECT id, nome, setor_pai_id FROM setores")->fetchAll(PDO::FETCH_UNIQUE);
$equips = $pdo->query("SELECT id, patrimonio, nome, setor_id FROM equipamentos ORDER BY patrimonio ASC")->fetchAll();

// Montagem do caminho para o select
$lista_caminhos = [];
foreach ($setores_raw as $sid => $s) { $lista_caminhos[$sid] = getCaminhoSetor($sid, $setores_raw); }
asort($lista_caminhos);

// --- 1. LÓGICA DE ABERTURA DE CHAMADO ---
if (isset($_POST['abrir_chamado'])) {
    $categoria = $_POST['categoria_chamado'];
    $setor_id = $_POST['setor_id'];
    $equip_id = ($categoria === 'Equipamento' && !empty($_POST['equipamento_id'])) ? $_POST['equipamento_id'] : null;
    $titulo = $_POST['titulo'];
    $desc = $_POST['descricao'];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO chamados (setor_id, equipamento_id, usuario_id, titulo, descricao_problema, status) VALUES (?, ?, ?, ?, ?, 'Aberto')");
        $stmt->execute([$setor_id, $equip_id, $usuario_id_logado, $titulo, $desc]);
        $chamado_id = $pdo->lastInsertId();

        $nome_solicitante = $_SESSION['usuario_nome'] ?? 'Solicitante';
        $stmt_hist_inicial = $pdo->prepare("INSERT INTO chamados_historico (chamado_id, tecnico_nome, texto_historico, status_momento, data_registro) VALUES (?, ?, ?, 'Aberto', NOW())");
        $stmt_hist_inicial->execute([$chamado_id, $nome_solicitante, "Problema relatado na abertura: \n" . $desc]);

        if (!empty($_FILES['fotos_abertura']['name'][0])) {
            foreach ($_FILES['fotos_abertura']['name'] as $key => $name) {
                if ($_FILES['fotos_abertura']['error'][$key] === 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $novo_nome = "CHAMADO_" . $chamado_id . "_" . time() . "_" . $key . "." . $ext;
                    move_uploaded_file($_FILES['fotos_abertura']['tmp_name'][$key], __DIR__ . "/uploads/" . $novo_nome);

                    if ($key === 0) {
                        $pdo->prepare("UPDATE chamados SET foto_abertura = ? WHERE id = ?")->execute([$novo_nome, $chamado_id]);
                    }
                    $pdo->prepare("INSERT INTO chamados_historico (chamado_id, tecnico_nome, texto_historico, status_momento, foto_historico, data_registro) VALUES (?, ?, 'Anexo abertura', 'Aberto', ?, NOW())")->execute([$chamado_id, 'Sistema', $novo_nome]);
                }
            }
        }
        $pdo->commit();

        $nome_setor_msg = getCaminhoSetor($setor_id, $setores_raw);
        enviarNotificacaoTelegram("🚨 *NOVO CHAMADO #$chamado_id*\n🏥 *Setor:* $nome_setor_msg\n📝 *Assunto:* $titulo");

        echo "<script>window.location.href='index.php?p=chamados&sucesso=1';</script>";
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro: " . $e->getMessage());
    }
}

// --- 2. LÓGICA OBSERVAÇÃO COORDENADOR ---
if (isset($_POST['salvar_observacao'])) {
    if ($nivel_logado === 'admin' || $nivel_logado === 'coordenador') {
        $id_chamado = $_POST['chamado_id'];
        $nova_obs = trim($_POST['observacao_texto']);
        $nome_coord = $_SESSION['usuario_nome'] ?? 'Coordenador';
        $texto_formatado = "\n\n--- " . date('d/m/Y H:i') . " ($nome_coord) ---\n" . $nova_obs;
        $upd_obs = $pdo->prepare("UPDATE chamados SET observacao_coordenador = CONCAT(COALESCE(observacao_coordenador, ''), ?) WHERE id = ?");
        $upd_obs->execute([$texto_formatado, $id_chamado]);
        echo "<script>window.location.href='index.php?p=chamados&sucesso_obs=1';</script>";
        exit;
    }
}

// --- 3. CONSTRUÇÃO DA QUERY COM FILTROS ---
$sql_base = "SELECT c.*, s.nome as setor_nome, e.patrimonio as eq_pat, e.nome as eq_nome FROM chamados c LEFT JOIN setores s ON c.setor_id = s.id LEFT JOIN equipamentos e ON c.equipamento_id = e.id";
$condicoes = [];
$params = [];

if (!empty($filtro_id)) { $condicoes[] = "c.id = ?"; $params[] = $filtro_id; }
if (!empty($filtro_setor)) { $condicoes[] = "c.setor_id = ?"; $params[] = $filtro_setor; }
if (!empty($filtro_tecnico)) { $condicoes[] = "c.tecnico_responsavel LIKE ?"; $params[] = "%$filtro_tecnico%"; }
if (!empty($filtro_equip)) { 
    $condicoes[] = "(e.patrimonio LIKE ? OR e.nome LIKE ?)"; 
    $params[] = "%$filtro_equip%"; 
    $params[] = "%$filtro_equip%"; 
}
if ($status_filtro !== 'Todos') { $condicoes[] = "c.status = ?"; $params[] = $status_filtro; }
if ($nivel_logado === 'usuario') { $condicoes[] = "c.usuario_id = ?"; $params[] = $usuario_id_logado; }

$where = count($condicoes) > 0 ? " WHERE " . implode(" AND ", $condicoes) : "";
$sql_final = $sql_base . $where . " ORDER BY FIELD(c.status, 'Aberto', 'Em Atendimento', 'Concluído'), c.data_abertura DESC";

$stmt_c = $pdo->prepare($sql_final);
$stmt_c->execute($params);
$chamados = $stmt_c->fetchAll();
?>

<div class="d-flex justify-content-between mb-4 align-items-center mt-3 text-dark">
    <h2><i class="bi bi-ticket-perforated text-warning"></i> Chamados</h2>
    <button class="btn btn-warning shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalChamado">
        <i class="bi bi-plus-lg"></i> Abrir Chamado
    </button>
</div>

<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body p-3">
        <form method="GET" action="index.php" class="row g-2">
            <input type="hidden" name="p" value="chamados">
            
            <div class="col-md-1">
                <input type="number" name="f_id" class="form-control form-control-sm" placeholder="ID" value="<?= htmlspecialchars($filtro_id) ?>">
            </div>
            
            <div class="col-md-3">
                <select name="f_setor" class="form-select form-select-sm">
                    <option value="">Todos os Setores</option>
                    <?php foreach ($lista_caminhos as $sid => $caminho): ?>
                        <option value="<?= $sid ?>" <?= ($sid == $filtro_setor) ? 'selected' : '' ?>><?= $caminho ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <input type="text" name="f_tecnico" class="form-control form-control-sm" placeholder="Técnico" value="<?= htmlspecialchars($filtro_tecnico) ?>">
            </div>

            <div class="col-md-2">
                <input type="text" name="f_equip" class="form-control form-control-sm" placeholder="Equip/Pat" value="<?= htmlspecialchars($filtro_equip) ?>">
            </div>

            <div class="col-md-2">
                <select name="status_filtro" class="form-select form-select-sm">
                    <option value="Todos">Todos Status</option>
                    <option value="Aberto" <?= $status_filtro == 'Aberto' ? 'selected' : '' ?>>Aberto</option>
                    <option value="Em Atendimento" <?= $status_filtro == 'Em Atendimento' ? 'selected' : '' ?>>Em Atendimento</option>
                    <option value="Concluído" <?= $status_filtro == 'Concluído' ? 'selected' : '' ?>>Concluído</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-dark w-100 fw-bold">Filtrar</button>
                <a href="index.php?p=chamados" class="btn btn-sm btn-outline-secondary px-2"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<?php if(isset($_GET['sucesso'])): ?>
    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-check-circle-fill fs-4 me-2"></i>
        <div><strong>Sucesso!</strong> O chamado foi aberto e a equipe técnica já foi notificada.</div>
    </div>
<?php endif; ?>

<div class="row">
    <?php foreach($chamados as $c):
        $border_color = ($c['status'] == 'Aberto') ? 'border-danger' : (($c['status'] == 'Em Atendimento') ? 'border-warning' : 'border-success');
    ?>
    <div class="col-md-6 mb-4">
        <div class="card border-start border-4 <?= $border_color ?> shadow-sm h-100" style="min-height: 380px;">
            <div class="card-body p-3 d-flex flex-column text-dark">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <h5 class="card-title fw-bold text-truncate mb-0" style="max-width: 80%;"><?= htmlspecialchars($c['titulo']) ?></h5>
                    <span class="badge <?= ($c['status'] == 'Aberto') ? 'bg-danger' : (($c['status'] == 'Em Atendimento') ? 'bg-warning text-dark' : 'bg-success') ?>"><?= $c['status'] ?></span>
                </div>
                <h6 class="text-muted small mb-3">
                    #<?= $c['id'] ?> - <?= htmlspecialchars($c['setor_nome']) ?>
                    <?php if(!empty($c['eq_nome'])): ?>
                        <span class="text-primary fw-bold"> - [<?= $c['eq_pat'] ?>] <?= htmlspecialchars($c['eq_nome']) ?></span>
                    <?php endif; ?>
                </h6>

                <div class="d-flex gap-2 mb-3">
                    <a href="index.php?p=ver_chamado&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary">Detalhes</a>
                    <?php if($c['status'] !== 'Concluído' && $nivel_logado !== 'usuario'): ?>
                        <a href="index.php?p=tratar_chamado&id=<?= $c['id'] ?>" class="btn btn-sm btn-primary">Atender</a>
                    <?php endif; ?>
                    <?php if($c['status'] == 'Concluído' && ($nivel_logado === 'admin' || $nivel_logado === 'coordenador')): ?>
                        <textarea id="obs_data_<?= $c['id'] ?>" style="display:none;"><?= htmlspecialchars($c['observacao_coordenador'] ?? '') ?></textarea>
                        <button class="btn btn-sm btn-info text-white shadow-sm" onclick="abrirModalObs(<?= $c['id'] ?>)">
                            <i class="bi bi-chat-quote"></i> Obs. Coordenação
                        </button>
                    <?php endif; ?>
                </div>

                <div class="alert alert-info p-0 flex-grow-1 border-0 m-0 shadow-sm" style="background-color: #f0faff; border-left: 4px solid #0dcaf0 !important; overflow: hidden; display: flex; flex-direction: column;">
                    <div class="px-2 py-1 border-bottom bg-light fw-bold small text-info" style="font-size: 0.7rem;">HISTÓRICO DA COORDENAÇÃO:</div>
                    <div class="p-2" style="overflow-y: auto; max-height: 180px; white-space: pre-wrap; font-size: 0.8rem; line-height: 1.4; word-break: break-word;">
                        <?= !empty($c['observacao_coordenador']) ? htmlspecialchars($c['observacao_coordenador']) : '<span class="text-muted opacity-50">Nenhuma observação.</span>' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="modalChamado" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow text-dark" method="POST" enctype="multipart/form-data">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold"><i class="bi bi-megaphone"></i> Nova Solicitação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="categoria_chamado" id="categoria_input" value="Equipamento">
                <div class="mb-3">
                    <label class="form-label fw-bold">Localização</label>
                    <select name="setor_id" id="setor_select" class="form-select" required onchange="filtrarEquipamentos()">
                        <option value="">-- Selecione o Local --</option>
                        <?php foreach ($lista_caminhos as $sid => $caminho): ?>
                            <option value="<?= $sid ?>" <?= ($sid == $setor_selecionado_id) ? 'selected' : '' ?>><?= $caminho ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="bloco-equipamento" class="mb-3">
                    <label class="form-label fw-bold">Equipamento (Patrimônio)</label>
                    <select name="equipamento_id" id="equipamento_select" class="form-select">
                        <option value="" data-setor="todos">-- Selecione o Patrimônio --</option>
                        <?php foreach($equips as $eq): 
                            $selected_eq = ($eq['id'] == $equip_selecionado_id) ? 'selected' : ''; ?>
                            <option value="<?= $eq['id'] ?>" data-setor="<?= $eq['setor_id'] ?>" <?= $selected_eq ?>>
                                <?= $eq['patrimonio'] ?> - <?= htmlspecialchars($eq['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Assunto/Título</label>
                    <input type="text" name="titulo" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Anexar Fotos</label>
                    <input type="file" name="fotos_abertura[]" class="form-control" accept="image/*" multiple>
                </div>
            </div>
            <div class="modal-footer text-end">
                <button type="submit" name="abrir_chamado" class="btn btn-danger w-100 fw-bold shadow">ENVIAR SOLICITAÇÃO</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalObservacao" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold">Histórico da Coordenação</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-dark">
                <input type="hidden" name="chamado_id" id="obs_chamado_id">
                <div id="visualizar_obs_anterior" class="mb-3 small p-2 bg-light border rounded" style="max-height: 180px; overflow-y: auto; display: none;">
                    <div id="texto_anterior_exibicao" style="white-space: pre-wrap;"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nova Anotação:</label>
                    <textarea name="observacao_texto" id="obs_texto" class="form-control" rows="4" required></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" name="salvar_observacao" class="btn btn-info text-white fw-bold w-100">ADICIONAR</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalObs(id) {
    const textoAnterior = document.getElementById('obs_data_' + id).value;
    document.getElementById('obs_chamado_id').value = id;
    document.getElementById('obs_texto').value = '';
    const divVisualizar = document.getElementById('visualizar_obs_anterior');
    const displayTexto = document.getElementById('texto_anterior_exibicao');
    if(textoAnterior && textoAnterior.trim() !== "") {
        divVisualizar.style.display = 'block';
        displayTexto.innerText = textoAnterior;
    } else {
        divVisualizar.style.display = 'none';
    }
    new bootstrap.Modal(document.getElementById('modalObservacao')).show();
}

function filtrarEquipamentos() {
    const setorId = document.getElementById('setor_select').value;
    const options = document.getElementById('equipamento_select').options;
    for (let i = 0; i < options.length; i++) {
        const optSetorId = options[i].getAttribute('data-setor');
        options[i].style.display = (optSetorId === "todos" || optSetorId === setorId) ? "block" : "none";
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('setor_id') || urlParams.has('equipamento_id')) {
        new bootstrap.Modal(document.getElementById('modalChamado')).show();
    }
    filtrarEquipamentos();
});

setInterval(function(){
    if(!document.querySelector('.modal.show')) window.location.reload();
}, 60000);
</script>
