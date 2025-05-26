<?php
// login.php (Adaptado para SQL Server)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Agora $conexao é um recurso SQLSRV
require_once __DIR__ . '/LogHelper.php'; // Assegure que LogHelper está adaptado para SQLSRV

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); // $conexao agora é SQLSRV

function fecharConexaoLoginSQLSRVRedirect($conexaoSqlsrv, $url) {
    if (isset($conexaoSqlsrv) && is_resource($conexaoSqlsrv)) {
        sqlsrv_close($conexaoSqlsrv);
    }
    header('Location: ' . $url);
    exit;
}

if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    fecharConexaoLoginSQLSRVRedirect($conexao, 'home.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $erro_login = "";

    if (!$conexao || !is_resource($conexao) || get_resource_type($conexao) !== 'SQL Server Connection') {
        $logger->log('CRITICAL', 'Conexão com BD indisponível ou inválida em login.php (SQLSRV).', ['connection_status' => ($conexao ? 'Tipo inválido ou não é SQLSRV' : 'Não conectado')]);
        fecharConexaoLoginSQLSRVRedirect(null, 'index.html?erro=' . urlencode("Falha crítica na conexão. Contate o suporte."));
    }

    $usuario_digitado = isset($_POST['usuario']) ? trim($_POST['usuario']) : null;
    $senha_digitada = isset($_POST['senha']) ? $_POST['senha'] : null;

    if (empty($usuario_digitado) || empty($senha_digitada)) {
        $erro_login = "Usuário e Senha são obrigatórios.";
    }

    if (empty($erro_login)) {
        // SQL adaptado para SQL Server: TOP 1 e sem crases
        $sql = "SELECT TOP 1 id, usuario, senha, nome_completo, email FROM usuarios WHERE (usuario = ? OR email = ?) AND ativo = 1";
        
        $params = [$usuario_digitado, $usuario_digitado];
        $stmt = sqlsrv_query($conexao, $sql, $params); // sqlsrv_query para SELECT

        if ($stmt) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);

            if ($row) { // Usuário encontrado
                $db_id = $row['id'];
                $db_usuario = $row['usuario'];
                $db_senha_hash = $row['senha'];
                $db_nome_completo = $row['nome_completo'];
                $db_email = $row['email'];

                if (password_verify($senha_digitada, $db_senha_hash)) {
                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = $db_id;
                    $_SESSION['usuario_nome'] = $db_usuario;
                    $_SESSION['usuario_nome_completo'] = $db_nome_completo;
                    $_SESSION['usuario_email'] = $db_email;
                    $_SESSION['logado'] = true;
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 

                    $logger->log('AUTH_SUCCESS', 'Login bem-sucedido (SQLSRV).', ['usuario_id' => $db_id, 'usuario' => $db_usuario]);
                    fecharConexaoLoginSQLSRVRedirect($conexao, 'home.php');
                } else {
                    $erro_login = "Usuário ou senha incorretos.";
                    $logger->log('AUTH_FAILURE', $erro_login, ['usuario_digitado' => $usuario_digitado, 'motivo' => 'Senha não confere (SQLSRV)']);
                }
            } else { // Nenhum usuário encontrado
                $erro_login = "Usuário ou senha incorretos, ou usuário inativo.";
                $logger->log('AUTH_FAILURE', $erro_login, ['usuario_digitado' => $usuario_digitado, 'motivo' => 'Usuário não encontrado ou inativo (SQLSRV)']);
            }
        } else {
            $errors = sqlsrv_errors();
            $error_message_sqlsrv = "";
            if ($errors) { foreach($errors as $error) { $error_message_sqlsrv .= $error['message']." "; } }
            $erro_login = "Erro no sistema ao processar o login. Tente novamente.";
            $logger->log('ERROR', 'Falha ao executar query de login (SQLSRV).', ['sqlsrv_errors' => $error_message_sqlsrv]);
        }
    }

    if (!empty($erro_login)) {
        fecharConexaoLoginSQLSRVRedirect($conexao, 'index.html?erro=' . urlencode($erro_login));
    }

} else {
    fecharConexaoLoginSQLSRVRedirect($conexao, 'index.html');
}
