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

// Buscar técnicos internos
$tecnicos_lista = $pdo->query("SELECT nome FROM usuarios WHERE nivel IN ('tecnico', 'admin', 'coordenador') ORDER BY nome ASC")->fetchAll();

// BUSCAR FORNECEDORES (CADASTRO OFICIAL)
$fornecedores_lista = $pdo->query("SELECT id, nome_fantasia FROM fornecedores WHERE status = 'Ativo' ORDER BY nome_fantasia ASC")->fetchAll();

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
    $causa_raiz = $_POST['causa_raiz'] ?? 'Não Informada';
    $tipo_atendimento = $_POST['tipo_atendimento'] ?? 'Interno';
    $nota_fornecedor = $_POST['nota_fornecedor'] ?? 0;
    
    // NOVOS CAMPOS ESTRUTURADOS
    $fornecedor_id = !empty($_POST['fornecedor_id']) ? $_POST['fornecedor_id'] : null;
    $tecnico_externo = !empty($_POST['tecnico_externo_nome']) ? $_POST['tecnico_externo_nome'] : null;
    
    $nf = !empty($_POST['nf_referencia']) ? $_POST['nf_referencia'] : null;
    $custo = !empty($_POST['custo_servico']) ? $_POST['custo_servico'] : 0;

    $data_atual = date('Y-m-d H:i:s');
    $data_conclusao = ($status == 'Concluído') ? $data_atual : null;

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
$stmt = $pdo->prepare("SELECT c.*, e.patrimonio, e.nome as eq_nome FROM chamados c LEFT JOIN equipamentos e ON c.equipamento_id = e.id WHERE c.id = ?");
$stmt->execute([$id]);
$chamado = $stmt->fetch();

if (!$chamado) { echo "Chamado inválido."; exit; }

$caminho_setor = getCaminhoCompletoTratamento($chamado['setor_id'], $setores_mapa);
$logs = $pdo->prepare("SELECT * FROM chamados_historico WHERE chamado_id = ? ORDER BY data_registro DESC");
$logs->execute([$id]);
$logs = $logs->fetchAll();
?>

