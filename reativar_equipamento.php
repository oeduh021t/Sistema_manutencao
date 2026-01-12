<?php
include_once 'includes/db.php';

$id = $_GET['id'] ?? null;
if (!$id) { die("Equipamento não especificado."); }

// Busca dados atuais do equipamento
$stmt = $pdo->prepare("SELECT e.*, s.nome as setor_nome FROM equipamentos e LEFT JOIN setores s ON e.setor_id = s.id WHERE e.id = ?");
$stmt->execute([$id]);
$eq = $stmt->fetch();

// Busca todos os setores para caso queira mudar o destino na reativação
$setores = $pdo->query("SELECT * FROM setores ORDER BY nome ASC")->fetchAll();

if (isset($_POST['confirmar_reativacao'])) {
    $novo_setor_id = $_POST['setor_id'];
    $tecnico_acao = $_SESSION['usuario_nome'] ?? 'Sistema';

    try {
        $pdo->beginTransaction();

        // 1. Grava no Histórico a Reativação
        $stmt_log = $pdo->prepare("INSERT INTO equipamentos_historico 
            (equipamento_id, setor_origem_id, setor_destino_id, status_anterior, status_novo, descricao_log, tecnico_nome) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt_log->execute([
            $id, 
            $eq['setor_id'], // Onde ele está agora (Almoxarifado/Oficina)
            $novo_setor_id, 
            $eq['status'], 
            'Ativo', 
            "REATIVAÇÃO: Equipamento retornou da manutenção/reserva para operação ativa.", 
            $tecnico_acao
        ]);

        // 2. Atualiza o equipamento
        $up = $pdo->prepare("UPDATE equipamentos SET setor_id = ?, status = 'Ativo' WHERE id = ?");
        $up->execute([$novo_setor_id, $id]);

        $pdo->commit();
        echo "<script>alert('Equipamento reativado com sucesso!'); window.location.href='index.php?p=equipamentos';</script>";
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erro ao reativar: " . $e->getMessage());
    }
}
?>

<div class="container mt-4">
    <div class="card shadow border-0">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i> Reativar Equipamento</h5>
        </div>
        <div class="card-body bg-light">
            <form method="POST">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p>Você está reativando o patrimônio: <strong><?= $eq['patrimonio'] ?> - <?= $eq['nome'] ?></strong></p>
                        <p class="text-muted small">Status Atual: <span class="badge bg-warning text-dark"><?= $eq['status'] ?></span></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Destinar para o Setor:</label>
                        <select name="setor_id" class="form-select" required>
                            <?php foreach($setores as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['id'] == $eq['setor_id'] ? 'selected' : '' ?>>
                                    <?= $s['nome'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <a href="index.php?p=equipamentos" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" name="confirmar_reativacao" class="btn btn-success fw-bold">
                        EFETIVAR RETORNO AO USO
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
