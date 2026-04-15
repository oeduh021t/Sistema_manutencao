<?php
include_once 'includes/db.php';

// Ativar exibição de erros para diagnóstico
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Captura o técnico logado na sessão
$nome_tecnico_logado = $_SESSION['usuario_nome'] ?? 'Técnico Não Identificado';

// 1. Mapa de setores para construir a hierarquia
$setores_mapa = $pdo->query("SELECT id, nome, setor_pai_id FROM setores")->fetchAll(PDO::FETCH_UNIQUE);

function getCaminhoCompletoTratamento($id, $mapa) {
    if (!isset($mapa[$id])) return "Setor não identificado";
    $setor = $mapa[$id];
    if (!empty($setor['setor_pai_id']) && isset($mapa[$setor['setor_pai_id']])) {
        return getCaminhoCompletoTratamento($setor['setor_pai_id'], $mapa) . " > " . $setor['nome'];
    }
    return $setor['nome'];
}

$tecnicos_lista = $pdo->query("SELECT nome FROM usuarios WHERE nivel IN ('tecnico', 'admin', 'coordenador') ORDER BY nome ASC")->fetchAll();
$fornecedores_lista = $pdo->query("SELECT id, nome_fantasia FROM fornecedores WHERE status = 'Ativo' ORDER BY nome_fantasia ASC")->fetchAll();

if (!isset($_GET['id'])) { die("Chamado não especificado."); }
$id = $_GET['id'];

// --- LÓGICA DE ADICIONAR ITEM AO ESTOQUE ---
if (isset($_POST['adicionar_item_estoque'])) {
    $item_id = $_POST['item_id'];
    $qtd_usada = $_POST['qtd_usada'];

    $st = $pdo->prepare("SELECT nome, quantidade, valor_unitario FROM itens_estoque WHERE id = ?");
    $st->execute([$item_id]);
    $item_info = $st->fetch();

    if ($item_info && $item_info['quantidade'] >= $qtd_usada) {
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("INSERT INTO chamados_itens (chamado_id, item_id, quantidade, valor_unitario_na_epoca) VALUES (?, ?, ?, ?)");
            $ins->execute([$id, $item_id, $qtd_usada, $item_info['valor_unitario']]);

            $upd = $pdo->prepare("UPDATE itens_estoque SET quantidade = quantidade - ? WHERE id = ?");
            $upd->execute([$qtd_usada, $item_id]);

            $msg_estoque = "Peça utilizada: " . $qtd_usada . "x " . $item_info['nome'];
            $pdo->prepare("INSERT INTO chamados_historico (chamado_id, tecnico_nome, texto_historico, status_momento, data_registro) VALUES (?, ?, ?, ?, NOW())")
                ->execute([$id, $_SESSION['usuario_nome'], $msg_estoque, 'Em Atendimento']);

            $pdo->commit();
            echo "<script>window.location.href='index.php?p=tratar_chamado&id=$id&sucesso_item=1';</script>";
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erro ao baixar estoque: " . $e->getMessage());
        }
    } else {
        echo "<script>alert('Estoque insuficiente!');</script>";
    }
}

