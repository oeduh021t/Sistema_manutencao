<?php
include_once 'includes/db.php';

if ($_SESSION['usuario_nivel'] != 'admin') {
    echo "<div class='alert alert-danger'>Acesso negado. Apenas administradores podem gerenciar usuários.</div>";
    exit;
}

// 1. Lógica para Criar Usuário
if (isset($_POST['salvar_usuario'])) {
    $nome  = $_POST['nome'];
    $login = $_POST['login'];
    $nivel = $_POST['nivel'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, login, senha, nivel) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nome, $login, $senha, $nivel]);
        echo "<div class='alert alert-success mt-2 shadow-sm'>Usuário '$login' criado com sucesso!</div>";
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger mt-2 shadow-sm'>Erro: Este login já está em uso.</div>";
    }
}

// 2. LÓGICA PARA EDITAR USUÁRIO
if (isset($_POST['editar_usuario'])) {
    $id    = $_POST['id_usuario'];
    $nome  = $_POST['nome'];
    $login = $_POST['login'];
    $nivel = $_POST['nivel'];
    $senha_nova = $_POST['senha_nova'];

    try {
        if (!empty($senha_nova)) {
            // Se preencheu senha, atualiza tudo inclusive a senha
            $hash = password_hash($senha_nova, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET nome=?, login=?, nivel=?, senha=? WHERE id=?");
            $stmt->execute([$nome, $login, $nivel, $hash, $id]);
        } else {
            // Se deixou senha em branco, não altera a senha atual
            $stmt = $pdo->prepare("UPDATE usuarios SET nome=?, login=?, nivel=? WHERE id=?");
            $stmt->execute([$nome, $login, $nivel, $id]);
        }
        echo "<script>window.location.href='index.php?p=usuarios&sucesso=1';</script>";
        exit;
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger mt-2 shadow-sm'>Erro ao atualizar: O login pode já estar em uso.</div>";
    }
}

// 3. Lógica para Excluir
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id != $_SESSION['usuario_id']) {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
        echo "<script>window.location.href='index.php?p=usuarios';</script>";
    }
}

$usuarios = $pdo->query("SELECT id, nome, login, nivel FROM usuarios ORDER BY nome ASC")->fetchAll();
?>

<div class="container-fluid text-dark">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <h2 class="fw-bold"><i class="bi bi-people text-primary"></i> Gestão de Usuários</h2>
        <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalUsuario">
            <i class="bi bi-person-plus"></i> Novo Usuário
        </button>
    </div>

    <?php if(isset($_GET['sucesso'])): ?>
        <div class="alert alert-success shadow-sm border-0">Alterações salvas com sucesso!</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small fw-bold text-muted">
                    <tr>
                        <th class="ps-4">NOME</th>
                        <th>LOGIN</th>
                        <th>NÍVEL</th>
                        <th class="text-end pe-4">AÇÕES</th>
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
                                if($u['nivel'] == 'coordenador') $badge = "bg-primary text-white";
                                if($u['nivel'] == 'tecnico') $badge = "bg-success text-white";
                            ?>
                            <span class="badge <?= $badge ?>">
                                <?= ucfirst($u['nivel']) ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary border-0" 
                                        onclick="abrirModalEdicao(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nome']) ?>', '<?= htmlspecialchars($u['login']) ?>', '<?= $u['nivel'] ?>')">
                                    <i class="bi bi-pencil-square"></i>
                                </button>

                                <?php if ($u['id'] != $_SESSION['usuario_id']): ?>
                                    <a href="index.php?p=usuarios&delete=<?= $u['id'] ?>" 
                                       class="btn btn-sm btn-outline-danger border-0" 
                                       onclick="return confirm('Excluir este usuário?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
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

<div class="modal fade text-dark" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow" method="POST">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Cadastrar Novo Usuário</h5>
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

<div class="modal fade text-dark" id="modalEditarUsuario" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content border-0 shadow" method="POST">
            <input type="hidden" name="id_usuario" id="edit_id">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Editar Usuário</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Nome Completo</label>
                    <input type="text" name="nome" id="edit_nome" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Login</label>
                    <input type="text" name="login" id="edit_login" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-danger">Alterar Senha (Opcional)</label>
                    <input type="password" name="senha_nova" class="form-control" placeholder="Deixe em branco para manter a atual">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nível de Acesso</label>
                    <select name="nivel" id="edit_nivel" class="form-select">
                        <option value="usuario">Solicitante (Usuário)</option>
                        <option value="tecnico">Técnico de Manutenção</option>
                        <option value="coordenador">Coordenador (Gestor)</option>
                        <option value="admin">Administrador do Sistema</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="submit" name="editar_usuario" class="btn btn-success w-100 shadow">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalEdicao(id, nome, login, nivel) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nome').value = nome;
    document.getElementById('edit_login').value = login;
    document.getElementById('edit_nivel').value = nivel;
    
    var modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
    modal.show();
}
</script>
