<?php
// cadastrar_colaborador.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define o BASE_URL para redirecionamento e links.
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
// Ajuste $project_root_web_path se o script não estiver na raiz do projeto web.
// Ex: se o projeto está em /var/www/html/TurnoSQLServer e é acessado por localhost/TurnoSQLServer
// $_SERVER['SCRIPT_NAME'] seria /TurnoSQLServer/cadastrar_colaborador.php
// dirname($_SERVER['SCRIPT_NAME']) seria /TurnoSQLServer
$project_root_web_path = dirname($_SERVER['SCRIPT_NAME']);
if ($project_root_web_path === '/' || $project_root_web_path === '\\') {
    $project_root_web_path = ''; // Evita // no BASE_URL se estiver na raiz do domínio.
}
define('BASE_URL_REDIRECT', rtrim($protocol . $host . $project_root_web_path, '/'));


// Verificação de login ANTES de qualquer output HTML
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sessão expirada ou acesso negado.', 'action' => 'redirect', 'location' => BASE_URL_REDIRECT . '/index.html']);
        exit;
    }
    header('Location: ' . BASE_URL_REDIRECT . '/index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}

$pageTitle = 'Cadastrar Novo Colaborador';
$currentPage = 'colaboradores'; // Para destacar 'Colaboradores' na sidebar
$headerIcon = '<i data-lucide="user-plus" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600"></i>';

// Inclui o cabeçalho comum. config.php é incluído dentro de header.php
require_once __DIR__ . '/templates/header.php';

// Token CSRF para este formulário específico (gerado em config.php ou no header se necessário)
$csrfTokenCadColab = $_SESSION['csrf_token_cad_colab'] ?? '';
if (empty($csrfTokenCadColab) && isset($_SESSION['csrf_token_cad_colab'])) { // Garante que o token existe se a sessão está ativa
    $_SESSION['csrf_token_cad_colab'] = bin2hex(random_bytes(32));
    $csrfTokenCadColab = $_SESSION['csrf_token_cad_colab'];
}

$flashMessage = null;
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
?>

<section class="bg-white p-4 md:p-6 rounded-lg shadow max-w-2xl mx-auto">
    <h2 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
        <i data-lucide="edit-3" class="w-5 h-5 mr-2 text-blue-600"></i> Informações do Novo Colaborador
    </h2>
    <form method="POST" action="<?php echo BASE_URL; ?>/processar_cadastro_colaborador.php" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfTokenCadColab); ?>">

        <div>
            <label for="nome_completo_cad" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo <span class="text-red-500">*</span></label>
            <input type="text" name="nome_completo" id="nome_completo_cad" required aria-label="Nome Completo"
                   class="form-input block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm placeholder-gray-400"
                   placeholder="Digite o nome completo">
        </div>

        <div>
            <label for="email_cad" class="block text-sm font-medium text-gray-700 mb-1">E-mail (Opcional)</label>
            <input type="email" name="email" id="email_cad" aria-label="E-mail (Opcional)"
                   class="form-input block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm placeholder-gray-400"
                   placeholder="exemplo@dominio.com">
        </div>

        <div>
            <label for="cargo_cad" class="block text-sm font-medium text-gray-700 mb-1">Cargo (Opcional)</label>
            <input type="text" name="cargo" id="cargo_cad" aria-label="Cargo (Opcional)"
                   class="form-input block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm placeholder-gray-400"
                   placeholder="Ex: Frentista, Caixa">
        </div>

        <div>
            <label for="ativo_cad" class="block text-sm font-medium text-gray-700 mb-1">Status do Colaborador</label>
            <select name="ativo" id="ativo_cad"
                    class="form-select block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="1" selected>Ativo</option>
                <option value="0">Inativo</option>
            </select>
        </div>

        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 pt-2">
            <a href="<?php echo BASE_URL; ?>/gerenciar_colaboradores.php" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 order-last sm:order-first">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Voltar
            </a>
            <button type="submit"
                    class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i> Cadastrar Colaborador
            </button>
        </div>
    </form>
</section>

<?php
// Define scripts JS específicos para esta página, se houver.
// $pageSpecificJs = ['/public/js/page_specific/cadastrar_colaborador.js'];
require_once __DIR__ . '/templates/footer.php';
?>