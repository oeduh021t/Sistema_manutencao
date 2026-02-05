<?php
include_once 'includes/db.php';

// Proteção: Se não houver ID na URL, volta para a lista
if (!isset($_GET['id'])) {
    echo "<div class='alert alert-danger'>Chamado não especificado.</div>";
    exit;
}

$id = $_GET['id'];
$usuario_id = $_SESSION['usuario_id'];
$nivel = $_SESSION['usuario_nivel'];

// 1. Busca os dados do chamado + Cruzamento com tabelas de TI (Impressoras e Inventário)
// Utilizamos o patrimônio como chave de ligação entre os módulos
$sql_c = "SELECT c.*, e.patrimonio, e.nome as eq_nome, s.nome as setor_nome,
          imp.id as ti_imp_id, imp.ip_rede as ti_imp_ip, imp.nivel_toner, imp.nivel_cilindro, imp.contador_total,
          inv.id as ti_inv_id, inv.ip as ti_inv_ip
          FROM chamados c 
          JOIN equipamentos e ON c.equipamento_id = e.id 
          JOIN setores s ON e.setor_id = s.id 
          LEFT JOIN ti_impressoras imp ON e.patrimonio = imp.patrimonio 
          LEFT JOIN ti_inventario inv ON e.patrimonio = inv.patrimonio
          WHERE c.id = ?";

if ($nivel === 'usuario') {
    $sql_c .= " AND c.usuario_id = ?";
    $stmt = $pdo->prepare($sql_c);
    $stmt->execute([$id, $usuario_id]);
} else {
    $stmt = $pdo->prepare($sql_c);
    $stmt->execute([$id]);
}

$c = $stmt->fetch();

if (!$c) {
    echo "<div class='alert alert-warning'>Chamado não encontrado ou você não tem permissão para visualizá-lo.</div>";
    exit;
}

// 2. Busca o histórico de atualizações (Timeline)
$stmt_hist = $pdo->prepare("SELECT * FROM chamados_historico WHERE chamado_id = ? ORDER BY data_registro DESC");
$stmt_hist->execute([$id]);
$historico = $stmt_hist->fetchAll();
?>