// --- LÓGICA DE ATUALIZAÇÃO DO CHAMADO ---
if (isset($_POST['atualizar_chamado'])) {
    try {
        $anotacao = trim($_POST['descricao_solucao']);
        $status = $_POST['status'];
        $tecnico = $_POST['tecnico_responsavel'];
        $causa_raiz = $_POST['causa_raiz'] ?? 'Não Informada';
        $tipo_atendimento = $_POST['tipo_atendimento'] ?? 'Interno';
        $nota_fornecedor = $_POST['nota_fornecedor'] ?? 0;

        $fornecedor_id = !empty($_POST['fornecedor_id']) ? $_POST['fornecedor_id'] : null;
        $tecnico_externo = !empty($_POST['tecnico_externo_nome']) ? $_POST['tecnico_externo_nome'] : null;
        $nf = !empty($_POST['nf_referencia']) ? $_POST['nf_referencia'] : null;
        $custo = !empty($_POST['custo_servico']) ? $_POST['custo_servico'] : 0;

        $data_conclusao = ($status == 'Concluído') ? date('Y-m-d H:i:s') : null;

        $pdo->beginTransaction();

        if ($status == 'Concluído') {
            $sql = "UPDATE chamados SET status = ?, data_conclusao = ?, tecnico_responsavel = ?, nf_referencia = ?, custo_servico = ?, causa_raiz = ?, tipo_atendimento = ?, nota_fornecedor = ?, fornecedor_id = ?, tecnico_externo_nome = ?, descricao_solucao = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$status, $data_conclusao, $tecnico, $nf, $custo, $causa_raiz, $tipo_atendimento, $nota_fornecedor, $fornecedor_id, $tecnico_externo, $anotacao, $id]);
        } else {
            $sql = "UPDATE chamados SET status = ?, data_conclusao = ?, tecnico_responsavel = ?, nf_referencia = ?, custo_servico = ?, causa_raiz = ?, tipo_atendimento = ?, nota_fornecedor = ?, fornecedor_id = ?, tecnico_externo_nome = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$status, $data_conclusao, $tecnico, $nf, $custo, $causa_raiz, $tipo_atendimento, $nota_fornecedor, $fornecedor_id, $tecnico_externo, $id]);
        }

        if (!empty($anotacao)) {
            $pdo->prepare("INSERT INTO chamados_historico (chamado_id, tecnico_nome, texto_historico, status_momento, data_registro) VALUES (?, ?, ?, ?, NOW())")->execute([$id, $tecnico, $anotacao, $status]);
        }

        if (!empty($_FILES['foto_conclusao']['name'][0])) {
            foreach ($_FILES['foto_conclusao']['name'] as $key => $name) {
                if ($_FILES['foto_conclusao']['error'][$key] === 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $foto_nome = "HIST_" . $id . "_" . time() . "_" . $key . "." . $ext;
                    if (move_uploaded_file($_FILES['foto_conclusao']['tmp_name'][$key], __DIR__ . "/uploads/" . $foto_nome)) {
                        $pdo->prepare("INSERT INTO chamados_historico (chamado_id, tecnico_nome, texto_historico, status_momento, foto_historico, data_registro) VALUES (?, ?, 'Anexo fotográfico', ?, ?, NOW())")->execute([$id, $tecnico, $status, $foto_nome]);
                    }
                }
            }
        }

        $pdo->commit();
        echo "<script>window.location.href='index.php?p=tratar_chamado&id=$id&sucesso=1';</script>";
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro técnico: " . $e->getMessage());
    }
}

// Busca dados para exibição
$stmt = $pdo->prepare("SELECT c.*, e.patrimonio, e.nome as eq_nome FROM chamados c LEFT JOIN equipamentos e ON c.equipamento_id = e.id WHERE c.id = ?");
$stmt->execute([$id]);
$chamado = $stmt->fetch();
$caminho_setor = getCaminhoCompletoTratamento($chamado['setor_id'], $setores_mapa);
$logs = $pdo->prepare("SELECT * FROM chamados_historico WHERE chamado_id = ? ORDER BY data_registro DESC");
$logs->execute([$id]);
$logs = $logs->fetchAll();
?>

<style>
    .btn-acao { height: 60px; display: flex; align-items: center; justify-content: center; font-weight: bold; border-width: 2px; }
    .btn-acao i { font-size: 1.2rem; margin-right: 8px; }
</style>

