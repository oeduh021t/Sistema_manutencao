<?php
include_once 'includes/db.php';

$nivel_logado = $_SESSION['usuario_nivel'];

// --- FUNÇÃO PARA GERAR O LINK DO QR CODE (API ESTÁVEL) ---
function gerarLinkQRCodeLocal($id) {
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $dominio = $_SERVER['HTTP_HOST'];
    $caminho_projeto = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
    if ($caminho_projeto == '/') $caminho_projeto = '';
    
    $url_destino = "{$protocolo}://{$dominio}{$caminho_projeto}/qrcode.php?id={$id}";
    return "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($url_destino);
}

// --- LÓGICA DE FILTRO ---
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$filtro_sql = "";
$params = [];

if (!empty($busca)) {
    $filtro_sql = " WHERE (e.nome LIKE ? OR e.patrimonio LIKE ? OR e.num_serie LIKE ? OR s.nome LIKE ? OR t.nome LIKE ? OR e.status LIKE ?)";
    $term = "%$busca%";
    $params = [$term, $term, $term, $term, $term, $term];
}

// Função Auxiliar para Breadcrumb
function getCaminhoCompletoSelect($id, $mapa) {
    if (!isset($mapa[$id])) return "";
    $setor = $mapa[$id];
    if (!empty($setor['setor_pai_id']) && isset($mapa[$setor['setor_pai_id']])) {
        return getCaminhoCompletoSelect($setor['setor_pai_id'], $mapa) . " > " . $setor['nome'];
    }
    return $setor['nome'];
}

// 2. Processar Cadastro
if (isset($_POST['salvar_equipamento'])) {
    $patrimonio = $_POST['patrimonio'];
    $num_serie = $_POST['num_serie'];
    $nome = $_POST['nome'];
    $tipo_id = $_POST['tipo_id'];
    $setor_id = $_POST['setor_id'];
    $status_ini = $_POST['status_inicial'] ?? 'Ativo';
    
    // Tratamento dos campos Opcionais de Preventiva
    $periodicidade = (!empty($_POST['periodicidade_preventiva'])) ? (int)$_POST['periodicidade_preventiva'] : 0;
    $ultima_prev = (!empty($_POST['data_ultima_preventiva'])) ? $_POST['data_ultima_preventiva'] : null;

    $foto_nome = null;
    if (!empty($_FILES['foto']['name'])) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto_nome = "EQUIP_" . time() . "." . $ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], "uploads/" . $foto_nome);
    }

    $stmt = $pdo->prepare("INSERT INTO equipamentos (patrimonio, num_serie, nome, tipo_id, setor_id, foto_equipamento, status, periodicidade_preventiva, data_ultima_preventiva) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$patrimonio, $num_serie, $nome, $tipo_id, $setor_id, $foto_nome, $status_ini, $periodicidade, $ultima_prev]);

    echo "<div class='alert alert-success mt-3 shadow-sm'>Equipamento cadastrado com sucesso!</div>";
}

// 3. Buscar Equipamentos
$sql = "SELECT e.*, s.nome as setor_nome, t.nome as tipo_nome FROM equipamentos e LEFT JOIN setores s ON e.setor_id = s.id LEFT JOIN tipos_equipamentos t ON e.tipo_id = t.id $filtro_sql ORDER BY e.id DESC";
$stmt_eq = $pdo->prepare($sql);
$stmt_eq->execute($params);
$equipamentos = $stmt_eq->fetchAll();

