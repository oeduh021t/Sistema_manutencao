<?php
include 'includes/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$mensagem = '';

if (isset($_POST['salvar_senha'])) {
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirma_senha = $_POST['confirma_senha'];

    // Buscar senha atual no banco
    $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuario = $stmt->fetch();

    if (!password_verify($senha_atual, $usuario['senha'])) {
        $mensagem = "<div class='alert alert-danger'>Senha atual incorreta.</div>";
    } elseif ($nova_senha !== $confirma_senha) {
        $mensagem = "<div class='alert alert-danger'>As senhas não coincidem.</div>";
    } else {
        $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")->execute([$hash, $_SESSION['usuario_id']]);
        $mensagem = "<div class='alert alert-success'>Senha alterada com sucesso!</div>";
    }
}
?>

<div class="container mt-4">
    <h3><i class="bi bi-key-fill"></i> Trocar Senha</h3>
    <?= $mensagem ?>
    <form method="POST" class="mt-3" style="max-width:400px;">
        <div class="mb-3">
            <label class="form-label">Senha Atual</label>
            <input type="password" name="senha_atual" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Nova Senha</label>
            <input type="password" name="nova_senha" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Confirmar Nova Senha</label>
            <input type="password" name="confirma_senha" class="form-control" required>
        </div>
        <button type="submit" name="salvar_senha" class="btn btn-warning w-100">Salvar</button>
    </form>
</div>
