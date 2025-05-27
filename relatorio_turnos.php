<?php
// relatorio_turnos.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$project_root_web_path = dirname($_SERVER['SCRIPT_NAME']);
if ($project_root_web_path === '/' || $project_root_web_path === '\\') {
    $project_root_web_path = '';
}
define('BASE_URL_REDIRECT', rtrim($protocol . $host . $project_root_web_path, '/'));

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sessão expirada ou acesso negado.', 'action' => 'redirect', 'location' => BASE_URL_REDIRECT . '/index.html']);
        exit;
    }
    header('Location: ' . BASE_URL_REDIRECT . '/index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}

$pageTitle = 'Relatório de Turnos Trabalhados';
$currentPage = 'relatorios';
$headerIcon = '<i data-lucide="file-pie-chart" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600"></i>';

require_once __DIR__ . '/templates/header.php';

// Token CSRF para esta página de relatórios
$csrfTokenReports = $_SESSION['csrf_token_reports'] ?? '';
if (empty($csrfTokenReports) && isset($_SESSION['csrf_token_reports'])) {
    $_SESSION['csrf_token_reports'] = bin2hex(random_bytes(32));
    $csrfTokenReports = $_SESSION['csrf_token_reports'];
}
?>

<section class="bg-white p-4 md:p-6 rounded-lg shadow">
    <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
        <i data-lucide="filter" class="w-5 h-5 mr-2 text-blue-600"></i> Filtros do Relatório
    </h2>
    <form id="report-filters-form" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        <input type="hidden" id="csrf-token-reports" value="<?php echo htmlspecialchars($csrfTokenReports); ?>">

        <div>
            <label for="filtro-data-inicio" class="block text-sm font-medium text-gray-700 mb-1">Data Início:</label>
            <input type="date" id="filtro-data-inicio" name="filtro-data-inicio" class="form-input block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
        </div>
        <div>
            <label for="filtro-data-fim" class="block text-sm font-medium text-gray-700 mb-1">Data Fim:</label>
            <input type="date" id="filtro-data-fim" name="filtro-data-fim" class="form-input block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
        </div>
        <div>
            <label for="filtro-colaborador" class="block text-sm font-medium text-gray-700 mb-1">Colaborador:</label>
            <select id="filtro-colaborador" name="filtro-colaborador" class="form-select block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="">Todos os Colaboradores</option>
                </select>
        </div>
        <div class="sm:col-span-2 lg:col-span-1">
            <button type="submit" id="generate-report-button" class="w-full flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i data-lucide="search" class="w-4 h-4 mr-2"></i> Gerar Relatório
            </button>
        </div>
    </form>
</section>

<section class="mt-6 bg-white p-4 md:p-6 rounded-lg shadow">
    <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
        <i data-lucide="list-checks" class="w-5 h-5 mr-2 text-blue-600"></i> Resultado do Relatório
    </h2>
    <div id="report-summary" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-md text-sm text-blue-700">
        <p>Utilize os filtros acima e clique em "Gerar Relatório".</p>
    </div>
    <div class="overflow-x-auto">
        <table id="report-table" class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Colaborador</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hora Início</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hora Fim</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duração</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr>
                    <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Nenhum relatório gerado ainda.</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<?php
$pageSpecificJs = ['/public/js/page_specific/relatorio_turnos.js'];
require_once __DIR__ . '/templates/footer.php';
?>