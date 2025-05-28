<?php
// home.php (Versão de Teste Simplificada)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Simula o básico para o header.php não quebrar
$pageTitle = 'Página de Teste';
$currentPage = 'home_teste'; 
// $headerIcon = '<i data-lucide="alert-triangle"></i>'; // Removido para simplicidade máxima

// Inclui o header (que por sua vez inclui config, conexao, e define funções)
require_once __DIR__ . '/templates/header.php';
?>

{/* O conteúdo de <main> será mínimo */}
<div class="p-4">
    <h1 class="text-2xl font-bold text-red-600">PÁGINA DE TESTE CARREGADA</h1>
    <p class="text-slate-700">Se você vê esta mensagem, o PHP básico, header e footer estão funcionando.</p>
    <p class="text-slate-700">Verifique o console do navegador para logs do main.js.</p>
</div>

<?php
// Limpa pageSpecificJs para este teste
$pageSpecificJs = []; 
require_once __DIR__ . '/templates/footer.php';
?>