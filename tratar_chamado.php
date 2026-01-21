<?php
include_once 'includes/db.php';

// Ativar exibição de erros para diagnóstico
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// --- LÓGICA DE ATUALIZAÇÃO ---
if (isset($_POST['atualizar_chamado'])) {
    try {
        $anotacao = $_POST['descricao_solucao']; 
        $status = $_POST['status'];
        $tecnico = $_POST['tecnico_responsavel'];
        $causa_raiz = $_POST['causa_raiz'] ?? 'Não Informada';
        $tipo_atendimento = $_POST['tipo_atendimento'] ?? 'Interno';
        $nota_fornecedor = $_POST['nota_fornecedor'] ?? 0;
        
        $fornecedor_id = !empty($_POST['fornecedor_id']) ? $_POST['fornecedor_id'] : null;
        $tecnico_externo = !empty($_POST['tecnico_externo_nome']) ? $_POST['tecnico_externo_nome'] : null;
        $nf = !empty($_POST['nf_referencia']) ? $_POST['nf_referencia'] : null;
        $custo = !empty($_POST['custo_servico']) ? $_POST['custo_servico'] : 0;

        $data_atual = date('Y-m-d H:i:s');
        $data_conclusao = ($status == 'Concluído') ? $data_atual : null;

        // Inicia Transação para garantir que tudo salve ou nada salve
        $pdo->beginTransaction();

        $sql = "UPDATE chamados SET 
                descricao_solucao = ?, status = ?, data_conclusao = ?, tecnico_responsavel = ?,
                nf_referencia = ?, custo_servico = ?, causa_raiz = ?,
                tipo_atendimento = ?, nota_fornecedor = ?, fornecedor_id = ?, tecnico_externo_nome = ?
                WHERE id = ?";
        
        $pdo->prepare($sql)->execute([
            $anotacao, $status, $data_conclusao, $tecnico, 
            $nf, $custo, $causa_raiz, $tipo_atendimento, 
            $nota_fornecedor, $fornecedor_id, $tecnico_externo, $id
        ]);

        // Tratar Upload de Fotos e Histórico
        if (!empty($_FILES['foto_conclusao']['name'][0])) {
            foreach ($_FILES['foto_conclusao']['name'] as $key => $name) {
                if ($_FILES['foto_conclusao']['error'][$key] == 0) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $foto_nome = "HIST_" . $id . "_" . time() . "_" . $key . "." . $ext;
                    
                    if (move_uploaded_file($_FILES['foto_conclusao']['tmp_name'][$key], "uploads/" . $foto_nome)) {
                        // Se for a primeira foto e estiver concluindo, salva na capa do chamado
                        if ($key === 0 && $status == 'Concluído') {
                            $pdo->prepare("UPDATE chamados SET foto_conclusao = ? WHERE id = ?")->execute([$foto_nome, $id]);
                        }
                        // Registra no histórico (Garantindo que a coluna foto_historico existe)
                        $sql_hist = "INSERT INTO chamados_historico (chamado_id, tecnico_nome, texto_historico, status_momento, foto_historico, data_registro) VALUES (?, ?, ?, ?, ?, ?)";
                        $pdo->prepare($sql_hist)->execute([$id, $tecnico, ($key === 0 ? $anotacao : "Anexo Adicional"), $status, $foto_nome, $data_atual]);
                    }
                }
            }
        } else {
            // Se não houver fotos, apenas registra o texto no histórico
            if (!empty($anotacao)) {
                $sql_hist = "INSERT INTO chamados_historico (chamado_id, tecnico_nome, texto_historico, status_momento, data_registro) VALUES (?, ?, ?, ?, ?)";
                $pdo->prepare($sql_hist)->execute([$id, $tecnico, $anotacao, $status, $data_atual]);
            }
        }

        $pdo->commit();
        echo "<script>window.location.href='index.php?p=tratar_chamado&id=$id&sucesso=1';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("<div class='alert alert-danger'>ERRO TÉCNICO: " . $e->getMessage() . "</div>");
    }
}

// --- BUSCA DE DADOS PARA EXIBIÇÃO ---
$stmt = $pdo->prepare("SELECT c.*, e.patrimonio, e.nome as eq_nome FROM chamados c LEFT JOIN equipamentos e ON c.equipamento_id = e.id WHERE c.id = ?");
$stmt->execute([$id]);
$chamado = $stmt->fetch();
if (!$chamado) { die("Chamado inválido."); }

