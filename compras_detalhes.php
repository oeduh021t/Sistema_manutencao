<?php
// compras_detalhes.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['usuario_id'])) { die("Acesso negado."); }

include_once 'includes/db.php'; // Certifique-se de que a conexão está inclusa

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php?p=compras_lista"); exit; }

// --- LÓGICA PARA PROCESSAR NOVO ANEXO ---
if (isset($_FILES['novo_anexo']) && $_FILES['novo_anexo']['error'] === 0) {
    $diretorio = "uploads/compras/";
    if (!is_dir($diretorio)) { mkdir($diretorio, 0777, true); }

    $extensao = pathinfo($_FILES['novo_anexo']['name'], PATHINFO_EXTENSION);
    $nome_arquivo = "PEDIDO_" . $id . "_" . time() . "." . $extensao;

    if (move_uploaded_file($_FILES['novo_anexo']['tmp_name'], $diretorio . $nome_arquivo)) {
        $ins = $pdo->prepare("INSERT INTO solicitacoes_compra_anexos (solicitacao_id, arquivo_nome, data_upload) VALUES (?, ?, NOW())");
        $ins->execute([$id, $nome_arquivo]);
        echo "<script>alert('Anexo enviado com sucesso!'); window.location.href='index.php?p=compras_detalhes&id=$id';</script>";
    }
}

// 1. Busca os detalhes da "Capa" da compra
$sql = "SELECT c.*, e.nome as nome_equipamento, e.patrimonio,
               u_sol.nome as solicitante,
               u_fin.nome as aprovador_financeiro,
               u_dir.nome as aprovador_diretoria
        FROM solicitacoes_compra c
        LEFT JOIN equipamentos e ON c.equipamento_id = e.id
        JOIN usuarios u_sol ON c.solicitante_id = u_sol.id
        LEFT JOIN usuarios u_fin ON c.user_financeiro_id = u_fin.id
        LEFT JOIN usuarios u_dir ON c.user_diretoria_id = u_dir.id
        WHERE c.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$compra = $stmt->fetch();

if (!$compra) { die("Solicitação não encontrada."); }

// 2. Busca os itens desta solicitação
$itens = $pdo->prepare("SELECT * FROM solicitacoes_compra_itens WHERE solicitacao_id = ?");
$itens->execute([$id]);
$lista_itens = $itens->fetchAll();

// 3. Busca os anexos
$anexos = $pdo->prepare("SELECT * FROM solicitacoes_compra_anexos WHERE solicitacao_id = ? ORDER BY data_upload DESC");
$anexos->execute([$id]);
$lista_anexos = $anexos->fetchAll();

$cores = ['Pendente' => 'secondary', 'Financeiro' => 'primary', 'Diretoria' => 'info', 'Aprovado' => 'success', 'Negado' => 'danger', 'Comprado' => 'dark'];
?>

