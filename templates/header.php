<?php
// templates/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start(); 
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

// Tenta determinar o diretório base do projeto de forma mais robusta
// Se __DIR__ é C:\xampp\htdocs\turno\templates
// e DOCUMENT_ROOT é C:\xampp\htdocs
// Então o caminho relativo a partir da raiz do servidor é /turno
$project_path_on_server = str_replace(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '', str_replace('\\', '/', dirname(__DIR__)));
if (strpos($project_path_on_server, '/') !== 0) {
    $project_path_on_server = '/' . $project_path_on_server;
}
$project_base_path = rtrim($project_path_on_server, '/');

define('BASE_URL', rtrim($protocol . $host . $project_base_path, '/'));

require_once __DIR__ . '/../config/config.php';

$pageTitle = $pageTitle ?? 'Sim Posto'; 
$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';
$csrfTokenBackup = $_SESSION['csrf_token_backup'] ?? ''; 
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
            // Verifica se é uma URL completa (CDN) ou um caminho local
            if (preg_match('/^(http:\/\/|https:\/\/|\/\/)/i', $cssFile)) {
                echo '<link href="' . htmlspecialchars($cssFile) . '" rel="stylesheet">' . "\n";
            } else {
                echo '<link href="' . BASE_URL . htmlspecialchars($cssFile) . '" rel="stylesheet">' . "\n";
            }
        }
    }
    ?>
</head>
<body class="bg-gray-100 font-poppins text-gray-700">
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
                  Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?>
                  <i data-lucide="circle-user-round" class="w-5 h-5 md:w-6 md:h-6 ml-2 text-blue-600"></i>
                </div>
            </header>
            <main class="flex-grow p-4 md:p-6">
                ```

**`TurnoSQLServer/templates/sidebar.php`**
```php
<?php
// templates/sidebar.php
$csrfTokenBackup = $_SESSION['csrf_token_backup'] ?? '';
$currentPage = $currentPage ?? ''; // Vem do arquivo que inclui o header/sidebar
?>
<aside class="w-64 bg-gradient-to-b from-blue-800 to-blue-700 text-indigo-100 flex flex-col flex-shrink-0">
    <div class="h-16 flex items-center px-4 md:px-6 border-b border-white/10">
        <i data-lucide="gauge-circle" class="w-7 h-7 md:w-8 md:h-8 mr-2 md:mr-3 text-white"></i>
        <h2 class="text-lg md:text-xl font-semibold text-white">Sim Posto</h2>
    </div>
    <nav class="flex-grow p-2 space-y-1">
        <a href="<?php echo BASE_URL; ?>/home.php" class="flex items-center px-3 py-2.5 rounded-lg transition-colors text-sm <?php echo ($currentPage === 'home') ? 'bg-blue-600 text-white font-medium' : 'hover:bg-blue-500 hover:text-white'; ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i> Dashboard
        </a>
        <a href="<?php echo BASE_URL; ?>/relatorio_turnos.php" class="flex items-center px-3 py-2.5 rounded-lg transition-colors text-sm <?php echo ($currentPage === 'relatorios') ? 'bg-blue-600 text-white font-medium' : 'hover:bg-blue-500 hover:text-white'; ?>">
            <i data-lucide="file-text" class="w-5 h-5 mr-3"></i> Relatórios
        </a>
        <a href="<?php echo BASE_URL; ?>/gerenciar_colaboradores.php" class="flex items-center px-3 py-2.5 rounded-lg transition-colors text-sm <?php echo ($currentPage === 'colaboradores') ? 'bg-blue-600 text-white font-medium' : 'hover:bg-blue-500 hover:text-white'; ?>">
            <i data-lucide="users" class="w-5 h-5 mr-3"></i> Colaboradores
        </a>
        <a href="<?php echo BASE_URL; ?>/gerador_senhas.php" class="flex items-center px-3 py-2.5 rounded-lg transition-colors text-sm <?php echo ($currentPage === 'gerador_senhas') ? 'bg-blue-600 text-white font-medium' : 'hover:bg-blue-500 hover:text-white'; ?>">
            <i data-lucide="key-round" class="w-5 h-5 mr-3"></i> Gerador de Senhas
        </a>
        <a href="<?php echo BASE_URL; ?>/gerenciar_scripts.php" class="flex items-center px-3 py-2.5 rounded-lg transition-colors text-sm <?php echo ($currentPage === 'scripts') ? 'bg-blue-600 text-white font-medium' : 'hover:bg-blue-500 hover:text-white'; ?>">
            <i data-lucide="file-code-2" class="w-5 h-5 mr-3"></i> Scripts
        </a>
    </nav>
    <div class="p-2 border-t border-white/10">
         <div class="px-2 py-1 space-y-1.5">
            <?php if (!empty($csrfTokenBackup)): ?>
                <input type="hidden" id="csrf-token-backup" value="<?php echo htmlspecialchars($csrfTokenBackup); ?>">
                <a href="#" id="backup-db-btn" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-teal-500 hover:bg-teal-600 text-white font-medium transition-colors text-sm">
                    <i data-lucide="database-backup" class="w-4 h-4 mr-2"></i> Backup BD
                </a>
            <?php endif; ?>
        </div>
        <div class="px-2 py-1 mt-1.5">
            <a href="<?php echo BASE_URL; ?>/logout.php" id="logout-link" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-red-500 hover:bg-red-600 text-white font-medium transition-colors text-sm">
                <i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Sair
            </a>
        </div>
    </div>
</aside>