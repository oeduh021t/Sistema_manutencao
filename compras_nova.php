<?php
// compras_nova.php
if (!isset($_SESSION['usuario_id'])) { die("Acesso negado."); }

$setores_query = $pdo->query("SELECT id, nome, setor_pai_id FROM setores");
$setores_raw = $setores_query->fetchAll(PDO::FETCH_UNIQUE);

function getCaminhoSetor($id, $mapa) {
    if (!isset($mapa[$id])) return "";
    $setor = $mapa[$id];
    if (!empty($setor['setor_pai_id']) && isset($mapa[$setor['setor_pai_id']])) {
        return getCaminhoSetor($setor['setor_pai_id'], $mapa) . " > " . $setor['nome'];
    }
    return $setor['nome'];
}

$lista_caminhos = [];
foreach ($setores_raw as $sid => $s) { 
    $lista_caminhos[$sid] = getCaminhoSetor($sid, $setores_raw); 
}
asort($lista_caminhos);

$equipamentos = $pdo->query("SELECT e.id, e.nome, e.patrimonio, e.setor_id FROM equipamentos e ORDER BY e.nome ASC")->fetchAll();
?>

<div class="container mt-4">
    <form action="compras_processar.php" method="POST" enctype="multipart/form-data">
        <div class="card shadow border-0 mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-cart-plus"></i> Nova Solicitação Unificada</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Vincular Equipamento (Opcional)</label>
                        <select name="equipamento_id" id="equipamento_id" class="form-select" onchange="atualizarSetor()">
                            <option value="">-- Uso Geral --</option>
                            <?php foreach ($equipamentos as $e): ?>
                                <option value="<?= $e['id'] ?>" data-setor-id="<?= $e['setor_id'] ?>">
                                    <?= htmlspecialchars($e['nome']) ?> - Pat: <?= htmlspecialchars($e['patrimonio']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Setor Destinado</label>
                        <select name="setor_id" id="setor_id" class="form-select" required>
                            <option value="">-- Selecione o Local --</option>
                            <?php foreach ($lista_caminhos as $sid => $caminho): ?>
                                <option value="<?= $sid ?>"><?= htmlspecialchars($caminho) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Justificativa Geral</label>
                        <textarea name="motivo" class="form-control" rows="2" placeholder="Descreva o motivo geral desta compra..." required></textarea>
                    </div>
                </div>

                <hr>
                <h6 class="fw-bold mb-3">Itens da Solicitação</h6>
                <div id="container-itens">
                    <div class="row item-linha mb-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label small">Descrição do Item</label>
                            <input type="text" name="item_nome[]" class="form-control" placeholder="Ex: Filtro, Correia, Óleo..." required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Qtd</label>
                            <input type="number" name="item_qtd[]" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Valor Unit. Est. (R$)</label>
                            <input type="number" step="0.01" name="item_valor[]" class="form-control" placeholder="0,00">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removerItem(this)"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="adicionarItem()">
                    <i class="bi bi-plus-circle"></i> Adicionar Mais Itens
                </button>

                <hr>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Urgência</label>
                        <select name="urgencia" class="form-select">
                            <option value="Baixa">Baixa</option>
                            <option value="Média" selected>Média</option>
                            <option value="Alta">Alta</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Anexar Arquivos (Orçamentos/Fotos)</label>
                        <input type="file" name="anexos[]" class="form-control" multiple>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4 border-top pt-3">
                    <a href="index.php?p=compras_lista" class="btn btn-outline-secondary px-4">Cancelar</a>
                    <button type="submit" class="btn btn-success px-5 fw-bold">Enviar Solicitação</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function adicionarItem() {
    const container = document.getElementById('container-itens');
    const novaLinha = container.firstElementChild.cloneNode(true);
    // Limpa os valores dos campos clonados
    novaLinha.querySelectorAll('input').forEach(input => input.value = (input.type === 'number' ? 1 : ''));
    container.appendChild(novaLinha);
}

function removerItem(btn) {
    const linhas = document.querySelectorAll('.item-linha');
    if (linhas.length > 1) {
        btn.closest('.item-linha').remove();
    } else {
        alert("A solicitação deve ter pelo menos um item.");
    }
}

function atualizarSetor() {
    var selectEquip = document.getElementById('equipamento_id');
    var selectSetor = document.getElementById('setor_id');
    var setorId = selectEquip.options[selectEquip.selectedIndex].getAttribute('data-setor-id');
    if (setorId) selectSetor.value = setorId;
}
</script>
