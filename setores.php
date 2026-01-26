<?php
include_once 'includes/db.php';

$nivel_logado = $_SESSION['usuario_nivel'];

// Lógica para Salvar
if (isset($_POST['salvar_setor'])) {
    $nome = trim($_POST['nome_setor']);
    $pai_id = !empty($_POST['setor_pai_id']) ? $_POST['setor_pai_id'] : null;

    if (!empty($nome)) {
        $stmt = $pdo->prepare("INSERT INTO setores (nome, setor_pai_id) VALUES (?, ?)");
        $stmt->execute([$nome, $pai_id]);
        echo "<div class='alert alert-success mt-3 shadow-sm'>Localização salva com sucesso!</div>";
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
        <td class="text-end px-4">
            <div class="btn-group shadow-sm">
                <a href="relatorio_setor.php?id=<?= $id ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Relatório de Infraestrutura">
                    <i class="bi bi-file-earmark-pdf"></i>
                </a>

                <?php if (in_array($nivel_logado, ['admin', 'coordenador'])): ?>
                    <a href="index.php?p=excluir_setor&id=<?= $id ?>" 
                       class="btn btn-sm btn-outline-danger" 
                       onclick="return confirm('Excluir este local?')">
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

<div class="row mt-3">
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0 small text-uppercase"><i class="bi bi-plus-circle me-2"></i>Novo Local</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nome</label>
                        <input type="text" name="nome_setor" class="form-control" placeholder="Ex: Sala 102" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Dentro de:</label>
                        <select name="setor_pai_id" class="form-select">
                            <option value="">--- Local Principal ---</option>
                            <?php 
                            $opcoes = [];
                            foreach($mapa_setores as $sid => $s) { $opcoes[$sid] = getCaminhoCompleto($sid, $mapa_setores); }
                            asort($opcoes);
                            foreach($opcoes as $sid => $caminho) echo "<option value='$sid'>$caminho</option>";
                            ?>
                        </select>
                    </div>
                    <button type="submit" name="salvar_setor" class="btn btn-primary w-100 fw-bold shadow-sm">Salvar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 small fw-bold text-uppercase"><i class="bi bi-diagram-3 me-2 text-primary"></i>Estrutura de Localizações</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Nome e Caminho</th>
                                <th class="text-end px-4">Relatórios / Ações</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0">
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
