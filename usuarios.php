<?php
include_once 'includes/db.php';

if ($_SESSION['usuario_nivel'] != 'admin') {
    echo "<div class='alert alert-danger'>Acesso negado. Apenas administradores podem gerenciar usuários.</div>";
    exit;
}

if (isset($_POST['salvar_usuario'])) {
    $nome  = $_POST['nome'];
    $login = $_POST['login'];
    $nivel = $_POST['nivel'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, login, senha, nivel) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nome, $login, $senha, $nivel]);
        echo "<div class='alert alert-success mt-2'>Usuário '$login' criado com sucesso!</div>";
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger mt-2'>Erro: Este login já está em uso.</div>";
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id != $_SESSION['usuario_id']) {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
        echo "<script>window.location.href='index.php?p=usuarios';</script>";
    }
}

$usuarios = $pdo->query("SELECT id, nome, login, nivel FROM usuarios ORDER BY nome ASC")->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <h2><i class="bi bi-people"></i> Gestão de Usuários</h2>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalUsuario">
            <i class="bi bi-person-plus"></i> Novo Usuário
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Nome</th>
                        <th>Login</th>
                        <th>Nível</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td class="ps-4"><strong><?= htmlspecialchars($u['nome']) ?></strong></td>
                        <td><code><?= htmlspecialchars($u['login']) ?></code></td>
                        <td>
                            <?php 
                                $badge = "bg-info text-dark";
                                if($u['nivel'] == 'admin') $badge = "bg-dark";
                                if($u['nivel'] == 'coordenador') $badge = "bg-primary";
                            ?>
                            <span class="badge <?= $badge ?>">
                                <?= ucfirst($u['nivel']) ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <?php if ($u['id'] != $_SESSION['usuario_id']): ?>
                                <a href="index.php?p=usuarios&delete=<?= $u['id'] ?>" 
                                   class="btn btn-sm btn-outline-danger border-0" 
                                   onclick="return confirm('Excluir este usuário?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow" method="POST">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Cadastrar Novo Usuário</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Nome Completo</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Login</label>
                    <input type="text" name="login" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Senha Inicial</label>
                    <input type="password" name="senha" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nível de Acesso</label>
                    <select name="nivel" class="form-select">
                        <option value="usuario">Solicitante (Usuário)</option>
                        <option value="tecnico">Técnico de Manutenção</option>
                        <option value="coordenador">Coordenador (Gestor)</option>
                        <option value="admin">Administrador do Sistema</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="submit" name="salvar_usuario" class="btn btn-primary w-100 shadow">Criar Conta</button>
            </div>
        </form>
    </div>
</div>