<div class="container-fluid">
    <?php if(isset($_GET['sucesso'])): ?>
        <div class="alert alert-success mt-3 shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i> Atualização salva com sucesso!</div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3 mt-3 text-dark">
        <div>
            <h3 class="mb-0 fw-bold"><i class="bi bi-wrench-adjustable text-primary"></i> Atendimento #<?= $id ?></h3>
            <span class="badge bg-light text-dark border mt-1"><i class="bi bi-geo-alt text-danger"></i> <?= htmlspecialchars($caminho_setor) ?></span>
            <?php if($chamado['patrimonio']): ?>
                <span class="badge bg-info text-white ms-2 mt-1">Patrimônio: <?= $chamado['patrimonio'] ?></span>
            <?php endif; ?>
        </div>
        <a href="index.php?p=chamados" class="btn btn-outline-secondary btn-sm shadow-sm">Voltar</a>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold">Histórico / Evolução</div>
                <div class="card-body" style="max-height: 750px; overflow-y: auto;">
                    <div class="timeline text-dark">
                        <?php foreach($logs as $l): ?>
                            <div class="border-start border-2 ps-3 pb-3 position-relative mb-2 text-dark">
                                <i class="bi bi-circle-fill position-absolute text-primary" style="left: -7px; top: 0; font-size: 0.8rem;"></i>
                                <div class="small text-muted"><?= date('d/m/H:i', strtotime($l['data_registro'])) ?> - <b><?= $l['status_momento'] ?></b></div>
                                <div class="fw-bold small"><?= $l['tecnico_nome'] ?></div>
                                <div class="bg-light p-2 rounded small border mt-1 shadow-sm">
                                    <?= nl2br($l['texto_historico']) ?>
                                    <?php if(!empty($l['foto_historico'])): ?>
                                        <div class="mt-2 text-center">
                                            <a href="uploads/<?= $l['foto_historico'] ?>" target="_blank">
                                                <img src="uploads/<?= $l['foto_historico'] ?>" class="img-fluid rounded border" style="max-height: 120px;">
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="border-start border-2 ps-3 position-relative text-dark">
                            <i class="bi bi-circle-fill text-danger position-absolute" style="left: -7px; top: 0; font-size: 0.8rem;"></i>
                            <div class="small text-muted"><?= date('d/m/H:i', strtotime($chamado['data_abertura'])) ?></div>
                            <div class="fw-bold text-danger">Abertura do Chamado:</div>
                            <div class="text-muted small"><?= htmlspecialchars($chamado['descricao_problema']) ?></div>
                            <?php if(!empty($chamado['foto_abertura'])): ?>
                                <img src="uploads/<?= $chamado['foto_abertura'] ?>" class="img-fluid mt-2 border rounded shadow-sm" style="max-height: 150px;">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-7 text-dark">
            <form method="POST" enctype="multipart/form-data" class="card shadow-sm border-0">
                <div class="card-header bg-success text-white fw-bold">Tratativa do Chamado</div>
                <div class="card-body">
                    
                    <label class="form-label fw-bold small text-muted text-uppercase">Origem da Mão de Obra</label>
                    <div class="btn-group w-100 mb-4 shadow-sm" role="group">
                        <input type="radio" class="btn-check" name="tipo_atendimento" id="tipoI" value="Interno" <?= ($chamado['tipo_atendimento'] != 'Externo') ? 'checked' : '' ?> onclick="toggleExterno(false)">
                        <label class="btn btn-outline-primary fw-bold" for="tipoI"><i class="bi bi-person-badge"></i> Interno (Equipe Hospital)</label>

                        <input type="radio" class="btn-check" name="tipo_atendimento" id="tipoE" value="Externo" <?= ($chamado['tipo_atendimento'] == 'Externo') ? 'checked' : '' ?> onclick="toggleExterno(true)">
                        <label class="btn btn-outline-danger fw-bold" for="tipoE"><i class="bi bi-truck"></i> Externo (Fornecedor)</label>
                    </div>

                    <div class="row mb-3 text-dark">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Status</label>
                            <select name="status" class="form-select border-2">
                                <option value="Aberto" <?= ($chamado['status'] == 'Aberto') ? 'selected' : '' ?>>Aberto</option>
                                <option value="Em Atendimento" <?= ($chamado['status'] == 'Em Atendimento') ? 'selected' : '' ?>>Em Atendimento</option>
                                <option value="Aguardando Aprovação" <?= ($chamado['status'] == 'Aguardando Aprovação') ? 'selected' : '' ?>>Aguardando Aprovação</option>
                                <option value="Concluído" <?= ($chamado['status'] == 'Concluído') ? 'selected' : '' ?>>Concluído</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Responsável Engenharia</label>
                            <select name="tecnico_responsavel" class="form-select shadow-sm" required>
                                <option value="">-- Selecione --</option>
                                <?php foreach($tecnicos_lista as $tec): ?>
                                    <option value="<?= $tec['nome'] ?>" <?= ($chamado['tecnico_responsavel'] == $tec['nome']) ? 'selected' : '' ?>><?= $tec['nome'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="secao_externa" class="p-3 mb-3 border rounded shadow-sm" style="display: <?= ($chamado['tipo_atendimento'] == 'Externo') ? 'block' : 'none' ?>; background-color: #fff9f9; border-color: #ffc9c9 !important;">
                        <h6 class="text-danger fw-bold small text-uppercase mb-3"><i class="bi bi-cash-stack"></i> Detalhes da Assistência Externa</h6>
                        <div class="row g-2 text-dark">
                            <div class="col-md-6">
                                <label class="small fw-bold">Fornecedor</label>
                                <select name="fornecedor_id" class="form-select">
                                    <option value="">-- Escolha a Empresa --</option>
                                    <?php foreach($fornecedores_lista as $forn): ?>
                                        <option value="<?= $forn['id'] ?>" <?= ($chamado['fornecedor_id'] == $forn['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($forn['nome_fantasia']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Técnico Externo (Nome)</label>
                                <input type="text" name="tecnico_externo_nome" class="form-control" placeholder="Quem executou o serviço?" value="<?= htmlspecialchars($chamado['tecnico_externo_nome'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mt-2">
                                <label class="small fw-bold">NF Referência</label>
                                <input type="text" name="nf_referencia" class="form-control" value="<?= $chamado['nf_referencia'] ?>">
                            </div>
                            <div class="col-md-4 mt-2">
                                <label class="small fw-bold">Custo R$</label>
                                <input type="number" step="0.01" name="custo_servico" class="form-control fw-bold text-danger" value="<?= $chamado['custo_servico'] ?>">
                            </div>
                            <div class="col-md-4 mt-2">
                                <label class="small fw-bold">Avaliação</label>
                                <select name="nota_fornecedor" class="form-select">
                                    <option value="0">Avaliar...</option>
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($chamado['nota_fornecedor'] == $i) ? 'selected' : '' ?>><?= $i ?> Estrelas</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary small text-uppercase"><i class="bi bi-search"></i> Causa Raiz</label>
                        <select name="causa_raiz" class="form-select border-primary shadow-sm" required>
                            <option value="">-- Selecione --</option>
                            <option value="Desgaste Natural" <?= ($chamado['causa_raiz'] == 'Desgaste Natural') ? 'selected' : '' ?>>Desgaste Natural</option>
                            <option value="Mau Uso / Queda" <?= ($chamado['causa_raiz'] == 'Mau Uso / Queda') ? 'selected' : '' ?>>Mau Uso / Queda</option>
                            <option value="Falha na Rede Elétrica" <?= ($chamado['causa_raiz'] == 'Falha na Rede Elétrica') ? 'selected' : '' ?>>Rede Elétrica</option>
                            <option value="Acessório Danificado" <?= ($chamado['causa_raiz'] == 'Acessório Danificado') ? 'selected' : '' ?>>Acessório Danificado</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Descrição da Solução / Atividade</label>
                        <textarea name="descricao_solucao" class="form-control" rows="4" required><?= $chamado['descricao_solucao'] ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Anexar Evidências</label>
                        <input type="file" name="foto_conclusao[]" class="form-control" accept="image/*" multiple>
                    </div>
                </div>
                
                <div class="card-footer bg-light d-flex justify-content-between">
                    <button type="submit" name="atualizar_chamado" class="btn btn-success px-4 fw-bold shadow">
                        <i class="bi bi-save"></i> Registrar
                    </button>
                    <?php if ($chamado['status'] == 'Concluído'): ?>
                        <a href="imprimir_os.php?id=<?= $id ?>" target="_blank" class="btn btn-dark fw-bold">
                            <i class="bi bi-printer"></i> Imprimir OS
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleExterno(show) {
    const secao = document.getElementById('secao_externa');
    secao.style.display = show ? 'block' : 'none';
}
</script>
