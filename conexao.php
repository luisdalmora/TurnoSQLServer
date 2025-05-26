<?php
// conexao.php (Adaptado para SQL Server)

// Definição das variáveis de conexão para SQL Server
// Substitua pelos seus dados de conexão SQL Server
$db_servername_sqlsrv = "SimPosto-Luis\sqlexpress"; // Ex: "localhost", "SERVIDOR\INSTANCIA", "SERVIDOR,PORTA"
$db_username_sqlsrv   = "sa";       // Nome de usuário do SQL Server (pode ser omitido para Autenticação do Windows)
$db_password_sqlsrv   = "SA_0bjetiva";         // Senha do SQL Server (pode ser omitido para Autenticação do Windows)
$db_database_sqlsrv   = "simposto";      // Nome do banco de dados SQL Server

// Informações de conexão para SQLSRV
$connectionInfo = array(
    "Database" => $db_database_sqlsrv,
    "CharacterSet" => "UTF-8" // Recomendado para consistência
);

// Adicionar UID e PWD se não for usar Autenticação do Windows
if (!empty($db_username_sqlsrv)) {
    $connectionInfo["UID"] = $db_username_sqlsrv;
    $connectionInfo["PWD"] = $db_password_sqlsrv;
}
// Se estiver usando Autenticação do Windows, $db_username_sqlsrv e $db_password_sqlsrv podem ser vazios
// e o PHP precisa rodar sob uma conta de usuário com acesso ao SQL Server.

// Tentativa de estabelecer a conexão com o banco de dados SQL Server
$conexao = sqlsrv_connect($db_servername_sqlsrv, $connectionInfo);

// Verificação da conexão
if ($conexao === false) {
    // Se a conexão falhar, registra o erro e encerra o script.
    // Em um ambiente de produção, evite exibir sqlsrv_errors() diretamente ao usuário.
    $error_msg_sqlsrv = "Erro de conexão com o banco de dados SQL Server em conexao.php: ";
    if (($errors = sqlsrv_errors()) != null) {
        foreach ($errors as $error) {
            $error_msg_sqlsrv .= "SQLSTATE: " . $error['SQLSTATE'] . "; code: " . $error['code'] . "; message: " . $error['message'] . " | ";
        }
    } else {
        $error_msg_sqlsrv .= "Erro desconhecido ao tentar conectar.";
    }
    error_log($error_msg_sqlsrv); // Loga o erro no log do servidor

    // Resposta genérica para o usuário ou tratamento específico da API
    // header('Content-Type: application/json');
    // http_response_code(503); // Service Unavailable
    // echo json_encode(['success' => false, 'message' => 'Erro crítico: não foi possível conectar ao banco de dados.']);
    // exit;

    die($error_msg_sqlsrv); // Para desenvolvimento, pode ser útil. Em produção, remova ou substitua.
}

// A variável $conexao (recurso sqlsrv) permanece disponível se este script for incluído por outro.
// Não há um equivalente direto de mysqli_set_charset para sqlsrv após a conexão,
// o charset é definido em $connectionInfo.
