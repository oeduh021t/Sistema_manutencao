<?php
include_once 'includes/db.php';

// --- FUNÇÃO TELEGRAM MANTIDA ---
function enviarNotificacaoTelegram($mensagem) {
    $token = "8477438164:AAFz5SkUaN3pdF0X0sP-O-sokNGhK3xHSjU";
    $chat_id = "-4879637458"; 
    $url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=" . urlencode($mensagem) . "&parse_mode=Markdown";
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    @file_get_contents($url, false, $ctx);
}

$usuario_id_logado = $_SESSION['usuario_id'];
$nivel_logado = $_SESSION['usuario_nivel'];

// --- CAPTURA DADOS VINDOS DO QR CODE ---
$equip_selecionado_id = isset($_GET['equipamento_id']) ? $_GET['equipamento_id'] : null;
$setor_selecionado_id = isset($_GET['setor_id']) ? $_GET['setor_id'] : null;

// --- LÓGICA DE FILTROS E BUSCA MANTIDA ---
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$status_filtro = isset($_GET['status_filtro']) ? $_GET['status_filtro'] : 'Todos';
$params = [];

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

// 2. Lógica de abertura de chamado
if (isset($_POST['abrir_chamado'])) {
    $setor_id = $_POST['setor_id'];
    $equip_id = !empty($_POST['equipamento_id']) ? $_POST['equipamento_id'] : null;
    $titulo = $_POST['titulo'];
    $desc = $_POST['descricao'];

    $stmt = $pdo->prepare("INSERT INTO chamados (setor_id, equipamento_id, usuario_id, titulo, descricao_problema, status) VALUES (?, ?, ?, ?, ?, 'Aberto')");
    $stmt->execute([$setor_id, $equip_id, $usuario_id_logado, $titulo, $desc]);
    $chamado_id = $pdo->lastInsertId();

    // Notificação Telegram
    $nome_setor_msg = getCaminhoSetor($setor_id, $setores_raw);
    $alerta_msg = "🚨 *NOVO CHAMADO #$chamado_id* 🚨\n\n";
    $alerta_msg .= "🏥 *Setor:* $nome_setor_msg\n";
    $alerta_msg .= "📝 *Assunto:* $titulo\n";
    if($equip_id) {
        $stmt_eq = $pdo->prepare("SELECT patrimonio, nome FROM equipamentos WHERE id = ?");
        $stmt_eq->execute([$equip_id]);
        $eq_info = $stmt_eq->fetch();
        $alerta_msg .= "📟 *Equipamento:* " . $eq_info['patrimonio'] . " - " . $eq_info['nome'] . "\n";
    }
    enviarNotificacaoTelegram($alerta_msg);

    // Upload de Fotos mantido...
    if (!empty($_FILES['fotos_abertura']['name'][0])) {
        foreach ($_FILES['fotos_abertura']['name'] as $key => $name) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $novo_nome = "CHAMADO_" . $chamado_id . "_" . time() . "_" . $key . "." . $ext;
            if (move_uploaded_file($_FILES['fotos_abertura']['tmp_name'][$key], "uploads/" . $novo_nome)) {
                if ($key === 0) {
                    $pdo->prepare("UPDATE chamados SET foto_abertura = ? WHERE id = ?")->execute([$novo_nome, $chamado_id]);
                }
            }
        }
    }
    echo "<script>window.location.href='index.php?p=chamados&sucesso=1';</script>";
    exit;
}

// 3. Busca de chamados mantida...
$sql_base = "SELECT c.*, e.patrimonio, e.nome as eq_nome, s.nome as setor_nome FROM chamados c LEFT JOIN equipamentos e ON c.equipamento_id = e.id LEFT JOIN setores s ON c.setor_id = s.id";
$condicoes = [];
if ($nivel_logado === 'usuario') { $condicoes[] = "c.usuario_id = ?"; $params[] = $usuario_id_logado; }
if (!empty($busca)) { $condicoes[] = "(c.id LIKE ? OR c.titulo LIKE ? OR e.patrimonio LIKE ? OR e.nome LIKE ? OR s.nome LIKE ?)"; $term = "%$busca%"; array_push($params, $term, $term, $term, $term, $term); }
if ($status_filtro !== 'Todos') { $condicoes[] = "c.status = ?"; $params[] = $status_filtro; }
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

<?php if(isset($_GET['sucesso'])): ?>
    <div class="alert alert-success shadow-sm border-0">Chamado aberto com sucesso! A equipe técnica foi notificada.</div>
<?php endif; ?>

