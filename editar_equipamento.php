<?php
include_once 'includes/db.php';

// Proteção: Apenas admin pode editar
if ($_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: index.php?p=equipamentos");
    exit;
}

// 1. Função Auxiliar para montar o nome completo (Breadcrumb) no Select
function getCaminhoCompletoSelect($id, $mapa) {
    if (!isset($mapa[$id])) return "";
    $setor = $mapa[$id];
    if (!empty($setor['setor_pai_id']) && isset($mapa[$setor['setor_pai_id']])) {
        return getCaminhoCompletoSelect($setor['setor_pai_id'], $mapa) . " > " . $setor['nome'];
    }
    return $setor['nome'];
}

$id = $_GET['id'];

// 2. Processar a Atualização
if (isset($_POST['atualizar_equipamento'])) {
    $nome = $_POST['nome'];
    $num_serie = $_POST['num_serie'];
    $patrimonio = $_POST['patrimonio'];
    $tipo_id = $_POST['tipo_id'];
    $setor_id = $_POST['setor_id'];
    $status = $_POST['status'];

    if (!empty($_FILES['foto']['name'])) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto_nome = "EQUIP_" . time() . "." . $ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], "uploads/" . $foto_nome);
        
        $stmt = $pdo->prepare("UPDATE equipamentos SET nome=?, num_serie=?, patrimonio=?, tipo_id=?, setor_id=?, status=?, foto_equipamento=? WHERE id=?");
        $stmt->execute([$nome, $num_serie, $patrimonio, $tipo_id, $setor_id, $status, $foto_nome, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE equipamentos SET nome=?, num_serie=?, patrimonio=?, tipo_id=?, setor_id=?, status=? WHERE id=?");
        $stmt->execute([$nome, $num_serie, $patrimonio, $tipo_id, $setor_id, $status, $id]);
    }
    echo "<div class='alert alert-success mt-3 shadow-sm'>Equipamento atualizado com sucesso!</div>";
}

// 3. Buscar dados atuais
$stmt = $pdo->prepare("SELECT * FROM equipamentos WHERE id = ?");
$stmt->execute([$id]);
$eq = $stmt->fetch();

if (!$eq) { echo "Equipamento não encontrado."; exit; }

// 4. Buscar Setores e Tipos para os Selects
$setores_mapa = $pdo->query("SELECT id, nome, setor_pai_id FROM setores")->fetchAll(PDO::FETCH_UNIQUE);
$tipos = $pdo->query("SELECT * FROM tipos_equipamentos ORDER BY nome ASC")->fetchAll();
?>

<div class="container-fluid mt-3">
    <div class="card shadow border-0">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Ativo: <?= htmlspecialchars($eq['nome']) ?></h5>
            <a href="index.php?p=equipamentos" class="btn btn-sm btn-light fw-bold shadow-sm">Voltar para Lista</a>
        </div>
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Nome do Equipamento</label>
                    <input type="text" name="nome" class="form-control form-control-lg" value="<?= htmlspecialchars($eq['nome']) ?>" placeholder="Ex: Ar Condicionado Split" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Nº de Série</label>
                        <input type="text" name="num_serie" class="form-control" value="<?= htmlspecialchars($eq['num_serie']) ?>" placeholder="Ex: SN123456">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Nº Patrimônio</label>
                        <input type="text" name="patrimonio" class="form-control font-monospace" value="<?= htmlspecialchars($eq['patrimonio']) ?>" placeholder="Ex: HSP-001" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Tipo de Equipamento</label>
                        <select name="tipo_id" class="form-select" required>
                            <?php foreach($tipos as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($t['id'] == $eq['tipo_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Localização (Setor)</label>
                        <select name="setor_id" class="form-select" required>
                            <?php
                            $lista_localizacoes = [];
                            foreach ($setores_mapa as $sid => $s) {
                                $lista_localizacoes[$sid] = getCaminhoCompletoSelect($sid, $setores_mapa);
                            }
                            asort($lista_localizacoes);

                            foreach ($lista_localizacoes as $sid => $caminho_total):
                                $stmt_filho = $pdo->prepare("SELECT COUNT(*) FROM setores WHERE setor_pai_id = ?");
                                $stmt_filho->execute([$sid]);
                                $tem_filhos = $stmt_filho->fetchColumn() > 0;

                                $niveis = explode(" > ", $caminho_total);
                                $recuo = str_repeat("&nbsp;&nbsp;", (count($niveis) - 1) * 2);
                                $simbolo = (count($niveis) > 1) ? "└─ " : "";
                                $selected = ($sid == $eq['setor_id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $sid ?>" <?= $selected ?> <?= ($tem_filhos && $sid != $eq['setor_id']) ? 'disabled style="background:#f8f9fa; font-weight:bold; color:#0d6efd;"' : '' ?>>
                                    <?= $recuo . $simbolo . $caminho_total ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Status Atual</label>
                        <select name="status" class="form-select">
                            <option value="Ativo" <?= $eq['status'] == 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="Em Manutenção" <?= $eq['status'] == 'Em Manutenção' ? 'selected' : '' ?>>Em Manutenção</option>
                            <option value="Inativo" <?= $eq['status'] == 'Inativo' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3 border-top pt-4 mt-2">
                    <label class="form-label fw-bold d-block">Foto do Equipamento</label>
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <?php if($eq['foto_equipamento']): ?>
                                <img src="uploads/<?= $eq['foto_equipamento'] ?>" class="img-thumbnail shadow-sm mb-2 w-100" style="max-height: 200px; object-fit: contain;">
                            <?php else: ?>
                                <div class="bg-light border rounded text-center py-4 text-muted mb-2">Sem foto cadastrada</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <input type="file" name="foto" class="form-control mb-2" accept="image/*">
                            <small class="text-muted"><i class="bi bi-info-circle me-1"></i> Selecione um novo arquivo se desejar substituir a foto atual por uma mais recente.</small>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" name="atualizar_equipamento" class="btn btn-success btn-lg px-5 fw-bold shadow">
                        <i class="bi bi-check-lg me-2"></i>Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
