<?php
require_once __DIR__ . '/config.php'; 

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sessão expirada ou acesso negado.', 'action' => 'redirect', 'location' => 'index.html']);
        exit;
    }
    header('Location: index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}

if (empty($_SESSION['csrf_token_reports'])) { 
    $_SESSION['csrf_token_reports'] = bin2hex(random_bytes(32));
}
$csrfTokenReports = $_SESSION['csrf_token_reports'];
$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Relatório de Turnos - Sim Posto</title>
  <link href="src/output.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
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
        <a href="relatorio_turnos.php" class="flex items-center px-3 py-2.5 rounded-lg bg-blue-600 text-white font-medium text-sm dark:bg-blue-700 dark:hover:bg-blue-600">
          <i data-lucide="file-text" class="w-5 h-5 mr-3"></i> Relatórios
        </a>
        <a href="gerenciar_colaboradores.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm dark:hover:bg-blue-600">
          <i data-lucide="users" class="w-5 h-5 mr-3"></i> Colaboradores
        </a>
        <a href="gerador_senhas.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm dark:hover:bg-blue-600">
        <i data-lucide="key-round" class="w-5 h-5 mr-3"></i> Gerador de Senhas
        </a>
        <a href="gerenciar_scripts.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm dark:hover:bg-blue-600">
            <i data-lucide="file-code-2" class="w-5 h-5 mr-3"></i> Scripts
        </a>
      </nav>
      <div class="p-2 border-t border-white/10 dark:border-gray-700">
        <div class="px-2 py-1 space-y-1.5">
            <?php
            if (isset($_SESSION['csrf_token_backup'])) { // Reutilizando o token de backup se existir
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
          <i data-lucide="file-pie-chart" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600 dark:text-blue-400"></i>
          <h1 class="text-md md:text-lg font-semibold text-gray-800 dark:text-gray-100">Relatório de Turnos Trabalhados</h1>
        </div>
        <div class="flex items-center">
          <div id="user-info" class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300">
              Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?>
              <i data-lucide="circle-user-round" class="w-5 h-5 md:w-6 md:h-6 ml-2 text-blue-600 dark:text-blue-400"></i>
            </div>
        </div>
      </header>

      <main class="flex-grow p-4 md:p-6 space-y-6">
        <section class="bg-white p-4 md:p-6 rounded-lg shadow dark:bg-gray-800 dark:border dark:border-gray-700 transition-colors duration-300">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center">
            <i data-lucide="filter" class="w-5 h-5 mr-2 text-blue-600 dark:text-blue-400"></i> Filtros do Relatório
          </h2>
          <form id="report-filters-form" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <input type="hidden" id="csrf-token-reports" value="<?php echo htmlspecialchars($csrfTokenReports); ?>">

            <div>
              <label for="filtro-data-inicio" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data Início:</label>
              <input type="date" id="filtro-data-inicio" name="filtro-data-inicio" class="form-input block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:focus:ring-indigo-600 dark:focus:border-indigo-600" required>
            </div>
            <div>
              <label for="filtro-data-fim" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data Fim:</label>
              <input type="date" id="filtro-data-fim" name="filtro-data-fim" class="form-input block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:focus:ring-indigo-600 dark:focus:border-indigo-600" required>
            </div>
            <div>
              <label for="filtro-colaborador" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Colaborador:</label>
              <select id="filtro-colaborador" name="filtro-colaborador" class="form-select block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:focus:ring-indigo-600 dark:focus:border-indigo-600">
                <option value="">Todos os Colaboradores</option>
              </select>
            </div>
            <div class="sm:col-span-2 lg:col-span-1">
              <button type="submit" id="generate-report-button" class="w-full flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-700 dark:hover:bg-blue-600 dark:focus:ring-offset-gray-800">
                <i data-lucide="search" class="w-4 h-4 mr-2"></i> Gerar Relatório
              </button>
            </div>
          </form>
        </section>

        <section class="bg-white p-4 md:p-6 rounded-lg shadow dark:bg-gray-800 dark:border dark:border-gray-700 transition-colors duration-300">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center">
            <i data-lucide="list-checks" class="w-5 h-5 mr-2 text-blue-600 dark:text-blue-400"></i> Resultado do Relatório
          </h2>
          <div id="report-summary" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-md text-sm text-blue-700 dark:bg-blue-900/50 dark:border-blue-700 dark:text-blue-300">
            <p>Utilize os filtros acima e clique em "Gerar Relatório".</p>
          </div>
          <div class="overflow-x-auto">
            <table id="report-table" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Data</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Colaborador</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Hora Início</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Hora Fim</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Duração</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                <tr>
                  <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">Nenhum relatório gerado ainda.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </main>
    </div>
  </div>
<script src="src/js/main.js" type="module"></script> {/* Inclui a lógica do dark mode e outras comuns */}
<script src="src/js/relatorio_turnos.js" type="module"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
    });
  </script>
</body>
</html>