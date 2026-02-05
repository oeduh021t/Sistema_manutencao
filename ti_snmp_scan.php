<?php
// ti_snmp_scan.php - Sistema de Captura Inteligente
ini_set('display_errors', 0); 
require_once 'includes/db.php';
set_time_limit(600);
$db = isset($pdo) ? $pdo : $conn;

$impressoras = $db->query("SELECT id, ip_rede FROM ti_impressoras")->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Varredura Hospitalar Iniciada</h2><hr>";

foreach ($impressoras as $imp) {
    $ip = $imp['ip_rede'];
    
    // 1. CAPTURA DO CONTADOR
    $res_c = shell_exec("snmpget -v 2c -c public -O qv -t 2 $ip .1.3.6.1.2.1.43.10.2.1.4.1.1 2>/dev/null");
    $val_contador = (int)preg_replace('/[^0-9]/', '', (string)$res_c);

    if ($val_contador > 0 && $val_contador < 3000000) {
        $pct_toner = 100;
        $pct_cilindro = 100;

        // 2. DESCOBERTA DE ÍNDICES (Toner vs Cilindro)
        // O snmpwalk retorna: 1.1 = "Black Toner...", 1.2 = "Drum Unit"
        $walk_nomes = shell_exec("snmpwalk -v 2c -c public -On $ip .1.3.6.1.2.1.43.11.1.1.6.1 2>/dev/null");
        
        if ($walk_nomes) {
            $linhas = explode("\n", trim($walk_nomes));
            foreach ($linhas as $linha) {
                // Extrai o índice final da OID (ex: de .1.3...6.1.1 pegamos o 1)
                preg_match('/\.6\.1\.(\d+)\s+=\s+STRING:\s+"?([^"]+)"?/', $linha, $matches);
                
                if (count($matches) == 3) {
                    $idx = $matches[1];
                    $nome_suprimento = $matches[2];

                    // Busca os valores atuais e máximos para este índice específico
                    $at = (int)shell_exec("snmpget -v 2c -c public -O qv $ip .1.3.6.1.2.1.43.11.1.1.9.1.$idx 2>/dev/null");
                    $max = (int)shell_exec("snmpget -v 2c -c public -O qv $ip .1.3.6.1.2.1.43.11.1.1.8.1.$idx 2>/dev/null");

                    // Lógica para TONER
                    if (stripos($nome_suprimento, "Toner") !== false) {
                        if ($at >= 0 && $at <= 100) $pct_toner = $at;
                        elseif ($at == -3) $pct_toner = 100;
                        elseif ($at == -2) $pct_toner = 15;
                        elseif ($max > 0) $pct_toner = round(($at / $max) * 100);
                    }
                    
                    // Lógica para CILINDRO (Drum)
                    if (stripos($nome_suprimento, "Drum") !== false || stripos($nome_suprimento, "Cilindro") !== false) {
                        if ($max > 0 && $at >= 0) {
                            $pct_cilindro = round(($at / $max) * 100);
                        }
                    }
                }
            }
        }

        // Limites de segurança 0-100
        $pct_toner = max(0, min(100, (int)$pct_toner));
        $pct_cilindro = max(0, min(100, (int)$pct_cilindro));

        // 3. SALVAMENTO
        $stmt = $db->prepare("UPDATE ti_impressoras SET contador_total = ?, nivel_toner = ?, nivel_cilindro = ?, ultima_leitura = NOW(), status = 'Online' WHERE id = ?");
        $stmt->execute([$val_contador, $pct_toner, $pct_cilindro, $imp['id']]);

        echo "<b>$ip</b> -> Contador: $val_contador | Toner: $pct_toner% | Cilindro: $pct_cilindro%<br>";
    } else {
        $db->prepare("UPDATE ti_impressoras SET status = 'Offline' WHERE id = ?")->execute([$imp['id']]);
        echo "<span style='color:red'>OFFLINE: $ip</span><br>";
    }
    @ob_flush();
    flush();
}
