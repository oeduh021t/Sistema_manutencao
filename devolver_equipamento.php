<?php
include_once 'includes/db.php';

// 1. Identifica o Almoxarifado
$id_almoxarifado = $pdo->query("SELECT id FROM setores WHERE nome LIKE '%Almoxarifado%' OR nome LIKE '%Estoque%' LIMIT 1")->fetchColumn();

// 2. Equipamento que ESTÁ VOLTANDO (O que estava no conserto/reserva)
$id_voltando = $_GET['id'] ?? null;
$stmt = $pdo->prepare("SELECT e.*, t.nome as tipo_nome FROM equipamentos e JOIN tipos_equipamentos t ON e.tipo_id = t.id WHERE e.id = ?");
$stmt->execute([$id_voltando]);
$eq_voltando = $stmt->fetch();

if (!$eq_voltando) { die("Equipamento não encontrado."); }

// 3. BUSCA INTELIGENTE: Onde existe um equipamento do mesmo TIPO que está ocupando vaga como reserva?
// Procuramos equipamentos que não estão no almoxarifado, mas que vieram de lá (estão ativos em setores)
$stmt_vagas = $pdo->prepare("
    SELECT e.id as reserva_id, e.patrimonio as reserva_pat, s.id as setor_id, s.nome as setor_nome
    FROM equipamentos e
    JOIN setores s ON e.setor_id = s.id
    WHERE e.tipo_id = ? 
    AND e.id != ? 
    AND s.id != ?
    AND e.status = 'Ativo'
    ORDER BY s.nome ASC
");
$stmt_vagas->execute([$eq_voltando['tipo_id'], $id_voltando, $id_almoxarifado]);
$vagas_disponiveis = $stmt_vagas->fetchAll();

if (isset($_POST['confirmar_devolucao'])) {
    $id_reserva_sair = $_POST['reserva_id'];
    $setor_destino = $_POST['setor_id'];
    $tecnico = $_SESSION['usuario_nome'] ?? 'Sistema';

    try {
        $pdo->beginTransaction();

        // A. O Reserva sai do setor e volta para o Almoxarifado como 'Reserva'
        $up1 = $pdo->prepare("UPDATE equipamentos SET setor_id = ?, status = 'Reserva' WHERE id = ?");
        $up1->execute([$id_almoxarifado, $id_reserva_sair]);

        // B. O Consertado volta para o Setor como 'Ativo'
        $up2 = $pdo->prepare("UPDATE equipamentos SET setor_id = ?, status = 'Ativo' WHERE id = ?");
        $up2->execute([$setor_destino, $id_voltando]);

        // C. Registra os Históricos
        $log = $pdo->prepare("INSERT INTO equipamentos_historico (equipamento_id, setor_origem_id, setor_destino_id, status_anterior, status_novo, descricao_log, tecnico_nome) VALUES (?,?,?,?,?,?,?)");
        
        // Log do que volta
        $log->execute([$id_voltando, $id_almoxarifado, $setor_destino, $eq_voltando['status'], 'Ativo', "DEVOLUÇÃO: Retornou ao setor de origem após manutenção.", $tecnico]);
        
        // Log do reserva que sai
        $log->execute([$id_reserva_sair, $setor_destino, $id_almoxarifado, 'Ativo', 'Reserva', "RECOLHIMENTO: Voltou ao estoque pois o titular foi reinstalado.", $tecnico]);

        $pdo->commit();
        echo "<script>alert('Troca de devolução concluída!'); window.location.href='index.php?p=equipamentos';</script>";
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro na devolução: " . $e->getMessage());
    }
}
?>

<div class="container mt-4">
    <div class="card shadow border-0">
        <div class="card-header bg-success text-white py-3">
            <h5 class="mb-0"><i class="bi bi-arrow-down-up me-2"></i> Devolver para Setor e Recolher Reserva</h5>
        </div>
        <div class="card-body bg-light">
            <div class="alert alert-info border-0 shadow-sm">
                <strong>Equipamento pronto:</strong> <?= $eq_voltando['nome'] ?> (Pat: <?= $eq_voltando['patrimonio'] ?>)<br>
                <small>O sistema localizou os seguintes locais onde este tipo de aparelho está sendo substituído por um reserva:</small>
            </div>

            <form method="POST">
                <input type="hidden" name="id_volta" value="<?= $id_voltando ?>">
                
                <div class="list-group shadow-sm mb-4">
                    <?php if (empty($vagas_disponiveis)): ?>
                        <div class="list-group-item text-center py-4">
                            <i class="bi bi-exclamation-circle text-warning fs-2"></i>
                            <p class="mb-0">Não foram encontrados reservas deste tipo (<?= $eq_voltando['tipo_nome'] ?>) em uso nos setores.</p>
                            <small>Se desejar apenas reativar sem trocar, use a função de edição.</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($vagas_disponiveis as $vaga): ?>
                            <label class="list-group-item d-flex justify-content-between align-items-center p-3">
                                <div>
                                    <input class="form-check-input me-3" type="radio" name="reserva_data" 
                                           value="<?= $vaga['reserva_id'] ?>|<?= $vaga['setor_id'] ?>" required 
                                           onclick="document.getElementById('res_id').value='<?= $vaga['reserva_id'] ?>'; document.getElementById('set_id').value='<?= $vaga['setor_id'] ?>';">
                                    <span class="fw-bold text-primary"><?= $vaga['setor_nome'] ?></span>
                                    <div class="small text-muted ms-4">Atualmente com o Reserva Pat: <?= $vaga['reserva_pat'] ?></div>
                                </div>
                                <i class="bi bi-geo-alt-fill text-danger"></i>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <input type="hidden" name="reserva_id" id="res_id">
                <input type="hidden" name="setor_id" id="set_id">

                <div class="d-flex justify-content-between">
                    <a href="index.php?p=equipamentos" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" name="confirmar_devolucao" class="btn btn-success px-5 fw-bold shadow" <?= empty($vagas_disponiveis) ? 'disabled' : '' ?>>
                        EXECUTAR TROCA DE DEVOLUÇÃO
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
