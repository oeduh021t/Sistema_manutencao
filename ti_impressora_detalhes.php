<?php
require_once 'includes/db.php';
$db = isset($pdo) ? $pdo : $conn;
$id = $_GET['id'];

// Busca dados da impressora
$imp = $db->prepare("SELECT * FROM ti_impressoras WHERE id = ?");
$imp->execute([$id]);
$dados = $imp->fetch(PDO::FETCH_ASSOC);

// Processa o formulário de manutenção
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tecnico = $_POST['tecnico'];
    $tipo = $_POST['tipo_servico'];
    $desc = $_POST['descricao'];
    $contador = $dados['contador_total'];

    $ins = $db->prepare("INSERT INTO ti_impressoras_manutencao (impressora_id, tecnico, tipo_servico, descricao, contador_na_hora) VALUES (?, ?, ?, ?, ?)");
    $ins->execute([$id, $tecnico, $tipo, $desc, $contador]);
    echo "<script>alert('Registro salvo!'); window.location.href='ti_impressora_detalhes.php?id=$id';</script>";
}

// Busca histórico de manutenção
$manutencoes = $db->prepare("SELECT * FROM ti_impressoras_manutencao WHERE impressora_id = ? ORDER BY data_manutencao DESC");
$manutencoes->execute([$id]);
$historico = $manutencoes->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Detalhes - <?php echo $dados['hostname']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 900px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
        .header-info { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-info">
        <h1><i class="fas fa-print"></i> <?php echo $dados['hostname']; ?></h1>
        <a href="ti_impressoras.php" style="text-decoration:none; color:#7f8c8d;"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
        <div>
            <h3>Registrar Manutenção / Troca</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Técnico:</label>
                    <input type="text" name="tecnico" required>
                </div>
                <div class="form-group">
                    <label>Tipo de Serviço:</label>
                    <select name="tipo_servico">
                        <option>Troca de Toner</option>
                        <option>Troca de Cilindro</option>
                        <option>Limpeza</option>
                        <option>Manutenção Preventiva</option>
                        <option>Outros</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Descrição:</label>
                    <textarea name="descricao" rows="3"></textarea>
                </div>
                <button type="submit" style="background:#2ecc71; color:#fff; border:none; padding:10px 20px; border-radius:4px; cursor:pointer;">
                    Salvar Registro
                </button>
            </form>
        </div>

        <div style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
            <h3>Dados Atuais (Real-time)</h3>
            <p><strong>IP:</strong> <?php echo $dados['ip_rede']; ?></p>
            <p><strong>Contador:</strong> <?php echo number_format($dados['contador_total'], 0, ',', '.'); ?> </p>
            <p><strong>Toner:</strong> <?php echo $dados['nivel_toner']; ?>%</p>
            <p><strong>Cilindro:</strong> <?php echo $dados['nivel_cilindro']; ?>%</p>
        </div>
    </div>

    <hr>
    <h3>Histórico de Manutenções</h3>
    <table>
        <thead>
            <tr style="background:#f4f4f4;">
                <th>Data</th>
                <th>Técnico</th>
                <th>Serviço</th>
                <th>Contador na Época</th>
                <th>Descrição</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($historico as $h): ?>
            <tr>
                <td><?php echo date('d/m/Y H:i', strtotime($h['data_manutencao'])); ?></td>
                <td><?php echo $h['tecnico']; ?></td>
                <td><span style="background:#eee; padding:2px 5px; border-radius:3px;"><?php echo $h['tipo_servico']; ?></span></td>
                <td><?php echo number_format($h['contador_na_hora'], 0, ',', '.'); ?></td>
                <td><?php echo $h['descricao']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
