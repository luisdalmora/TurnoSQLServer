<?php
// login.php 

require_once __DIR__ . '/config/config.php'; //
require_once __DIR__ . '/config/conexao.php';  //
require_once __DIR__ . '/lib/LogHelper.php';  //

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);  //

$home_page_url = SITE_URL . '/home.php'; //
$login_page_url = SITE_URL . '/index.html'; //

// Função para definir flash message e redirecionar
function setFlashAndRedirectLogin($type, $message, $location, $conexaoSqlsrv = null) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
    if (isset($conexaoSqlsrv) && is_resource($conexaoSqlsrv)) {
        sqlsrv_close($conexaoSqlsrv);
    }
    header('Location: ' . $location);
    exit;
}


if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    if (isset($conexao)) sqlsrv_close($conexao);
    header('Location: ' . $home_page_url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $erro_login_msg = ""; // Usaremos para flash message

    if (!$conexao || !is_resource($conexao) || get_resource_type($conexao) !== 'SQL Server Connection') {
        $logger->log('CRITICAL', 'Conexão com BD indisponível ou inválida em login.php (SQLSRV).', ['connection_status' => ($conexao ? 'Tipo inválido ou não é SQLSRV' : 'Não conectado')]);
        // Para index.html, que não processa PHP flash messages, redirecionamos com GET param
        // ou garantimos que auth_forms.js lide com isso de alguma forma.
        // A melhoria aqui é usar flash_message se o redirecionamento for para uma página PHP.
        // Como index.html é estático, manteremos o parâmetro GET para ele.
        header('Location: ' . $login_page_url . '?erro=' . urlencode("Falha crítica na conexão. Contate o suporte."));
        if (isset($conexao)) sqlsrv_close($conexao);
        exit;
    }

    $usuario_digitado = isset($_POST['usuario']) ? trim($_POST['usuario']) : null;
    $senha_digitada = isset($_POST['senha']) ? $_POST['senha'] : null;

    if (empty($usuario_digitado) || empty($senha_digitada)) {
        $erro_login_msg = "Usuário e Senha são obrigatórios.";
    }

    if (empty($erro_login_msg)) {
        $sql = "SELECT TOP 1 id, usuario, senha, nome_completo, email, role FROM usuarios WHERE (usuario = ? OR email = ?) AND ativo = 1"; //
        
        $params = [$usuario_digitado, $usuario_digitado];
        $stmt = sqlsrv_query($conexao, $sql, $params); 

        if ($stmt) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);

            if ($row) { 
                $db_id = $row['id'];
                $db_usuario = $row['usuario'];
                $db_senha_hash = $row['senha'];
                $db_nome_completo = $row['nome_completo'];
                $db_email = $row['email'];
                $db_role = $row['role'];

                if (password_verify($senha_digitada, $db_senha_hash)) {
                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = $db_id;
                    $_SESSION['usuario_nome'] = $db_usuario;
                    $_SESSION['usuario_nome_completo'] = $db_nome_completo;
                    $_SESSION['usuario_email'] = $db_email;
                    $_SESSION['usuario_role'] = $db_role; 
                    $_SESSION['logado'] = true;
                    
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
                    $_SESSION['csrf_token_backup'] = bin2hex(random_bytes(32));
                    $_SESSION['csrf_token_ausencias'] = bin2hex(random_bytes(32));
                    $_SESSION['csrf_token_obs_geral'] = bin2hex(random_bytes(32));
                    $_SESSION['csrf_token_colab_manage'] = bin2hex(random_bytes(32));
                    $_SESSION['csrf_token_cad_colab'] = bin2hex(random_bytes(32));
                    $_SESSION['csrf_token_scripts_manage'] = bin2hex(random_bytes(32));
                    $_SESSION['csrf_token_reports'] = bin2hex(random_bytes(32));

                    $logger->log('AUTH_SUCCESS', 'Login bem-sucedido (SQLSRV).', ['usuario_id' => $db_id, 'usuario' => $db_usuario, 'role' => $db_role]); //
                    if (isset($conexao)) sqlsrv_close($conexao);
                    header('Location: ' . $home_page_url);
                    exit;
                } else {
                    $erro_login_msg = "Usuário ou senha incorretos.";
                    $logger->log('AUTH_FAILURE', $erro_login_msg, ['usuario_digitado' => $usuario_digitado, 'motivo' => 'Senha não confere (SQLSRV)']); //
                }
            } else { 
                $erro_login_msg = "Usuário ou senha incorretos, ou usuário inativo.";
                $logger->log('AUTH_FAILURE', $erro_login_msg, ['usuario_digitado' => $usuario_digitado, 'motivo' => 'Usuário não encontrado ou inativo (SQLSRV)']); //
            }
        } else {
            $errors = sqlsrv_errors();
            $error_message_sqlsrv = "";
            if ($errors) { foreach($errors as $error) { $error_message_sqlsrv .= $error['message']." "; } }
            $erro_login_msg = "Erro no sistema ao processar o login. Tente novamente.";
            $logger->log('ERROR', 'Falha ao executar query de login (SQLSRV).', ['sqlsrv_errors' => $error_message_sqlsrv]); //
        }
    }

    if (!empty($erro_login_msg)) {
        // Para index.html, continuamos usando o parâmetro GET.
        // Se você converter index.html para index.php, poderá usar flash messages aqui também.
        if (isset($conexao)) sqlsrv_close($conexao);
        header('Location: ' . $login_page_url . '?erro=' . urlencode($erro_login_msg));
        exit;
    }

} else {
    if (isset($conexao)) sqlsrv_close($conexao);
    header('Location: ' . $login_page_url);
    exit;
}