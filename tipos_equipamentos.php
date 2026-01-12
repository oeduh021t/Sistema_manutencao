<?php
include_once 'includes/db.php';

// Lógica para Salvar Novo Tipo
if (isset($_POST['salvar_tipo'])) {
    $nome = $_POST['nome_tipo'];
    if (!empty($nome)) {
        $stmt = $pdo->prepare("INSERT INTO tipos_equipamentos (nome) VALUES (?)");
        $stmt->execute([$nome]);
        echo "<div class='alert alert-success mt-3'>Tipo '$nome' adicionado com sucesso!</div>";
    }
}

$tipos = $pdo->query("SELECT * FROM tipos_equipamentos ORDER BY nome ASC")->fetchAll();
?>

<div class="container-fluid">
    <?php if (isset($_GET['erro'])): ?>
        <div class="alert alert-danger mt-3 alert-dismissible fade show">
            <i class="bi bi-exclamation-octagon-fill me-2"></i>
            <?= htmlspecialchars($_GET['erro']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'excluido'): ?>
        <div class="alert alert-success mt-3 alert-dismissible fade show">
            Tipo de equipamento removido com sucesso.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row mt-3">
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-tags"></i> Novo Tipo</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nome do Tipo</label>
                            <input type="text" name="nome_tipo" class="form-control" placeholder="Ex: Ar Condicionado, Bomba Infusora..." required>
                        </div>
                        <button type="submit" name="salvar_tipo" class="btn btn-success w-100 shadow-sm">
                            <i class="bi bi-plus-lg"></i> Adicionar Tipo
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white text-dark">
                    <h5 class="mb-0">Tipos de Equipamentos Cadastrados</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
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
                                <td><strong><?= $t['nome'] ?></strong></td>
                                <td class="text-end pe-4">
                                    <a href="index.php?p=excluir_tipo&id=<?= $t['id'] ?>" 
                                       class="btn btn-sm btn-outline-danger border-0" 
                                       onclick="return confirm('Deseja realmente excluir este tipo de equipamento?')">
                                        <i class="bi bi-trash3"></i>
                                    </a>
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
