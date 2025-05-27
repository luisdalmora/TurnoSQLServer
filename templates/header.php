<?php
// templates/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start(); 
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

$project_path_on_server = str_replace(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '', str_replace('\\', '/', dirname(__DIR__)));
if (strpos($project_path_on_server, '/') !== 0) {
    $project_path_on_server = '/' . $project_path_on_server;
}
$project_base_path = rtrim($project_path_on_server, '/');

// Garante que BASE_URL é definida apenas uma vez
if (!defined('BASE_URL')) {
    define('BASE_URL', rtrim($protocol . $host . $project_base_path, '/'));
}

require_once __DIR__ . '/../config/config.php';

$pageTitle = $pageTitle ?? 'Sim Posto'; 
$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';
$csrfTokenBackup = $_SESSION['csrf_token_backup'] ?? ''; 

// Variável para verificação de permissão e função auxiliar
$USUARIO_ATUAL_ROLE = $_SESSION['usuario_role'] ?? 'user'; // Default para 'user' se não estiver setado

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['usuario_role']) && $_SESSION['usuario_role'] === 'admin';
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
                echo '<link href="' . BASE_URL . htmlspecialchars($cssFile) . '" rel="stylesheet">' . "\n";
            }
        }
    }
    ?>
    <style>
        .body-fade-in { opacity: 0; transition: opacity 0.4s ease-in-out; }
        .body-visible { opacity: 1; }
        #edit-collaborator-modal.hidden, #backup-modal-backdrop.hidden { display: none; }
        #edit-collaborator-modal, #backup-modal-backdrop { display: flex; }
        #edit-collaborator-modal-content, .modal-content-backup {
            transition-property: transform, opacity;
            transition-duration: 300ms;
            transition-timing-function: ease-in-out;
        }
    </style>
    <script>
        window.APP_USER_ROLE = "<?php echo htmlspecialchars($USUARIO_ATUAL_ROLE, ENT_QUOTES, 'UTF-8'); ?>";
    </script>
</head>
<body class="bg-gray-100 font-poppins text-gray-700 body-fade-in">
    <div class="flex h-screen overflow-hidden">
        <?php
        $currentPage = $currentPage ?? ''; 
        require_once __DIR__ . '/sidebar.php';
        ?>
        <div class="flex-grow flex flex-col overflow-y-auto">
            <header class="h-16 bg-white shadow-sm flex items-center justify-between px-4 md:px-6 flex-shrink-0">
                <div class="flex items-center">
                    <?php echo $headerIcon ?? '<i data-lucide="file" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600"></i>'; ?>
                    <h1 class="text-md md:text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($pageTitle); ?></h1>
                </div>
                <div id="user-info" class="flex items-center text-sm font-medium text-gray-700">
                  Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?> (<?php echo htmlspecialchars(ucfirst($USUARIO_ATUAL_ROLE)); ?>)
                  <i data-lucide="circle-user-round" class="w-5 h-5 md:w-6 md:h-6 ml-2 text-blue-600" data-tooltip-text="Usuário Logado: <?php echo htmlspecialchars($nomeUsuarioLogado); ?>"></i>
                </div>
            </header>
            <main class="flex-grow p-4 md:p-6">
<?php
// templates/sidebar.php
// ...
?>