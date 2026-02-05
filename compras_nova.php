<?php
// compras_nova.php
if (!isset($_SESSION['usuario_id'])) { die("Acesso negado."); }

// 1. Busca todos os setores para montar o mapa de caminhos hierárquicos
$setores_query = $pdo->query("SELECT id, nome, setor_pai_id FROM setores");
$setores_raw = $setores_query->fetchAll(PDO::FETCH_UNIQUE);

// Função para montar o caminho (ex: Bloco A > Térreo > Banheiro)
function getCaminhoSetor($id, $mapa) {
    if (!isset($mapa[$id])) return "";
    $setor = $mapa[$id];
    if (!empty($setor['setor_pai_id']) && isset($mapa[$setor['setor_pai_id']])) {
        return getCaminhoSetor($setor['setor_pai_id'], $mapa) . " > " . $setor['nome'];
    }
    return $setor['nome'];
}

// 2. Monta a lista de caminhos e ordena alfabeticamente para evitar confusão com nomes iguais
$lista_caminhos = [];
foreach ($setores_raw as $sid => $s) { 
    $lista_caminhos[$sid] = getCaminhoSetor($sid, $setores_raw); 
}
asort($lista_caminhos);

// 3. Busca equipamentos com o ID do setor para o JavaScript de preenchimento automático
$sql = "SELECT e.id, e.nome, e.patrimonio, e.setor_id, s.nome as setor_nome 
        FROM equipamentos e 
        LEFT JOIN setores s ON e.setor_id = s.id 
        ORDER BY e.nome ASC";
$stmt = $pdo->query($sql);
$equipamentos = $stmt->fetchAll();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow border-0">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-cart-plus"></i> Nova Solicitação de Compra</h5>
                </div>
                <div class="card-body">
                    <form action="compras_processar.php" method="POST" enctype="multipart/form-data">
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label fw-bold">Item / Peça / Serviço</label>
                                <input type="text" name="item_nome" class="form-control" placeholder="Ex: Filtro de ar, Correia..." required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Quantidade</label>
                                <input type="number" name="quantidade" class="form-control" value="1" min="1" required>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">Vincular a um Equipamento? (Opcional)</label>
                                <select name="equipamento_id" id="equipamento_id" class="form-select" onchange="atualizarSetor()">
                                    <option value="">-- Uso Geral (Não vinculado) --</option>
                                    <?php foreach ($equipamentos as $e): ?>
                                        <option value="<?= $e['id'] ?>" data-setor-id="<?= $e['setor_id'] ?>">
                                            <?= htmlspecialchars($e['nome']) ?> - Pat: <?= htmlspecialchars($e['patrimonio']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">Setor Destinado</label>
                                <select name="setor_id" id="setor_id" class="form-select" required>
                                    <option value="">-- Selecione o Local --</option>
                                    <?php foreach ($lista_caminhos as $sid => $caminho): ?>
                                        <option value="<?= $sid ?>"><?= htmlspecialchars($caminho) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Se escolher um equipamento acima, o setor será preenchido sozinho.</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Urgência</label>
                                <select name="urgencia" class="form-select">
                                    <option value="Baixa">Baixa</option>
                                    <option value="Média" selected>Média</option>
                                    <option value="Alta">Alta</option>
                                    <option value="Crítica">Crítica</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Valor Estimado Unitário (R$)</label>
                                <input type="number" step="0.01" name="valor_estimado" class="form-control" placeholder="0,00">
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">Justificativa da Necessidade</label>
                                <textarea name="motivo" class="form-control" rows="3" placeholder="Por que precisamos comprar isso agora?" required></textarea>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">Anexar Arquivos (Opcional)</label>
                                <input type="file" name="anexos[]" class="form-control" accept=".jpg,.jpeg,.png,.pdf" multiple>
                                <small class="text-muted">Fotos da peça ou orçamentos em PDF. Você pode selecionar vários arquivos.</small>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4 border-top pt-3">
                            <a href="index.php?p=compras_lista" class="btn btn-outline-secondary px-4">Cancelar</a>
                            <button type="submit" class="btn btn-success px-5 fw-bold">Enviar Solicitação</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function atualizarSetor() {
    var selectEquip = document.getElementById('equipamento_id');
    var selectSetor = document.getElementById('setor_id');
    
    // Pega a opção selecionada
    var selectedOption = selectEquip.options[selectEquip.selectedIndex];
    
    // Pega o ID do setor que está no atributo data-setor-id
    var setorId = selectedOption.getAttribute('data-setor-id');

    if (setorId && setorId !== "") {
        selectSetor.value = setorId;
    } else {
        // Se for Uso Geral, limpa a seleção para o usuário escolher manualmente
        selectSetor.value = "";
    }
}
</script>
