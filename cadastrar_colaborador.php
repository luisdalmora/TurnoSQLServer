<?php
require_once __DIR__ . '/config.php'; // Garante que a sessão está iniciada

// Verificar se o usuário está logado, redirecionar para o login se não estiver
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Para requisições AJAX, responder com JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sessão expirada ou acesso negado.', 'action' => 'redirect', 'location' => 'index.html']);
        exit;
    }
    // Para requisições normais, redirecionar
    header('Location: index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}
$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';

// Token CSRF para este formulário específico
if (empty($_SESSION['csrf_token_cad_colab'])) {
    $_SESSION['csrf_token_cad_colab'] = bin2hex(random_bytes(32));
}
$csrfTokenCadColab = $_SESSION['csrf_token_cad_colab'];

$flashMessage = null;
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Novo Colaborador - Sim Posto</title>
    <link href="src/output.css" rel="stylesheet"> 
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body class="bg-gray-100 font-poppins text-gray-700">
    <div class="flex h-screen overflow-hidden"> 
        
        <aside class="w-64 bg-gradient-to-b from-blue-800 to-blue-700 text-indigo-100 flex flex-col flex-shrink-0">
            <div class="h-16 flex items-center px-4 md:px-6 border-b border-white/10">
                <i data-lucide="gauge-circle" class="w-7 h-7 md:w-8 md:h-8 mr-2 md:mr-3 text-white"></i>
                <h2 class="text-lg md:text-xl font-semibold text-white">Sim Posto</h2>
            </div>
            <nav class="flex-grow p-2 space-y-1">
                <a href="home.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
                    <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i> Dashboard
                </a>
                <a href="relatorio_turnos.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
                    <i data-lucide="file-text" class="w-5 h-5 mr-3"></i> Relatórios
                </a>
                
                <a href="gerenciar_colaboradores.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
                    <i data-lucide="users" class="w-5 h-5 mr-3"></i> Colaboradores
                </a>
                <a href="gerador_senhas.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
                    <i data-lucide="key-round" class="w-5 h-5 mr-3"></i> Gerador de Senhas
                </a>
                <a href="gerenciar_scripts.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
                    <i data-lucide="file-code-2" class="w-5 h-5 mr-3"></i> Scripts
                </a>
            </nav>
            <div class="p-2 border-t border-white/10">
                 <div class="px-2 py-1 space-y-1.5">
                    <?php
                    if (isset($_SESSION['csrf_token_backup'])) {
                        echo '<input type="hidden" id="csrf-token-backup" value="' . htmlspecialchars($_SESSION['csrf_token_backup']) . '">';
                        echo '<a href="#" id="backup-db-btn" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-teal-500 hover:bg-teal-600 text-white font-medium transition-colors text-sm">';
                        echo '<i data-lucide="database-backup" class="w-4 h-4 mr-2"></i> Backup BD';
                        echo '</a>';
                    }
                    ?>
                </div>
                <div class="px-2 py-1 mt-1.5">
                    <a href="logout.php" id="logout-link" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-red-500 hover:bg-red-600 text-white font-medium transition-colors text-sm">
                        <i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Sair
                    </a>
                </div>
            </div>
        </aside>

        <div class="flex-grow flex flex-col overflow-y-auto">
            <header class="h-16 bg-white shadow-sm flex items-center justify-between px-4 md:px-6 flex-shrink-0">
                <div class="flex items-center">
                  <i data-lucide="user-plus" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600"></i>
                  <h1 class="text-md md:text-lg font-semibold text-gray-800">Cadastrar Novo Colaborador</h1>
                </div>
                <div id="user-info" class="flex items-center text-sm font-medium text-gray-700">
                  Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?>
                  <i data-lucide="circle-user-round" class="w-5 h-5 md:w-6 md:h-6 ml-2 text-blue-600"></i>
                </div>
            </header>

            <main class="flex-grow p-4 md:p-6">
                <section class="bg-white p-4 md:p-6 rounded-lg shadow max-w-2xl mx-auto"> 
                    <h2 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                        <i data-lucide="edit-3" class="w-5 h-5 mr-2 text-blue-600"></i> Informações do Novo Colaborador
                    </h2>
                    <form method="POST" action="processar_cadastro_colaborador.php" class="space-y-6">
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
                            <a href="gerenciar_colaboradores.php" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 order-last sm:order-first">
                                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Voltar
                            </a>
                            <button type="submit"
                                    class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i> Cadastrar Colaborador
                            </button>
                        </div>
                    </form>
                </section>
            </main>
        </div>
    </div>
    <script src="src/js/main.js" type="module"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            // A lógica de placeholder flutuante do style.css foi removida
            // e os inputs agora usam o estilo padrão do Tailwind Forms.
            // Se quiser manter o efeito de placeholder flutuante, precisaria
            // de JS adicional e classes Tailwind customizadas para replicar.

            <?php if ($flashMessage && is_array($flashMessage) && isset($flashMessage['message']) && isset($flashMessage['type'])): ?>
            // Usa a função global showToast definida em utils.js (importada por main.js)
            if (typeof window.showGlobalToast === 'function') {
                window.showGlobalToast('<?php echo addslashes(htmlspecialchars($flashMessage['message'])); ?>', '<?php echo addslashes(htmlspecialchars($flashMessage['type'])); ?>');
            } else {
                // Fallback se showGlobalToast não estiver disponível (improvável se main.js carrega)
                alert('<?php echo ucfirst(addslashes(htmlspecialchars($flashMessage['type']))); ?>: <?php echo addslashes(htmlspecialchars($flashMessage['message'])); ?>');
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>