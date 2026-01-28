<?php
include_once 'includes/db.php';

$nivel_logado = $_SESSION['usuario_nivel'];

// --- FUNÇÃO PARA GERAR O LINK DO QR CODE DO SETOR (REDIRECIONA PARA ABERTURA DE CHAMADO) ---
function gerarLinkQRCodeSetor($id) {
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $dominio = $_SERVER['HTTP_HOST'];
    
    // Pegamos o caminho da pasta atual (ex: /sistema_manutencao)
    $caminho_projeto = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
    if ($caminho_projeto == '/') $caminho_projeto = '';
    
    // CORREÇÃO: Aponta para index.php chamando a página 'chamados'
    $url_destino = "{$protocolo}://{$dominio}{$caminho_projeto}/index.php?p=chamados&setor_id={$id}";
    
    return "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($url_destino);
}
// 1. Lógica para Salvar Novo Setor
if (isset($_POST['salvar_setor'])) {
    $nome = trim($_POST['nome_setor']);
    $pai_id = !empty($_POST['setor_pai_id']) ? $_POST['setor_pai_id'] : null;

    if (!empty($nome)) {
        $stmt = $pdo->prepare("INSERT INTO setores (nome, setor_pai_id) VALUES (?, ?)");
        $stmt->execute([$nome, $pai_id]);
        echo "<script>window.location.href='index.php?p=setores&msg=cadastrado';</script>";
        exit;
    }
}

// 2. Lógica para Editar
if (isset($_POST['editar_setor'])) {
    $id_edit = $_POST['id_setor'];
    $nome_edit = trim($_POST['nome_setor']);
    $pai_id_edit = !empty($_POST['setor_pai_id']) ? $_POST['setor_pai_id'] : null;

    if ($id_edit == $pai_id_edit) {
        echo "<script>alert('Erro: Um setor não pode estar dentro dele mesmo!'); window.location.href='index.php?p=setores';</script>";
        exit;
    }

    if (!empty($nome_edit)) {
        $stmt = $pdo->prepare("UPDATE setores SET nome = ?, setor_pai_id = ? WHERE id = ?");
        $stmt->execute([$nome_edit, $pai_id_edit, $id_edit]);
        echo "<script>window.location.href='index.php?p=setores&msg=editado';</script>";
        exit;
    }
}

$mapa_setores = $pdo->query("SELECT id, nome, setor_pai_id FROM setores ORDER BY nome ASC")->fetchAll(PDO::FETCH_UNIQUE);

function getCaminhoCompleto($id, $mapa) {
    if (!isset($mapa[$id])) return "";
    $setor = $mapa[$id];
    if (!empty($setor['setor_pai_id']) && isset($mapa[$setor['setor_pai_id']])) {
        return getCaminhoCompleto($setor['setor_pai_id'], $mapa) . " > " . $setor['nome'];
    }
    return $setor['nome'];
}

function exibirLinhaSetor($id, $mapa, $nivel = 0) {
    global $nivel_logado;
    if (!isset($mapa[$id])) return;
    $setor = $mapa[$id];
    $recuo = $nivel * 30; 
    $qr_link = gerarLinkQRCodeSetor($id);
    ?>
    <tr>
        <td style="padding-left: <?= $recuo + 15 ?>px;">
            <?php if ($nivel > 0): ?>
                <i class="bi bi-arrow-return-right text-muted me-2"></i>
                <i class="bi bi-folder2 text-warning me-1"></i>
            <?php else: ?>
                <i class="bi bi-building text-primary me-2"></i>
            <?php endif; ?>
            <span class="<?= $nivel == 0 ? 'fw-bold text-dark' : 'text-secondary' ?>">
                <?= htmlspecialchars($setor['nome']) ?>
            </span>
            <small class="d-block text-muted" style="font-size: 0.65rem; margin-left: 25px;">
                <?= getCaminhoCompleto($id, $mapa) ?>
            </small>
        </td>
        <td class="text-center">
            <a href="#" data-bs-toggle="modal" data-bs-target="#modalQRSetor<?= $id ?>">
                <img src="<?= $qr_link ?>" width="35" class="img-thumbnail shadow-sm border-primary">
            </a>

            <div class="modal fade" id="modalQRSetor<?= $id ?>" tabindex="-1">
                <div class="modal-dialog modal-sm text-center">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-dark text-white py-2">
                            <h6 class="modal-title small">Etiqueta de Localização</h6>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4" id="etiquetaSetor<?= $id ?>">
                            <div style="border: 2px solid #000; padding: 10px; border-radius: 5px; background: #fff; display: inline-block;">
                                <small class="fw-bold d-block text-uppercase text-dark" style="font-size: 9px; margin-bottom: 5px;">HOSPITAL DOMINGOS LOURENÇO</small>
                                <img src="<?= $qr_link ?>" style="width: 140px; height: 140px;">
                                <div class="fw-bold text-dark" style="font-size: 16px; margin-top: 5px; border-top: 1px solid #000;"><?= htmlspecialchars($setor['nome']) ?></div>
                                <small class="text-muted" style="font-size: 8px;"><?= getCaminhoCompleto($id, $mapa) ?></small>
                                <div style="font-size: 7px; margin-top: 5px;" class="text-primary fw-bold">ESCANEIE PARA ABRIR CHAMADO</div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light p-2">
                            <button type="button" class="btn btn-dark btn-sm w-100 fw-bold shadow-sm" onclick="imprimirEtiquetaSetor('etiquetaSetor<?= $id ?>')">
                                <i class="bi bi-printer me-2"></i>IMPRIMIR
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </td>
        <td class="text-end px-4">
            <div class="btn-group shadow-sm">
                <button type="button" class="btn btn-sm btn-outline-secondary" 
                        onclick="abrirModalEdicaoSetor(<?= $id ?>, '<?= htmlspecialchars($setor['nome']) ?>', '<?= $setor['setor_pai_id'] ?>')" 
                        title="Editar Localização">
                    <i class="bi bi-pencil-square"></i>
                </button>

                <a href="relatorio_setor.php?id=<?= $id ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Relatório">
                    <i class="bi bi-file-earmark-pdf"></i>
                </a>

                <?php if (in_array($nivel_logado, ['admin', 'coordenador'])): ?>
                    <a href="index.php?p=excluir_setor&id=<?= $id ?>" 
                       class="btn btn-sm btn-outline-danger" 
                       onclick="return confirm('Deseja excluir este local e seus sub-setores?')">
                        <i class="bi bi-trash3"></i>
                    </a>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php
    foreach ($mapa as $sub_id => $s) {
        if ($s['setor_pai_id'] == $id) {
            exibirLinhaSetor($sub_id, $mapa, $nivel + 1);
        }
    }
}
?>

