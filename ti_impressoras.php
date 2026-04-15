<?php
require_once 'includes/db.php';
$db = isset($pdo) ? $pdo : $conn;

// Busca os dados das impressoras
$stmt = $db->query("SELECT * FROM ti_impressoras ORDER BY ip_rede ASC");
$impressoras = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Função auxiliar para definir cores das barras
function getCorBarra($percentual) {
    if ($percentual <= 15) return "#e74c3c"; // Vermelho (Crítico)
    if ($percentual <= 40) return "#f1c40f"; // Amarelo (Atenção)
    return "#2ecc71"; // Verde (OK)
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard de Impressoras</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
        
        .card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); position: relative; border-top: 6px solid #ccc; transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); }
        .card.online { border-top-color: #2ecc71; }
        .card.offline { border-top-color: #e74c3c; opacity: 0.7; }

        .info-header { margin-bottom: 15px; }
        .ip-text { color: #7f8c8d; font-size: 0.9em; font-weight: bold; }
        .counter-box { background: #f8f9fa; padding: 10px; border-radius: 8px; text-align: center; margin-bottom: 15px; border: 1px solid #eee; }
        .counter-val { font-size: 1.4em; color: #2c3e50; font-weight: bold; display: block; }
        
        /* Estilização das Barras */
        .supply-label { display: flex; justify-content: space-between; font-size: 0.85em; margin-bottom: 5px; font-weight: 600; color: #34495e; }
        .progress-bg { background: #eee; border-radius: 10px; height: 12px; width: 100%; margin-bottom: 15px; overflow: hidden; }
        .progress-fill { height: 100%; transition: width 0.8s ease-in-out; }
        
        .badge { position: absolute; top: 15px; right: 15px; padding: 4px 10px; border-radius: 20px; font-size: 0.75em; color: #fff; text-transform: uppercase; }
        .btn-update { background: #3498db; color: #fff; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; }
        .btn-update:hover { background: #2980b9; }
    </style>
</head>
<body>

<div class="header">
    <div>
        <h1 style="margin:0; color: #2c3e50;"><i class="fas fa-print"></i> Parque de Impressão</h1>
        <small>Hospital - Gerenciamento de Suprimentos</small>
    </div>
<button type="button" onclick="executarScan()" id="btnSync" class="btn-update btn btn-primary">
    <i class="fas fa-sync-alt" id="iconSync"></i> Sincronizar Agora
</button>

<script>
function executarScan() {
    const btn = document.getElementById('btnSync');
    const icon = document.getElementById('iconSync');
    
    // 1. Efeito visual de carregamento
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sincronizando...';
    
    // 2. Chama o PHP sem sair da página
    fetch('ti_snmp_scan.php')
        .then(response => response.text())
        .then(data => {
            // Opcional: imprimir o resultado no console para debug
            console.log(data);
            
            // 3. Sucesso! Avisa o usuário e recarrega os dados
            alert("Sincronização concluída com sucesso!");
            window.location.reload(); 
        })
        .catch(error => {
            alert("Erro na sincronização: " + error);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sincronizar Agora';
        });
}
</script>
</div>

<div class="grid">
    <?php foreach ($impressoras as $i): 
        $isOnline = (strtolower($i['status']) == 'online');
    ?>
    <div class="card <?php echo $isOnline ? 'online' : 'offline'; ?>">
        <span class="badge" style="background: <?php echo $isOnline ? '#2ecc71' : '#e74c3c'; ?>">
            <?php echo $i['status']; ?>
        </span>

        <div class="info-header">
            <h3 style="margin:0; color:#2c3e50;"><?php echo $i['hostname'] ?: 'Impressora'; ?></h3>
            <span class="ip-text"><i class="fas fa-network-wired"></i> <?php echo $i['ip_rede']; ?></span>
        </div>

        <div class="counter-box">
            <small>CONTADOR TOTAL</small>
            <span class="counter-val"><?php echo number_format($i['contador_total'], 0, ',', '.'); ?></span>
        </div>

        <div class="supply-label">
            <span><i class="fas fa-fill-drip"></i> Toner Preto</span>
            <span><?php echo $i['nivel_toner']; ?>%</span>
        </div>
        <div class="progress-bg">
            <div class="progress-fill" style="width: <?php echo $i['nivel_toner']; ?>%; background: <?php echo getCorBarra($i['nivel_toner']); ?>;"></div>
        </div>

        <div class="supply-label">
            <span><i class="fas fa-tools"></i> Unidade de Cilindro</span>
            <span><?php echo $i['nivel_cilindro']; ?>%</span>
        </div>
        <div class="progress-bg">
            <div class="progress-fill" style="width: <?php echo $i['nivel_cilindro']; ?>%; background: <?php echo getCorBarra($i['nivel_cilindro']); ?>;"></div>
        </div>
<div style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px; display: flex; justify-content: space-between;">
    <a href="http://<?php echo $i['ip_rede']; ?>" target="_blank" class="btn-link" title="Acessar Web Admin">
        <i class="fas fa-external-link-alt"></i> Web
    </a>
<a href="index.php?p=ti_impressora_detalhes&id=<?php echo $i['id']; ?>" class="btn-details" style="background: #34495e; color: #fff; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 0.8em;">
    <i class="fas fa-tools"></i> Detalhes / Manutenção
</a>

</div>


        <div style="text-align: right; font-size: 0.75em; color: #bdc3c7; margin-top: 10px;">
            <i class="far fa-clock"></i> Atualizado em: <?php echo date('d/m/Y H:i', strtotime($i['ultima_leitura'])); ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

</body>
</html>
