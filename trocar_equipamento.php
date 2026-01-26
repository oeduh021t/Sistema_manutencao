<?php
include_once 'includes/db.php';

// 1. Identifica o Almoxarifado automaticamente pelo nome
$id_almoxarifado = $pdo->query("SELECT id FROM setores WHERE nome LIKE '%Almoxarifado%' OR nome LIKE '%Estoque%' LIMIT 1")->fetchColumn();

if (!$id_almoxarifado) {
    echo "<div class='alert alert-danger'>Erro: Setor 'Almoxarifado' não encontrado. Cadastre um setor com este nome primeiro.</div>";
    exit;
}

// 2. Busca o equipamento que será RETIRADO
$id_retirada = $_GET['id'] ?? null;
if (!$id_retirada) {
    echo "Equipamento não especificado.";
    exit;
}

$stmt = $pdo->prepare("SELECT e.*, s.nome as setor_nome FROM equipamentos e JOIN setores s ON e.setor_id = s.id WHERE e.id = ?");
$stmt->execute([$id_retirada]);
$eq_retirada = $stmt->fetch();

// 3. Busca equipamentos disponíveis em RESERVA
$reservas = $pdo->query("SELECT * FROM equipamentos WHERE status = 'Reserva' ORDER BY nome ASC")->fetchAll();

// --- LÓGICA DE EXECUÇÃO DA TROCA ---
if (isset($_POST['confirmar_troca'])) {
    $id_sai = $_POST['id_sai'];
    $id_entra = $_POST['id_entra'];
    $setor_destino_id = $_POST['setor_id']; // Onde o reserva vai entrar
    $motivo = $_POST['motivo_troca'];
    $status_final_sai = $_POST['status_sai'];
    $tecnico_acao = $_SESSION['usuario_nome'] ?? 'Sistema';

    try {
        $pdo->beginTransaction();

        // --- A. PROCESSAR O EQUIPAMENTO QUE SAI (DEFEITUOSO) ---
        // Registrar Histórico de Saída
        $stmt_log_sai = $pdo->prepare("INSERT INTO equipamentos_historico 
            (equipamento_id, setor_origem_id, setor_destino_id, status_anterior, status_novo, descricao_log, tecnico_nome) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_log_sai->execute([
            $id_sai, 
            $eq_retirada['setor_id'], 
            $id_almoxarifado, 
            $eq_retirada['status'], 
            $status_final_sai, 
            "RETIRADA: " . $motivo, 
            $tecnico_acao
        ]);

        // Atualizar Equipamento que sai
        $stmt1 = $pdo->prepare("UPDATE equipamentos SET setor_id = ?, status = ? WHERE id = ?");
        $stmt1->execute([$id_almoxarifado, $status_final_sai, $id_sai]);


        // --- B. PROCESSAR O EQUIPAMENTO QUE ENTRA (RESERVA) ---
        // Buscar dados do reserva antes da troca para o log
        $stmt_res = $pdo->prepare("SELECT * FROM equipamentos WHERE id = ?");
        $stmt_res->execute([$id_entra]);
        $eq_reserva = $stmt_res->fetch();

        // Registrar Histórico de Entrada
        $stmt_log_entra = $pdo->prepare("INSERT INTO equipamentos_historico 
            (equipamento_id, setor_origem_id, setor_destino_id, status_anterior, status_novo, descricao_log, tecnico_nome) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_log_entra->execute([
            $id_entra, 
            $eq_reserva['setor_id'], 
            $setor_destino_id, 
            $eq_reserva['status'], 
            'Ativo', 
            "INSTALAÇÃO: Substituiu o patrimônio " . $eq_retirada['patrimonio'] . " (Motivo: $motivo)", 
            $tecnico_acao
        ]);

        // Atualizar Equipamento que entra
        $stmt2 = $pdo->prepare("UPDATE equipamentos SET setor_id = ?, status = 'Ativo' WHERE id = ?");
        $stmt2->execute([$setor_destino_id, $id_entra]);

        $pdo->commit();
        echo "<script>alert('Troca realizada e históricos atualizados!'); window.location.href='index.php?p=equipamentos';</script>";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='alert alert-danger'>Erro crítico na transação: " . $e->getMessage() . "</div>";
    }
}
?>

<div class="container mt-3">
    <div class="card shadow border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Substituição de Ativo</h5>
            <a href="index.php?p=equipamentos" class="btn btn-sm btn-outline-light">Cancelar</a>
        </div>
        <div class="card-body bg-light">
            <form method="POST">
                <input type="hidden" name="id_sai" value="<?= $eq_retirada['id'] ?>">
                <input type="hidden" name="setor_id" value="<?= $eq_retirada['setor_id'] ?>">

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-white h-100 shadow-sm">
                            <h6 class="text-danger fw-bold border-bottom pb-2 mb-3">1. RETIRAR DO SETOR</h6>
                            <div class="mb-3">
                                <label class="small text-muted d-block">Equipamento Defeituoso:</label>
                                <span class="fw-bold fs-5"><?= $eq_retirada['nome'] ?></span>
                            </div>
                            <div class="mb-3">
                                <label class="small text-muted d-block">Patrimônio / Local Atual:</label>
                                <span class="badge bg-secondary"><?= $eq_retirada['patrimonio'] ?></span>
                                <span class="ms-2"><?= $eq_retirada['setor_nome'] ?></span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Enviar para:</label>
                                <select name="status_sai" class="form-select border-danger" required>
                                    <option value="Em Manutenção">🛠️ Oficina (Em Manutenção)</option>
                                    <option value="Baixado/Quebrado">❌ Descarte (Baixa Definitiva)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Motivo da Substituição:</label>
                                <textarea name="motivo_troca" class="form-control" rows="2" placeholder="Ex: Vazamento de gás interno, requer oficina." required></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-white h-100 shadow-sm border-success">
                            <h6 class="text-success fw-bold border-bottom pb-2 mb-3">2. INSTALAR RESERVA</h6>
                            <label class="form-label fw-bold">Selecione um item do Estoque:</label>
                            
                            <?php if(empty($reservas)): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> Não há equipamentos com status <b>"Reserva"</b> cadastrados.
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach($reservas as $res): ?>
                                        <label class="list-group-item d-flex gap-3 cursor-pointer">
                                            <input class="form-check-input flex-shrink-0" type="radio" name="id_entra" value="<?= $res['id'] ?>" required>
                                            <span>
                                                <strong><?= $res['nome'] ?></strong>
                                                <small class="d-block text-muted">Pat: <?= $res['patrimonio'] ?> | SN: <?= $res['num_serie'] ?></small>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" name="confirmar_troca" class="btn btn-primary btn-lg w-100 shadow fw-bold" 
                            <?= empty($reservas) ? 'disabled' : '' ?>>
                        <i class="bi bi-check2-all me-2"></i>FINALIZAR TROCA E ATUALIZAR SETOR
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .cursor-pointer { cursor: pointer; }
    .list-group-item:hover { background-color: #f8f9fa; }
</style>
