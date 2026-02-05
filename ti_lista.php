<?php
require_once 'includes/db.php'; // Sua conexão PDO

// 1. Consultas para os Cards de Resumo
$totalMaquinas = $pdo->query("SELECT COUNT(*) FROM ti_inventario")->fetchColumn();
$win10 = $pdo->query("SELECT COUNT(*) FROM ti_inventario WHERE sistema_operacional LIKE '%Windows 10%'")->fetchColumn();
$win11 = $pdo->query("SELECT COUNT(*) FROM ti_inventario WHERE sistema_operacional LIKE '%Windows 11%'")->fetchColumn();

// 2. Busca a lista de máquinas
$stmt = $pdo->query("SELECT id, hostname, usuario_logado, ip_rede, sistema_operacional, status, ultima_atualizacao FROM ti_inventario ORDER BY ultima_atualizacao DESC");
$maquinas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Parque de TI - HMDL</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-light">

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-laptop-medical"></i> Inventário de TI - HMDL</h2>
        <span class="badge badge-primary">Total: <?= $totalMaquinas ?> dispositivos</span>
    </div>
    <hr>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-white shadow-sm border-left border-primary">
                <div class="card-body">
                    <h6 class="text-muted uppercase">Total de Máquinas</h6>
                    <h2 class="font-weight-bold"><?= $totalMaquinas ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-white shadow-sm border-left border-info">
                <div class="card-body">
                    <h6 class="text-muted">Windows 10</h6>
                    <h2 class="font-weight-bold text-info"><?= $win10 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-white shadow-sm border-left border-dark">
                <div class="card-body">
                    <h6 class="text-muted">Windows 11</h6>
                    <h2 class="font-weight-bold"><?= $win11 ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Status</th>
                            <th>Hostname</th>
                            <th>Usuário Atual</th>
                            <th>IP</th>
                            <th>S.O.</th>
                            <th>Visto em</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($maquinas as $m): 
                            // Lógica para verificar se a máquina está "Online" (Visto nas últimas 24h)
                            $ultima_visto = strtotime($m['ultima_atualizacao']);
                            $vinte_quatro_horas = strtotime('-24 hours');
                            $online = ($ultima_visto > $vinte_quatro_horas);
                        ?>
                        <tr>
                            <td>
                                <?php if($online): ?>
                                    <span class="badge badge-success"><i class="fas fa-check-circle"></i> Online</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><i class="fas fa-times-circle"></i> Offline</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= $m['hostname'] ?></strong></td>
                            <td><i class="fas fa-user-circle text-muted"></i> <?= $m['usuario_logado'] ?></td>
                            <td><code class="text-primary"><?= $m['ip_rede'] ?></code></td>
                            <td><small><?= $m['sistema_operacional'] ?></small></td>
                            <td><?= date('d/m/Y H:i', $ultima_visto) ?></td>

                            <td class="text-center">
                            <a href="index.php?p=ti_detalhes&id=<?= $m['id'] ?>" class="btn btn-info btn-sm text-white shadow-sm">
                            <i class="bi bi-search"></i> Detalhes
    </a>
</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
