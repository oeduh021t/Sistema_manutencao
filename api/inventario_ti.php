<?php
// api/inventario.php
require_once '../includes/db.php'; // Ajuste o caminho para sua conexão PDO

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    die("Dados inválidos.");
}

try {
    // Tenta encontrar o PC pelo Hostname (que é Único)
    $stmt = $pdo->prepare("SELECT id FROM ti_inventario WHERE hostname = ?");
    $stmt->execute([$data['nome']]);
    $pc = $stmt->fetch();

    if ($pc) {
        // UPDATE: Atualiza os dados de hardware e rede
        $sql = "UPDATE ti_inventario SET 
                usuario_logado = ?, ip_rede = ?, sistema_operacional = ?, 
                fabricante_mb = ?, modelo_mb = ?, processador = ?, 
                memoria_ram = ?, video_monitor = ?, softwares_lista = ?, remoto_id = ?
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['usuario'], $data['ip'], $data['so'],
            $data['mb_fabricante'], $data['mb_modelo'], $data['processador'],
            $data['memoria'], $data['monitor'], $data['programas'], $data['remoto_id'],
            $pc['id']
        ]);
        echo "Máquina atualizada com sucesso.";
    } else {
        // INSERT: Cria o registro da nova máquina
        $sql = "INSERT INTO ti_inventario 
                (hostname, usuario_logado, ip_rede, sistema_operacional, fabricante_mb, modelo_mb, processador, memoria_ram, video_monitor, softwares_lista, remoto_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['nome'], $data['usuario'], $data['ip'], $data['so'],
            $data['mb_fabricante'], $data['mb_modelo'], $data['processador'],
            $data['memoria'], $data['monitor'], $data['programas'], $data['remoto_id']
        ]);
        echo "Nova máquina cadastrada no inventário.";
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "Erro no banco: " . $e->getMessage();
}
