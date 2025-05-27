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
?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
    // Configuração do Toastr (pode já estar no seu gerador_senhas.js ou main.js)
    if (typeof toastr !== 'undefined') {
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": true,
            "timeOut": "3000"
        };
    }
</script>

<?php
$pageTitle = 'Gerador de Senhas';
$currentPage = 'gerador_senhas';
$headerIcon = '<i data-lucide="key-round" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600"></i>';


$pageSpecificJs = ['/public/js/page_specific/gerador_senhas.js'];
// CSS do Toastr pode ser linkado no header.php ou aqui via $pageSpecificCss se não quiser global.
// Adicionando ao $pageSpecificCss para ser pego pelo header.php:
$pageSpecificCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

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
// jQuery e Toastr são carregados via CDN no footer para esta página,
// conforme o arquivo original. Se preferir local, adicione aos $pageSpecificJs.
$pageSpecificJs = [
    '/public/js/page_specific/gerador_senhas.js' // O seu script JS que usa jQuery e Toastr
];

// O footer.php incluirá os scripts.
// Adicione a referência ao Toastr CSS e Roboto Mono font no header.php ou
// inclua o CSS do Toastr via $pageSpecificCss aqui se preferir.
$pageSpecificCssLinks = [
    "https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"
];

// Modificando o header para incluir CSS e JS específicos de CDN para esta página
// Isso é um hack. Idealmente, o header.php seria mais flexível ou essas libs seriam locais.
// Para este caso, vamos adicionar diretamente no footer.php.
// Se preferir, pode modificar o header.php para aceitar um array de links CSS/JS externos.

require_once __DIR__ . '/templates/footer.php';
?>