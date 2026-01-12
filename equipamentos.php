<?php
include_once 'includes/db.php';

$nivel_logado = $_SESSION['usuario_nivel'];

// --- LÓGICA DE FILTRO ---
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$filtro_sql = "";
$params = [];

if (!empty($busca)) {
    $filtro_sql = " WHERE (e.nome LIKE ? OR e.patrimonio LIKE ? OR e.num_serie LIKE ? OR s.nome LIKE ? OR t.nome LIKE ? OR e.status LIKE ?)";
    $term = "%$busca%";
    $params = [$term, $term, $term, $term, $term, $term];
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

// 2. Processar Cadastro
if (isset($_POST['salvar_equipamento'])) {
    $patrimonio = $_POST['patrimonio'];
    $num_serie = $_POST['num_serie'];
    $nome = $_POST['nome'];
    $tipo_id = $_POST['tipo_id'];
    $setor_id = $_POST['setor_id'];
    $status_ini = $_POST['status_inicial'] ?? 'Ativo';

    $foto_nome = null;
    if (!empty($_FILES['foto']['name'])) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto_nome = "EQUIP_" . time() . "." . $ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], "uploads/" . $foto_nome);
    }

    $stmt = $pdo->prepare("INSERT INTO equipamentos (patrimonio, num_serie, nome, tipo_id, setor_id, foto_equipamento, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$patrimonio, $num_serie, $nome, $tipo_id, $setor_id, $foto_nome, $status_ini]);
    
    echo "<div class='alert alert-success mt-3 shadow-sm'>Equipamento '<strong>$nome</strong>' cadastrado com sucesso!</div>";
}

// 3. Buscar Equipamentos com Filtro
$sql = "
    SELECT e.*, s.nome as setor_nome, t.nome as tipo_nome 
    FROM equipamentos e 
    LEFT JOIN setores s ON e.setor_id = s.id 
    LEFT JOIN tipos_equipamentos t ON e.tipo_id = t.id
    $filtro_sql
    ORDER BY e.id DESC
";
$stmt_eq = $pdo->prepare($sql);
$stmt_eq->execute($params);
$equipamentos = $stmt_eq->fetchAll();

