<?php
// gerenciar_scripts.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!defined('BASE_URL_REDIRECT')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $project_root_web_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
     if ($project_root_web_path === '/' || $project_root_web_path === '\\') { $project_root_web_path = ''; }
    define('BASE_URL_REDIRECT', rtrim($protocol . $host . $project_root_web_path, '/'));
}


if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) { 
    header('Location: ' . BASE_URL_REDIRECT . '/index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}

require_once __DIR__ . '/templates/header.php'; // Define can()

// Permissão para acessar a página de gerenciamento de scripts
// O usuário precisa poder ler seus próprios scripts ou gerenciar todos
if (!can('ler_proprio', 'scripts') && !can('gerenciar_todos', 'scripts')) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Acesso negado a esta página.'];
    header('Location: ' . BASE_URL . '/home.php');
    exit;
}

$pageTitle = 'Gerenciar Scripts'; 
$currentPage = 'scripts'; // Para destacar na sidebar
$headerIcon = '<i data-lucide="file-code-2" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600"></i>'; //

$csrfTokenScriptsManage = $_SESSION['csrf_token_scripts_manage'] ?? ''; //
if (empty($csrfTokenScriptsManage) && array_key_exists('csrf_token_scripts_manage',$_SESSION)) {
    $_SESSION['csrf_token_scripts_manage'] = bin2hex(random_bytes(32));
    $csrfTokenScriptsManage = $_SESSION['csrf_token_scripts_manage'];
} elseif (empty($csrfTokenScriptsManage) && !array_key_exists('csrf_token_scripts_manage',$_SESSION)) {
    $_SESSION['csrf_token_scripts_manage'] = bin2hex(random_bytes(32));
    $csrfTokenScriptsManage = $_SESSION['csrf_token_scripts_manage'];
}
?>

<?php // Formulário de Adicionar/Editar só aparece se o usuário puder criar ou atualizar seus próprios scripts
if (can('criar', 'scripts') || can('atualizar_proprio', 'scripts')): ?>
<section class="bg-white p-4 md:p-6 rounded-lg shadow">
    <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
        <i data-lucide="plus-circle" class="w-5 h-5 mr-2 text-blue-600"></i> Adicionar/Editar Script
    </h2>
    <form id="form-novo-script">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfTokenScriptsManage); ?>">
        <input type="hidden" name="script_id" id="script_id" value="">
        <div class="mb-4">
            <label for="script-titulo" class="block text-sm font-medium text-gray-700 mb-1">Título:</label>
            <input type="text" id="script-titulo" name="titulo" class="form-input block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
        </div>
        <div class="mb-4">
            <label for="script-conteudo" class="block text-sm font-medium text-gray-700 mb-1">Conteúdo:</label>
            <textarea id="script-conteudo" name="conteudo" rows="8" class="form-textarea block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono" required></textarea>
        </div>
        <div>
            <button type="submit" id="btn-salvar-script" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i data-lucide="save" class="w-4 h-4 mr-2"></i> Salvar Script
            </button>
            <button type="button" id="btn-limpar-formulario-script" class="ml-2 inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" style="display: none;">
                <i data-lucide="rotate-ccw" class="w-4 h-4 mr-2"></i> Cancelar Edição
            </button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="mt-6 bg-white p-4 md:p-6 rounded-lg shadow">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center">
            <i data-lucide="list-filter" class="w-5 h-5 mr-2 text-blue-600"></i> Scripts Salvos
            <?php if (! (can('criar', 'scripts') || can('atualizar_proprio', 'scripts')) ): ?>
                 (Modo Somente Leitura)
            <?php endif; ?>
        </h2>
        <div class="relative w-full sm:w-72">
            <input type="text" id="input-pesquisa-script" placeholder="Pesquisar scripts..." class="form-input block w-full pl-10 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i data-lucide="search" class="w-5 h-5 text-gray-400"></i>
            </div>
        </div>
    </div>
    <div id="lista-scripts-container" class="overflow-x-auto">
        <p class="text-center text-gray-500">Carregando scripts...</p>
    </div>
</section>

<?php
$pageSpecificJs = ['/public/js/page_specific/gerenciar_scripts.js'];
require_once __DIR__ . '/templates/footer.php';
?>