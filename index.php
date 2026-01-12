<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}
include 'includes/db.php'; 

$nivel = $_SESSION['usuario_nivel'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Manutenção</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { display: flex; min-height: 100vh; background-color: #f8f9fa; }
        #sidebar { width: 250px; background: #212529; color: white; transition: 0.3s; display: flex; flex-direction: column; }
        #content { flex: 1; padding: 20px; }
        .nav-link { color: #adb5bd; margin: 5px 0; }
        .nav-link:hover, .nav-link.active { color: white; background: #343a40; }
        .sidebar-header { padding: 20px; font-weight: bold; font-size: 1.2rem; border-bottom: 1px solid #444; }
    </style>
</head>
<body>

<nav id="sidebar">
    <div class="p-3 border-bottom border-secondary mb-2">
        <small class="text-muted d-block">Bem-vindo,</small>
        <span class="fw-bold"><?= $_SESSION['usuario_nome'] ?></span>
        <br>
        <span class="badge bg-secondary" style="font-size: 0.7rem;"><?= ucfirst($nivel) ?></span>
    </div>

    <div class="sidebar-header">
        <i class="bi bi-tools"></i> MNT Setor
    </div>
    
    <ul class="nav flex-column p-2 flex-grow-1">
        
        <?php if ($nivel !== 'usuario'): ?>
        <li class="nav-item">
            <a href="index.php?p=dashboard" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
        </li>
        <?php endif; ?>

        <?php if (in_array($nivel, ['admin', 'coordenador'])): ?>
        <li class="nav-item">
            <a href="index.php?p=equipamentos" class="nav-link"><i class="bi bi-pc-display"></i> Equipamentos</a>
        </li>
        <?php endif; ?>

        <li class="nav-item">
            <a href="index.php?p=chamados" class="nav-link"><i class="bi bi-ticket-perforated"></i> Chamados</a>
        </li>

        <?php if (in_array($nivel, ['admin', 'coordenador'])): ?>
        <hr class="text-secondary">
        <li class="nav-item">
            <a href="index.php?p=setores" class="nav-link"><i class="bi bi-geo-alt"></i> Setores</a>
        </li>
        <li class="nav-item border-bottom border-secondary pb-2 mb-2">
            <a href="index.php?p=tipos_equipamentos" class="nav-link"><i class="bi bi-tags"></i> Tipos de Equip.</a>
        </li>
        <?php endif; ?>

        <?php if ($nivel == 'admin'): ?>
        <li class="nav-item">
            <a href="index.php?p=usuarios" class="nav-link text-info">
                <i class="bi bi-people"></i> Gestão de Usuários
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item mt-auto">
            <a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-left"></i> Sair</a>
        </li>
    </ul>
</nav>

<div id="content">
    <?php
        // Define a página inicial padrão baseada no nível do usuário
        $default_page = ($nivel === 'usuario') ? 'chamados' : 'dashboard';
        
        $pagina = isset($_GET['p']) ? $_GET['p'] : $default_page;
        
        // --- SISTEMA DE PERMISSÕES DE ACESSO ---
        
        // Páginas que COORDENADOR e ADMIN podem acessar, mas TÉCNICO e USUÁRIO não
        $paginas_gerenciais = ['equipamentos', 'setores', 'tipos_equipamentos', 'editar_equipamento', 'excluir_equipamento'];
        
        // Bloqueio para Usuário Comum
        $paginas_proibidas_usuario = ['dashboard', 'equipamentos', 'setores', 'usuarios', 'tratar_chamado', 'tipos_equipamentos'];
        
        if ($nivel === 'usuario' && in_array($pagina, $paginas_proibidas_usuario)) {
            $pagina = 'chamados';
        }

        // Bloqueio para Técnico (não acessa áreas de configuração/equipamentos)
        if ($nivel === 'tecnico' && in_array($pagina, $paginas_gerenciais)) {
            $pagina = 'dashboard';
        }

        // Bloqueio EXCLUSIVO para Coordenador e Técnico (Gestão de Usuários)
        if ($pagina === 'usuarios' && $nivel !== 'admin') {
            echo "<div class='alert alert-danger fw-bold'><i class='bi bi-shield-lock'></i> Acesso negado: Apenas administradores podem gerenciar usuários.</div>";
            $pagina = null; // Impede o include abaixo
        }

        if ($pagina) {
            $arquivo = $pagina . ".php";
            if (file_exists($arquivo)) {
                include($arquivo);
            } else {
                echo "<h3>Página não encontrada!</h3>";
            }
        }
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