<div class="container-fluid text-dark py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-diagram-3 text-primary me-2"></i>Gestão de Setores</h2>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success shadow-sm small border-0 alert-dismissible fade show">
            Operação realizada com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4 text-dark">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0 small text-uppercase fw-bold"><i class="bi bi-plus-circle me-2"></i>Novo Local</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Nome do Setor/Sala</label>
                            <input type="text" name="nome_setor" class="form-control" placeholder="Ex: Farmácia Central" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Vincular a (Setor Pai):</label>
                            <select name="setor_pai_id" class="form-select">
                                <option value="">--- Local Principal (Raiz) ---</option>
                                <?php 
                                $opcoes = [];
                                foreach($mapa_setores as $sid => $s) { $opcoes[$sid] = getCaminhoCompleto($sid, $mapa_setores); }
                                asort($opcoes);
                                foreach($opcoes as $sid => $caminho) echo "<option value='$sid'>$caminho</option>";
                                ?>
                            </select>
                        </div>
                        <button type="submit" name="salvar_setor" class="btn btn-primary w-100 fw-bold shadow-sm">SALVAR LOCAL</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0 text-dark">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-dark">
                            <thead class="table-light small fw-bold">
                                <tr>
                                    <th class="ps-4">ESTRUTURA HIERÁRQUICA</th>
                                    <th class="text-center">QR CODE</th>
                                    <th class="text-end px-4">AÇÕES</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ($mapa_setores as $id => $setor) {
                                    if (empty($setor['setor_pai_id'])) exibirLinhaSetor($id, $mapa_setores);
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarSetor" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow text-dark" method="POST">
            <input type="hidden" name="id_setor" id="edit_id_setor">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Editar Localização</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted">Nome do Setor</label>
                    <input type="text" name="nome_setor" id="edit_nome_setor" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted">Mover para dentro de:</label>
                    <select name="setor_pai_id" id="edit_setor_pai_id" class="form-select">
                        <option value="">--- Local Principal ---</option>
                        <?php foreach($opcoes as $sid => $caminho) echo "<option value='$sid'>$caminho</option>"; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="editar_setor" class="btn btn-primary fw-bold">SALVAR ALTERAÇÕES</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalEdicaoSetor(id, nome, pai_id) {
    document.getElementById('edit_id_setor').value = id;
    document.getElementById('edit_nome_setor').value = nome;
    document.getElementById('edit_setor_pai_id').value = pai_id;
    new bootstrap.Modal(document.getElementById('modalEditarSetor')).show();
}

function imprimirEtiquetaSetor(divId) {
    var conteudo = document.getElementById(divId).innerHTML;
    var win = window.open('', '', 'height=500,width=500');
    win.document.write('<html><head><title>Imprimir Etiqueta</title>');
    win.document.write('<style>body{display:flex; justify-content:center; align-items:center; height:100vh; margin:0; font-family: sans-serif;}</style>');
    win.document.write('</head><body>' + conteudo + '</body></html>');
    win.document.close();
    setTimeout(function() { win.print(); win.close(); }, 500);
}
</script>
