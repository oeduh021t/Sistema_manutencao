<?php
include_once 'includes/db.php';

// --- 1. LÓGICA DE CADASTRO ---
if (isset($_POST['salvar_fornecedor'])) {
    $sql = "INSERT INTO fornecedores (nome_fantasia, razao_social, cnpj, contato, email, especialidade) VALUES (?, ?, ?, ?, ?, ?)";
    $pdo->prepare($sql)->execute([$_POST['nome_fantasia'], $_POST['razao_social'], $_POST['cnpj'], $_POST['contato'], $_POST['email'], $_POST['especialidade']]);
    header("Location: index.php?p=fornecedores&msg=cadastrado"); exit;
}

// --- 2. LÓGICA DE EDIÇÃO ---
if (isset($_POST['editar_fornecedor'])) {
    $sql = "UPDATE fornecedores SET nome_fantasia=?, razao_social=?, cnpj=?, contato=?, email=?, especialidade=?, status=? WHERE id=?";
    $pdo->prepare($sql)->execute([$_POST['nome_fantasia'], $_POST['razao_social'], $_POST['cnpj'], $_POST['contato'], $_POST['email'], $_POST['especialidade'], $_POST['status'], $_POST['id']]);
    header("Location: index.php?p=fornecedores&msg=editado"); exit;
}

// --- 3. LÓGICA DE EXCLUSÃO ---
if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM fornecedores WHERE id = ?")->execute([$_GET['excluir']]);
    header("Location: index.php?p=fornecedores&msg=excluido"); exit;
}

$fornecedores = $pdo->query("SELECT * FROM fornecedores ORDER BY nome_fantasia ASC")->fetchAll();
?>

<div class="container-fluid py-4 text-dark">
    <div class="d-flex justify-content-between align-items-center mb-4 text-dark">
        <div>
            <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-truck text-primary me-2"></i>Gestão de Fornecedores</h2>
            <small class="text-muted">Controle de parceiros e assistências técnicas</small>
        </div>
        <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNovo">
            <i class="bi bi-plus-lg"></i> Novo Fornecedor
        </button>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-info border-0 shadow-sm small">
            <i class="bi bi-info-circle me-2"></i> Operação realizada com sucesso!
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 text-dark">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-dark">
                    <thead class="table-light small text-uppercase fw-bold">
                        <tr>
                            <th class="ps-4">Empresa</th>
                            <th>CNPJ / Especialidade</th>
                            <th>Contato</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($fornecedores as $f): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?= htmlspecialchars($f['nome_fantasia']) ?></div>
                                <span class="badge <?= $f['status']=='Ativo'?'bg-success':'bg-danger' ?> px-2" style="font-size: 0.6rem;"><?= $f['status'] ?></span>
                            </td>
                            <td>
                                <div class="small fw-bold text-dark"><?= $f['cnpj'] ?: '---' ?></div>
                                <div class="text-muted small"><?= $f['especialidade'] ?></div>
                            </td>
                            <td>
                                <div class="small text-dark fw-bold"><?= $f['contato'] ?></div>
                                <div class="small text-muted"><?= $f['email'] ?></div>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary border-0" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $f['id'] ?>"><i class="bi bi-pencil-square"></i></button>
                                <a href="index.php?p=fornecedores&excluir=<?= $f['id'] ?>" class="btn btn-sm btn-outline-danger border-0" onclick="return confirm('Deseja realmente excluir este fornecedor?')"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>

                        <div class="modal fade" id="modalEdit<?= $f['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <form method="POST" class="modal-content border-0 shadow text-dark">
                                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                    <div class="modal-header bg-dark text-white">
                                        <h5 class="modal-title fw-bold">Editar Fornecedor</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-md-6"><label class="form-label fw-bold small">Nome Fantasia</label><input type="text" name="nome_fantasia" class="form-control" value="<?= $f['nome_fantasia'] ?>" required></div>
                                            <div class="col-md-6"><label class="form-label fw-bold small">Razão Social</label><input type="text" name="razao_social" class="form-control" value="<?= $f['razao_social'] ?>"></div>
                                            <div class="col-md-4"><label class="form-label fw-bold small">CNPJ</label><input type="text" name="cnpj" class="form-control" value="<?= $f['cnpj'] ?>"></div>
                                            <div class="col-md-4"><label class="form-label fw-bold small">Especialidade</label><input type="text" name="especialidade" class="form-control" value="<?= $f['especialidade'] ?>"></div>
                                            <div class="col-md-4"><label class="form-label fw-bold small">Status</label>
                                                <select name="status" class="form-select"><option value="Ativo" <?= $f['status']=='Ativo'?'selected':'' ?>>Ativo</option><option value="Inativo" <?= $f['status']=='Inativo'?'selected':'' ?>>Inativo</option></select>
                                            </div>
                                            <div class="col-md-6"><label class="form-label fw-bold small">Pessoa de Contato</label><input type="text" name="contato" class="form-control" value="<?= $f['contato'] ?>"></div>
                                            <div class="col-md-6"><label class="form-label fw-bold small">E-mail</label><input type="email" name="email" class="form-control" value="<?= $f['email'] ?>"></div>
                                        </div>
                                    </div>
                                    <div class="modal-footer bg-light">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" name="editar_fornecedor" class="btn btn-dark px-4 fw-bold">Salvar Alterações</button>
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
</div>

<div class="modal fade" id="modalNovo" tabindex="-1">
    <div class="modal-dialog modal-lg text-dark">
        <form method="POST" class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Cadastrar Novo Fornecedor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label fw-bold small">Nome Fantasia *</label><input type="text" name="nome_fantasia" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label fw-bold small">Razão Social</label><input type="text" name="razao_social" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label fw-bold small">CNPJ</label><input type="text" name="cnpj" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label fw-bold small">Especialidade</label><input type="text" name="especialidade" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label fw-bold small">Contato</label><input type="text" name="contato" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label fw-bold small">E-mail</label><input type="email" name="email" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="submit" name="salvar_fornecedor" class="btn btn-primary px-4 fw-bold">Salvar Cadastro</button>
            </div>
        </form>
    </div>
</div>
