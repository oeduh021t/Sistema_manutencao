<?php
include_once 'includes/db.php';

// --- LOGICA DE PROCESSAMENTO ---

// 1. Cadastrar
if (isset($_POST['salvar_item'])) {
    $stmt = $pdo->prepare("INSERT INTO itens_estoque (nome, descricao, quantidade, valor_unitario) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['nome'], $_POST['descricao'], $_POST['quantidade'], $_POST['valor']]);
    echo "<script>window.location.href='index.php?p=itens&msg=cadastrado';</script>";
    exit;
}

// 2. Editar
if (isset($_POST['editar_item'])) {
    $stmt = $pdo->prepare("UPDATE itens_estoque SET nome = ?, descricao = ?, valor_unitario = ? WHERE id = ?");
    $stmt->execute([$_POST['nome'], $_POST['descricao'], $_POST['valor'], $_POST['id']]);
    echo "<script>window.location.href='index.php?p=itens&msg=editado';</script>";
    exit;
}

// 3. Excluir
if (isset($_GET['excluir'])) {
    $stmt = $pdo->prepare("DELETE FROM itens_estoque WHERE id = ?");
    $stmt->execute([$_GET['excluir']]);
    echo "<script>window.location.href='index.php?p=itens&msg=excluido';</script>";
    exit;
}

// 4. Ajuste Rápido
if (isset($_GET['ajuste'], $_GET['id'])) {
    $id = intval($_GET['id']);
    $op = ($_GET['ajuste'] === 'add') ? "+" : "-";
    $pdo->query("UPDATE itens_estoque SET quantidade = quantidade $op 1 WHERE id = $id" . ($op === '-' ? " AND quantidade > 0" : ""));
    echo "<script>window.location.href='index.php?p=itens';</script>";
    exit;
}

$itens = $pdo->query("SELECT *, (quantidade * valor_unitario) AS subtotal FROM itens_estoque ORDER BY nome ASC")->fetchAll();
?>

<div class="container-fluid text-dark py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-box-seam text-primary"></i> Gestão de Peças</h2>
        <button type="button" class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNovoItem">
            <i class="bi bi-plus-lg"></i> Novo Item
        </button>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Operação concluída com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-dark">
                <thead class="table-light fw-bold">
                    <tr>
                        <th class="ps-4">Item</th>
                        <th class="text-center">Quantidade</th>
                        <th>Valor Unit.</th>
                        <th>Total</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($itens as $it): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?= htmlspecialchars($it['nome']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($it['descricao']) ?></small>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="index.php?p=itens&ajuste=sub&id=<?= $it['id'] ?>" class="btn btn-outline-secondary">-</a>
                                <span class="btn btn-light disabled fw-bold border" style="width: 50px; opacity: 1;"><?= $it['quantidade'] ?></span>
                                <a href="index.php?p=itens&ajuste=add&id=<?= $it['id'] ?>" class="btn btn-outline-secondary">+</a>
                            </div>
                        </td>
                        <td>R$ <?= number_format($it['valor_unitario'], 2, ',', '.') ?></td>
                        <td class="fw-bold">R$ <?= number_format($it['subtotal'], 2, ',', '.') ?></td>
                        <td class="text-end pe-4">
                            <button type="button" class="btn btn-sm btn-outline-warning border-0" 
                                    title="Busca Inteligente"
                                    onclick="buscarPrecosItem('<?= htmlspecialchars($it['nome'] . ' ' . $it['descricao'], ENT_QUOTES) ?>')">
                                <i class="bi bi-search-heart fs-5"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary border-0" 
                                    onclick='abrirEdicao(<?= json_encode($it) ?>)'>
                                <i class="bi bi-pencil-square fs-5"></i>
                            </button>
                            <a href="index.php?p=itens&excluir=<?= $it['id'] ?>" 
                               class="btn btn-sm btn-outline-danger border-0" 
                               onclick="return confirm('Excluir permanentemente?')">
                                <i class="bi bi-trash3 fs-5"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end text-dark" tabindex="-1" id="sidebarCotacao" style="width: 420px;">
  <div class="offcanvas-header bg-dark text-white">
    <h5 class="offcanvas-title fw-bold"><i class="bi bi-robot me-2"></i>Busca Inteligente</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body bg-light">
    <div id="status-ia-item" class="text-center py-5 d-none">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <p class="fw-bold text-muted">Consultando mercado real...</p>
    </div>
    <div id="lista-precos-ia"></div>
  </div>
</div>

<div class="modal fade" id="modalNovoItem" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow text-dark" method="POST">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Cadastrar Novo Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="fw-bold">Nome</label><input type="text" name="nome" class="form-control" required></div>
                <div class="mb-3"><label class="fw-bold">Descrição</label><textarea name="descricao" class="form-control"></textarea></div>
                <div class="row">
                    <div class="col-6"><label class="fw-bold text-dark">Qtd Inicial</label><input type="number" name="quantidade" class="form-control" value="0"></div>
                    <div class="col-6"><label class="fw-bold text-dark">Valor (R$)</label><input type="number" step="0.01" name="valor" class="form-control" value="0.00"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" name="salvar_item" class="btn btn-primary w-100 fw-bold">SALVAR</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEditarItem" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow text-dark" method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Editar Detalhes do Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="fw-bold text-dark">Nome</label><input type="text" name="nome" id="edit_nome" class="form-control" required></div>
                <div class="mb-3"><label class="fw-bold text-dark">Descrição</label><textarea name="descricao" id="edit_descricao" class="form-control"></textarea></div>
                <div class="mb-3"><label class="fw-bold text-dark">Valor Unitário</label><input type="number" step="0.01" name="valor" id="edit_valor" class="form-control" required></div>
            </div>
            <div class="modal-footer"><button type="submit" name="editar_item" class="btn btn-dark w-100 fw-bold">ATUALIZAR</button></div>
        </form>
    </div>
</div>

<script>
function abrirEdicao(item) {
    document.getElementById('edit_id').value = item.id;
    document.getElementById('edit_nome').value = item.nome;
    document.getElementById('edit_descricao').value = item.descricao;
    document.getElementById('edit_valor').value = item.valor_unitario;
    
    var myModal = new bootstrap.Modal(document.getElementById('modalEditarItem'));
    myModal.show();
}

function buscarPrecosItem(termo) {
    const el = document.getElementById('sidebarCotacao');
    const sidebar = bootstrap.Offcanvas.getInstance(el) || new bootstrap.Offcanvas(el);
    const divStatus = document.getElementById('status-ia-item');
    const divLista = document.getElementById('lista-precos-ia');
    
    divLista.innerHTML = '';
    divStatus.classList.remove('d-none');
    sidebar.show();

    fetch(`cotar_ia.php?termo=${encodeURIComponent(termo)}`)
    .then(r => r.json())
    .then(data => {
        divStatus.classList.add('d-none');
        if(!data || data.length === 0) {
            divLista.innerHTML = '<div class="alert alert-warning border-0 shadow-sm text-center">Nenhum preço real encontrado para este item no momento.</div>';
            return;
        }

        data.forEach(i => {
            divLista.innerHTML += `
                <div class="card mb-3 border-0 shadow-sm">
                    <div class="card-body p-2">
                        <div class="d-flex align-items-center">
                            <img src="${i.foto || 'https://via.placeholder.com/60'}" class="rounded border me-3" style="width: 60px; height: 60px; object-fit: cover;">
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="fw-bold text-truncate" style="font-size: 0.9rem;" title="${i.titulo}">${i.titulo}</div>
                                <div class="text-success fw-bold fs-5">${i.preco}</div>
                                <small class="text-muted d-block">🛒 ${i.loja}</small>
                            </div>
                            <a href="${i.link}" target="_blank" class="btn btn-sm btn-outline-primary ms-2"><i class="bi bi-box-arrow-up-right"></i></a>
                        </div>
                    </div>
                </div>`;
        });
    })
    .catch(err => {
        divStatus.classList.add('d-none');
        divLista.innerHTML = '<div class="alert alert-danger border-0 shadow-sm">Erro ao conectar com a inteligência de mercado. Verifique sua conexão.</div>';
    });
}
</script>