// 4. Buscar Setores para o Select
$setores_mapa = $pdo->query("SELECT id, nome, setor_pai_id FROM setores")->fetchAll(PDO::FETCH_UNIQUE);
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <h2><i class="bi bi-pc-display text-primary"></i> Gestão de Equipamentos</h2>
    <button class="btn btn-primary shadow" data-bs-toggle="modal" data-bs-target="#modalEquipamento">
        <i class="bi bi-plus-circle"></i> Novo Equipamento
    </button>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body bg-light rounded">
        <form method="GET" action="index.php" class="row g-2">
            <input type="hidden" name="p" value="equipamentos">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="busca" class="form-control border-start-0 shadow-none" placeholder="Buscar..." value="<?= htmlspecialchars($busca) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100 fw-bold">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary">
                    <tr>
                        <th class="ps-4">Equipamento</th>
                        <th>Nº Série / Patrimônio</th>
                        <th>Tipo</th>
                        <th>Setor</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipamentos as $e): ?>
                    <tr>
                        <td class="ps-4"><strong><?= htmlspecialchars($e['nome']) ?></strong></td>
                        <td>
                            <small class="text-muted d-block">SN: <?= htmlspecialchars($e['num_serie']) ?: '---' ?></small>
                            <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($e['patrimonio']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($e['tipo_nome']) ?></td>
                        <td><small class="fw-bold text-primary"><?= htmlspecialchars($e['setor_nome']) ?></small></td>
                        <td>
                            <?php 
                                $status_class = 'bg-secondary';
                                if($e['status'] == 'Ativo') $status_class = 'bg-success';
                                if($e['status'] == 'Em Manutenção') $status_class = 'bg-warning text-dark';
                                if($e['status'] == 'Reserva') $status_class = 'bg-info text-white';
                            ?>
                            <span class="badge <?= $status_class ?>"><?= $e['status'] ?></span>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group shadow-sm">
                                <?php if ($e['status'] == 'Reserva' || $e['status'] == 'Em Manutenção'): ?>
                                    <a href="index.php?p=devolver_equipamento&id=<?= $e['id'] ?>" class="btn btn-sm btn-success" title="Devolver ao Setor"><i class="bi bi-arrow-down-up"></i></a>
                                <?php endif; ?>
                                <?php if ($e['status'] == 'Ativo'): ?>
                                    <a href="index.php?p=trocar_equipamento&id=<?= $e['id'] ?>" class="btn btn-sm btn-warning" title="Retirar e Substituir"><i class="bi bi-arrow-left-right"></i></a>
                                <?php endif; ?>
                                <a href="relatorio_equipamento.php?id=<?= $e['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-file-earmark-medical"></i></a>
                                <a href="index.php?p=historico_equipamento&id=<?= $e['id'] ?>" class="btn btn-sm btn-info text-white"><i class="bi bi-clock-history"></i></a>
                                <?php if (in_array($nivel_logado, ['admin', 'coordenador'])): ?>
                                    <a href="index.php?p=editar_equipamento&id=<?= $e['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="index.php?p=excluir_equipamento&id=<?= $e['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir?')"><i class="bi bi-trash"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEquipamento" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow" method="POST" enctype="multipart/form-data">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-tag"></i> Cadastrar Ativo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Nome do Ativo</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label fw-bold">Nº de Série</label><input type="text" name="num_serie" class="form-control"></div>
                    <div class="col-md-6 mb-3"><label class="form-label fw-bold">Patrimônio</label><input type="text" name="patrimonio" class="form-control" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Tipo</label>
                        <select name="tipo_id" class="form-select" required>
                            <option value="">-- Selecione --</option>
                            <?php
                            $tipos = $pdo->query("SELECT * FROM tipos_equipamentos ORDER BY nome ASC")->fetchAll();
                            foreach($tipos as $t) echo "<option value='{$t['id']}'>{$t['nome']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Status Inicial</label>
                        <select name="status_inicial" class="form-select">
                            <option value="Ativo">Ativo (Em uso)</option>
                            <option value="Reserva" selected>Reserva (Estoque)</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Localização</label>
                    <input type="text" id="filtro_setor" class="form-control form-control-sm mb-1" placeholder="🔍 Digite para pesquisar o setor...">
                    
                    <select name="setor_id" id="setor_id_equip" class="form-select" size="8" required>
                        <?php
                        $lista_locais = [];
                        foreach ($setores_mapa as $sid => $s) {
                            $lista_locais[$sid] = getCaminhoCompletoSelect($sid, $setores_mapa);
                        }
                        asort($lista_locais);
                        foreach ($lista_locais as $sid => $caminho):
                            $stmt_f = $pdo->prepare("SELECT COUNT(*) FROM setores WHERE setor_pai_id = ?");
                            $stmt_f->execute([$sid]);
                            $eh_pai = $stmt_f->fetchColumn() > 0;
                            
                            $niveis = explode(" > ", $caminho);
                            $recuo = str_repeat("  ", (count($niveis) - 1) * 2);
                        ?>
                            <option value="<?= $sid ?>" <?= $eh_pai ? 'style="font-weight:bold; background-color:#f8f9fa;"' : '' ?>>
                                <?= $recuo . ($eh_pai ? "■ " : "└─ ") . $caminho ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Todos os níveis são selecionáveis.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Foto</label>
                    <input type="file" name="foto" class="form-control" accept="image/*">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="submit" name="salvar_equipamento" class="btn btn-primary w-100 shadow">Salvar Equipamento</button>
            </div>
        </form>
    </div>
</div>

<script>
// Lógica de Busca Nativa (Sem bibliotecas externas para não dar erro)
document.getElementById('filtro_setor').addEventListener('input', function() {
    let termo = this.value.toLowerCase();
    let options = document.getElementById('setor_id_equip').options;
    
    for (let i = 0; i < options.length; i++) {
        let texto = options[i].text.toLowerCase();
        options[i].style.display = texto.includes(termo) ? "" : "none";
    }
});
</script>
