<?php
// api/obter_colaboradores.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php'; 
// require_once __DIR__ . '/../lib/LogHelper.php'; // Descomente se for usar logs aqui

header('Content-Type: application/json');

// Se este endpoint não precisa de autenticação para ser usado pelo JS em qualquer página,
// a verificação de sessão pode ser omitida. Se precisar, adicione-a.
// if (session_status() == PHP_SESSION_NONE) { 
//     session_start();
// }
// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) { /* ... tratar ... */ }


// $logger = new LogHelper($conexao); // Descomente se for usar logs.

function fecharConexaoObterColabESair($conexaoSqlsrv, $jsonData) {
    if (isset($conexaoSqlsrv) && $conexaoSqlsrv) {
        sqlsrv_close($conexaoSqlsrv);
    }
    echo json_encode($jsonData);
    exit;
}

$colaboradores = [];
$sql = "SELECT id, nome_completo FROM colaboradores WHERE ativo = 1 ORDER BY nome_completo ASC";

$stmt = sqlsrv_query($conexao, $sql);

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $colaboradores[] = $row;
    }
    sqlsrv_free_stmt($stmt); 
    fecharConexaoObterColabESair($conexao, ['success' => true, 'colaboradores' => $colaboradores]);
} else {
    $errors = sqlsrv_errors();
    $error_message_sqlsrv = "";
    if ($errors) { foreach($errors as $error) { $error_message_sqlsrv .= $error['message']." "; } }
    // if (isset($logger)) $logger->log('ERROR', 'Falha ao buscar colaboradores ativos (SQLSRV).', ['sqlsrv_errors' => $error_message_sqlsrv, 'user_id' => $userId ?? null]);
    error_log("Erro ao buscar colaboradores em obter_colaboradores.php (SQLSRV): " . $error_message_sqlsrv); 
    fecharConexaoObterColabESair($conexao, ['success' => false, 'message' => 'Erro ao buscar lista de colaboradores.']);
}

if (isset($conexao) && $conexao) { // Fallback
    sqlsrv_close($conexao);
}