<div class="row">
    <?php foreach($chamados as $c): 
        $border_color = ($c['status'] == 'Aberto') ? 'border-danger' : (($c['status'] == 'Em Atendimento') ? 'border-warning' : 'border-success');
    ?>
    <div class="col-md-6 mb-3 text-dark">
        <div class="card border-start border-4 <?= $border_color ?> shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <h5 class="card-title fw-bold text-truncate" style="max-width: 70%;"><?= htmlspecialchars($c['titulo']) ?></h5>
                    <span class="badge <?= ($c['status'] == 'Aberto') ? 'bg-danger' : (($c['status'] == 'Em Atendimento') ? 'bg-warning text-dark' : 'bg-success') ?>"><?= $c['status'] ?></span>
                </div>
                <h6 class="text-muted small">#<?= $c['id'] ?> - <?= htmlspecialchars($c['setor_nome']) ?></h6>
                <div class="mt-3">
                    <a href="index.php?p=ver_chamado&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary">Detalhes</a>
                    <?php if($nivel_logado !== 'usuario'): ?>
                        <a href="index.php?p=tratar_chamado&id=<?= $c['id'] ?>" class="btn btn-sm btn-primary">Atender</a>
                    <?php endif; ?>
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
                <h5 class="modal-title fw-bold"><i class="bi bi- megaphone"></i> Nova Solicitação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Localização</label>
                    <select name="setor_id" id="setor_select" class="form-select" required onchange="filtrarEquipamentos()">
                        <option value="">-- Selecione o Local --</option>
                        <?php
                        $lista_caminhos = [];
                        foreach ($setores_raw as $sid => $s) { $lista_caminhos[$sid] = getCaminhoSetor($sid, $setores_raw); }
                        asort($lista_caminhos);
                        foreach ($lista_caminhos as $sid => $caminho):
                            $selected = ($sid == $setor_selecionado_id) ? 'selected' : '';
                        ?>
                            <option value="<?= $sid ?>" <?= $selected ?>><?= $caminho ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Equipamento (Se houver)</label>
                    <select name="equipamento_id" id="equipamento_select" class="form-select">
                        <option value="" data-setor="todos">-- Não se aplica a um equipamento --</option>
                        <?php foreach($equips as $eq): 
                            $selected_eq = ($eq['id'] == $equip_selecionado_id) ? 'selected' : '';
                        ?>
                            <option value="<?= $eq['id'] ?>" data-setor="<?= $eq['setor_id'] ?>" <?= $selected_eq ?>>
                                <?= $eq['patrimonio'] ?> - <?= htmlspecialchars($eq['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Assunto/Título</label>
                    <input type="text" name="titulo" class="form-control" placeholder="Resuma o problema" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Descrição Detalhada</label>
                    <textarea name="descricao" class="form-control" rows="3" placeholder="Conte-nos o que está acontecendo..." required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Anexar Fotos</label>
                    <input type="file" name="fotos_abertura[]" class="form-control" accept="image/*" multiple>
                </div>
            </div>
            <div class="modal-footer bg-light text-center">
                <button type="submit" name="abrir_chamado" class="btn btn-danger w-100 fw-bold shadow">ENVIAR SOLICITAÇÃO</button>
            </div>
        </form>
    </div>
</div>

<script>
// Filtra a lista de equipamentos baseada no setor escolhido
function filtrarEquipamentos() {
    const setorId = document.getElementById('setor_select').value;
    const equipSelect = document.getElementById('equipamento_select');
    const options = equipSelect.options;
    
    let encontrou = false;
    for (let i = 0; i < options.length; i++) {
        const optSetorId = options[i].getAttribute('data-setor');
        if (optSetorId === "todos" || optSetorId === setorId) {
            options[i].style.display = "block";
            encontrou = true;
        } else {
            options[i].style.display = "none";
        }
    }
    // Se mudar o setor, reseta o equipamento para não ficar um de outro setor selecionado por erro
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('equipamento_id')) {
        equipSelect.value = "";
    }
}

// LOGICA QR CODE: Abre o modal se houver ID na URL
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.has('setor_id') || urlParams.has('equipamento_id')) {
        // Abre o modal automaticamente
        var myModal = new bootstrap.Modal(document.getElementById('modalChamado'));
        myModal.show();
        
        // Executa o filtro de equipamentos imediatamente
        filtrarEquipamentos();

        // Limpa a URL para não reabrir o modal em caso de F5
        if (window.history.replaceState) {
            const urlLimpa = window.location.protocol + "//" + window.location.host + window.location.pathname + "?p=chamados";
            window.history.replaceState({path: urlLimpa}, '', urlLimpa);
        }
    }
});

// Auto-refresh de 30s apenas se o modal estiver fechado
setInterval(function(){
    if(!document.querySelector('.modal.show')){
        window.location.reload();
    }
}, 30000);
</script>
