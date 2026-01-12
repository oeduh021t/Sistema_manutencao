<?php
include_once 'includes/db.php';

// 1. Mapa de setores para construir a hierarquia
$setores_mapa = $pdo->query("SELECT id, nome, setor_pai_id FROM setores")->fetchAll(PDO::FETCH_UNIQUE);

// Função para montar o caminho completo do setor
function getCaminhoCompletoTratamento($id, $mapa) {
    if (!isset($mapa[$id])) return "Setor não identificado";
    $setor = $mapa[$id];
    if (!empty($setor['setor_pai_id']) && isset($mapa[$setor['setor_pai_id']])) {
        return getCaminhoCompletoTratamento($setor['setor_pai_id'], $mapa) . " > " . $setor['nome'];
    }
    return $setor['nome'];
}

// Buscar técnicos para a lista suspensa
$tecnicos_lista = $pdo->query("SELECT nome FROM usuarios WHERE nivel IN ('tecnico', 'admin', 'coordenador') ORDER BY nome ASC")->fetchAll();

if (!isset($_GET['id'])) {
    echo "Chamado não especificado.";
    exit;
}
$id = $_GET['id'];

// --- LÓGICA DE ATUALIZAÇÃO E HISTÓRICO ---
if (isset($_POST['atualizar_chamado'])) {
    $anotacao = $_POST['descricao_solucao']; 
    $status = $_POST['status'];
    $tecnico = $_POST['tecnico_responsavel'];
    
    $empresa = !empty($_POST['empresa_terceirizada']) ? $_POST['empresa_terceirizada'] : null;
    $nf = !empty($_POST['nf_referencia']) ? $_POST['nf_referencia'] : null;
    $custo = !empty($_POST['custo_servico']) ? $_POST['custo_servico'] : 0;

    $data_atual = date('Y-m-d H:i:s');
    $data_conclusao = ($status == 'Concluído') ? $data_atual : null;

    $sql = "UPDATE chamados SET 
            descricao_solucao = ?, 
            status = ?, 
            data_conclusao = ?, 
            tecnico_responsavel = ?,
            empresa_terceirizada = ?, 
            nf_referencia = ?, 
            custo_servico = ?
            WHERE id = ?";
    $pdo->prepare($sql)->execute([$anotacao, $status, $data_conclusao, $tecnico, $empresa, $nf, $custo, $id]);

    if (!empty($_FILES['foto_conclusao']['name'][0])) {
        foreach ($_FILES['foto_conclusao']['name'] as $key => $name) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $foto_nome = "HIST_" . $id . "_" . time() . "_" . $key . "." . $ext;
            
            if (move_uploaded_file($_FILES['foto_conclusao']['tmp_name'][$key], "uploads/" . $foto_nome)) {
                if ($key === 0 && $status == 'Concluído') {
                    $pdo->prepare("UPDATE chamados SET foto_conclusao = ? WHERE id = ?")->execute([$foto_nome, $id]);
                }
                $sql_hist = "INSERT INTO chamados_historico (chamado_id, tecnico_nome, texto_historico, status_momento, foto_historico, data_registro) VALUES (?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql_hist)->execute([$id, $tecnico, ($key === 0 ? $anotacao : "Foto adicional"), $status, $foto_nome, $data_atual]);
            }
        }
    } else {
        if (!empty($anotacao)) {
            $sql_hist = "INSERT INTO chamados_historico (chamado_id, tecnico_nome, texto_historico, status_momento, data_registro) VALUES (?, ?, ?, ?, ?)";
            $pdo->prepare($sql_hist)->execute([$id, $tecnico, $anotacao, $status, $data_atual]);
        }
    }

    header("Location: index.php?p=tratar_chamado&id=$id&sucesso=1");
    exit;
}