// 4. Buscar Setores
$setores_mapa = $pdo->query("SELECT id, nome, setor_pai_id FROM setores")->fetchAll(PDO::FETCH_UNIQUE);
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <h2><i class="bi bi-pc-display text-primary"></i> Gestão de Ativos</h2>
    <button class="btn btn-primary shadow" data-bs-toggle="modal" data-bs-target="#modalEquipamento">
        <i class="bi bi-plus-circle"></i> Novo Ativo
    </button>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body bg-light rounded p-3">
        <form method="GET" action="index.php" class="row g-2">
            <input type="hidden" name="p" value="equipamentos">
            <div class="col-md-10">
                <input type="text" name="busca" class="form-control" placeholder="Buscar por nome, patrimônio ou setor..." value="<?= htmlspecialchars($busca) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100 fw-bold">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary">
                    <tr>
                        <th class="ps-4">Ativo</th>
                        <th>Nº Série / Patrimônio</th>
                        <th>Setor</th>
                        <th>Status</th>
                        <th>QR Code</th> 
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipamentos as $e): 
                        $alerta_vencido = false;
                        if ($e['periodicidade_preventiva'] > 0 && !empty($e['data_ultima_preventiva'])) {
                            $data_vencimento = date('Y-m-d', strtotime($e['data_ultima_preventiva'] . " + {$e['periodicidade_preventiva']} days"));
                            if (date('Y-m-d') >= $data_vencimento) { $alerta_vencido = true; }
                        }
                    ?>
                    <tr>
                        <td class="ps-4">
                            <strong><?= htmlspecialchars($e['nome']) ?></strong>
                            <?php if ($alerta_vencido): ?>
                                <i class="bi bi-calendar-x-fill text-danger ms-1" title="Preventiva Vencida!"></i>
                            <?php endif; ?>
                            <br><small class="text-muted"><?= htmlspecialchars($e['tipo_nome']) ?></small>
                        </td>
                        <td>
                            <small class="text-muted d-block">SN: <?= htmlspecialchars($e['num_serie']) ?: '---' ?></small>
                            <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($e['patrimonio']) ?></span>
                        </td>
                        <td><small class="fw-bold text-primary"><?= htmlspecialchars($e['setor_nome']) ?></small></td>
                        <td>
                            <?php
                                $status_class = match($e['status']) {
                                    'Ativo' => 'bg-success',
                                    'Em Manutenção' => 'bg-warning text-dark',
                                    'Reserva' => 'bg-info text-white',
                                    default => 'bg-secondary'
                                };
                            ?>
                            <span class="badge <?= $status_class ?>"><?= $e['status'] ?></span>
                        </td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#modalQR<?= $e['id'] ?>">
                                <img src="<?= gerarLinkQRCodeLocal($e['id']) ?>" width="35" class="img-thumbnail shadow-sm border-primary">
                            </a>
                            
                            <div class="modal fade" id="modalQR<?= $e['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content border-0 shadow">
                                        <div class="modal-header bg-primary text-white py-2">
                                            <h6 class="modal-title">Etiqueta de Patrimônio</h6>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-center p-4" id="etiqueta<?= $e['id'] ?>">
                                            <div style="border: 2px solid #000; padding: 10px; border-radius: 5px; background: #fff; display: inline-block;">
                                                <small class="fw-bold d-block text-uppercase" style="font-size: 9px; margin-bottom: 5px;">HOSPITAL DOMINGOS LOURENÇO</small>
                                                <img src="<?= gerarLinkQRCodeLocal($e['id']) ?>" style="width: 130px; height: 130px;">
                                                <div class="fw-bold" style="font-size: 18px; margin-top: 5px; border-top: 1px solid #000;"><?= $e['patrimonio'] ?></div>
                                                <div style="font-size: 10px; color: #000; font-weight: bold;"><?= htmlspecialchars($e['nome']) ?></div>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light">
                                            <button type="button" class="btn btn-dark w-100 fw-bold shadow-sm" onclick="imprimirEtiqueta('etiqueta<?= $e['id'] ?>')">
                                                <i class="bi bi-printer me-2"></i>IMPRIMIR
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group shadow-sm">
                                <?php if ($e['status'] == 'Reserva' || $e['status'] == 'Em Manutenção'): ?>
                                    <a href="index.php?p=devolver_equipamento&id=<?= $e['id'] ?>" class="btn btn-sm btn-success" title="Devolver ao Setor"><i class="bi bi-arrow-down-up"></i></a>
                                <?php endif; ?>
                                
                                <?php if ($e['status'] == 'Ativo'): ?>
                                    <a href="index.php?p=trocar_equipamento&id=<?= $e['id'] ?>" class="btn btn-sm btn-warning" title="Retirar e Substituir"><i class="bi bi-arrow-left-right"></i></a>
                                <?php endif; ?>
                                
                                <a href="relatorio_equipamento.php?id=<?= $e['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Relatório"><i class="bi bi-file-earmark-medical"></i></a>
                                
                                <a href="index.php?p=historico_equipamento&id=<?= $e['id'] ?>" class="btn btn-sm btn-info text-white" title="Histórico"><i class="bi bi-clock-history"></i></a>
                                
                                <?php if (in_array($nivel_logado, ['admin', 'coordenador'])): ?>
                                    <a href="index.php?p=editar_equipamento&id=<?= $e['id'] ?>" class="btn btn-sm btn-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <a href="index.php?p=excluir_equipamento&id=<?= $e['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir Ativo?')" title="Excluir"><i class="bi bi-trash"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEquipamento" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow" method="POST" enctype="multipart/form-data">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-tag"></i> Novo Ativo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Nome do Ativo</label>
                    <input type="text" name="nome" class="form-control" placeholder="Ex: Cadeira de Rodas, Ar Condicionado" required>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label fw-bold">Nº de Série</label><input type="text" name="num_serie" class="form-control"></div>
                    <div class="col-md-6 mb-3"><label class="form-label fw-bold">Patrimônio</label><input type="text" name="patrimonio" class="form-control" required></div>
                </div>

                <div class="p-3 bg-light border rounded mb-3">
                    <h6 class="text-muted fw-bold small mb-3 text-uppercase"><i class="bi bi-calendar-check"></i> Manutenção Preventiva (Opcional)</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Dias p/ revisão</label>
                            <input type="number" name="periodicidade_preventiva" class="form-control form-control-sm" placeholder="Ex: 180">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Última Realizada</label>
                            <input type="date" name="data_ultima_preventiva" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Tipo</label>
                        <select name="tipo_id" class="form-select" required>
                            <option value="">-- Selecione --</option>
                            <?php $tipos = $pdo->query("SELECT * FROM tipos_equipamentos ORDER BY nome ASC")->fetchAll();
                                  foreach($tipos as $t) echo "<option value='{$t['id']}'>{$t['nome']}</option>"; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Status Inicial</label>
                        <select name="status_inicial" class="form-select">
                            <option value="Ativo">Ativo</option>
                            <option value="Reserva" selected>Reserva</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Localização</label>
                    <select name="setor_id" class="form-select" required>
                        <?php foreach ($setores_mapa as $sid => $s): ?>
                            <option value="<?= $sid ?>"><?= getCaminhoCompletoSelect($sid, $setores_mapa) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Foto</label>
                    <input type="file" name="foto" class="form-control" accept="image/*">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="submit" name="salvar_equipamento" class="btn btn-primary w-100 fw-bold">SALVAR ATIVO</button>
            </div>
        </form>
    </div>
</div>

<script>
function imprimirEtiqueta(divId) {
    var conteudo = document.getElementById(divId).innerHTML;
    var win = window.open('', '', 'height=500,width=500');
    win.document.write('<html><head><title>Imprimir</title>');
    win.document.write('<style>body{display:flex; justify-content:center; align-items:center; height:100vh; margin:0;}</style>');
    win.document.write('</head><body>');
    win.document.write(conteudo);
    win.document.write('</body></html>');
    win.document.close();
    setTimeout(function() { win.print(); win.close(); }, 500);
}
</script>