<div class="container-fluid py-3 text-dark">
    <?php if(isset($_GET['sucesso'])): ?>
        <div class="alert alert-success shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i> Atualização salva com sucesso!</div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold mb-0 text-dark"><i class="bi bi-wrench-adjustable text-primary"></i> Atendimento #<?= $id ?></h3>
            <span class="badge bg-light text-dark border mt-1"><i class="bi bi-geo-alt text-danger"></i> <?= htmlspecialchars($caminho_setor) ?></span>
        </div>
        <a href="index.php?p=chamados" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-dark text-white fw-bold small text-uppercase">Cronologia</div>
                <div class="card-body" style="max-height: 450px; overflow-y: auto;">
                    <?php foreach($logs as $l): ?>
                        <div class="border-start border-3 border-primary ps-3 pb-3 mb-3 position-relative">
                            <i class="bi bi-circle-fill text-primary position-absolute" style="left: -7px; top: 0; font-size: 0.7rem;"></i>
                            <small class="text-muted d-block"><?= date('d/m H:i', strtotime($l['data_registro'])) ?> - <b><?= $l['status_momento'] ?></b></small>
                            <div class="fw-bold small"><?= htmlspecialchars($l['tecnico_nome']) ?></div>
                            <div class="bg-light p-2 rounded small border mt-1"><?= nl2br(htmlspecialchars($l['texto_historico'])) ?></div>
                            <?php if($l['foto_historico']): ?>
                                <img src="uploads/<?= $l['foto_historico'] ?>" class="img-fluid rounded mt-2 shadow-sm" style="max-height: 100px;">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white fw-bold small text-uppercase">Peças e Materiais</div>
                <div class="card-body">
                    <form method="POST" class="row g-2 align-items-end mb-3">
                        <div class="col-8">
                            <label class="small fw-bold">Item do Estoque</label>
                            <select name="item_id" class="form-select form-select-sm" required>
                                <option value="">-- Selecione --</option>
                                <?php $itens_estoque = $pdo->query("SELECT * FROM itens_estoque WHERE quantidade > 0 ORDER BY nome ASC")->fetchAll();
                                foreach($itens_estoque as $it): ?>
                                    <option value="<?= $it['id'] ?>"><?= $it['nome'] ?> (Disp: <?= $it['quantidade'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm">
                                <input type="number" name="qtd_usada" class="form-control" value="1" min="1">
                                <button type="submit" name="adicionar_item_estoque" class="btn btn-primary"><i class="bi bi-plus"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <form method="POST" enctype="multipart/form-data" class="card shadow-sm border-0">
                <div class="card-header bg-success text-white fw-bold small text-uppercase">Conclusão Técnica</div>
                <div class="card-body">
                    
                    <div class="mb-4 text-center">
                        <label class="form-label d-block fw-bold small text-muted text-uppercase">Origem da Execução</label>
                        <div class="btn-group w-100 shadow-sm">
                            <input type="radio" class="btn-check" name="tipo_atendimento" id="tipoI" value="Interno" <?= ($chamado['tipo_atendimento'] != 'Externo') ? 'checked' : '' ?> onclick="document.getElementById('secao_externa').style.display='none'">
                            <label class="btn btn-outline-primary fw-bold" for="tipoI">EQUIPE INTERNA</label>
                            <input type="radio" class="btn-check" name="tipo_atendimento" id="tipoE" value="Externo" <?= ($chamado['tipo_atendimento'] == 'Externo') ? 'checked' : '' ?> onclick="document.getElementById('secao_externa').style.display='block'">
                            <label class="btn btn-outline-danger fw-bold" for="tipoE">FORNECEDOR</label>
                        </div>
                    </div>

                    <div id="secao_externa" class="p-3 mb-3 border rounded bg-light" style="display: <?= ($chamado['tipo_atendimento'] == 'Externo') ? 'block' : 'none' ?>;">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="small fw-bold">Fornecedor</label>
                                <select name="fornecedor_id" class="form-select">
                                    <option value="">-- Escolha --</option>
                                    <?php foreach($fornecedores_lista as $forn): ?>
                                        <option value="<?= $forn['id'] ?>" <?= ($chamado['fornecedor_id'] == $forn['id']) ? 'selected' : '' ?>><?= $forn['nome_fantasia'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">NF / Custo R$</label>
                                <div class="input-group">
                                    <input type="text" name="nf_referencia" class="form-control" placeholder="NF" value="<?= $chamado['nf_referencia'] ?>">
                                    <input type="number" step="0.01" name="custo_servico" class="form-control" value="<?= $chamado['custo_servico'] ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="small fw-bold">Status Atual</label>
                            <select name="status" class="form-select border-2">
                                <option value="Aberto" <?= $chamado['status'] == 'Aberto' ? 'selected' : '' ?>>Aberto</option>
                                <option value="Em Atendimento" <?= $chamado['status'] == 'Em Atendimento' ? 'selected' : '' ?>>Em Atendimento</option>
                                <option value="Concluído" <?= $chamado['status'] == 'Concluído' ? 'selected' : '' ?>>Concluído</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">Técnico Responsável</label>
                            <input type="text" class="form-control bg-light fw-bold text-success" value="<?= $nome_tecnico_logado ?>" readonly>
                            <input type="hidden" name="tecnico_responsavel" value="<?= $nome_tecnico_logado ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-success text-uppercase small">Ações Rápidas (Toque para preencher)</label>
                        <div class="row g-2">
                            <div class="col-6 col-sm-4"><button type="button" class="btn btn-outline-dark w-100 btn-acao" onclick="addTexto('Realizada limpeza técnica e lubrificação.')"><i class="bi bi-droplet"></i> Limpeza</button></div>
                            <div class="col-6 col-sm-4"><button type="button" class="btn btn-outline-dark w-100 btn-acao" onclick="addTexto('Realizada troca de componente danificado.')"><i class="bi bi-recycle"></i> Troca Peça</button></div>
                            <div class="col-6 col-sm-4"><button type="button" class="btn btn-outline-dark w-100 btn-acao" onclick="addTexto('Equipamento testado e em pleno funcionamento.')"><i class="bi bi-check-all"></i> Testado</button></div>
                            <div class="col-6 col-sm-4"><button type="button" class="btn btn-outline-dark w-100 btn-acao" onclick="addTexto('Ajuste de configuração e calibração.')"><i class="bi bi-gear"></i> Ajuste</button></div>
                            <div class="col-6 col-sm-4"><button type="button" class="btn btn-outline-dark w-100 btn-acao" onclick="addTexto('Aguardando chegada de peças externas.')"><i class="bi bi-clock"></i> Aguard. Peça</button></div>
                            <div class="col-6 col-sm-4"><button type="button" class="btn btn-outline-dark w-100 btn-acao" onclick="addTexto('Nenhum defeito constatado na visita.')"><i class="bi bi-question"></i> S/ Defeito</button></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary text-uppercase small">Relatório do Atendimento</label>
                        <textarea name="descricao_solucao" id="campo_descricao" class="form-control border-primary bg-light" rows="4" required></textarea>
                    </div>

                    <div class="mb-3 p-3 border rounded bg-warning bg-opacity-10 text-center">
                        <label class="form-label fw-bold small text-dark"><i class="bi bi-camera"></i> ANEXAR FOTOS / EVIDÊNCIAS</label>
                        <input type="file" name="foto_conclusao[]" class="form-control" multiple>
                    </div>

                </div>

                <div class="card-footer bg-light p-3 d-flex justify-content-between gap-2">
                    <button type="submit" name="atualizar_chamado" class="btn btn-success btn-lg flex-grow-1 fw-bold shadow">
                        <i class="bi bi-save me-2"></i>SALVAR ATUALIZAÇÃO
                    </button>
                    
                    <?php if ($chamado['status'] == 'Concluído'): ?>
                        <a href="imprimir_os.php?id=<?= $id ?>" target="_blank" class="btn btn-dark btn-lg fw-bold shadow">
                            <i class="bi bi-printer me-2"></i>IMPRIMIR OS
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addTexto(t) {
    let c = document.getElementById('campo_descricao');
    c.value = (c.value == "") ? t : c.value + " " + t;
}
</script>
