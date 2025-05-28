<?php
// gerador_senhas.php

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

$pageTitle = 'Gerador de Senhas';
$currentPage = 'gerador_senhas';
$headerIcon = '<i data-lucide="key-round" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600"></i>';

// O script JS será carregado como módulo pelo footer.php
$pageSpecificJs = ['/public/js/page_specific/gerador_senhas_script.js'];
// Não precisamos mais do CSS do Toastr aqui, pois usamos o showToast customizado
$pageSpecificCss = []; 

require_once __DIR__ . '/templates/header.php';
?>

<div class="flex items-center justify-center flex-grow">
    <section class="bg-white p-6 md:p-8 rounded-lg shadow-lg w-full max-w-xs text-center">
        <h2 class="text-xl font-semibold text-gray-800 mb-6">Gerar Senha</h2>

        <button id="senhaPay" class="w-full mb-3 flex items-center justify-center px-4 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
            <i data-lucide="credit-card" class="w-4 h-4 mr-2"></i> Senha Pay
        </button>
        <button id="senhaAutomacao" class="w-full mb-6 flex items-center justify-center px-4 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
            <i data-lucide="zap" class="w-4 h-4 mr-2"></i> Senha Automação
        </button>

        <div id="senhaGeradaDisplayContainer" class="mb-6 hidden py-4">
            <p id="senhaGeradaDisplay" class="text-blue-600 text-4xl md:text-5xl font-bold break-all font-roboto-mono"></p>
        </div>

        <button id="copiarSenha" class="w-full items-center justify-center px-4 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150 ease-in-out hidden">
            <i data-lucide="copy" class="w-4 h-4 mr-2"></i> Copiar Senha
        </button>
    </section>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>