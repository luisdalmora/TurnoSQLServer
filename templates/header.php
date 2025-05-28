<?php
// templates/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start(); 
}

require_once __DIR__ . '/../config/config.php'; 

if (!defined('BASE_URL')) {
    define('BASE_URL', SITE_URL); 
}

$pageTitle = $pageTitle ?? 'Sim Posto'; 
$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';
$csrfTokenBackup = $_SESSION['csrf_token_backup'] ?? ''; 

$USUARIO_ATUAL_ROLE = $_SESSION['usuario_role'] ?? 'user'; 

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['usuario_role']) && $_SESSION['usuario_role'] === 'admin';
    }
}

if (!function_exists('can')) {
    function can($action, $resource, $resourceOwnerId = null) {
        $role = $_SESSION['usuario_role'] ?? 'guest'; 
        $currentUserId = $_SESSION['usuario_id'] ?? null;
        $permissions = [
            'admin' => [
                'turnos' => ['criar', 'ler_proprio', 'atualizar_proprio', 'excluir_proprio', 'ler_todos', 'gerenciar_todos'],
                'ausencias' => ['criar', 'ler_proprio', 'atualizar_proprio', 'excluir_proprio', 'ler_todos', 'gerenciar_todos'],
                'colaboradores' => ['criar', 'ler', 'atualizar', 'excluir', 'gerenciar'],
                'scripts' => ['criar', 'ler_proprio', 'atualizar_proprio', 'excluir_proprio', 'ler_todos', 'gerenciar_todos'],
                'relatorios' => ['visualizar'],
                'observacoes_gerais' => ['ler', 'editar'], // Mantido, caso seja usado em outra página
                'backup' => ['executar'],
                'sistema' => ['acessar_admin_geral'] 
            ],
            'user' => [ 
                'turnos' => ['criar', 'ler_proprio', 'atualizar_proprio', 'excluir_proprio'],
                'ausencias' => ['criar', 'ler_proprio', 'atualizar_proprio', 'excluir_proprio'],
                'scripts' => ['criar', 'ler_proprio', 'atualizar_proprio', 'excluir_proprio'], 
                'observacoes_gerais' => ['ler'],
                'relatorios' => [], 
                'colaboradores' => [],
                'backup' => [],
                'sistema' => []
            ],
            'guest' => [ 
                'turnos' => [], 'ausencias' => [], 'colaboradores' => [], 'scripts' => [],
                'relatorios' => [], 'observacoes_gerais' => [], 'backup' => [], 'sistema' => []
            ]
        ];
        if (!isset($permissions[$role]) || !isset($permissions[$role][$resource])) {
            return false; 
        }
        if (str_ends_with($action, '_proprio')) {
            if ($role === 'admin' && in_array('gerenciar_todos', $permissions[$role][$resource])) {
                $baseAction = str_replace('_proprio', '', $action);
                return in_array($baseAction, $permissions[$role][$resource]);
            }
            if ($resourceOwnerId !== null && $currentUserId !== null && (int)$resourceOwnerId === (int)$currentUserId) {
                $baseAction = str_replace('_proprio', '', $action);
                return in_array($baseAction, $permissions[$role][$resource]);
            }
            return false; 
        }
        if (in_array($action, $permissions[$role][$resource]) || 
            in_array('gerenciar', $permissions[$role][$resource]) || 
            in_array('gerenciar_todos', $permissions[$role][$resource])) {
            return true;
        }
        if ($role === 'admin' && $action === 'visualizar_pagina_admin' && in_array('acessar_admin_geral', $permissions['admin']['sistema'])) {
            if (in_array($resource, ['colaboradores', 'scripts', 'relatorios', 'backup_pagina'])) {
                return true;
            }
        }
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Sim Posto</title>
    <link href="<?php echo BASE_URL; ?>/src/output.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <?php
    if (isset($pageSpecificCss) && is_array($pageSpecificCss)) {
        foreach ($pageSpecificCss as $cssFile) {
            if (preg_match('/^(http:\/\/|https:\/\/|\/\/)/i', $cssFile)) {
                echo '<link href="' . htmlspecialchars($cssFile) . '" rel="stylesheet">' . "\n";
            } else {
                echo '<link href="' . BASE_URL . '/' . ltrim(htmlspecialchars($cssFile), '/') . '" rel="stylesheet">' . "\n";
            }
        }
    }
    ?>
    <style>
        body {
            background-color: #f3f4f6; /* Exemplo: bg-slate-100 ou bg-gray-100 */
            color: #374151; /* Exemplo: text-slate-700 ou text-gray-700 */
        }
        .body-fade-in { opacity: 0; transition: opacity 0.4s ease-in-out; }
        .body-visible { opacity: 1; }
        #edit-collaborator-modal.hidden, #backup-modal-backdrop.hidden { display: none; }
        #edit-collaborator-modal-content, .modal-content-backup {
            transition-property: transform, opacity;
            transition-duration: 300ms;
            transition-timing-function: ease-in-out;
        }
        /* Estilos para a sidebar móvel */
        @media (max-width: 767px) { /* Ajustado para breakpoint 'md' do Tailwind (768px) */
            .mobile-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 40; 
            }
            .mobile-sidebar.open {
                transform: translateX(0);
            }
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 30; 
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
            }
            .sidebar-overlay.open {
                opacity: 1;
                visibility: visible;
            }
        }
        /* Estilo para scrollbar fina (aplicar com a classe .custom-scrollbar-thin) */
        .custom-scrollbar-thin::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar-thin::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .custom-scrollbar-thin::-webkit-scrollbar-thumb {
            background: #c1c1c1; /* Cinza claro para o polegar */
            border-radius: 10px;
        }
        .custom-scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1; /* Cinza um pouco mais escuro no hover */
        }
    </style>
    <script>
        window.APP_USER_ROLE = "<?php echo htmlspecialchars($USUARIO_ATUAL_ROLE, ENT_QUOTES, 'UTF-8'); ?>";
        window.APP_USER_ID = <?php echo isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 'null'; ?>;
        window.BASE_URL = "<?php echo BASE_URL; ?>"; 
    </script>
