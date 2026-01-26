<?php
include_once 'includes/db.php';

// Lógica para Salvar Novo Tipo
if (isset($_POST['salvar_tipo'])) {
    $nome = $_POST['nome_tipo'];
    if (!empty($nome)) {
        $stmt = $pdo->prepare("INSERT INTO tipos_equipamentos (nome) VALUES (?)");
        $stmt->execute([$nome]);
        echo "<div class='alert alert-success mt-3 shadow-sm'>Tipo '$nome' adicionado com sucesso!</div>";
    }
}

// Lógica para Editar Tipo Existente
if (isset($_POST['editar_tipo'])) {
    $id_edit = $_POST['id_tipo'];
    $nome_edit = $_POST['nome_tipo'];
    if (!empty($nome_edit)) {
        $stmt = $pdo->prepare("UPDATE tipos_equipamentos SET nome = ? WHERE id = ?");
        $stmt->execute([$nome_edit, $id_edit]);
        echo "<script>window.location.href='index.php?p=tipos_equipamentos&msg=editado';</script>";
        exit;
    }
}

$tipos = $pdo->query("SELECT * FROM tipos_equipamentos ORDER BY nome ASC")->fetchAll();
?>

<div class="container-fluid text-dark">
    <?php if (isset($_GET['erro'])): ?>
        <div class="alert alert-danger mt-3 alert-dismissible fade show shadow-sm">
            <i class="bi bi-exclamation-octagon-fill me-2"></i>
            <?= htmlspecialchars($_GET['erro']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'editado'): ?>
        <div class="alert alert-primary mt-3 alert-dismissible fade show shadow-sm">
            Tipo de equipamento atualizado com sucesso.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row mt-3">
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Novo Tipo</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3 text-dark">
                            <label class="form-label fw-bold small text-muted">Nome do Tipo</label>
                            <input type="text" name="nome_tipo" class="form-control" placeholder="Ex: Ar Condicionado..." required>
                        </div>
                        <button type="submit" name="salvar_tipo" class="btn btn-success w-100 fw-bold shadow-sm">
                            <i class="bi bi-check-lg"></i> ADICIONAR
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white text-dark py-3">
                    <h5 class="mb-0 fw-bold">Tipos de Equipamentos Cadastrados</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-dark">
                            <thead class="table-light small text-uppercase fw-bold">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Nome do Tipo</th>
                                    <th class="text-end pe-4">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tipos as $t): ?>
                                <tr>
                                    <td class="ps-4 text-muted">#<?= $t['id'] ?></td>
                                    <td><span class="fw-bold"><?= htmlspecialchars($t['nome']) ?></span></td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary border-0" 
                                                    onclick="abrirModalEdicao(<?= $t['id'] ?>, '<?= htmlspecialchars($t['nome']) ?>')">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            
                                            <a href="index.php?p=excluir_tipo&id=<?= $t['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger border-0" 
                                               onclick="return confirm('Deseja realmente excluir este tipo?')">
                                                <i class="bi bi-trash3"></i>
                                            </a>
                                        </div>
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

<div class="modal fade" id="modalEditarTipo" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow" method="POST">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Editar Tipo de Equipamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-dark">
                <input type="hidden" name="id_tipo" id="edit_id_tipo">
                <div class="mb-3">
                    <label class="form-label fw-bold">Novo Nome</label>
                    <input type="text" name="nome_tipo" id="edit_nome_tipo" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="editar_tipo" class="btn btn-primary fw-bold">SALVAR ALTERAÇÕES</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalEdicao(id, nome) {
    document.getElementById('edit_id_tipo').value = id;
    document.getElementById('edit_nome_tipo').value = nome;
    var modal = new bootstrap.Modal(document.getElementById('modalEditarTipo'));
    modal.show();
}
</script>
