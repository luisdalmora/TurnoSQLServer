<?php
// home.php

if (session_status() == PHP_SESSION_NONE) {
    session_start(); 
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$project_root_web_path = dirname($_SERVER['SCRIPT_NAME']);
if ($project_root_web_path === '/' || $project_root_web_path === '\\') {
    $project_root_web_path = ''; 
}
// Define BASE_URL_REDIRECT se ainda não estiver definido (evita conflito com header.php)
if (!defined('BASE_URL_REDIRECT')) {
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

$pageTitle = 'Dashboard';
$currentPage = 'home'; 
$headerIcon = '<i data-lucide="layout-dashboard" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600"></i>';

require_once __DIR__ . '/templates/header.php'; // Isso define $USUARIO_ATUAL_ROLE e isAdmin()

$csrfToken = $_SESSION['csrf_token'] ?? '';
$csrfTokenAusencias = $_SESSION['csrf_token_ausencias'] ?? '';
$csrfTokenObsGeral = $_SESSION['csrf_token_obs_geral'] ?? '';

$anoExibicao = date('Y');
$mesExibicao = date('m');
$nomesMeses = ["", "Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
$nomeMesExibicao = $nomesMeses[(int)$mesExibicao] ?? '';

$isUserAdmin = isAdmin(); // Usar a função definida no header.php
?>

<?php if ($isUserAdmin): ?>
<div id="backup-modal-backdrop" class="fixed inset-0 bg-gray-600 bg-opacity-75 backdrop-blur-sm flex items-center justify-center hidden z-[1070]">
    <div class="modal-content-backup bg-white p-8 rounded-lg shadow-xl w-full max-w-sm text-center transform transition-all scale-95 opacity-0">
      <h3 id="backup-modal-title" class="text-lg font-medium text-gray-900">Backup do Banco de Dados</h3>
      <div id="backup-modal-message" class="mt-2 text-sm text-gray-600">Iniciando o processo de backup...</div>
      <div class="progress-bar-container w-full bg-gray-200 rounded overflow-hidden my-4" id="backup-progress-bar-container" style="display: none;">
        <div class="progress-bar h-5 bg-blue-600 text-center leading-5 text-white text-xs transition-all duration-500 ease-linear" id="backup-progress-bar">0%</div>
      </div>
      <div class="mt-6 flex flex-col sm:flex-row-reverse sm:justify-start gap-3">
        <a href="#" id="backup-download-link" 
           class="hidden w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-150 ease-in-out hover:scale-105 active:scale-95"
           data-tooltip-text="Baixar arquivo de Backup">
            <i data-lucide="download" class="w-4 h-4 mr-2"></i> Baixar Backup
        </a>
        <button type="button" id="backup-modal-close-btn" 
                class="w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 px-4 py-2 bg-white text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" 
                style="display: none;" data-tooltip-text="Fechar Janela">
          Fechar
        </button>
    </div>
    </div>
</div>
<?php endif; ?>


<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 md:gap-6">

    <section class="xl:col-span-1 bg-white p-4 md:p-5 rounded-lg shadow space-y-4 md:space-y-5">
        <div>
            <h3 class="text-sm md:text-base font-semibold text-gray-700 mb-2 flex items-center justify-center py-2 border-b border-gray-200" id="feriados-mes-ano-display" data-tooltip-text="Feriados Nacionais do Mês">
                <i data-lucide="calendar-heart" class="w-4 h-4 mr-2 text-blue-600"></i> Feriados - Carregando...
            </h3>
            <div class="max-h-60 overflow-y-auto text-xs md:text-sm">
                <table id="feriados-table" class="w-full">
                    <thead class="sticky top-0 bg-blue-600 text-white z-10"><tr><th class="p-2 text-left font-semibold uppercase text-xs">DATA</th><th class="p-2 text-left font-semibold uppercase text-xs">OBSERVAÇÃO</th></tr></thead>
                    <tbody class="divide-y divide-gray-200"><tr><td colspan="2" class="p-2 text-center text-gray-500">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">
            <h3 class="text-sm md:text-base font-semibold text-gray-700 mb-2 flex items-center justify-center py-2 border-b border-gray-200" id="escala-sabados-display" data-tooltip-text="Escala de Trabalho aos Sábados">
                <i data-lucide="calendar-check" class="w-4 h-4 mr-2 text-blue-600"></i> Escala - Sábados
            </h3>
            <div class="max-h-60 overflow-y-auto text-xs md:text-sm">
                <table id="escala-sabados-table" class="w-full">
                    <thead class="sticky top-0 bg-blue-600 text-white z-10"><tr><th class="p-2 text-left font-semibold uppercase text-xs">DATA</th><th class="p-2 text-left font-semibold uppercase text-xs">COLABORADOR</th></tr></thead>
                    <tbody class="divide-y divide-gray-200"><tr><td colspan="2" class="p-2 text-center text-gray-500">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">
            <h3 class="text-sm md:text-base font-semibold text-gray-700 mb-2 flex items-center justify-center py-2 border-b border-gray-200" id="ausencia-setor-display" data-tooltip-text="Colaboradores Ausentes do Setor">
                <i data-lucide="user-cog" class="w-4 h-4 mr-2 text-blue-600"></i> Ausência Setor
            </h3>
            <div class="max-h-60 overflow-y-auto text-xs md:text-sm">
                <table id="ausencia-setor-table" class="w-full">
                     <thead class="sticky top-0 bg-blue-600 text-white z-10"><tr><th class="p-2 text-left font-semibold uppercase text-xs">DATA</th><th class="p-2 text-left font-semibold uppercase text-xs">COLABORADOR</th></tr></thead>
                    <tbody class="divide-y divide-gray-200"><tr><td colspan="2" class="p-2 text-center text-gray-500">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="xl:col-span-2 bg-white p-4 md:p-5 rounded-lg shadow space-y-4 md:space-y-5">
        <div>
            <?php if ($isUserAdmin): ?>
                <input type="hidden" id="csrf-token-shifts" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <?php endif; ?>
            <div class="flex flex-col sm:flex-row justify-between items-center mb-3 pb-3 border-b border-gray-200 gap-2">
                <button id="prev-month-button" class="px-3 py-1.5 text-xs font-medium text-white bg-gray-500 hover:bg-gray-600 rounded-md flex items-center w-full sm:w-auto justify-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Mês Anterior">
                    <i data-lucide="chevron-left" class="w-4 h-4 mr-1"></i> Anterior
                </button>
                <h2 id="current-month-year-display" data-year="<?php echo $anoExibicao; ?>" data-month="<?php echo $mesExibicao; ?>" class="text-base md:text-lg font-semibold text-gray-800 flex items-center order-first sm:order-none text-center" data-tooltip-text="Mês e Ano de Exibição dos Turnos">
                    <i data-lucide="list-todo" class="w-5 h-5 mr-2 text-blue-600"></i> Turnos - <?php echo htmlspecialchars($nomeMesExibicao . ' ' . $anoExibicao); ?>
                </h2>
                <button id="next-month-button" class="px-3 py-1.5 text-xs font-medium text-white bg-gray-500 hover:bg-gray-600 rounded-md flex items-center w-full sm:w-auto justify-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Próximo Mês">
                    Próximo <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                </button>
            </div>
            
            <?php if ($isUserAdmin): // Botões de ação de Turnos apenas para Admin ?>
            <div class="flex flex-wrap gap-2 mb-3">
                <button id="add-shift-row-button" class="px-3 py-1.5 text-xs font-medium text-white bg-green-500 hover:bg-green-600 rounded-md flex items-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Adicionar Nova Linha de Turno">
                    <i data-lucide="plus-circle" class="w-4 h-4 mr-1.5"></i> Adicionar Turno
                </button>
                <button id="delete-selected-shifts-button" class="px-3 py-1.5 text-xs font-medium text-white bg-red-500 hover:bg-red-600 rounded-md flex items-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Excluir Turnos Selecionados">
                    <i data-lucide="trash-2" class="w-4 h-4 mr-1.5"></i> Excluir
                </button>
                <button id="save-shifts-button" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md flex items-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Salvar Alterações nos Turnos">
                    <i data-lucide="save" class="w-4 h-4 mr-1.5"></i> Salvar
                </button>
            </div>
            <?php endif; ?>

            <div class="overflow-x-auto max-h-80 text-xs md:text-sm">
                <table id="shifts-table-main" class="w-full min-w-[500px]">
                <thead class="sticky top-0 bg-blue-600 text-white z-10">
                    <tr>
                        <?php if ($isUserAdmin): ?>
                        <th class="p-2 w-10 text-center"><input type="checkbox" id="select-all-shifts" title="Selecionar Todos os Turnos" class="form-checkbox h-3.5 w-3.5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer" data-tooltip-text="Selecionar/Desselecionar Todos"></th>
                        <?php else: ?>
                        <th class="p-2 w-10 text-center"></th>
                        <?php endif; ?>
                        <th class="p-2 text-left font-semibold uppercase text-xs">Dia (dd/Mês)</th>
                        <th class="p-2 text-left font-semibold uppercase text-xs">Início</th>
                        <th class="p-2 text-left font-semibold uppercase text-xs">Fim</th>
                        <th class="p-2 text-left font-semibold uppercase text-xs">Colaborador</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200"></tbody>
                </table>
            </div>
        </div>

        <div class="pt-4">
            <h2 class="text-base md:text-lg font-semibold text-gray-800 mb-3 flex items-center pb-3 border-b border-gray-200" data-tooltip-text="Resumo de Horas Trabalhadas por Colaborador no Mês">
                <i data-lucide="bar-chart-3" class="w-5 h-5 mr-2 text-blue-600"></i>
                Resumo de Horas (<span id="employee-summary-period"><?php echo htmlspecialchars($nomeMesExibicao); ?></span>)
            </h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
                <div class="max-h-60 overflow-y-auto text-xs md:text-sm">
                    <table id="employee-summary-table" class="w-full">
                        <thead class="sticky top-0 bg-blue-600 text-white z-10"><tr><th class="p-2 text-left font-semibold uppercase text-xs">Colaborador</th><th class="p-2 text-left font-semibold uppercase text-xs">Total Horas</th></tr></thead>
                        <tbody class="divide-y divide-gray-200"></tbody>
                    </table>
                </div>
                <div id="employee-hours-chart-container" class="w-full h-[280px] relative">
                    <canvas id="employee-hours-chart"></canvas>
                </div>
            </div>
        </div>
    </section>

    <section class="xl:col-span-3 bg-white p-4 md:p-5 rounded-lg shadow">
        <?php if ($isUserAdmin): ?>
            <input type="hidden" id="csrf-token-ausencias" value="<?php echo htmlspecialchars($csrfTokenAusencias); ?>">
        <?php endif; ?>
        <div class="flex flex-col sm:flex-row justify-between items-center mb-3 pb-3 border-b border-gray-200 gap-2">
            <button id="prev-month-ausencias-button" class="px-3 py-1.5 text-xs font-medium text-white bg-gray-500 hover:bg-gray-600 rounded-md flex items-center w-full sm:w-auto justify-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Mês Anterior (Ausências)">
                <i data-lucide="chevron-left" class="w-4 h-4 mr-1"></i> Anterior
            </button>
            <h2 id="current-month-year-ausencias-display" data-year="<?php echo $anoExibicao; ?>" data-month="<?php echo $mesExibicao; ?>" class="text-base md:text-lg font-semibold text-gray-800 flex items-center order-first sm:order-none text-center" data-tooltip-text="Mês e Ano de Exibição das Ausências">
                <i data-lucide="user-x" class="w-5 h-5 mr-2 text-blue-600"></i> Ausências - <?php echo htmlspecialchars($nomeMesExibicao . ' ' . $anoExibicao); ?>
            </h2>
            <button id="next-month-ausencias-button" class="px-3 py-1.5 text-xs font-medium text-white bg-gray-500 hover:bg-gray-600 rounded-md flex items-center w-full sm:w-auto justify-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Próximo Mês (Ausências)">
                Próximo <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
            </button>
        </div>
        <?php if ($isUserAdmin): ?>
        <div class="flex flex-wrap gap-2 mb-3">
            <button id="add-ausencia-row-button" class="px-3 py-1.5 text-xs font-medium text-white bg-green-500 hover:bg-green-600 rounded-md flex items-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Adicionar Nova Linha de Ausência">
                <i data-lucide="plus-circle" class="w-4 h-4 mr-1.5"></i> Adicionar Ausência
            </button>
            <button id="delete-selected-ausencias-button" class="px-3 py-1.5 text-xs font-medium text-white bg-red-500 hover:bg-red-600 rounded-md flex items-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Excluir Ausências Selecionadas">
                <i data-lucide="trash-2" class="w-4 h-4 mr-1.5"></i> Excluir
            </button>
            <button id="save-ausencias-button" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md flex items-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Salvar Alterações nas Ausências">
                <i data-lucide="save" class="w-4 h-4 mr-1.5"></i> Salvar Ausências
            </button>
        </div>
        <?php endif; ?>
        <div class="overflow-x-auto max-h-72 text-xs md:text-sm">
            <table id="ausencias-table-main" class="w-full min-w-[500px]">
                <thead class="sticky top-0 bg-blue-600 text-white z-10">
                <tr>
                    <?php if ($isUserAdmin): ?>
                    <th class="p-2 w-10 text-center"><input type="checkbox" id="select-all-ausencias" title="Selecionar Todas as Ausências" class="form-checkbox h-3.5 w-3.5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 cursor-pointer" data-tooltip-text="Selecionar/Desselecionar Todas"></th>
                    <?php else: ?>
                    <th class="p-2 w-10 text-center"></th>
                    <?php endif; ?>
                    <th class="p-2 text-left font-semibold uppercase text-xs">Data Início</th>
                    <th class="p-2 text-left font-semibold uppercase text-xs">Data Fim</th>
                    <th class="p-2 text-left font-semibold uppercase text-xs">Colaborador</th>
                    <th class="p-2 text-left font-semibold uppercase text-xs">Motivo/Observações</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-200"><tr><td colspan="5" class="p-2 text-center text-gray-500">Carregando...</td></tr></tbody>
            </table>
        </div>
    </section>

    <section class="xl:col-span-3 bg-white p-4 md:p-5 rounded-lg shadow">
        <h2 class="text-base md:text-lg font-semibold text-gray-800 mb-3 flex items-center" data-tooltip-text="Observações Gerais para o Mês">
            <i data-lucide="notebook-pen" class="w-5 h-5 mr-2 text-blue-600"></i> Observações Gerais
        </h2>
        <?php if ($isUserAdmin): ?>
            <input type="hidden" id="csrf-token-obs-geral" value="<?php echo htmlspecialchars($csrfTokenObsGeral); ?>">
        <?php endif; ?>
        <textarea id="observacoes-gerais-textarea" rows="3" placeholder="Digite aqui qualquer informação importante para este mês..." 
                  class="form-textarea w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm"
                  <?php echo !$isUserAdmin ? 'readonly' : ''; ?>></textarea>
        <?php if ($isUserAdmin): ?>
        <button id="salvar-observacoes-gerais-btn" class="mt-3 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md flex items-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Salvar Observações Gerais">
            <i data-lucide="save" class="w-4 h-4 mr-1.5"></i> Salvar Observações
        </button>
        <?php endif; ?>
    </section>
</div>

<?php
$pageSpecificJs = [
    'https://cdn.jsdelivr.net/npm/chart.js'
];
require_once __DIR__ . '/templates/footer.php';
?>