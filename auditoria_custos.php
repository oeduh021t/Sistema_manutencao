<?php
include_once 'includes/db.php';

// --- 1. CONFIGURAÇÃO DE HIERARQUIA DE SETORES ---
// Buscamos todos os setores para montar o mapa de nomes completos
$setores_mapa = $pdo->query("SELECT id, nome, setor_pai_id FROM setores")->fetchAll(PDO::FETCH_UNIQUE);

// Função recursiva para montar o nome: "Pai > Filho > Neto"
function getCaminhoSetorAuditoria($id, $mapa) {
    if (!isset($mapa[$id])) return "Não identificado";
    $setor = $mapa[$id];
    if (!empty($setor['setor_pai_id']) && isset($mapa[$setor['setor_pai_id']])) {
        return getCaminhoSetorAuditoria($setor['setor_pai_id'], $mapa) . " > " . $setor['nome'];
    }
    return $setor['nome'];
}

// --- 2. CAPTURAR FILTROS ---
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim    = $_GET['data_fim']    ?? date('Y-m-t');
$setor_filtro = $_GET['setor_id']   ?? '';

// Preparação da Query Base
$where_base = " WHERE c.status = 'Concluído' AND c.data_abertura BETWEEN ? AND ? ";
$params = [$data_inicio . ' 00:00:00', $data_fim . ' 23:59:59'];

if ($setor_filtro != '') {
    $where_base .= " AND c.setor_id = ? ";
    $params[] = $setor_filtro;
}

// --- 3. CONSULTAS PARA AS TABELAS ---

// A. Custo por Equipamento
$sql_equipamentos = "SELECT e.nome, e.patrimonio, SUM(c.custo_servico) as total 
                     FROM chamados c 
                     JOIN equipamentos e ON c.equipamento_id = e.id 
                     $where_base
                     GROUP BY e.id ORDER BY total DESC LIMIT 15";
$stmt_eq = $pdo->prepare($sql_equipamentos);
$stmt_eq->execute($params);
$custo_equipamento = $stmt_eq->fetchAll();

// B. Custo de Infraestrutura por Setor (Sem Equipamento)
$sql_setores_infra = "SELECT c.setor_id, SUM(c.custo_servico) as total 
                      FROM chamados c 
                      $where_base AND (c.equipamento_id IS NULL OR c.equipamento_id = 0)
                      GROUP BY c.setor_id ORDER BY total DESC";
$stmt_infra = $pdo->prepare($sql_setores_infra);
$stmt_infra->execute($params);
$custo_setor_infra = $stmt_infra->fetchAll();

// C. Custo por Fornecedor
$sql_fornecedores = "SELECT f.nome_fantasia, SUM(c.custo_servico) as total 
                     FROM chamados c 
                     JOIN fornecedores f ON c.fornecedor_id = f.id 
                     $where_base
                     GROUP BY f.id ORDER BY total DESC";
$stmt_forn = $pdo->prepare($sql_fornecedores);
$stmt_forn->execute($params);
$custo_fornecedor = $stmt_forn->fetchAll();
?>

<div class="container-fluid py-3 text-dark">
    
    <div class="card shadow-sm border-0 mb-4 d-print-none bg-light text-dark">
        <div class="card-body">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="p" value="auditoria_custos">
                
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Data Inicial</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= $data_inicio ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Data Final</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= $data_fim ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Filtrar Setor (Hierarquia Completa)</label>
                    <select name="setor_id" class="form-select">
                        <option value="">Todos os Setores</option>
                        <?php 
                        // Gerar lista ordenada de setores com caminho completo para o Select
                        $lista_ordenada = [];
                        foreach($setores_mapa as $sid => $sdata) {
                            $lista_ordenada[$sid] = getCaminhoSetorAuditoria($sid, $setores_mapa);
                        }
                        asort($lista_ordenada);
                        foreach($lista_ordenada as $id_s => $nome_s): ?>
                            <option value="<?= $id_s ?>" <?= $setor_filtro == $id_s ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nome_s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 text-dark">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="bi bi-filter"></i> FILTRAR
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3 text-dark">
        <div>
            <h3 class="fw-bold mb-0 text-dark"><i class="bi bi-cash-stack text-success me-2"></i>Auditoria Financeira</h3>
            <span class="text-muted small">RELATÓRIO DE CUSTOS POR UNIDADE E ATIVOS</span>
        </div>
        <button onclick="window.print()" class="btn btn-dark d-print-none fw-bold">
            <i class="bi bi-printer me-2"></i>IMPRIMIR PDF
        </button>
    </div>

    <div class="card shadow-sm border-0 mb-4 text-dark">
        <div class="card-header bg-primary text-white fw-bold small text-uppercase">Custos Acumulados por Equipamento</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0 small text-dark">
                <thead class="table-light">
                    <tr><th class="ps-4">Patrimônio</th><th>Equipamento</th><th class="text-end pe-4">Total Gasto</th></tr>
                </thead>
                <tbody>
                    <?php foreach($custo_equipamento as $ce): ?>
                    <tr>
                        <td class="ps-4 fw-bold"><?= $ce['patrimonio'] ?></td>
                        <td><?= htmlspecialchars($ce['nome']) ?></td>
                        <td class="text-end pe-4 fw-bold text-danger">R$ <?= number_format($ce['total'], 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7 text-dark">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-info text-white fw-bold small text-uppercase">Infraestrutura do Setor (Sem Ativos)</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 small text-dark">
                        <thead class="table-light">
                            <tr><th class="ps-4">Unidade / Setor</th><th class="text-end pe-4">Custo</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($custo_setor_infra as $cs): 
                                $nome_completo = getCaminhoSetorAuditoria($cs['setor_id'], $setores_mapa);
                            ?>
                            <tr>
                                <td class="ps-4 text-dark"><?= htmlspecialchars($nome_completo) ?></td>
                                <td class="text-end pe-4 fw-bold text-dark">R$ <?= number_format($cs['total'], 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card shadow-sm border-0 mb-4 text-dark">
                <div class="card-header bg-dark text-white fw-bold small text-uppercase">Pagamentos a Fornecedores</div>
                <div class="card-body p-0 text-dark">
                    <table class="table table-hover mb-0 small text-dark">
                        <thead class="table-light">
                            <tr><th class="ps-4 text-dark">Fornecedor</th><th class="text-end pe-4 text-dark">Pago</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($custo_fornecedor as $cf): ?>
                            <tr>
                                <td class="ps-4 text-dark"><?= htmlspecialchars($cf['nome_fantasia']) ?></td>
                                <td class="text-end pe-4 fw-bold text-primary">R$ <?= number_format($cf['total'], 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    #sidebar, .d-print-none { display: none !important; }
    body, #content { width: 100% !important; margin: 0 !important; padding: 0 !important; background: white; color: black; }
    .card { border: 1px solid #ccc !important; box-shadow: none !important; margin-bottom: 20px !important; }
    .card-header { background-color: #f8f9fa !important; color: black !important; border-bottom: 2px solid #000 !important; font-weight: bold; }
}
</style>
