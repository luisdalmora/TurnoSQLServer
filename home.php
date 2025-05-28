<?php
// home.php
// ... (início do arquivo como na última versão, com definições e includes) ...
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL_REDIRECT')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $project_root_web_path = rtrim($script_dir, '/');
    if ($project_root_web_path === '/' || $project_root_web_path === '\\') {
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

$pageTitle = 'Dashboard';
$currentPage = 'home';
$headerIcon = '<i data-lucide="layout-dashboard" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-sky-600"></i>'; // Cor do ícone suave

require_once __DIR__ . '/templates/header.php'; // header.php já foi atualizado com a cor de fundo do body

$csrfToken = $_SESSION['csrf_token'] ?? '';
$csrfTokenAusencias = $_SESSION['csrf_token_ausencias'] ?? '';

$anoExibicao = date('Y');
$mesExibicao = date('m');
$nomesMeses = ["", "Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
$nomeMesExibicao = $nomesMeses[(int)$mesExibicao] ?? '';

$cardPequenoMaxH = "max-h-72"; // Aproximadamente 288px
$cardGrandeMaxH = "max-h-[480px]";

?>

<?php if (can('executar', 'backup')): ?>
<div id="backup-modal-backdrop" class="fixed inset-0 bg-gray-600 bg-opacity-75 backdrop-blur-sm flex items-center justify-center hidden z-[1070]">
    <div class="modal-content-backup bg-white p-8 rounded-xl shadow-2xl w-full max-w-sm text-center transform transition-all scale-95 opacity-0"> 
      <h3 id="backup-modal-title" class="text-lg font-medium text-slate-900">Backup do Banco de Dados</h3>
      <div id="backup-modal-message" class="mt-2 text-sm text-slate-600">Iniciando o processo de backup...</div>
      <div class="progress-bar-container w-full bg-slate-200 rounded overflow-hidden my-4" id="backup-progress-bar-container" style="display: none;">
        <div class="progress-bar h-5 bg-sky-600 text-center leading-5 text-white text-xs transition-all duration-500 ease-linear" id="backup-progress-bar">0%</div>
      </div>
      <div class="mt-6 flex flex-col sm:flex-row-reverse sm:justify-start gap-3">
        <a href="#" id="backup-download-link"
           class="hidden w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-lg shadow-md text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-150 ease-in-out hover:scale-105 active:scale-95"
           data-tooltip-text="Baixar arquivo de Backup">
            <i data-lucide="download" class="w-4 h-4 mr-2"></i> Baixar Backup
        </a>
        <button type="button" id="backup-modal-close-btn"
                class="w-full sm:w-auto inline-flex justify-center rounded-lg border border-slate-300 px-4 py-2 bg-white text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500 transition-all duration-150 ease-in-out hover:scale-105 active:scale-95"
                style="display: none;" data-tooltip-text="Fechar Janela">
          Fechar
        </button>
    </div>
    </div>
</div>
<?php endif; ?>


<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-6">

    <div class="bg-white shadow-lg rounded-xl p-4 md:p-5 flex flex-col <?php echo $cardPequenoMaxH; ?>">
        <h3 class="text-sm md:text-base font-semibold text-slate-700 mb-2 flex items-center justify-center py-2 border-b border-slate-200 flex-shrink-0" id="feriados-mes-ano-display" data-tooltip-text="Feriados Nacionais do Mês">
            <i data-lucide="calendar-heart" class="w-4 h-4 mr-2 text-sky-600"></i> Feriados - Carregando...
        </h3>
        <div class="text-xs md:text-sm overflow-y-auto flex-grow custom-scrollbar-thin">
            <table id="feriados-table" class="w-full">
                <thead class="sticky top-0 bg-sky-600 text-white z-10"><tr><th class="p-2 text-left font-semibold uppercase text-xs">DATA</th><th class="p-2 text-left font-semibold uppercase text-xs">OBSERVAÇÃO</th></tr></thead>
                <tbody class="divide-y divide-slate-200"><tr><td colspan="2" class="p-2 text-center text-slate-500">Carregando...</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="bg-white shadow-lg rounded-xl p-4 md:p-5 flex flex-col <?php echo $cardPequenoMaxH; ?>">
        <h3 class="text-sm md:text-base font-semibold text-slate-700 mb-2 flex items-center justify-center py-2 border-b border-slate-200 flex-shrink-0" id="ausencia-setor-display" data-tooltip-text="Colaboradores Ausentes do Setor">
            <i data-lucide="user-cog" class="w-4 h-4 mr-2 text-sky-600"></i> Ausência Setor
        </h3>
        <div class="text-xs md:text-sm overflow-y-auto flex-grow custom-scrollbar-thin">
            <table id="ausencia-setor-table" class="w-full">
                 <thead class="sticky top-0 bg-sky-600 text-white z-10"><tr><th class="p-2 text-left font-semibold uppercase text-xs">DATA</th><th class="p-2 text-left font-semibold uppercase text-xs">COLABORADOR</th></tr></thead>
                <tbody class="divide-y divide-slate-200"><tr><td colspan="2" class="p-2 text-center text-slate-500">Carregando...</td></tr></tbody>
            </table>
        </div>
    </div>
    
    <div class="bg-white shadow-lg rounded-xl p-4 md:p-5 flex flex-col <?php echo $cardPequenoMaxH; ?>">
        <h3 class="text-sm md:text-base font-semibold text-slate-700 mb-2 flex items-center justify-center py-2 border-b border-slate-200 flex-shrink-0" id="escala-sabados-display" data-tooltip-text="Escala de Trabalho aos Sábados">
            <i data-lucide="calendar-check" class="w-4 h-4 mr-2 text-sky-600"></i> Escala - Sábados
        </h3>
        <div class="text-xs md:text-sm overflow-y-auto flex-grow custom-scrollbar-thin">
            <table id="escala-sabados-table" class="w-full">
                <thead class="sticky top-0 bg-sky-600 text-white z-10"><tr><th class="p-2 text-left font-semibold uppercase text-xs">DATA</th><th class="p-2 text-left font-semibold uppercase text-xs">COLABORADOR</th></tr></thead>
                <tbody class="divide-y divide-slate-200"><tr><td colspan="2" class="p-2 text-center text-slate-500">Carregando...</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="bg-white shadow-lg rounded-xl p-4 md:p-5 xl:col-span-2 flex flex-col <?php echo $cardGrandeMaxH; ?>">
        <div class="mb-4 border-b border-slate-200 flex-shrink-0">
            <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="main-tabs" role="tablist">
                <li class="mr-2" role="presentation">
                    <button class="inline-flex items-center justify-center p-3 border-b-2 rounded-t-lg text-sky-600 border-sky-600" id="tab-button-turnos" data-tabs-target="#tab-content-turnos" type="button" role="tab" aria-controls="tab-content-turnos" aria-selected="true"> 
                        <i data-lucide="list-todo" class="w-5 h-5 mr-2"></i>Turnos
                    </button>
                </li>
                <li class="mr-2" role="presentation">
                    <button class="inline-flex items-center justify-center p-3 border-b-2 rounded-t-lg border-transparent hover:text-slate-600 hover:border-slate-300" id="tab-button-ausencias" data-tabs-target="#tab-content-ausencias" type="button" role="tab" aria-controls="tab-content-ausencias" aria-selected="false">
                        <i data-lucide="user-x" class="w-5 h-5 mr-2"></i>Gerenciar Ausências
                    </button>
                </li>
            </ul>
        </div>
        <div id="main-tabs-content" class="overflow-y-auto flex-grow custom-scrollbar-thin">
            <div class="p-1 rounded-lg bg-slate-50" id="tab-content-turnos" role="tabpanel" aria-labelledby="tab-button-turnos"> 
                <?php if (can('criar', 'turnos') || can('atualizar_proprio', 'turnos') || can('excluir_proprio', 'turnos') || can('gerenciar_todos', 'turnos')): ?>
                    <input type="hidden" id="csrf-token-shifts" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <?php endif; ?>
                <div class="flex flex-col sm:flex-row justify-between items-center mb-3 pb-3 border-b border-slate-200 gap-2">
                    <button id="prev-month-button" class="px-3 py-1.5 text-xs font-medium text-white bg-slate-500 hover:bg-slate-600 rounded-lg flex items-center w-full sm:w-auto justify-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Mês Anterior">
                        <i data-lucide="chevron-left" class="w-4 h-4 mr-1"></i> Anterior
                    </button>
                    <h3 id="current-month-year-display" data-year="<?php echo $anoExibicao; ?>" data-month="<?php echo $mesExibicao; ?>" class="text-base md:text-lg font-semibold text-slate-700 flex items-center order-first sm:order-none text-center" data-tooltip-text="Mês e Ano de Exibição dos Turnos">
                         <?php echo htmlspecialchars($nomeMesExibicao . ' ' . $anoExibicao); ?>
                    </h3>
                    <button id="next-month-button" class="px-3 py-1.5 text-xs font-medium text-white bg-slate-500 hover:bg-slate-600 rounded-lg flex items-center w-full sm:w-auto justify-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Próximo Mês">
                        Próximo <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                    </button>
                </div>
                <?php
                $podeCriarTurno = can('criar', 'turnos');
                $podeExcluirTurno = can('excluir_proprio', 'turnos') || can('gerenciar_todos', 'turnos');
                $podeSalvarTurno = $podeCriarTurno || can('atualizar_proprio', 'turnos') || can('gerenciar_todos', 'turnos');
                ?>
                <?php if ($podeCriarTurno || $podeExcluirTurno || $podeSalvarTurno): ?>
                <div class="flex flex-wrap gap-2 mb-3">
                    <?php if ($podeCriarTurno): ?>
                    <button id="add-shift-row-button" class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg flex items-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Adicionar Nova Linha de Turno">
                        <i data-lucide="plus-circle" class="w-4 h-4 mr-1.5"></i> Adicionar Turno
                    </button>
                    <?php endif; ?>
                    <?php if ($podeExcluirTurno): ?>
                    <button id="delete-selected-shifts-button" class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg flex items-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Excluir Turnos Selecionados">
                        <i data-lucide="trash-2" class="w-4 h-4 mr-1.5"></i> Excluir
                    </button>
                    <?php endif; ?>
                    <?php if ($podeSalvarTurno): ?>
                    <button id="save-shifts-button" class="px-3 py-1.5 text-xs font-medium text-white bg-sky-600 hover:bg-sky-700 rounded-lg flex items-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Salvar Alterações nos Turnos">
                        <i data-lucide="save" class="w-4 h-4 mr-1.5"></i> Salvar
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="overflow-x-auto text-xs md:text-sm">
                    <table id="shifts-table-main" class="w-full min-w-[500px]">
                    <thead class="sticky top-0 bg-sky-600 text-white z-10"> 
                        <tr>
                            <?php if ($podeExcluirTurno): ?>
                            <th class="p-2 w-10 text-center"><input type="checkbox" id="select-all-shifts" title="Selecionar Todos os Turnos" class="form-checkbox h-3.5 w-3.5 text-sky-600 border-slate-300 rounded focus:ring-sky-500 cursor-pointer" data-tooltip-text="Selecionar/Desselecionar Todos"></th>
                            <?php else: ?>
                            <th class="p-2 w-10 text-center"></th>
                            <?php endif; ?>
                            <th class="p-2 text-left font-semibold uppercase text-xs">Dia (dd/Mês)</th>
                            <th class="p-2 text-left font-semibold uppercase text-xs">Início</th>
                            <th class="p-2 text-left font-semibold uppercase text-xs">Fim</th>
                            <th class="p-2 text-left font-semibold uppercase text-xs">Colaborador</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white"></tbody>
                    </table>
                </div>
            </div>

            <div class="hidden p-1 rounded-lg bg-slate-50" id="tab-content-ausencias" role="tabpanel" aria-labelledby="tab-button-ausencias">
                <?php if (can('criar', 'ausencias') || can('atualizar_propria', 'ausencias') || can('excluir_propria', 'ausencias') || can('gerenciar_todos', 'ausencias')): ?>
                    <input type="hidden" id="csrf-token-ausencias" value="<?php echo htmlspecialchars($csrfTokenAusencias); ?>">
                <?php endif; ?>
                <div class="flex flex-col sm:flex-row justify-between items-center mb-3 pb-3 border-b border-slate-200 gap-2">
                    <button id="prev-month-ausencias-button" class="px-3 py-1.5 text-xs font-medium text-white bg-slate-500 hover:bg-slate-600 rounded-lg flex items-center w-full sm:w-auto justify-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Mês Anterior (Ausências)">
                        <i data-lucide="chevron-left" class="w-4 h-4 mr-1"></i> Anterior
                    </button>
                    <h3 id="current-month-year-ausencias-display" data-year="<?php echo $anoExibicao; ?>" data-month="<?php echo $mesExibicao; ?>" class="text-base md:text-lg font-semibold text-slate-700 flex items-center order-first sm:order-none text-center" data-tooltip-text="Mês e Ano de Exibição das Ausências">
                        <?php echo htmlspecialchars($nomeMesExibicao . ' ' . $anoExibicao); ?>
                    </h3>
                    <button id="next-month-ausencias-button" class="px-3 py-1.5 text-xs font-medium text-white bg-slate-500 hover:bg-slate-600 rounded-lg flex items-center w-full sm:w-auto justify-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Próximo Mês (Ausências)">
                        Próximo <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                    </button>
                </div>
                <?php
                    $podeCriarAusencia = can('criar', 'ausencias');
                    $podeExcluirAusencia = can('excluir_propria', 'ausencias') || can('gerenciar_todos', 'ausencias');
                    $podeSalvarAusencia = $podeCriarAusencia || can('atualizar_propria', 'ausencias') || can('gerenciar_todos', 'ausencias');
                ?>
                <?php if ($podeCriarAusencia || $podeExcluirAusencia || $podeSalvarAusencia): ?>
                <div class="flex flex-wrap gap-2 mb-3">
                    <?php if ($podeCriarAusencia): ?>
                    <button id="add-ausencia-row-button" class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg flex items-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Adicionar Nova Linha de Ausência">
                        <i data-lucide="plus-circle" class="w-4 h-4 mr-1.5"></i> Adicionar Ausência
                    </button>
                    <?php endif; ?>
                    <?php if ($podeExcluirAusencia): ?>
                    <button id="delete-selected-ausencias-button" class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg flex items-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Excluir Ausências Selecionadas">
                        <i data-lucide="trash-2" class="w-4 h-4 mr-1.5"></i> Excluir
                    </button>
                    <?php endif; ?>
                    <?php if ($podeSalvarAusencia): ?>
                    <button id="save-ausencias-button" class="px-3 py-1.5 text-xs font-medium text-white bg-sky-600 hover:bg-sky-700 rounded-lg flex items-center transition-all duration-150 ease-in-out hover:scale-105 active:scale-95" data-tooltip-text="Salvar Alterações nas Ausências">
                        <i data-lucide="save" class="w-4 h-4 mr-1.5"></i> Salvar Ausências
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="overflow-x-auto text-xs md:text-sm">
                    <table id="ausencias-table-main" class="w-full min-w-[500px]">
                        <thead class="sticky top-0 bg-sky-600 text-white z-10">
                        <tr>
                            <?php if ($podeExcluirAusencia): ?>
                            <th class="p-2 w-10 text-center"><input type="checkbox" id="select-all-ausencias" title="Selecionar Todas as Ausências" class="form-checkbox h-3.5 w-3.5 text-sky-600 border-slate-300 rounded focus:ring-sky-500 cursor-pointer" data-tooltip-text="Selecionar/Desselecionar Todas"></th>
                            <?php else: ?>
                            <th class="p-2 w-10 text-center"></th>
                            <?php endif; ?>
                            <th class="p-2 text-left font-semibold uppercase text-xs">Data Início</th>
                            <th class="p-2 text-left font-semibold uppercase text-xs">Data Fim</th>
                            <th class="p-2 text-left font-semibold uppercase text-xs">Colaborador</th>
                            <th class="p-2 text-left font-semibold uppercase text-xs">Motivo/Observações</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white"><tr><td colspan="<?php echo $podeExcluirAusencia ? '5' : '4'; ?>" class="p-2 text-center text-slate-500">Carregando...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white shadow-lg rounded-xl p-4 md:p-5 xl:col-span-1 flex flex-col <?php echo $cardGrandeMaxH; ?>">
        <h2 class="text-base md:text-lg font-semibold text-slate-700 mb-3 flex items-center flex-shrink-0" data-tooltip-text="Visualização em Calendário dos Eventos do Mês">
            <i data-lucide="calendar-days" class="w-5 h-5 mr-2 text-sky-600"></i>
            Calendário (<span id="calendar-view-period"><?php echo htmlspecialchars($nomeMesExibicao); ?></span>)
        </h2>
        <div id="turnos-calendar-view-container" class="overflow-y-auto flex-grow custom-scrollbar-thin">
            <p class="text-center text-slate-500 py-4">Carregando calendário...</p>
        </div>
    </div>

</div>

<?php
$pageSpecificJs = [];
require_once __DIR__ . '/templates/footer.php';
?>