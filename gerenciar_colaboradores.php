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
$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';

if (empty($_SESSION['csrf_token_colab_manage'])) {
    $_SESSION['csrf_token_colab_manage'] = bin2hex(random_bytes(32));
}
$csrfTokenColabManage = $_SESSION['csrf_token_colab_manage'];
?>
<!DOCTYPE html>
<html lang="pt-BR" class="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Colaboradores - Sim Posto</title>
    <link href="src/output.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.6); display: none; align-items: center; justify-content: center;
            z-index: 1000; opacity: 0; transition: opacity 0.3s ease-in-out;
        }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal-content {
            background-color: white; padding: 20px; border-radius: 8px; 
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15); 
            width: 90%; max-width: 500px; position: relative;
            transform: scale(0.95); transition: transform 0.3s ease-in-out;
        }
        html.dark .modal-content { background-color: #1f2937; /* bg-gray-800 */ color: #d1d5db; /* text-gray-300 */ }
        html.dark .modal-content h2 { color: #f3f4f6; /* text-gray-100 */ border-bottom-color: #4b5563; /* border-gray-600 */ }
        html.dark .form-group-modal label { color: #d1d5db; /* text-gray-300 */ }
        html.dark .form-group-modal input[type="text"],
        html.dark .form-group-modal input[type="email"] {
             background-color: #374151; /* bg-gray-700 */ border-color: #4b5563; /* border-gray-600 */ color: #f3f4f6; /* text-gray-100 */
        }
         html.dark .form-group-modal input[type="text"]:focus,
        html.dark .form-group-modal input[type="email"]:focus {
            border-color: #60a5fa; /* focus:border-blue-400 */
            box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.3); /* focus:ring-2 focus:ring-blue-500/30 */
        }
        .modal-overlay.show .modal-content { transform: scale(1); }
        .modal-close-button {
            position: absolute; top: 10px; right: 15px; font-size: 1.8em; color: #6b7280; /* text-gray-500 */ cursor: pointer; line-height: 1;
        }
        html.dark .modal-close-button { color: #9ca3af; /* text-gray-400 */ }
        .modal-close-button:hover { color: #374151; /* text-gray-700 */ }
        html.dark .modal-close-button:hover { color: #e5e7eb; /* text-gray-200 */ }
        .form-group-modal { margin-bottom: 18px; }
        .form-group-modal label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 0.9em; }
        .form-group-modal input[type="text"], .form-group-modal input[type="email"] {
             width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 0.375rem; box-sizing: border-box; font-size: 0.95em;
        }
        .form-group-modal input[type="text"]:focus, .form-group-modal input[type="email"]:focus {
            border-color: #4f46e5; box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2); outline: none;
        }
        .modal-actions { margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px; }
        .status-ativo { color: #10b981; font-weight: bold; } /* text-green-600 */
        .status-inativo { color: #ef4444; font-weight: bold; } /* text-red-500 */
        html.dark .status-ativo { color: #34d399; } /* dark:text-green-400 */
        html.dark .status-inativo { color: #f87171; } /* dark:text-red-400 */
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
            <a href="gerenciar_colaboradores.php" class="flex items-center px-3 py-2.5 rounded-lg bg-blue-600 text-white font-medium text-sm dark:bg-blue-700 dark:hover:bg-blue-600">
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
                  <i data-lucide="users-cog" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600 dark:text-blue-400"></i>
                  <h1 class="text-md md:text-lg font-semibold text-gray-800 dark:text-gray-100">Gerenciar Colaboradores</h1>
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

            <main class="flex-grow p-4 md:p-6">
                <section class="bg-white p-4 md:p-6 rounded-lg shadow dark:bg-gray-800 dark:border dark:border-gray-700 transition-colors duration-300">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 flex items-center">
                            <i data-lucide="list-ul" class="w-5 h-5 mr-2 text-blue-600 dark:text-blue-400"></i> Lista de Colaboradores
                        </h2>
                        <a href="cadastrar_colaborador.php" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:bg-green-700 dark:hover:bg-green-600 dark:focus:ring-offset-gray-800">
                            <i data-lucide="user-plus" class="w-4 h-4 mr-2"></i> Novo Colaborador
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table id="collaborators-table" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nome Completo</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cargo</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                <tr><td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">Carregando colaboradores... <i data-lucide="loader-circle" class="lucide-spin inline-block"></i></td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <div id="edit-collaborator-modal" class="modal-overlay">
        <div class="modal-content"> {/* Estilos dark para modal-content e filhos são aplicados via CSS global na tag style */}
            <form id="edit-collaborator-form">
                <input type="hidden" id="edit-colab-id" name="colab_id">
                <input type="hidden" id="edit-csrf-token" name="csrf_token" value="<?php echo htmlspecialchars($csrfTokenColabManage); ?>">
                <button type="button" class="modal-close-button" id="modal-close-btn" title="Fechar"><i data-lucide="x"></i></button>
                <h2 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">Editar Colaborador</h2>
                
                <div class="form-group-modal">
                    <label for="edit-nome_completo" class="text-sm font-medium">Nome Completo:</label>
                    <input type="text" id="edit-nome_completo" name="nome_completo" class="mt-1 block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none sm:text-sm" required>
                </div>
                <div class="form-group-modal">
                    <label for="edit-email" class="text-sm font-medium">Email:</label>
                    <input type="email" id="edit-email" name="email" class="mt-1 block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none sm:text-sm">
                </div>
                <div class="form-group-modal">
                    <label for="edit-cargo" class="text-sm font-medium">Cargo:</label>
                    <input type="text" id="edit-cargo" name="cargo" class="mt-1 block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none sm:text-sm">
                </div>
                <div class="modal-actions">
                    <button type="button" id="cancel-edit-colab-button" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500 dark:border-gray-500 dark:focus:ring-offset-gray-800">
                        <i data-lucide="x-circle" class="w-4 h-4 mr-2"></i> Cancelar
                    </button>
                    <button type="submit" id="save-edit-colab-button" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-700 dark:hover:bg-blue-600 dark:focus:ring-offset-gray-800">
                        <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script src="src/js/main.js" type="module"></script>
    <script src="src/js/gerenciar_colaboradores.js" type="module"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
      <?php
        if (isset($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            // Garante que showGlobalToast está disponível antes de chamar
            echo "if(typeof window.showGlobalToast === 'function'){ window.showGlobalToast('" . addslashes(htmlspecialchars($flash['message'])) . "', '" . addslashes(htmlspecialchars($flash['type'])) . "', 5000); } else { alert('" . addslashes(htmlspecialchars($flash['message'])) . "'); }";
            unset($_SESSION['flash_message']);
        }
      ?>
    });
    </script>
</body>
</html>