</head>
<body class="bg-slate-100 font-poppins text-slate-700 body-fade-in"> 
    <div id="sidebar-overlay" class="sidebar-overlay md:hidden"></div>
    <div class="flex h-screen overflow-hidden"> 
        <?php
        $currentPage = $currentPage ?? ''; 
        require_once __DIR__ . '/sidebar.php'; 
        ?>
        <div class="flex-grow flex flex-col overflow-y-auto"> 
            <header class="h-16 bg-white shadow-md flex items-center justify-between px-4 md:px-6 flex-shrink-0 sticky top-0 z-20"> 
                <div class="flex items-center">
                    <button id="mobile-menu-button" class="md:hidden mr-3 text-slate-600 hover:text-slate-800 p-2 -ml-2" aria-label="Abrir menu">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <?php echo $headerIcon ?? '<i data-lucide="file" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-sky-600"></i>'; // Cor do ícone principal suave ?>
                    <h1 class="text-md md:text-lg font-semibold text-slate-800"><?php echo htmlspecialchars($pageTitle); ?></h1>
                </div>
                <div id="user-info" class="flex items-center text-sm font-medium text-slate-700">
                  Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?> (<?php echo htmlspecialchars(ucfirst($USUARIO_ATUAL_ROLE)); ?>)
                  <i data-lucide="circle-user-round" class="w-5 h-5 md:w-6 md:h-6 ml-2 text-sky-600" data-tooltip-text="Usuário Logado: <?php echo htmlspecialchars($nomeUsuarioLogado); ?>"></i>
                </div>
            </header>
            <main class="flex-grow p-4 md:p-6">