<style>
/* Estilo de Impressão */
@media print {
    body * { visibility: hidden; }
    #imprimir-conteudo, #imprimir-conteudo * { visibility: visible; }
    #imprimir-conteudo { position: absolute; left: 0; top: 0; width: 100% !important; margin: 0 !important; padding: 10px !important; }
    .d-print-none { display: none !important; }
    .header-impressao { display: block !important; border-bottom: 2px solid #000; margin-bottom: 20px; padding-bottom: 10px; }
}
.header-impressao { display: none; }
.card-anexo:hover { background-color: #f8f9fa; }
</style>

<div class="container mt-4" id="imprimir-conteudo">
    
    <div class="header-impressao text-center">
        <h3 class="mb-1">Hospital Maternidade Domingos Lourenço</h3>
        <h5 class="text-uppercase text-muted" style="letter-spacing: 2px;">Solicitação de Compra Interna</h5>
        <hr>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
        <a href="index.php?p=compras_lista" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
        <button onclick="window.print();" class="btn btn-dark"><i class="bi bi-printer"></i> Imprimir Solicitação</button>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
                    <h5 class="mb-0">Pedido #<?= $compra['id'] ?></h5>
                    <span class="badge bg-<?= $cores[$compra['status']] ?> fs-6"><?= $compra['status'] ?></span>
                </div>
                <div class="card-body">
                    
                    <div class="row mb-4">
                        <div class="col-sm-6">
                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Solicitante</small>
                            <span class="fs-6"><?= htmlspecialchars($compra['solicitante']) ?></span>
                        </div>
                        <div class="col-sm-6 text-sm-end">
                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Data da Emissão</small>
                            <span class="fs-6"><?= date('d/m/Y H:i', strtotime($compra['data_solicitacao'])) ?></span>
                        </div>
                    </div>

                    <h6 class="fw-bold"><i class="bi bi-cart-fill me-2"></i>Itens do Pedido</h6>
                    <table class="table table-bordered align-middle mt-2">
                        <thead class="table-light">
                            <tr>
                                <th>Descrição do Produto/Serviço</th>
                                <th class="text-center" style="width: 70px;">Qtd</th>
                                <th class="text-end" style="width: 130px;">V. Unitário</th>
                                <th class="text-end" style="width: 130px;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_geral = 0;
                            foreach ($lista_itens as $item):
                                $subtotal = ($item['quantidade'] ?? 0) * ($item['valor_estimado'] ?? 0);
                                $total_geral += $subtotal;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($item['descricao']) ?></td>
                                <td class="text-center"><?= $item['quantidade'] ?></td>
                                <td class="text-end">R$ <?= number_format($item['valor_estimado'], 2, ',', '.') ?></td>
                                <td class="text-end fw-bold">R$ <?= number_format($subtotal, 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-secondary">
                                <td colspan="3" class="text-end fw-bold text-uppercase">Total Estimado</td>
                                <td class="text-end fw-bold text-dark fs-5">R$ <?= number_format($total_geral, 2, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="row mt-4">
                        <div class="col-12 mb-3">
                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Equipamento / Destino</small>
                            <div class="p-2 border-start border-4 border-primary bg-light">
                                <?= $compra['nome_equipamento'] ? "<strong>".htmlspecialchars($compra['nome_equipamento'])."</strong> (Pat: ".htmlspecialchars($compra['patrimonio']).")" : "Uso Geral / Setor Específico" ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Justificativa</small>
                            <div class="p-2 bg-light border rounded"><?= nl2br(htmlspecialchars($compra['motivo'])) ?></div>
                        </div>
                    </div>

                    <div class="d-none d-print-block" style="margin-top: 60px;">
                        <div class="row text-center">
                            <div class="col-4">
                                <div style="border-top: 1px solid #000; margin: 0 10px; padding-top: 5px;">
                                    <small>Assinatura Solicitante</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div style="border-top: 1px solid #000; margin: 0 10px; padding-top: 5px;">
                                    <small>Financeiro</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div style="border-top: 1px solid #000; margin: 0 10px; padding-top: 5px;">
                                    <small>Diretoria</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 d-print-none">
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white fw-bold small text-uppercase">Controle de Status</div>
                <div class="card-body">
                    <?php if ($compra['status'] == 'Pendente'): ?>
                        <a href="compras_status.php?id=<?= $id ?>&acao=financeiro" class="btn btn-primary w-100 mb-2">Dar Ciência (Financeiro)</a>
                    <?php elseif ($compra['status'] == 'Financeiro'): ?>
                        <a href="compras_status.php?id=<?= $id ?>&acao=diretoria" class="btn btn-info w-100 mb-2 text-white">Enviar para Diretoria</a>
                    <?php elseif ($compra['status'] == 'Diretoria'): ?>
                        <a href="compras_status.php?id=<?= $id ?>&acao=comprado" class="btn btn-success w-100 mb-2">Marcar como Comprado</a>
                    <?php endif; ?>

                    <a href="compras_status.php?id=<?= $id ?>&acao=negado" class="btn btn-outline-danger btn-sm w-100 mt-2" onclick="return confirm('Negar solicitação?')">Negar Pedido</a>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold small text-uppercase">Anexos e Documentos</span>
                    <i class="bi bi-paperclip"></i>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" class="mb-3 border-bottom pb-3">
                        <label class="form-label small text-muted">Adicionar NF ou Documento:</label>
                        <div class="input-group input-group-sm">
                            <input type="file" name="novo_anexo" class="form-control" required>
                            <button class="btn btn-primary" type="submit">Enviar</button>
                        </div>
                    </form>

                    <?php if ($lista_anexos): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($lista_anexos as $anexo): ?>
                                <a href="uploads/compras/<?= $anexo['arquivo_nome'] ?>" target="_blank" class="list-group-item list-group-item-action p-2 card-anexo">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div class="text-truncate" style="max-width: 80%;">
                                            <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                            <small><?= htmlspecialchars($anexo['arquivo_nome']) ?></small>
                                        </div>
                                        <i class="bi bi-box-arrow-up-right small text-muted"></i>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.65rem;"><?= date('d/m/Y H:i', strtotime($anexo['data_upload'])) ?></small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted small my-3">Nenhum anexo disponível.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
