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
    <title>Controle Interno - Hospital Domingos Lourenço</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { display: flex; min-height: 100vh; background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        #sidebar { width: 280px; background: #1a1d20; color: white; transition: 0.3s; display: flex; flex-direction: column; box-shadow: 2px 0 10px rgba(0,0,0,0.2); }
        #content { flex: 1; padding: 25px; overflow-y: auto; }
        .nav-link { color: #adb5bd; padding: 12px 20px; border-radius: 8px; margin: 2px 12px; transition: all 0.2s; display: flex; align-items: center; }
        .nav-link i { font-size: 1.1rem; width: 25px; }
        .nav-link:hover { color: white; background: rgba(255,255,255,0.08); }
        .nav-link.active { color: white; background: #0d6efd !important; font-weight: 600; }
        .submenu { background: rgba(0,0,0,0.3); border-radius: 8px; margin: 0 12px 10px 12px; padding: 5px 0; }
        .submenu .nav-link { font-size: 0.85rem; padding: 8px 15px 8px 45px; margin: 0; border-radius: 0; }
        .submenu .nav-link:hover { background: rgba(255,255,255,0.05); text-decoration: underline; }
        .sidebar-header { padding: 25px 20px; font-weight: 800; font-size: 1.1rem; border-bottom: 1px solid #2d3238; color: #fff; letter-spacing: 0.5px; }
        .user-panel { padding: 15px 20px; background: rgba(255,255,255,0.03); border-bottom: 1px solid #2d3238; }
        hr { border-color: #2d3238; opacity: 0.6; margin: 10px 20px; }
    </style>
</head>
<body>

<nav id="sidebar">
    <div class="sidebar-header d-flex align-items-center">
        <i class="bi bi-shield-check text-primary me-2"></i> MNT SISTEMA
    </div>

    <div class="user-panel text-white">
        <small class="text-muted d-block" style="font-size: 0.65rem;">SESSÃO ATIVA</small>
        <span class="fw-bold d-block text-truncate"><?= $_SESSION['usuario_nome'] ?></span>
        <span class="badge bg-secondary mt-1" style="font-size: 0.6rem;"><?= strtoupper($nivel) ?></span>
    </div>

    <ul class="nav flex-column flex-grow-1 mt-2">
        
        <?php if ($nivel !== 'usuario'): ?>
        <li class="nav-item text-white">
            <a href="index.php?p=dashboard" class="nav-link <?= (isset($_GET['p']) && $_GET['p'] == 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 text-success"></i> Dashboard
            </a>
        </li>
        <?php endif; ?>

        <?php if (in_array($nivel, ['admin', 'coordenador'])): ?>
        <li class="nav-item text-white">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#collapseCadastros">
                <span><i class="bi bi-plus-square-fill text-primary"></i> Cadastros</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= in_array(($_GET['p'] ?? ''), ['equipamentos', 'fornecedores', 'setores', 'tipos_equipamentos']) ? 'show' : '' ?>" id="collapseCadastros">
                <div class="submenu">
                    <a href="index.php?p=equipamentos" class="nav-link text-white">Equipamentos</a>
                    <a href="index.php?p=fornecedores" class="nav-link text-white">Fornecedores</a>
                    <a href="index.php?p=setores" class="nav-link text-white">Setores</a>
                    <a href="index.php?p=tipos_equipamentos" class="nav-link text-white">Tipos de Equip.</a>
                </div>
            </div>
        </li>
        <?php endif; ?>

        <li class="nav-item text-white">
            <a href="index.php?p=chamados" class="nav-link <?= (isset($_GET['p']) && $_GET['p'] == 'chamados') ? 'active' : '' ?>">
                <i class="bi bi-ticket-perforated text-warning"></i> Chamados / OS
            </a>
        </li>

        <?php if (in_array($nivel, ['admin', 'coordenador'])): ?>
        <li class="nav-item text-white">
            <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#collapseAuditoria">
                <span><i class="bi bi-file-earmark-check-fill text-info"></i> Relatórios & Auditoria</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= in_array(($_GET['p'] ?? ''), ['inventario_geral', 'auditoria_custos', 'auditoria_equipamentos']) ? 'show' : '' ?>" id="collapseAuditoria">
                <div class="submenu">
                    <a href="index.php?p=inventario_geral" class="nav-link text-white">Inventário Geral</a>
                    <a href="index.php?p=auditoria_custos" class="nav-link text-white">Auditoria de Custos</a>
                    <a href="index.php?p=auditoria_equipamentos" class="nav-link text-white"> Auditoria Equipamentos</a>
                </div>
            </div>
        </li>
        <?php endif; ?>

        <hr>

        <?php if ($nivel !== 'usuario'): ?>
        <li class="nav-item text-white">
            <a href="index.php?p=relatorios" class="nav-link fw-bold <?= (isset($_GET['p']) && $_GET['p'] == 'relatorios') ? 'active' : '' ?>" style="color: #6366f1;">
                <i class="bi bi-cpu-fill" style="color: #6366f1;"></i> BI - Inteligência
            </a>
        </li>
        <?php endif; ?>

        <?php if ($nivel == 'admin'): ?>
        <li class="nav-item text-white">
            <a href="index.php?p=usuarios" class="nav-link <?= (isset($_GET['p']) && $_GET['p'] == 'usuarios') ? 'active' : '' ?>">
                <i class="bi bi-people-fill text-light"></i> Gestão de Usuários
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item mt-auto border-top border-secondary">
            <a href="logout.php" class="nav-link text-danger py-3">
                <i class="bi bi-box-arrow-left me-2"></i> Sair do Sistema
            </a>
        </li>
    </ul>
</nav>

<div id="content">
    <?php
        $default_page = ($nivel === 'usuario') ? 'chamados' : 'dashboard';
        $pagina = isset($_GET['p']) ? $_GET['p'] : $default_page;

        // --- SISTEMA DE PERMISSÕES ATUALIZADO ---
        $paginas_gerenciais = ['equipamentos', 'setores', 'tipos_equipamentos', 'fornecedores', 'relatorios', 'inventario_geral', 'auditoria_custos', 'auditoria_equipamentos'];
        $paginas_proibidas_usuario = ['dashboard', 'equipamentos', 'setores', 'usuarios', 'tratar_chamado', 'relatorios', 'fornecedores', 'inventario_geral', 'auditoria_custos', 'auditoria_equipamentos'];

        if ($nivel === 'usuario' && in_array($pagina, $paginas_proibidas_usuario)) {
            $pagina = 'chamados';
        }

        if ($nivel === 'tecnico' && in_array($pagina, ['usuarios'])) {
            $pagina = 'dashboard';
        }

        if ($pagina === 'usuarios' && $nivel !== 'admin') {
            echo "<div class='alert alert-danger fw-bold'>Acesso negado.</div>";
            $pagina = null; 
        }

        if ($pagina) {
            $arquivo = $pagina . ".php";
            if (file_exists($arquivo)) {
                include($arquivo);
            } else {
                echo "<div class='card p-5 text-center shadow-sm border-0'>
                        <i class='bi bi-folder-x display-1 text-muted'></i>
                        <h4 class='mt-3'>Módulo não encontrado</h4>
                        <p class='text-muted small'>O arquivo <b>$arquivo</b> não foi localizado no servidor.</p>
                      </div>";
            }
        }
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
