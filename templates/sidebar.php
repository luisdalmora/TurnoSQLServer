<?php
// templates/sidebar.php
$csrfTokenBackup = $_SESSION['csrf_token_backup'] ?? '';
$currentPage = $currentPage ?? ''; // Vem do arquivo que inclui o header/sidebar
?>
<aside class="w-64 bg-gradient-to-b from-blue-800 to-blue-700 text-indigo-100 flex flex-col flex-shrink-0">
    <div class="h-16 flex items-center px-4 md:px-6 border-b border-white/10">
        <i data-lucide="gauge-circle" class="w-7 h-7 md:w-8 md:h-8 mr-2 md:mr-3 text-white"></i>
        <h2 class="text-lg md:text-xl font-semibold text-white">Sim Posto</h2>
    </div>
    <nav class="flex-grow p-2 space-y-1">
        <a href="<?php echo BASE_URL; ?>/home.php" class="flex items-center px-3 py-2.5 rounded-lg transition-colors text-sm <?php echo ($currentPage === 'home') ? 'bg-blue-600 text-white font-medium' : 'hover:bg-blue-500 hover:text-white'; ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i> Dashboard
        </a>
        <a href="<?php echo BASE_URL; ?>/relatorio_turnos.php" class="flex items-center px-3 py-2.5 rounded-lg transition-colors text-sm <?php echo ($currentPage === 'relatorios') ? 'bg-blue-600 text-white font-medium' : 'hover:bg-blue-500 hover:text-white'; ?>">
            <i data-lucide="file-text" class="w-5 h-5 mr-3"></i> Relatórios
        </a>
        <a href="<?php echo BASE_URL; ?>/gerenciar_colaboradores.php" class="flex items-center px-3 py-2.5 rounded-lg transition-colors text-sm <?php echo ($currentPage === 'colaboradores') ? 'bg-blue-600 text-white font-medium' : 'hover:bg-blue-500 hover:text-white'; ?>">
            <i data-lucide="users" class="w-5 h-5 mr-3"></i> Colaboradores
        </a>
        <a href="<?php echo BASE_URL; ?>/gerador_senhas.php" class="flex items-center px-3 py-2.5 rounded-lg transition-colors text-sm <?php echo ($currentPage === 'gerador_senhas') ? 'bg-blue-600 text-white font-medium' : 'hover:bg-blue-500 hover:text-white'; ?>">
            <i data-lucide="key-round" class="w-5 h-5 mr-3"></i> Gerador de Senhas
        </a>
        <a href="<?php echo BASE_URL; ?>/gerenciar_scripts.php" class="flex items-center px-3 py-2.5 rounded-lg transition-colors text-sm <?php echo ($currentPage === 'scripts') ? 'bg-blue-600 text-white font-medium' : 'hover:bg-blue-500 hover:text-white'; ?>">
            <i data-lucide="file-code-2" class="w-5 h-5 mr-3"></i> Scripts
        </a>
    </nav>
    <div class="p-2 border-t border-white/10">
         <div class="px-2 py-1 space-y-1.5">
            <?php if (!empty($csrfTokenBackup)): ?>
                <input type="hidden" id="csrf-token-backup" value="<?php echo htmlspecialchars($csrfTokenBackup); ?>">
                <a href="#" id="backup-db-btn" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-teal-500 hover:bg-teal-600 text-white font-medium transition-colors text-sm">
                    <i data-lucide="database-backup" class="w-4 h-4 mr-2"></i> Backup BD
                </a>
            <?php endif; ?>
        </div>
        <div class="px-2 py-1 mt-1.5">
            <a href="<?php echo BASE_URL; ?>/logout.php" id="logout-link" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-red-500 hover:bg-red-600 text-white font-medium transition-colors text-sm">
                <i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Sair
            </a>
        </div>
    </div>
</aside>