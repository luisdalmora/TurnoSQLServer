<?php
// processar_cadastro_colaborador.php 

// Caminhos atualizados
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/conexao.php'; 
require_once __DIR__ . '/lib/LogHelper.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); 
$adminUserId = $_SESSION['usuario_id'] ?? null;

// SITE_URL já definido em config.php
$cadastrar_colaborador_page_url = SITE_URL . '/cadastrar_colaborador.php';
$gerenciar_colaboradores_page_url = SITE_URL . '/gerenciar_colaboradores.php';


function setFlashAndRedirectColabSQLSRV($conexaoSqlsrv, $type, $message, $location) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
    if (isset($conexaoSqlsrv) && is_resource($conexaoSqlsrv)) {
        sqlsrv_close($conexaoSqlsrv);
    }
    header("Location: " . $location);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token_cad_colab']) || !hash_equals($_SESSION['csrf_token_cad_colab'], $_POST['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token ao cadastrar colaborador.', ['admin_user_id' => $adminUserId, 'posted_token' => $_POST['csrf_token'] ?? 'N/A']);
        setFlashAndRedirectColabSQLSRV($conexao, 'error', 'Erro de segurança (token inválido). Por favor, tente novamente.', $cadastrar_colaborador_page_url);
    }
    // Após o uso, o token deve ser regenerado na página do formulário se o usuário permanecer nela.
    // Aqui, como estamos redirecionando, a próxima carga da página irá gerar um novo se necessário.

    $nome_completo = isset($_POST['nome_completo']) ? trim($_POST['nome_completo']) : '';
    $email_input = isset($_POST['email']) ? trim($_POST['email']) : null;
    $cargo_input = isset($_POST['cargo']) ? trim($_POST['cargo']) : null;
    $ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1; 

    if (empty($nome_completo)) {
        setFlashAndRedirectColabSQLSRV($conexao, 'warning', 'Nome completo é obrigatório.', $cadastrar_colaborador_page_url);
    }

    $email = null;
    if (!empty($email_input)) {
        if (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            setFlashAndRedirectColabSQLSRV($conexao, 'warning', 'Formato de e-mail inválido.', $cadastrar_colaborador_page_url);
        }
        $email = $email_input;
    }
    $cargo = ($cargo_input !== null && trim($cargo_input) === '') ? null : $cargo_input;

    $sql = "INSERT INTO colaboradores (nome_completo, email, cargo, ativo) VALUES (?, ?, ?, ?); SELECT SCOPE_IDENTITY() AS id;";
    $params = [$nome_completo, $email, $cargo, $ativo];

    $stmt = sqlsrv_query($conexao, $sql, $params);

    if ($stmt) {
        sqlsrv_next_result($stmt); 
        sqlsrv_fetch($stmt); 
        $novo_colaborador_id = sqlsrv_get_field($stmt, 0); 
        sqlsrv_free_stmt($stmt);

        if ($novo_colaborador_id > 0) {
            $logger->log('INFO', 'Novo colaborador cadastrado com sucesso (SQLSRV).', [
                'colaborador_id' => $novo_colaborador_id,
                'nome' => $nome_completo,
                'admin_user_id' => $adminUserId
            ]);
            setFlashAndRedirectColabSQLSRV($conexao, 'success', "Colaborador '".htmlspecialchars($nome_completo)."' cadastrado com sucesso!", $gerenciar_colaboradores_page_url);
        } else {
            $logger->log('ERROR', 'Cadastro de colaborador executado (SQLSRV), mas ID não foi retornado ou foi zero.', [
                'nome' => $nome_completo, 'admin_user_id' => $adminUserId, 'scope_identity_result' => $novo_colaborador_id
            ]);
            setFlashAndRedirectColabSQLSRV($conexao, 'error', 'Erro ao finalizar o cadastro do colaborador. ID não obtido.', $cadastrar_colaborador_page_url);
        }
    } else {
        $errors = sqlsrv_errors();
        $error_message_sqlsrv = "";
        $error_code_sqlsrv = null;
        if ($errors) { 
            $error_message_sqlsrv = $errors[0]['message']; 
            $error_code_sqlsrv = $errors[0]['code'];
        }

        $logger->log('ERROR', 'Erro ao executar query de cadastro de colaborador (SQLSRV).', [
            'sqlsrv_errors' => $error_message_sqlsrv, 'sqlsrv_code' => $error_code_sqlsrv, 'nome' => $nome_completo, 'admin_user_id' => $adminUserId
        ]);

        $user_message = "Erro ao cadastrar o colaborador.";
        if ($error_code_sqlsrv == 2627 || $error_code_sqlsrv == 2601) {
            if (is_string($error_message_sqlsrv) && stripos($error_message_sqlsrv, 'email') !== false) { 
                 $user_message = "Erro: O e-mail informado ('".htmlspecialchars($email ?? '')."') já está cadastrado.";
            } else {
                 $user_message = "Erro: Já existe um registro com um dos valores únicos informados (ex: e-mail).";
            }
        } else {
            // $user_message .= " Detalhe técnico: " . htmlentities($error_message_sqlsrv); // Evitar expor
            $user_message = "Ocorreu um erro inesperado durante o cadastro. Tente novamente.";
        }
        setFlashAndRedirectColabSQLSRV($conexao, 'error', $user_message, $cadastrar_colaborador_page_url);
    }

} else {
    $logger->log('WARNING', 'Acesso inválido (não POST) à página de processar_cadastro_colaborador.', ['admin_user_id' => $adminUserId]);
    setFlashAndRedirectColabSQLSRV(isset($conexao) ? $conexao : null, 'error', 'Acesso inválido. Utilize o formulário de cadastro.', $cadastrar_colaborador_page_url);
}
?>