$caminho_setor = getCaminhoCompletoTratamento($chamado['setor_id'], $setores_mapa);
$logs = $pdo->prepare("SELECT * FROM chamados_historico WHERE chamado_id = ? ORDER BY data_registro DESC");
$logs->execute([$id]);
$logs = $logs->fetchAll();
?>

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
                <div class="card-header bg-dark text-white fw-bold small">CRONOLOGIA DO ATENDIMENTO</div>
                <div class="card-body" style="max-height: 700px; overflow-y: auto;">
                    <?php foreach($logs as $l): ?>
                        <div class="border-start border-3 border-primary ps-3 pb-3 mb-3 position-relative text-dark">
                            <i class="bi bi-circle-fill text-primary position-absolute" style="left: -9px; top: 0; font-size: 0.8rem;"></i>
                            <small class="text-muted d-block"><?= date('d/m H:i', strtotime($l['data_registro'])) ?> - <b><?= $l['status_momento'] ?></b></small>
                            <div class="fw-bold small"><?= $l['tecnico_nome'] ?></div>
                            <div class="bg-light p-2 rounded small border mt-1"><?= nl2br(htmlspecialchars($l['texto_historico'])) ?></div>
                            <?php if(!empty($l['foto_historico'])): ?>
                                <a href="uploads/<?= $l['foto_historico'] ?>" target="_blank">
                                    <img src="uploads/<?= $l['foto_historico'] ?>" class="img-fluid rounded border mt-2 shadow-sm" style="max-height: 100px;">
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <form method="POST" enctype="multipart/form-data" class="card shadow-sm border-0">
                <div class="card-header bg-success text-white fw-bold small">TRATATIVA TÉCNICA E CONCLUSÃO</div>
                <div class="card-body text-dark">
                    
                    <div class="mb-4 text-center">
                        <label class="form-label d-block fw-bold small text-muted text-uppercase">Origem da Execução</label>
                        <div class="btn-group w-100 shadow-sm">
                            <input type="radio" class="btn-check" name="tipo_atendimento" id="tipoI" value="Interno" <?= ($chamado['tipo_atendimento'] != 'Externo') ? 'checked' : '' ?> onclick="document.getElementById('secao_externa').style.display='none'">
                            <label class="btn btn-outline-primary fw-bold" for="tipoI">EQUIPE INTERNA</label>

                            <input type="radio" class="btn-check" name="tipo_atendimento" id="tipoE" value="Externo" <?= ($chamado['tipo_atendimento'] == 'Externo') ? 'checked' : '' ?> onclick="document.getElementById('secao_externa').style.display='block'">
                            <label class="btn btn-outline-danger fw-bold" for="tipoE">FORNECEDOR / EXTERNO</label>
                        </div>
                    </div>

                    <div id="secao_externa" class="p-3 mb-3 border rounded bg-light" style="display: <?= ($chamado['tipo_atendimento'] == 'Externo') ? 'block' : 'none' ?>;">
                        <div class="row g-2 text-dark">
                            <div class="col-md-6">
                                <label class="small fw-bold">Fornecedor Selecionado</label>
                                <select name="fornecedor_id" class="form-select">
                                    <option value="">-- Escolha --</option>
                                    <?php foreach($fornecedores_lista as $forn): ?>
                                        <option value="<?= $forn['id'] ?>" <?= ($chamado['fornecedor_id'] == $forn['id']) ? 'selected' : '' ?>><?= htmlspecialchars($forn['nome_fantasia']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Técnico Externo (Nome)</label>
                                <input type="text" name="tecnico_externo_nome" class="form-control" value="<?= htmlspecialchars($chamado['tecnico_externo_nome'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">NF Referência</label>
                                <input type="text" name="nf_referencia" class="form-control" value="<?= htmlspecialchars($chamado['nf_referencia'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Custo do Serviço R$</label>
                                <input type="number" step="0.01" name="custo_servico" class="form-control fw-bold text-danger" value="<?= $chamado['custo_servico'] ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Status do Atendimento</label>
                            <select name="status" class="form-select border-2">
                                <option value="Aberto" <?= $chamado['status'] == 'Aberto' ? 'selected' : '' ?>>Aberto</option>
                                <option value="Em Atendimento" <?= $chamado['status'] == 'Em Atendimento' ? 'selected' : '' ?>>Em Atendimento</option>
                                <option value="Concluído" <?= $chamado['status'] == 'Concluído' ? 'selected' : '' ?>>Concluído</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Técnico Responsável</label>
                            <select name="tecnico_responsavel" class="form-select" required>
                                <?php foreach($tecnicos_lista as $tec): ?>
                                    <option value="<?= $tec['nome'] ?>" <?= ($chamado['tecnico_responsavel'] == $tec['nome']) ? 'selected' : '' ?>><?= $tec['nome'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Causa Raiz da Falha</label>
                        <select name="causa_raiz" class="form-select" required>
                            <option value="Desgaste Natural" <?= $chamado['causa_raiz'] == 'Desgaste Natural' ? 'selected' : '' ?>>Desgaste Natural</option>
                            <option value="Mau Uso" <?= $chamado['causa_raiz'] == 'Mau Uso' ? 'selected' : '' ?>>Mau Uso / Queda</option>
                            <option value="Elétrica" <?= $chamado['causa_raiz'] == 'Elétrica' ? 'selected' : '' ?>>Rede Elétrica / Oscilação</option>
                            <option value="Configuração" <?= $chamado['causa_raiz'] == 'Configuração' ? 'selected' : '' ?>>Configuração / Software</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary">Descrição da Solução Aplicada</label>
                        <textarea name="descricao_solucao" class="form-control border-primary" rows="4" required><?= $chamado['descricao_solucao'] ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Anexar Fotos / Evidências (Múltiplas)</label>
                        <input type="file" name="foto_conclusao[]" class="form-control" multiple>
                    </div>
                </div>

                <div class="card-footer bg-light d-flex justify-content-between py-3">
                    <button type="submit" name="atualizar_chamado" class="btn btn-success fw-bold px-5 shadow">
                        <i class="bi bi-save me-2"></i>SALVAR ATUALIZAÇÃO
                    </button>
                    <?php if ($chamado['status'] == 'Concluído'): ?>
                        <a href="imprimir_os.php?id=<?= $id ?>" target="_blank" class="btn btn-dark fw-bold">
                            <i class="bi bi-printer me-2"></i>IMPRIMIR OS
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
