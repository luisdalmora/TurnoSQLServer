<?php
// gerenciar_scripts.php
require_once __DIR__ . '/config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sessão expirada ou acesso negado.', 'action' => 'redirect', 'location' => 'index.html']);
        exit;
    }
    header('Location: index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}

$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';

if (empty($_SESSION['csrf_token_scripts_manage'])) {
    $_SESSION['csrf_token_scripts_manage'] = bin2hex(random_bytes(32));
}
$csrfTokenScriptsManage = $_SESSION['csrf_token_scripts_manage'];

?>
<!DOCTYPE html>
<html lang="pt-BR" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Scripts - Sim Posto</title>
    <link href="src/output.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .script-content-display {
            white-space: pre-wrap; word-wrap: break-word; font-family: monospace;
            background-color: #f8f9fa; border: 1px solid #dee2e6;
            padding: 10px; border-radius: 0.25rem; max-height: 200px; overflow-y: auto;
        }
        html.dark .script-content-display {
            background-color: #1f2937; /* bg-gray-800 */ border-color: #4b5563; /* border-gray-600 */ color: #d1d5db; /* text-gray-300 */
        }
    </style>
</head>
<body class="bg-gray-100 font-poppins text-gray-700 dark:bg-gray-900 dark:text-gray-200 transition-colors duration-300">
    <div class="flex h-screen overflow-hidden">
        <aside class="w-64 bg-gradient-to-b from-blue-800 to-blue-700 text-indigo-100 flex flex-col flex-shrink-0 dark:from-gray-800 dark:to-gray-900 transition-colors duration-300">
            <div class="h-16 flex items-center px-4 md:px-6 border-b border-white/10 dark:border-gray-700">
                <i data-lucide="gauge-circle" class="w-7 h-7 md:w-8 md:h-8 mr-2 md:mr-3 text-white"></i>
                <h2 class="text-lg md:text-xl font-semibold text-white">Sim Posto</h2>
            </div>
            <nav class="flex-grow p-2 space-y-1">
                <a href="home.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm dark:hover:bg-blue-600">
                    <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i> Dashboard
                </a>
                <a href="relatorio_turnos.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm dark:hover:bg-blue-600">
                    <i data-lucide="file-text" class="w-5 h-5 mr-3"></i> Relatórios
                </a>
                <a href="gerenciar_colaboradores.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm dark:hover:bg-blue-600">
                    <i data-lucide="users" class="w-5 h-5 mr-3"></i> Colaboradores
                </a>
                <a href="gerador_senhas.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm dark:hover:bg-blue-600">
                    <i data-lucide="key-round" class="w-5 h-5 mr-3"></i> Gerador de Senhas
                </a>
                <a href="gerenciar_scripts.php" class="flex items-center px-3 py-2.5 rounded-lg bg-blue-600 text-white font-medium text-sm dark:bg-blue-700 dark:hover:bg-blue-600">
                    <i data-lucide="file-code-2" class="w-5 h-5 mr-3"></i> Scripts
                </a>
            </nav>
            <div class="p-2 border-t border-white/10 dark:border-gray-700">
                 <div class="px-2 py-1 space-y-1.5">
                    <?php
                    if (isset($_SESSION['csrf_token_backup'])) {
                        echo '<input type="hidden" id="csrf-token-backup" value="' . htmlspecialchars($_SESSION['csrf_token_backup']) . '">';
                        echo '<a href="#" id="backup-db-btn" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-teal-500 hover:bg-teal-600 text-white font-medium transition-colors text-sm dark:bg-teal-600 dark:hover:bg-teal-500">';
                        echo '<i data-lucide="database-backup" class="w-4 h-4 mr-2"></i> Backup BD';
                        echo '</a>';
                    }
                    ?>
                </div>
                <div class="px-2 py-1 mt-1.5">
                    <a href="logout.php" id="logout-link" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-red-500 hover:bg-red-600 text-white font-medium transition-colors text-sm dark:bg-red-600 dark:hover:bg-red-500">
                        <i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Sair
                    </a>
                </div>
            </div>
        </aside>

        <div class="flex-grow flex flex-col overflow-y-auto">
            <header class="h-16 bg-white shadow-sm flex items-center justify-between px-4 md:px-6 flex-shrink-0 dark:bg-gray-800 dark:border-b dark:border-gray-700 transition-colors duration-300">
                <div class="flex items-center">
                    <i data-lucide="file-code-2" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600 dark:text-blue-400"></i>
                    <h1 class="text-md md:text-lg font-semibold text-gray-800 dark:text-gray-100">Gerenciar Scripts</h1>
                </div>
                <div class="flex items-center">
                    <button id="darkModeToggle" title="Alternar tema" class="mr-4 p-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-blue-400 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i data-lucide="moon" class="w-5 h-5"></i>
                        <i data-lucide="sun" class="w-5 h-5 hidden"></i>
                    </button>
                    <div id="user-info" class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300">
                        Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?>
                        <i data-lucide="circle-user-round" class="w-5 h-5 md:w-6 md:h-6 ml-2 text-blue-600 dark:text-blue-400"></i>
                    </div>
                </div>
            </header>

            <main class="flex-grow p-4 md:p-6 space-y-6">
                <section class="bg-white p-4 md:p-6 rounded-lg shadow dark:bg-gray-800 dark:border dark:border-gray-700 transition-colors duration-300">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center">
                        <i data-lucide="plus-circle" class="w-5 h-5 mr-2 text-blue-600 dark:text-blue-400"></i> Adicionar/Editar Script
                    </h2>
                    <form id="form-novo-script">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfTokenScriptsManage); ?>">
                        <input type="hidden" name="script_id" id="script_id" value="">
                        
                        <div class="mb-4">
                            <label for="script-titulo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Título do Script:</label>
                            <input type="text" id="script-titulo" name="titulo" class="form-input block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:placeholder-gray-400 dark:focus:ring-indigo-600 dark:focus:border-indigo-600" required>
                        </div>
                        <div class="mb-4">
                            <label for="script-conteudo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Conteúdo do Script:</label>
                            <textarea id="script-conteudo" name="conteudo" rows="8" class="form-textarea block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:placeholder-gray-400 dark:focus:ring-indigo-600 dark:focus:border-indigo-600" required></textarea>
                        </div>
                        <div>
                            <button type="submit" id="btn-salvar-script" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-700 dark:hover:bg-blue-600 dark:focus:ring-offset-gray-800">
                                <i data-lucide="save" class="w-4 h-4 mr-2"></i> Salvar Script
                            </button>
                            <button type="button" id="btn-limpar-formulario-script" class="ml-2 inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500 dark:border-gray-500 dark:focus:ring-offset-gray-800" style="display: none;">
                                <i data-lucide="rotate-ccw" class="w-4 h-4 mr-2"></i> Cancelar Edição
                            </button>
                        </div>
                    </form>
                </section>

                <section class="bg-white p-4 md:p-6 rounded-lg shadow dark:bg-gray-800 dark:border dark:border-gray-700 transition-colors duration-300">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 flex items-center">
                            <i data-lucide="list-filter" class="w-5 h-5 mr-2 text-blue-600 dark:text-blue-400"></i> Scripts Salvos
                        </h2>
                        <div class="relative w-full sm:w-72">
                            <input type="text" id="input-pesquisa-script" placeholder="Pesquisar por título ou conteúdo..." class="form-input block w-full pl-10 pr-3 py-2 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:placeholder-gray-400 dark:focus:ring-indigo-600 dark:focus:border-indigo-600">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="search" class="w-5 h-5 text-gray-400 dark:text-gray-500"></i>
                            </div>
                        </div>
                    </div>
                    <div id="lista-scripts-container" class="overflow-x-auto">
                        <p class="text-center text-gray-500 dark:text-gray-400">Carregando scripts...</p>
                    </div>
                </section>
            </main>
        </div>
    </div>
    
    <script src="src/js/main.js" type="module"></script> 
    <script src="src/js/gerenciar_scripts.js" type="module"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>