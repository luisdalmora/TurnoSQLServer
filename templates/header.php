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
            if (preg_match('/^(http:\/\/|https:\/\/|\/\/)/i', $cssFile)) {
                echo '<link href="' . htmlspecialchars($cssFile) . '" rel="stylesheet">' . "\n";
            } else {
                echo '<link href="' . BASE_URL . htmlspecialchars($cssFile) . '" rel="stylesheet">' . "\n";
            }
        }
    }
    ?>
    <style>
        /* Estilo inicial para fade-in da página */
        .body-fade-in {
            opacity: 0;
            transition: opacity 0.4s ease-in-out; /* Duração e timing da animação */
        }
        .body-visible {
            opacity: 1;
        }

        /* Estilos para o modal de edição (movido de gerenciar_colaboradores.php para ser global se necessário, ou mantenha específico) */
        /* Se for apenas para colaboradores, pode ficar no CSS daquela página ou Tailwind no HTML */
        #edit-collaborator-modal.hidden {
            display: none;
        }
         #edit-collaborator-modal {
            display: flex; /* Para centralizar com items-center justify-center */
        }
        #edit-collaborator-modal-content {
            transition-property: transform, opacity;
            transition-duration: 300ms; /* Ajuste conforme preferência */
            transition-timing-function: ease-in-out;
        }

        /* Para o modal de backup (da home.php) */
        #backup-modal-backdrop.hidden {
            display: none;
        }
        #backup-modal-backdrop {
             display: flex; /* Para centralizar com items-center justify-center */
        }
        .modal-content-backup { /* Nome da classe no HTML da home.php */
            transition-property: transform, opacity;
            transition-duration: 300ms;
            transition-timing-function: ease-in-out;
        }

    </style>
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
                  Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?>
                  <i data-lucide="circle-user-round" class="w-5 h-5 md:w-6 md:h-6 ml-2 text-blue-600" data-tooltip-text="Usuário Logado: <?php echo htmlspecialchars($nomeUsuarioLogado); ?>"></i>
                </div>
            </header>
            <main class="flex-grow p-4 md:p-6">
<?php
// O restante do conteúdo da página será incluído aqui
// A tag <body> e <html> são fechadas no footer.php
?>