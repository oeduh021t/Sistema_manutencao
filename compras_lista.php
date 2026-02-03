<?php
// compras_lista.php
if (!isset($_SESSION['usuario_id'])) { die("Acesso negado."); }

$usuario_id = $_SESSION['usuario_id'];
$nivel_acesso = $_SESSION['usuario_nivel'] ?? 'comum'; // Ex: comum, financeiro, diretoria

// Consulta para buscar as solicitações
$sql = "SELECT c.*, e.nome as nome_equipamento, u.nome as nome_solicitante 
        FROM solicitacoes_compra c
        LEFT JOIN equipamentos e ON c.equipamento_id = e.id
        JOIN usuarios u ON c.solicitante_id = u.id
        ORDER BY c.data_solicitacao DESC";
$stmt = $pdo->query($sql);
$compras = $stmt->fetchAll();
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-dark"><i class="bi bi-cart-check-fill text-primary"></i> Gestão de Compras</h2>
        <a href="index.php?p=compras_nova" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Nova Solicitação
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Data</th>
                            <th>Item</th>
                            <th>Equipamento</th>
                            <th>Solicitante</th>
                            <th>Urgência</th>
                            <th>Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compras as $c): ?>
                            <tr>
                                <td>#<?= $c['id'] ?></td>
                                <td><?= date('d/m/Y', strtotime($c['data_solicitacao'])) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($c['item_nome']) ?></td>
                                <td><?= $c['nome_equipamento'] ? htmlspecialchars($c['nome_equipamento']) : '<span class="text-muted small">Uso Geral</span>' ?></td>
                                <td><?= htmlspecialchars($c['nome_solicitante']) ?></td>
                                <td>
                                    <?php 
                                    $cor_urg = ['Baixa'=>'info', 'Média'=>'warning', 'Alta'=>'danger', 'Crítica'=>'dark'];
                                    echo "<span class='badge bg-{$cor_urg[$c['urgencia']]}'>{$c['urgencia']}</span>";
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $cor_status = ['Pendente'=>'secondary', 'Financeiro'=>'primary', 'Diretoria'=>'info', 'Aprovado'=>'success', 'Negado'=>'danger', 'Comprado'=>'dark'];
                                    echo "<span class='badge rounded-pill bg-{$cor_status[$c['status']]}'>{$c['status']}</span>";
                                    ?>
                                </td>
                                <td class="text-center">
                                    <a href="index.php?p=compras_detalhes&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Detalhes
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($compras)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">Nenhuma solicitação encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
