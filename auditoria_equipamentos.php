<?php
include_once 'includes/db.php';

// 1. Busca de dados para os filtros
$tipos = $pdo->query("SELECT * FROM tipos_equipamentos ORDER BY nome ASC")->fetchAll();
$setores = $pdo->query("SELECT * FROM setores ORDER BY nome ASC")->fetchAll();

$tipo_id = $_GET['tipo_id'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01'); // Início do mês atual
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');

// 2. Query para listar os equipamentos disponíveis para seleção
$sql = "SELECT e.id, e.nome, e.patrimonio, e.num_serie, s.nome as setor_nome 
        FROM equipamentos e 
        LEFT JOIN setores s ON e.setor_id = s.id 
        WHERE 1=1";
$params = [];

if ($tipo_id) { 
    $sql .= " AND e.tipo_id = ?"; 
    $params[] = $tipo_id; 
}

$lista_equipamentos = $pdo->prepare($sql);
$lista_equipamentos->execute($params);
$equipamentos = $lista_equipamentos->fetchAll();
?>

<div class="container-fluid text-dark py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-shield-check text-danger"></i> Auditoria de Equipamentos</h2>
        <button type="button" onclick="document.getElementById('form_gerador').submit()" class="btn btn-danger btn-lg shadow fw-bold">
            <i class="bi bi-printer"></i> IMPRIMIR RELATÓRIO DE AUDITORIA
        </button>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white fw-bold">Parâmetros de Busca</div>
        <div class="card-body bg-light">
            <form method="GET" class="row g-3">
                <input type="hidden" name="p" value="auditoria_equipamentos">
                
                <div class="col-md-4">
                    <label class="small fw-bold">Tipo de Equipamento</label>
                    <select name="tipo_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Todos os Tipos --</option>
                        <?php foreach($tipos as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $tipo_id == $t['id'] ? 'selected' : '' ?>><?= $t['nome'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="small fw-bold">Período Início (Histórico)</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= $data_inicio ?>">
                </div>

                <div class="col-md-3">
                    <label class="small fw-bold">Período Fim (Histórico)</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= $data_fim ?>">
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-dark w-100">Atualizar Lista</button>
                </div>
            </form>
        </div>
    </div>

    <form id="form_gerador" action="gerar_pdf_auditoria.php" method="POST" target="_blank">
        <input type="hidden" name="data_inicio" value="<?= $data_inicio ?>">
        <input type="hidden" name="data_fim" value="<?= $data_fim ?>">

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold text-muted">Selecione os itens para o prontuário consolidado</span>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="select_all">
                    <label class="form-check-label fw-bold" for="select_all">Selecionar Todos</label>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50" class="text-center">#</th>
                            <th>Patrimônio</th>
                            <th>Equipamento</th>
                            <th>Nº Série</th>
                            <th>Setor Atual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($equipamentos as $eq): ?>
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" name="selecionados[]" value="<?= $eq['id'] ?>" class="form-check-input check-item">
                            </td>
                            <td class="fw-bold"><?= $eq['patrimonio'] ?></td>
                            <td><?= htmlspecialchars($eq['nome']) ?></td>
                            <td><?= htmlspecialchars($eq['num_serie']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= $eq['setor_nome'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<script>
// Lógica para selecionar todos
document.getElementById('select_all').addEventListener('change', function() {
    document.querySelectorAll('.check-item').forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});
</script>
