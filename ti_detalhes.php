<?php
// O index.php já incluiu o db.php, então apenas usamos a variável $pdo ou $conn
// Se o seu sistema usa $pdo, mantenha assim. Se usa $conn, troque abaixo.
$db = isset($pdo) ? $pdo : $conn;

$id_maquina = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_maquina === 0) {
    echo "<div class='alert alert-warning'>ID não fornecido.</div>";
    return; // Para a execução apenas deste include
}

// Busca os dados
$stmt = $db->prepare("SELECT * FROM ti_inventario WHERE id = ?");
$stmt->execute([$id_maquina]);
$pc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pc) {
    echo "<div class='alert alert-danger'>Máquina não encontrada.</div>";
    return;
}
?>

<?php if (isset($pc) && $pc): ?>
<div class="container-fluid p-0">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-laptop text-info"></i> Detalhes do Ativo</h3>
        <a href="index.php?p=ti_lista" class="btn btn-secondary shadow-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white p-0">
            <ul class="nav nav-tabs border-bottom-0" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active py-3 px-4" id="home-tab" data-bs-toggle="tab" data-bs-target="#home" type="button" role="tab">Geral</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 px-4" id="hardware-tab" data-bs-toggle="tab" data-bs-target="#hardware" type="button" role="tab">Hardware/Software</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 px-4" id="notas-tab" data-bs-toggle="tab" data-bs-target="#notas" type="button" role="tab">Notas</button>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content" id="myTabContent">
                
                <div class="tab-pane fade show active" id="home" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold">HOSTNAME</label>
                            <p class="fs-5 fw-bold"><?= htmlspecialchars($pc['hostname']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold">IP</label>
                            <p class="text-primary fw-bold"><?= htmlspecialchars($pc['ip_rede']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold">USUÁRIO</label>
                            <p><?= htmlspecialchars($pc['usuario_logado']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold">SISTEMA</label>
                            <p><?= htmlspecialchars($pc['sistema_operacional']) ?></p>
                        </div>
                    </div>
                </div>

<div class="tab-pane fade" id="hardware" role="tabpanel">
    <div class="row">
        <div class="col-md-5 border-end">
            <h6 class="fw-bold mb-4 text-primary"><i class="bi bi-cpu"></i> Especificações de Hardware</h6>
            
            <div class="mb-3">
                <label class="text-muted small fw-bold d-block">PROCESSADOR</label>
                <span class="fw-semibold"><?= htmlspecialchars($pc['processador']) ?></span>
            </div>
            
            <div class="mb-3">
                <label class="text-muted small fw-bold d-block">MEMÓRIA RAM</label>
                <span class="fw-semibold"><?= htmlspecialchars($pc['memoria_ram']) ?></span>
            </div>
            
            <div class="mb-3">
                <label class="text-muted small fw-bold d-block">PLACA MÃE / FABRICANTE</label>
                <span class="fw-semibold"><?= htmlspecialchars($pc['fabricante_mb']) ?> <?= htmlspecialchars($pc['modelo_mb']) ?></span>
            </div>
        </div>

        <div class="col-md-7">
            <h6 class="fw-bold mb-4 text-primary ps-md-3"><i class="bi bi-box-seam"></i> Softwares Instalados</h6>
            <div class="ps-md-3">
                <div class="bg-light border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                    <ul class="list-unstyled mb-0">
                        <?php 
                        // Transformamos a string de softwares em um array separando pela vírgula
                        $softwares = explode(',', $pc['softwares_lista']); 
                        
                        if (!empty($pc['softwares_lista'])):
                            foreach ($softwares as $software): 
                                $nome_limpo = trim($software); // Remove espaços em branco extras
                                if ($nome_limpo != ""):
                        ?>
                            <li class="pb-2 mb-2 border-bottom border-secondary-subtle small">
                                <i class="bi bi-caret-right-fill text-primary small"></i> <?= htmlspecialchars($nome_limpo) ?>
                            </li>
                        <?php 
                                endif;
                            endforeach; 
                        else:
                            echo "<li class='text-muted small'>Nenhum software listado.</li>";
                        endif;
                        ?>
                    </ul>
                </div>
                <small class="text-muted mt-2 d-block text-end">Total: <?= count(array_filter($softwares)) ?> itens detectados</small>
            </div>
        </div>
    </div>
</div>
                <div class="tab-pane fade" id="notas" role="tabpanel">
                    <form action="ti_acoes.php" method="POST" class="mb-3">
                        <input type="hidden" name="computador_id" value="<?= $id_maquina ?>">
                        <textarea name="texto_nota" class="form-control mb-2" required placeholder="Nova nota..."></textarea>
                        <button name="salvar_nota" class="btn btn-primary btn-sm">Salvar</button>
                    </form>
                    <div class="list-group list-group-flush">
                        <?php foreach($notas as $n): ?>
                            <div class="list-group-item px-0">
                                <small class="fw-bold"><?= $n['usuario_autor'] ?> - <?= date('d/m/Y', strtotime($n['data_registro'])) ?></small>
                                <p class="mb-0 small"><?= htmlspecialchars($n['texto_nota']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<?php endif; ?>
