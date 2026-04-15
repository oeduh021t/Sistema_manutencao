<?php
require_once 'includes/db.php';

// Desativa limite de tempo e limpa buffers de saída
set_time_limit(0);
ini_set('implicit_flush', 1);
ob_implicit_flush(true);

$rede = "192.168.4."; 
$comunidade = "public";
$oid_modelo = ".1.3.6.1.2.1.25.3.2.1.3.1"; 

echo "<h2>Iniciando varredura na rede {$rede}0/24...</h2>";
echo "<div id='status' style='font-family: monospace; background: #000; color: #0f0; padding: 15px; height: 400px; overflow-y: scroll;'>";

for ($i = 1; $i <= 254; $i++) {
    $ip = $rede . $i;
    
    // Tentativa de conexão com timeout de 200ms (0.2 segundos)
    // Para varredura, 200ms é o suficiente para uma rede local responder
    $resultado = @snmpget($ip, $comunidade, $oid_modelo, 200000, 1);

    if ($resultado) {
        $modelo = str_replace(['STRING: ', '"'], '', $resultado);
        $modelo = trim($modelo);

        $check = $pdo->prepare("SELECT id FROM ti_impressoras WHERE ip_rede = ?");
        $check->execute([$ip]);
        
        if ($check->rowCount() == 0) {
            $ins = $pdo->prepare("INSERT INTO ti_impressoras (hostname, ip_rede, modelo, status) VALUES (?, ?, ?, 'Online')");
            $ins->execute([$modelo, $ip, $modelo]);
            echo "[SUCESSO] IP: $ip - Encontrada: $modelo <br>";
        } else {
            echo "[EXISTE] IP: $ip - $modelo já está no banco <br>";
        }
    } else {
        // Mostra um ponto para cada IP testado para você saber que não travou
        echo ". "; 
        if ($i % 50 == 0) echo "<br>"; 
    }
    
    // Força o navegador a mostrar o que já foi processado
    echo str_pad('', 4096); 
}

echo "</div><h3>Varredura concluída!</h3>";
