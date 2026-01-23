<?php
include_once 'includes/db.php';

// --- LOGICA DE PROCESSAMENTO ---

// 1. Cadastrar Novo Item
if (isset($_POST['salvar_item'])) {
    $stmt = $pdo->prepare("INSERT INTO itens_estoque (nome, descricao, quantidade, valor_unitario) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['nome'], $_POST['descricao'], $_POST['quantidade'], $_POST['valor']]);
    header("Location: index.php?p=itens&msg=Item cadastrado");
}

// 2. Editar Item Existente
if (isset($_POST['editar_item'])) {
    $stmt = $pdo->prepare("UPDATE itens_estoque SET nome = ?, descricao = ?, valor_unitario = ? WHERE id = ?");
    $stmt->execute([$_POST['nome'], $_POST['descricao'], $_POST['valor'], $_POST['id']]);
    header("Location: index.php?p=itens&msg=Item atualizado");
}

// 3. Excluir Item
if (isset($_GET['excluir'])) {
    $stmt = $pdo->prepare("DELETE FROM itens_estoque WHERE id = ?");
    $stmt->execute([$_GET['excluir']]);
    header("Location: index.php?p=itens&msg=Item removido");
}

// 4. Ajuste Rápido de Quantidade (+ ou -)
if (isset($_GET['ajuste']) && isset($_GET['id'])) {
    $operacao = ($_GET['ajuste'] == 'add') ? "+ 1" : "- 1";
    $pdo->query("UPDATE itens_estoque SET quantidade = quantidade $operacao WHERE id = " . intval($_GET['id']) . " AND (quantidade > 0 OR '$ajuste' = 'add')");
    header("Location: index.php?p=itens");
}

// Busca os itens
$itens = $pdo->query("SELECT *, (quantidade * valor_unitario) as subtotal FROM itens_estoque ORDER BY nome ASC")->fetchAll();
?>

<div class="container-fluid text-dark py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-box-seam text-primary"></i> Gestão de Itens e Peças</h2>
        <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNovoItem">
            <i class="bi bi-plus-lg"></i> Novo Item
        </button>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($_GET['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item / Descrição</th>
                        <th class="text-center" width="180">Saldo Estoque</th>
                        <th>Vlr. Unitário</th>
                        <th>Subtotal</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $it): ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($it['nome']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($it['descricao']) ?></small>
                        </td>
                        <td class="text-center">
                            <div class="input-group input-group-sm justify-content-center">
                                <a href="index.php?p=itens&ajuste=sub&id=<?= $it['id'] ?>" class="btn btn-outline-danger border-end-0">-</a>
                                <span class="input-group-text bg-white fw-bold px-3"><?= $it['quantidade'] ?></span>
                                <a href="index.php?p=itens&ajuste=add&id=<?= $it['id'] ?>" class="btn btn-outline-success border-start-0">+</a>
                            </div>
                        </td>
                        <td>R$ <?= number_format($it['valor_unitario'], 2, ',', '.') ?></td>
                        <td class="fw-bold">R$ <?= number_format($it['subtotal'], 2, ',', '.') ?></td>
                        <td class="text-end pe-4">
                            <div class="btn-group shadow-sm">
                                <button class="btn btn-sm btn-light border" onclick="abrirEdicao(<?= htmlspecialchars(json_encode($it)) ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="index.php?p=itens&excluir=<?= $it['id'] ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Excluir este item permanentemente?')">
                                    <i class="bi bi-trash"></i>
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

<div class="modal fade" id="modalNovoItem" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow" method="POST">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Novo Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="fw-bold">Nome</label><input type="text" name="nome" class="form-control" required></div>
                <div class="mb-3"><label class="fw-bold">Descrição</label><textarea name="descricao" class="form-control" rows="2"></textarea></div>
                <div class="row">
                    <div class="col-6"><label class="fw-bold">Qtd Inicial</label><input type="number" name="quantidade" class="form-control" value="0"></div>
                    <div class="col-6"><label class="fw-bold">Valor (R$)</label><input type="number" step="0.01" name="valor" class="form-control" value="0.00"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" name="salvar_item" class="btn btn-primary w-100 fw-bold">CADASTRAR</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEditarItem" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow" method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Editar Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-dark">
                <div class="mb-3"><label class="fw-bold">Nome</label><input type="text" name="nome" id="edit_nome" class="form-control" required></div>
                <div class="mb-3"><label class="fw-bold">Descrição</label><textarea name="descricao" id="edit_descricao" class="form-control" rows="2"></textarea></div>
                <div class="mb-3"><label class="fw-bold">Valor Unitário (R$)</label><input type="number" step="0.01" name="valor" id="edit_valor" class="form-control" required></div>
            </div>
            <div class="modal-footer"><button type="submit" name="editar_item" class="btn btn-dark w-100 fw-bold">ATUALIZAR DADOS</button></div>
        </form>
    </div>
</div>

<script>
function abrirEdicao(item) {
    document.getElementById('edit_id').value = item.id;
    document.getElementById('edit_nome').value = item.nome;
    document.getElementById('edit_descricao').value = item.descricao;
    document.getElementById('edit_valor').value = item.valor_unitario;
    new bootstrap.Modal(document.getElementById('modalEditarItem')).show();
}
</script>
