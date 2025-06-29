<?php
// gerenciar_colaboradores.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL_REDIRECT')) { // Define se não foi definido pelo header (acesso direto)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    // Ajuste para calcular o caminho base do projeto
    $script_path_array = explode('/', dirname($_SERVER['SCRIPT_NAME']));
    // Se estiver em subpasta, remove o último elemento (nome do script/pasta atual)
    // Isso é uma heurística e pode precisar de ajuste fino dependendo da estrutura do servidor.
    // Para este projeto, dirname(__DIR__) no header.php é mais confiável se header.php está na raiz do include path.
    $project_root_web_path = implode('/', array_slice($script_path_array, 0, count($script_path_array) - (basename(getcwd()) == basename(dirname($_SERVER['SCRIPT_NAME'])) ? 0 : 0) ));
    if ($project_root_web_path === '/' || $project_root_web_path === '\\' || $project_root_web_path === '') {
        $project_root_web_path = ''; 
    }
    define('BASE_URL_REDIRECT', rtrim($protocol . $host . $project_root_web_path, '/'));
}


if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sessão expirada ou acesso negado.', 'action' => 'redirect', 'location' => BASE_URL_REDIRECT . '/index.html']);
        exit;
    }
    header('Location: ' . BASE_URL_REDIRECT . '/index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}

$pageTitle = 'Gerenciar Colaboradores'; //
$currentPage = 'colaboradores'; //
$headerIcon = '<i data-lucide="users-cog" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600"></i>'; //

require_once __DIR__ . '/templates/header.php'; // Define can()

// Verificação de Permissão para visualizar a página
if (!can('ler', 'colaboradores')) { 
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Você não tem permissão para acessar esta página.'];
    header('Location: ' . BASE_URL . '/home.php');
    exit;
}

$csrfTokenColabManage = $_SESSION['csrf_token_colab_manage'] ?? ''; //
// A regeneração de token é melhor feita no POST/API ou quando estritamente necessário.
// Se a sessão já tiver um, usa-o. Se não, um novo será criado quando a sessão iniciar.
// Apenas garantimos que se a chave de sessão existe, o token também exista.
if (empty($csrfTokenColabManage) && array_key_exists('csrf_token_colab_manage', $_SESSION)) {
    $_SESSION['csrf_token_colab_manage'] = bin2hex(random_bytes(32));
    $csrfTokenColabManage = $_SESSION['csrf_token_colab_manage'];
} elseif (empty($csrfTokenColabManage) && !array_key_exists('csrf_token_colab_manage', $_SESSION)) {
    // Se a chave nem existe na sessão, cria.
    $_SESSION['csrf_token_colab_manage'] = bin2hex(random_bytes(32));
    $csrfTokenColabManage = $_SESSION['csrf_token_colab_manage'];
}


$flashMessage = null; 
if (isset($_SESSION['flash_message'])) { //
    $flashMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
?>

<section class="bg-white p-4 md:p-6 rounded-lg shadow">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center">
            <i data-lucide="list-ul" class="w-5 h-5 mr-2 text-blue-600"></i> Lista de Colaboradores
        </h2>
        <?php if (can('criar', 'colaboradores')): ?>
            <a href="<?php echo BASE_URL; ?>/cadastrar_colaborador.php" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <i data-lucide="user-plus" class="w-4 h-4 mr-2"></i> Novo Colaborador
            </a>
        <?php endif; ?>
    </div>
    <div class="overflow-x-auto">
        <?php // O token CSRF para o modal de edição (se o usuário puder editar)
        if (can('atualizar', 'colaboradores')): ?>
            <input type="hidden" id="csrf-token-colab-manage" value="<?php echo htmlspecialchars($csrfTokenColabManage); ?>">
        <?php endif; ?>
        <table id="collaborators-table" class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome Completo</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cargo</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <?php if (can('atualizar', 'colaboradores') || can('excluir', 'colaboradores')): ?>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    <?php else: ?>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"></th> 
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr><td colspan="<?php echo (can('atualizar', 'colaboradores') || can('excluir', 'colaboradores')) ? '6' : '5'; ?>" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Carregando colaboradores... <i data-lucide="loader-circle" class="lucide-spin inline-block"></i></td></tr>
            </tbody>
        </table>
    </div>
</section>

<?php if (can('atualizar', 'colaboradores')): ?>
<div id="edit-collaborator-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full items-center justify-center hidden z-[1050]">
    <div class="relative mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white transform transition-all scale-95 opacity-0" 
         id="edit-collaborator-modal-content"
         style="transition-property: transform, opacity; transition-duration: 300ms; transition-timing-function: ease-in-out;">
        <form id="edit-collaborator-form">
            <input type="hidden" id="edit-colab-id" name="colab_id">
            <input type="hidden" id="edit-csrf-token" name="csrf_token" value="<?php echo htmlspecialchars($csrfTokenColabManage); ?>">
            
            <div class="flex justify-between items-center pb-3 border-b">
                <h2 class="text-xl font-semibold text-gray-900">Editar Colaborador</h2>
                <button type="button" class="text-gray-400 hover:text-gray-600" id="modal-close-btn" title="Fechar">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="edit-nome_completo" class="block text-sm font-medium text-gray-700">Nome Completo:</label>
                    <input type="text" id="edit-nome_completo" name="nome_completo" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                </div>
                <div>
                    <label for="edit-email" class="text-sm font-medium text-gray-700">Email:</label>
                    <input type="email" id="edit-email" name="email" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit-cargo" class="text-sm font-medium text-gray-700">Cargo:</label>
                    <input type="text" id="edit-cargo" name="cargo" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" id="cancel-edit-colab-button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i data-lucide="x-circle" class="w-4 h-4 mr-2"></i> Cancelar
                </button>
                <button type="submit" id="save-edit-colab-button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i> Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
$pageSpecificJs = ['/public/js/page_specific/gerenciar_colaboradores.js'];
require_once __DIR__ . '/templates/footer.php';
?>