<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="index.php?p=chamados" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
        <h4 class="mb-0">Detalhes do Chamado #<?= str_pad($c['id'], 5, "0", STR_PAD_LEFT) ?></h4>
        <?php
            $badge = 'bg-secondary';
            if($c['status'] == 'Aberto') $badge = 'bg-danger';
            if($c['status'] == 'Em Atendimento') $badge = 'bg-warning text-dark';
            if($c['status'] == 'Concluído') $badge = 'bg-success';
        ?>
        <span class="badge <?= $badge ?> p-2 fs-6"><?= $c['status'] ?></span>
    </div>

    <div class="row">
        <div class="col-md-4">
            
            <?php if ($c['ti_imp_id'] || $c['ti_inv_id']): ?>
            <div class="card shadow-sm mb-3 border-0 border-start border-5 border-info">
                <div class="card-header bg-info text-white fw-bold">
                    <i class="bi bi-cpu"></i> Detalhes Técnicos TI
                </div>
                <div class="card-body py-2">
                    <?php if ($c['ti_imp_id']): ?>
                        <label class="small text-muted d-block text-uppercase mt-2">Endereço IP</label>
                        <p class="fw-bold mb-2">
                            <a href="http://<?= $c['ti_imp_ip'] ?>" target="_blank" class="text-decoration-none">
                                <?= $c['ti_imp_ip'] ?> <i class="bi bi-box-arrow-up-right small"></i>
                            </a>
                        </p>
                        
                        <label class="small text-muted d-block text-uppercase">Níveis de Suprimento</label>
                        <div class="progress mb-1" style="height: 8px;">
                            <div class="progress-bar bg-dark" style="width: <?= $c['nivel_toner'] ?>%"></div>
                        </div>
                        <small class="d-block mb-2">Toner: <b><?= $c['nivel_toner'] ?>%</b> | Cilindro: <b><?= $c['nivel_cilindro'] ?>%</b></small>

                        <label class="small text-muted d-block text-uppercase">Contador</label>
                        <p class="mb-2"><?= number_format($c['contador_total'], 0, ',', '.') ?> págs</p>
                        
                        <a href="index.php?p=ti_impressora_detalhes&id=<?= $c['ti_imp_id'] ?>" class="btn btn-xs btn-outline-primary w-100 mt-2">
                            <i class="bi bi-search"></i> Histórico da Impressora
                        </a>
                    <?php endif; ?>

                    <?php if ($c['ti_inv_id']): ?>
                        <label class="small text-muted d-block text-uppercase mt-2">Endereço IP</label>
                        <p class="fw-bold mb-2"><?= $c['ti_inv_ip'] ?></p>
                        
                        <a href="index.php?p=ti_detalhes&id=<?= $c['ti_inv_id'] ?>" class="btn btn-xs btn-outline-dark w-100 mt-2">
                            <i class="bi bi-laptop"></i> Ver Inventário PC
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-3 border-0">
                <div class="card-header bg-primary text-white fw-bold">Informações do Chamado</div>
                <div class="card-body">
                    <label class="small text-muted d-block text-uppercase">Equipamento</label>
                    <p class="fw-bold mb-3"><?= htmlspecialchars($c['eq_nome']) ?></p>

                    <label class="small text-muted d-block text-uppercase">Patrimônio / Local</label>
                    <p class="mb-3">
                        <span class="badge bg-light text-dark border"><?= $c['patrimonio'] ?></span><br>
                        <small><?= htmlspecialchars($c['setor_nome']) ?></small>
                    </p>

                    <label class="small text-muted d-block text-uppercase">Abertura</label>
                    <p class="mb-0"><?= date('d/m/Y H:i', strtotime($c['data_abertura'])) ?></p>
                </div>
            </div>

            <?php if ($c['status'] == 'Concluído'): ?>
            <div class="card shadow-sm border-0 border-top border-5 border-success">
                <div class="card-header bg-white fw-bold">Resolução Final</div>
                <div class="card-body">
                    <p class="mb-0"><?= nl2br(htmlspecialchars($c['descricao_solucao'])) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold">Acompanhamento do Atendimento</div>
                <div class="card-body">
                    
                    <div class="mb-4">
                        <h6 class="fw-bold text-primary"><i class="bi bi-chat-left-text"></i> Problema Reportado:</h6>
                        <div class="bg-light p-3 rounded border italic">
                            <?= nl2br(htmlspecialchars($c['descricao_problema'])) ?>
                            <?php if($c['foto_abertura']): ?>
                                <div class="mt-2">
                                    <a href="uploads/<?= $c['foto_abertura'] ?>" target="_blank">
                                        <img src="uploads/<?= $c['foto_abertura'] ?>" class="img-thumbnail" style="max-height: 150px;">
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h6 class="fw-bold border-bottom pb-2 mb-3">Linha do Tempo (Histórico)</h6>
                    
                    <?php if (empty($historico)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clock-history fs-1 text-muted"></i>
                            <p class="text-muted mt-2">Aguardando início dos trabalhos pela equipe técnica.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($historico as $h): ?>
                                <div class="list-group-item px-0 py-3 border-bottom">
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($h['tecnico_nome']) ?></span>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($h['data_registro'])) ?></small>
                                    </div>
                                    <div class="badge bg-light text-dark border my-1" style="font-size: 0.7rem;">Status: <?= $h['status_momento'] ?></div>
                                    <p class="mt-2 mb-1 text-secondary"><?= nl2br(htmlspecialchars($h['texto_historico'])) ?></p>
                                    
                                    <?php if ($h['foto_historico']): ?>
                                        <div class="mt-2 text-start">
                                            <a href="uploads/<?= $h['foto_historico'] ?>" target="_blank">
                                                <img src="uploads/<?= $h['foto_historico'] ?>" class="img-thumbnail rounded" style="max-height: 100px;">
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
