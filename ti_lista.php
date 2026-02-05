<?php
// O index.php já incluiu o db.php, então apenas garantimos a variável
$db = isset($pdo) ? $pdo : $conn;

// 1. Consultas para os Cards de Resumo
$totalMaquinas = $db->query("SELECT COUNT(*) FROM ti_inventario")->fetchColumn();
$win10 = $db->query("SELECT COUNT(*) FROM ti_inventario WHERE sistema_operacional LIKE '%Windows 10%'")->fetchColumn();
$win11 = $db->query("SELECT COUNT(*) FROM ti_inventario WHERE sistema_operacional LIKE '%Windows 11%'")->fetchColumn();

// 2. Busca a lista de máquinas
$stmt = $db->query("SELECT id, hostname, usuario_logado, ip_rede, sistema_operacional, ultima_atualizacao FROM ti_inventario ORDER BY ultima_atualizacao DESC");
$maquinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold"><i class="bi bi-laptop text-primary"></i> Inventário de TI - HMDL</h2>
        <span class="badge bg-primary rounded-pill">Total: <?= $totalMaquinas ?> dispositivos</span>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-primary border-4">
                <div class="card-body">
                    <h6 class="text-muted fw-bold">TOTAL DE MÁQUINAS</h6>
                    <h2 class="mb-0"><?= $totalMaquinas ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-info border-4">
                <div class="card-body">
                    <h6 class="text-muted fw-bold">WINDOWS 10</h6>
                    <h2 class="mb-0 text-info"><?= $win10 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-dark border-4">
                <div class="card-body">
                    <h6 class="text-muted fw-bold">WINDOWS 11</h6>
                    <h2 class="mb-0"><?= $win11 ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-12">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" id="inputPesquisa" class="form-control border-start-0" placeholder="Pesquisar por hostname, IP, usuário ou sistema...">
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="120">Status</th>
                            <th>Hostname</th>
                            <th>Usuário Atual</th>
                            <th>IP de Rede</th>
                            <th>S.O.</th>
                            <th>Visto em</th>
                            <th class="text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaTI">
                        <?php foreach ($maquinas as $m): 
                            $ultima_visto = strtotime($m['ultima_atualizacao']);
                            $vinte_quatro_horas = strtotime('-24 hours');
                            $online = ($ultima_visto > $vinte_quatro_horas);
                        ?>
                        <tr>
                            <td>
                                <?php if($online): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> Online</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> Offline</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold"><?= htmlspecialchars($m['hostname']) ?></td>
                            <td><i class="bi bi-person text-muted"></i> <?= htmlspecialchars($m['usuario_logado']) ?></td>
                            <td><code class="fw-bold"><?= htmlspecialchars($m['ip_rede']) ?></code></td>
                            <td><small><?= htmlspecialchars($m['sistema_operacional']) ?></small></td>
                            <td><small class="text-muted"><?= date('d/m/Y H:i', $ultima_visto) ?></small></td>
                            <td class="text-center">
                                <a href="index.php?p=ti_detalhes&id=<?= $m['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye"></i> Detalhes
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

<script>
document.getElementById('inputPesquisa').addEventListener('keyup', function() {
    let busca = this.value.toLowerCase();
    let linhas = document.querySelectorAll('#tabelaTI tr');

    linhas.forEach(linha => {
        let texto = linha.textContent.toLowerCase();
        linha.style.display = texto.includes(busca) ? "" : "none";
    });
});
</script>
