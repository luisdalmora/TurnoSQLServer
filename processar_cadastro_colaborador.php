<?php
// processar_cadastro_colaborador.php 
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/conexao.php'; 
require_once __DIR__ . '/lib/LogHelper.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Função isAdmin (pode ser movida para um helper se usada em múltiplos scripts não-API)
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['usuario_role']) && $_SESSION['usuario_role'] === 'admin';
    }
}

$logger = new LogHelper($conexao); 
$adminUserId = $_SESSION['usuario_id'] ?? null; // Usado para logging

// URLs para redirecionamento
$cadastrar_colaborador_page_url = SITE_URL . '/cadastrar_colaborador.php';
$gerenciar_colaboradores_page_url = SITE_URL . '/gerenciar_colaboradores.php';
$home_page_url = SITE_URL . '/home.php'; // Para redirecionar não-admins
$login_page_url = SITE_URL . '/index.html';


function setFlashAndRedirectColabSQLSRV($conexaoSqlsrv, $type, $message, $location) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
    if (isset($conexaoSqlsrv) && is_resource($conexaoSqlsrv)) {
        sqlsrv_close($conexaoSqlsrv);
    }
    header("Location: " . $location);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verifica se o usuário está logado
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        setFlashAndRedirectColabSQLSRV(isset($conexao) ? $conexao : null, 'error', 'Acesso negado. Faça login primeiro.', $login_page_url);
    }
    // Verifica se o usuário é admin
    if (!isAdmin()) {
        if ($logger && is_object($logger) && method_exists($logger, 'log')) {
            $logger->log('AUTH_FAILURE', 'Tentativa de cadastro de colaborador sem permissão de admin.', ['admin_user_id' => $adminUserId, 'role' => $_SESSION['usuario_role'] ?? 'N/A']);
        }
        setFlashAndRedirectColabSQLSRV(isset($conexao) ? $conexao : null, 'error', 'Você não tem permissão para cadastrar colaboradores.', $home_page_url);
    }

    // Validação do CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token_cad_colab']) || !hash_equals($_SESSION['csrf_token_cad_colab'], $_POST['csrf_token'])) {
        if ($logger && is_object($logger) && method_exists($logger, 'log')) {
            $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token ao cadastrar colaborador.', ['admin_user_id' => $adminUserId, 'posted_token' => $_POST['csrf_token'] ?? 'N/A']);
        }
        setFlashAndRedirectColabSQLSRV(isset($conexao) ? $conexao : null, 'error', 'Erro de segurança (token inválido). Por favor, tente novamente.', $cadastrar_colaborador_page_url);
    }
    // Regenerar CSRF token para a página de cadastro, se o usuário for redirecionado de volta para ela por erro
    $_SESSION['csrf_token_cad_colab'] = bin2hex(random_bytes(32));


    $nome_completo = isset($_POST['nome_completo']) ? trim($_POST['nome_completo']) : '';
    $email_input = isset($_POST['email']) ? trim($_POST['email']) : null;
    $cargo_input = isset($_POST['cargo']) ? trim($_POST['cargo']) : null;
    $ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1; 

    if (empty($nome_completo)) {
        setFlashAndRedirectColabSQLSRV(isset($conexao) ? $conexao : null, 'warning', 'Nome completo é obrigatório.', $cadastrar_colaborador_page_url);
    }

    $email = null;
    if (!empty($email_input)) {
        if (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            setFlashAndRedirectColabSQLSRV(isset($conexao) ? $conexao : null, 'warning', 'Formato de e-mail inválido.', $cadastrar_colaborador_page_url);
        }
        $email = $email_input;
    }
    $cargo = ($cargo_input !== null && trim($cargo_input) === '') ? null : $cargo_input;

    $sql = "INSERT INTO colaboradores (nome_completo, email, cargo, ativo, criado_por_usuario_id) VALUES (?, ?, ?, ?, ?); SELECT SCOPE_IDENTITY() AS id;";
    $params = [$nome_completo, $email, $cargo, $ativo, $adminUserId]; // Adicionado criado_por_usuario_id

    $stmt = sqlsrv_query($conexao, $sql, $params);

    if ($stmt) {
        sqlsrv_next_result($stmt); 
        sqlsrv_fetch($stmt); 
        $novo_colaborador_id = sqlsrv_get_field($stmt, 0); 
        sqlsrv_free_stmt($stmt);

        if ($novo_colaborador_id > 0) {
            if ($logger && is_object($logger) && method_exists($logger, 'log')) {
                $logger->log('INFO', 'Novo colaborador cadastrado com sucesso (SQLSRV).', [
                    'colaborador_id' => $novo_colaborador_id,
                    'nome' => $nome_completo,
                    'admin_user_id' => $adminUserId
                ]);
            }
            setFlashAndRedirectColabSQLSRV(isset($conexao) ? $conexao : null, 'success', "Colaborador '".htmlspecialchars($nome_completo)."' cadastrado com sucesso!", $gerenciar_colaboradores_page_url);
        } else {
            if ($logger && is_object($logger) && method_exists($logger, 'log')) {
                $logger->log('ERROR', 'Cadastro de colaborador executado (SQLSRV), mas ID não foi retornado ou foi zero.', [
                    'nome' => $nome_completo, 'admin_user_id' => $adminUserId, 'scope_identity_result' => $novo_colaborador_id
                ]);
            }
            setFlashAndRedirectColabSQLSRV(isset($conexao) ? $conexao : null, 'error', 'Erro ao finalizar o cadastro do colaborador. ID não obtido.', $cadastrar_colaborador_page_url);
        }
    } else {
        $errors = sqlsrv_errors();
        $error_message_sqlsrv = "";
        $error_code_sqlsrv = null;
        if ($errors) { 
            $error_message_sqlsrv = $errors[0]['message']; 
            $error_code_sqlsrv = $errors[0]['code'];
        }
        if ($logger && is_object($logger) && method_exists($logger, 'log')) {
            $logger->log('ERROR', 'Erro ao executar query de cadastro de colaborador (SQLSRV).', [
                'sqlsrv_errors' => $error_message_sqlsrv, 'sqlsrv_code' => $error_code_sqlsrv, 'nome' => $nome_completo, 'admin_user_id' => $adminUserId
            ]);
        }

        $user_message = "Erro ao cadastrar o colaborador.";
        if ($error_code_sqlsrv == 2627 || $error_code_sqlsrv == 2601) { // Unique constraint violation
            if (is_string($error_message_sqlsrv) && stripos($error_message_sqlsrv, 'email') !== false) { 
                 $user_message = "Erro: O e-mail informado ('".htmlspecialchars($email ?? '')."') já está cadastrado.";
            } else {
                 $user_message = "Erro: Já existe um registro com um dos valores únicos informados (ex: e-mail).";
            }
        } else {
            $user_message = "Ocorreu um erro inesperado durante o cadastro. Tente novamente.";
        }
        setFlashAndRedirectColabSQLSRV(isset($conexao) ? $conexao : null, 'error', $user_message, $cadastrar_colaborador_page_url);
    }

} else {
    if ($logger && is_object($logger) && method_exists($logger, 'log')) {
        $logger->log('WARNING', 'Acesso inválido (não POST) à página de processar_cadastro_colaborador.', ['admin_user_id' => $adminUserId]);
    }
    setFlashAndRedirectColabSQLSRV(isset($conexao) ? $conexao : null, 'error', 'Acesso inválido. Utilize o formulário de cadastro.', $cadastrar_colaborador_page_url);
}
