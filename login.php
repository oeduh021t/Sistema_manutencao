<?php
session_start();
echo "DEBUG: Destino pendente é: " . ($_SESSION['url_redirecionamento'] ?? 'Nenhum');
require_once 'includes/db.php';

// Se o usuário já estiver logado e tentar acessar o login, 
// verificamos se há um redirecionamento pendente antes de mandá-lo para a home
if (isset($_SESSION['usuario_id'])) {
    if (isset($_SESSION['url_redirecionamento'])) {
        $destino = $_SESSION['url_redirecionamento'];
        unset($_SESSION['url_redirecionamento']);
        header("Location: " . $destino);
    } else {
        header("Location: index.php");
    }
    exit;
}

$erro = null;

if (isset($_POST['btn_login'])) {
    $login = trim($_POST['login']);
    $senha = trim($_POST['senha']);

    if (!empty($login) && !empty($senha)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE LOWER(login) = LOWER(?) LIMIT 1");
            $stmt->execute([$login]);
            $user = $stmt->fetch();

            if ($user) {
                // Verificação de Senha (Hash ou Plano B para senha '123')
                $senha_correta = password_verify($senha, $user['senha']);
                $fallback_123 = ($senha === '123' && strpos($user['senha'], '$2y$10$f6pGz') !== false);

                if ($senha_correta || $fallback_123) {
                    $_SESSION['usuario_id'] = $user['id'];
                    $_SESSION['usuario_nome'] = $user['nome'];
                    $_SESSION['usuario_nivel'] = $user['nivel'];

                    // --- LÓGICA DE REDIRECIONAMENTO PÓS-QRCODE ---
                    if (isset($_SESSION['url_redirecionamento'])) {
                        $destino = $_SESSION['url_redirecionamento'];
                        unset($_SESSION['url_redirecionamento']); // Limpa a sessão para não repetir o comportamento
                        header("Location: " . $destino);
                    } else {
                        header("Location: index.php");
                    }
                    exit;
                } else {
                    $erro = "Senha incorreta!";
                }
            } else {
                $erro = "Usuário não encontrado!";
            }
        } catch (PDOException $e) {
            $erro = "Erro no banco de dados: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha todos os campos!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso - Sistema Manutenção</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #1a1d20; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: sans-serif; }
        .login-card { width: 100%; max-width: 380px; border: none; border-radius: 12px; overflow: hidden; }
        .card-header { background: #0d6efd; color: white; text-align: center; padding: 25px; border: none; }
        .btn-primary { padding: 12px; font-weight: bold; border-radius: 8px; }
        .form-control { padding: 12px; border-radius: 8px; }
    </style>
</head>
<body>

<div class="card login-card shadow-lg">
    <div class="card-header">
        <h4 class="mb-0"><i class="bi bi-shield-lock"></i> Manutenção Hosp</h4>
        <small>Controle de Chamados e Ativos</small>
    </div>
    <div class="card-body p-4 bg-white">

        <?php if($erro): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div><?= $erro ?></div>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['url_redirecionamento'])): ?>
            <div class="alert alert-info py-2 small shadow-sm border-0 mb-3">
                <i class="bi bi-qr-code-scan me-1"></i> Faça login para ver o histórico do ativo.
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label fw-bold">Usuário</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                    <input type="text" name="login" class="form-control" placeholder="Digite seu login" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Senha</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                    <input type="password" name="senha" class="form-control" placeholder="Digite sua senha" required>
                </div>
            </div>

            <button type="submit" name="btn_login" class="btn btn-primary w-100 shadow-sm mt-2">
                Acessar Sistema <i class="bi bi-box-arrow-in-right ms-2"></i>
            </button>
        </form>
    </div>
    <div class="card-footer bg-light text-center py-3">
        <small class="text-muted">&copy; 2026 Sistema de Gestão Hospitalar</small>
    </div>
</div>

</body>
</html>
