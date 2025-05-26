<?php
// cadastrar.php (Adaptado para SQL Server)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // $conexao agora é um recurso SQLSRV
require_once __DIR__ . '/LogHelper.php'; // Assegure que LogHelper.php está adaptado para SQLSRV
require_once __DIR__ . '/EmailHelper.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); // $conexao é SQLSRV

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_completo = isset($_POST['nome_completo']) ? trim($_POST['nome_completo']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $senha_digitada = isset($_POST['senha']) ? $_POST['senha'] : '';

    if (empty($nome_completo) || empty($email) || empty($usuario) || empty($senha_digitada)) {
        $logger->log('WARNING', 'Tentativa de cadastro com campos obrigatórios vazios.', ['post_data' => $_POST]);
        echo "Erro: Todos os campos são obrigatórios.";
        if ($conexao) sqlsrv_close($conexao);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $logger->log('WARNING', 'Tentativa de cadastro com e-mail inválido.', ['email' => $email]);
        echo "Erro: Formato de e-mail inválido.";
        if ($conexao) sqlsrv_close($conexao);
        exit;
    }

    $senha_hash = password_hash($senha_digitada, PASSWORD_DEFAULT);
    
    // Para SQL Server, pegamos o ID com SCOPE_IDENTITY() após a execução.
    // Removido backticks. `ativo` como 1 (booleano/inteiro).
    $sql = "INSERT INTO usuarios (nome_completo, email, usuario, senha, ativo) VALUES (?, ?, ?, ?, 1); SELECT SCOPE_IDENTITY() AS id;";
    $params = array($nome_completo, $email, $usuario, $senha_hash);

    // Para SQL Server, prepared statements são um pouco diferentes.
    // sqlsrv_query pode ser usado para executar múltiplas queries separadas por ; (como o SELECT SCOPE_IDENTITY())
    $stmt = sqlsrv_query($conexao, $sql, $params);

    if ($stmt) {
        // Avançar para o resultado do SELECT SCOPE_IDENTITY()
        sqlsrv_next_result($stmt); 
        sqlsrv_fetch($stmt); // Pega a primeira linha do resultado do SCOPE_IDENTITY()
        $novo_usuario_id = sqlsrv_get_field($stmt, 0); // Pega o valor da primeira coluna (o ID)

        if ($novo_usuario_id > 0) {
            $logger->log('INFO', 'Novo usuário cadastrado com sucesso.', ['usuario_id' => $novo_usuario_id, 'usuario' => $usuario, 'email' => $email]);

            if (EmailHelper::sendRegistrationConfirmationEmail($email, $nome_completo)) {
                $logger->log('INFO', 'E-mail de confirmação de cadastro enviado.', ['usuario_id' => $novo_usuario_id, 'email' => $email]);
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Cadastro realizado com sucesso! Um e-mail de confirmação foi enviado.'];
            } else {
                $logger->log('ERROR', 'Falha ao enviar e-mail de confirmação de cadastro.', ['usuario_id' => $novo_usuario_id, 'email' => $email]);
                $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'Cadastro realizado, mas falha ao enviar e-mail de confirmação.'];
            }

            sqlsrv_free_stmt($stmt);
            if ($conexao) sqlsrv_close($conexao);
            header("Location: index.html?status=cadastro_sucesso");
            exit;
        } else {
            $logger->log('ERROR', 'Cadastro parece ter ocorrido, mas ID do novo usuário não foi retornado ou foi zero.', ['usuario' => $usuario, 'email' => $email, 'scope_identity_result' => $novo_usuario_id]);
            echo "Erro ao finalizar o cadastro. ID do usuário não obtido.";
        }
    } else {
        // Erro na execução da query
        $errors = sqlsrv_errors();
        $logger->log('ERROR', 'Erro ao executar query de cadastro.', ['sqlsrv_errors' => $errors, 'usuario' => $usuario, 'email' => $email]);

        $user_display_error = "Erro ao cadastrar o usuário.";
        // Códigos de erro do SQL Server para violação de chave única (UNIQUE constraint) são 2627 ou 2601
        if ($errors && isset($errors[0]['code']) && ($errors[0]['code'] == 2627 || $errors[0]['code'] == 2601)) {
            $errorMessageText = strtolower($errors[0]['message']);
            if (strpos($errorMessageText, 'email') !== false) { // Verifique o nome da constraint ou coluna no erro
                $user_display_error = "Erro ao cadastrar: O e-mail informado já existe.";
            } elseif (strpos($errorMessageText, 'usuario') !== false) { // Supondo que 'usuario' também seja UNIQUE
                $user_display_error = "Erro ao cadastrar: O nome de usuário já existe.";
            } else {
                $user_display_error = "Erro ao cadastrar: O e-mail ou nome de usuário já existe.";
            }
        } else if ($errors) {
            $user_display_error .= " Detalhe técnico: " . htmlentities($errors[0]['message']);
        }
        echo $user_display_error;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

} else {
    $logger->log('WARNING', 'Acesso inválido (não POST) à página de cadastro.');
    echo "Acesso inválido à página de cadastro.";
}

if (isset($conexao) && $conexao) {
    sqlsrv_close($conexao);
}
