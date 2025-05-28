<?php
// cadastrar.php

require_once __DIR__ . '/config/config.php'; //
require_once __DIR__ . '/config/conexao.php';  //
require_once __DIR__ . '/lib/LogHelper.php'; //
require_once __DIR__ . '/lib/EmailHelper.php'; //

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);  //

$login_page_url = SITE_URL . '/index.html'; //
$conta_page_url = SITE_URL . '/conta.html'; 


function setFlashAndRedirectCadastro($type, $message, $location, $conexaoSqlsrv = null) {
    // Para páginas HTML (conta.html), ainda usamos GET. Se fosse PHP, usaríamos SESSION.
    if (strpos(basename($location), '.html') !== false) {
        $param_name = ($type === 'error' || $type === 'warning') ? 'erro' : 'status';
        $param_val = $message; // Para erro, a mensagem é direta

        if ($type === 'success') {
            if (strpos(strtolower($message), 'email enviado') !== false || strpos(strtolower($message), 'verifique seu e-mail') !== false) {
                $param_val = 'cadastro_sucesso_email_enviado';
            } else {
                $param_val = 'cadastro_sucesso';
            }
        }
        
        if (isset($conexaoSqlsrv) && is_resource($conexaoSqlsrv)) sqlsrv_close($conexaoSqlsrv);
        header("Location: " . $location . "?{$param_name}=" . urlencode($param_val)); 
        exit;
    } else { // Para páginas PHP, usar flash messages
        $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
        if (isset($conexaoSqlsrv) && is_resource($conexaoSqlsrv)) sqlsrv_close($conexaoSqlsrv);
        header("Location: " . $location);
        exit;
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_completo = isset($_POST['nome_completo']) ? trim($_POST['nome_completo']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $senha_digitada = isset($_POST['senha']) ? $_POST['senha'] : '';
    $default_role = 'user';

    if (empty($nome_completo) || empty($email) || empty($usuario) || empty($senha_digitada)) {
        $logger->log('WARNING', 'Tentativa de cadastro com campos obrigatórios vazios.', ['post_data' => $_POST]); //
        setFlashAndRedirectCadastro('warning', 'Todos os campos são obrigatórios.', $conta_page_url, $conexao);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $logger->log('WARNING', 'Tentativa de cadastro com e-mail inválido.', ['email' => $email]); //
        setFlashAndRedirectCadastro('warning', 'Formato de e-mail inválido.', $conta_page_url, $conexao);
    }

    $senha_hash = password_hash($senha_digitada, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO usuarios (nome_completo, email, usuario, senha, ativo, role) VALUES (?, ?, ?, ?, 1, ?); SELECT SCOPE_IDENTITY() AS id;"; //
    $params = array($nome_completo, $email, $usuario, $senha_hash, $default_role);

    $stmt = sqlsrv_query($conexao, $sql, $params);

    if ($stmt) {
        sqlsrv_next_result($stmt); 
        sqlsrv_fetch($stmt); 
        $novo_usuario_id = sqlsrv_get_field($stmt, 0); 

        if ($novo_usuario_id > 0) {
            $logger->log('INFO', 'Novo usuário cadastrado com sucesso.', ['usuario_id' => $novo_usuario_id, 'usuario' => $usuario, 'email' => $email, 'role' => $default_role]); //

            $status_param_value = 'cadastro_sucesso';
            if (EmailHelper::sendRegistrationConfirmationEmail($email, $nome_completo)) { //
                $logger->log('INFO', 'E-mail de confirmação de cadastro enviado.', ['usuario_id' => $novo_usuario_id, 'email' => $email]); //
                $status_param_value = 'cadastro_sucesso_email_enviado';
            } else {
                $logger->log('ERROR', 'Falha ao enviar e-mail de confirmação de cadastro.', ['usuario_id' => $novo_usuario_id, 'email' => $email]); //
            }

            sqlsrv_free_stmt($stmt);
            if (isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
            header("Location: " . $login_page_url . "?status=" . $status_param_value);
            exit;
        } else {
            $logger->log('ERROR', 'Cadastro parece ter ocorrido, mas ID do novo usuário não foi retornado ou foi zero.', ['usuario' => $usuario, 'email' => $email, 'scope_identity_result' => $novo_usuario_id]); //
            setFlashAndRedirectCadastro('error', 'Erro ao finalizar o cadastro. ID não obtido.', $conta_page_url, $conexao);
        }
    } else {
        $errors = sqlsrv_errors();
        $logger->log('ERROR', 'Erro ao executar query de cadastro.', ['sqlsrv_errors' => $errors, 'usuario' => $usuario, 'email' => $email]); //

        $user_display_error = "Erro ao cadastrar o usuário.";
        if ($errors && isset($errors[0]['code']) && ($errors[0]['code'] == 2627 || $errors[0]['code'] == 2601)) { //
            $errorMessageText = strtolower($errors[0]['message']);
            if (strpos($errorMessageText, 'email') !== false) { 
                 $user_display_error = "Erro ao cadastrar: O e-mail informado já existe."; //
            } elseif (strpos($errorMessageText, 'usuario') !== false) { 
                $user_display_error = "Erro ao cadastrar: O nome de usuário já existe."; //
            } else {
                $user_display_error = "Erro ao cadastrar: O e-mail ou nome de usuário já existe."; //
            }
        } else if ($errors) {
            $user_display_error = "Ocorreu um erro inesperado durante o cadastro. Tente novamente."; //
        }
        setFlashAndRedirectCadastro('error', $user_display_error, $conta_page_url, $conexao);
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

} else {
    $logger->log('WARNING', 'Acesso inválido (não POST) à página de cadastro.'); //
    setFlashAndRedirectCadastro('error', 'Acesso inválido.', $conta_page_url, $conexao);
}

if (isset($conexao) && is_resource($conexao)) {
    sqlsrv_close($conexao);
}
exit;