// --- BUSCA DE DADOS ---
$stmt = $pdo->prepare("
    SELECT c.*, e.patrimonio, e.nome as eq_nome
    FROM chamados c
    LEFT JOIN equipamentos e ON c.equipamento_id = e.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$chamado = $stmt->fetch();

if (!$chamado) { echo "Chamado inválido."; exit; }

// Gerar o caminho completo do setor usando a função recursiva
$caminho_setor = getCaminhoCompletoTratamento($chamado['setor_id'], $setores_mapa);

$historico = $pdo->prepare("SELECT * FROM chamados_historico WHERE chamado_id = ? ORDER BY data_registro DESC");
$historico->execute([$id]);
$logs = $historico->fetchAll();
?>

<div class="container-fluid">
    <?php if(isset($_GET['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show mt-3 shadow-sm">
            <i class="bi bi-check-circle-fill me-2"></i> Atualização registrada e status atualizado!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
        <div>
            <h3 class="mb-0"><i class="bi bi-wrench-adjustable text-primary"></i> Atendimento #<?= $id ?></h3>
            <div class="mt-1">
                <span class="badge bg-light text-dark border shadow-sm" style="font-size: 0.9rem;">
                    <i class="bi bi-geo-alt-fill text-danger"></i> <?= htmlspecialchars($caminho_setor) ?>
                </span>
                <?php if($chamado['patrimonio']): ?>
                    <span class="badge bg-info text-white ms-2" style="font-size: 0.9rem;">
                        <i class="bi bi-cpu"></i> Ativo: <?= $chamado['patrimonio'] ?> - <?= $chamado['eq_nome'] ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <a href="index.php?p=chamados" class="btn btn-outline-secondary btn-sm shadow-sm">Voltar</a>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-dark text-white fw-bold">Histórico de Atendimento</div>
                <div class="card-body" style="max-height: 700px; overflow-y: auto;">
                    <div class="timeline">
                        <?php foreach($logs as $l): ?>
                            <div class="border-start border-2 ps-3 pb-3 position-relative mb-2">
                                <i class="bi bi-circle-fill position-absolute text-primary" style="left: -7px; top: 0; font-size: 0.8rem;"></i>
                                <div class="small text-muted">
                                    <?= (!empty($l['data_registro']) && $l['data_registro'] != '0000-00-00 00:00:00') ? date('d/m/Y H:i', strtotime($l['data_registro'])) : 'Data n/d' ?> 
                                    - <b><?= $l['status_momento'] ?></b>
                                </div>
                                <div class="fw-bold small text-dark"><?= $l['tecnico_nome'] ?></div>
                                <div class="bg-light p-2 rounded small border mt-1 shadow-sm">
                                    <?= nl2br($l['texto_historico']) ?>
                                    <?php if($l['foto_historico']): ?>
                                        <div class="mt-2 text-center">
                                            <a href="uploads/<?= $l['foto_historico'] ?>" target="_blank">
                                                <img src="uploads/<?= $l['foto_historico'] ?>" class="img-fluid rounded border" style="max-height: 120px;">
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="border-start border-2 ps-3 position-relative">
                            <i class="bi bi-circle-fill text-danger position-absolute" style="left: -7px; top: 0; font-size: 0.8rem;"></i>
                            <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($chamado['data_abertura'])) ?></div>
                            <div class="fw-bold">Chamado Iniciado</div>
                            <div class="text-muted small"><?= $chamado['descricao_problema'] ?></div>
                            <?php if($chamado['foto_abertura']): ?>
                                <img src="uploads/<?= $chamado['foto_abertura'] ?>" class="img-fluid mt-2 border rounded" style="max-height: 150px;">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <form method="POST" enctype="multipart/form-data" class="card shadow-sm border-0">
                <div class="card-header bg-success text-white fw-bold">Atualizar Status e Registrar Atividade</div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Novo Status</label>
                            <select name="status" class="form-select shadow-sm">
                                <option value="Aberto" <?= ($chamado['status'] == 'Aberto') ? 'selected' : '' ?>>Aberto</option>
                                <option value="Em Atendimento" <?= ($chamado['status'] == 'Em Atendimento') ? 'selected' : '' ?>>Em Atendimento</option>
                                <option value="Concluído" <?= ($chamado['status'] == 'Concluído') ? 'selected' : '' ?>>Concluído</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Técnico Responsável</label>
                            <select name="tecnico_responsavel" class="form-select shadow-sm" required>
                                <option value="">-- Selecione o Técnico --</option>
                                <?php foreach($tecnicos_lista as $tec): ?>
                                    <option value="<?= $tec['nome'] ?>" <?= ($chamado['tecnico_responsavel'] == $tec['nome']) ? 'selected' : '' ?>>
                                        <?= $tec['nome'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="p-3 mb-3 border rounded bg-light shadow-sm">
                        <h6 class="text-muted border-bottom pb-2 mb-3 fw-bold small uppercase"><i class="bi bi-building"></i> Custos / Terceiros (Opcional)</h6>
                        <div class="row">
                            <div class="col-md-12 mb-2">
                                <label class="form-label small fw-bold">Empresa</label>
                                <input type="text" name="empresa_terceirizada" class="form-control form-control-sm" value="<?= $chamado['empresa_terceirizada'] ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nota Fiscal</label>
                                <input type="text" name="nf_referencia" class="form-control form-control-sm" value="<?= $chamado['nf_referencia'] ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Custo R$</label>
                                <input type="number" step="0.01" name="custo_servico" class="form-control form-control-sm" value="<?= $chamado['custo_servico'] ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Relatório de Atividade / Solução</label>
                        <textarea name="descricao_solucao" class="form-control shadow-sm" rows="4" placeholder="O que foi feito?" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Anexar Fotos</label>
                        <input type="file" name="foto_conclusao[]" class="form-control shadow-sm" accept="image/*" multiple>
                    </div>
                </div>
                <div class="card-footer bg-light d-flex justify-content-between">
                    <?php if ($chamado['status'] == 'Concluído'): ?>
                        <a href="imprimir_os.php?id=<?= $id ?>" target="_blank" class="btn btn-dark shadow-sm fw-bold">
                            <i class="bi bi-printer"></i> Imprimir OS
                        </a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    
                    <button type="submit" name="atualizar_chamado" class="btn btn-success px-4 fw-bold shadow">
                        <i class="bi bi-save"></i> Registrar Atualização
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
