<?php
// obter_colaboradores.php (Adaptado para SQL Server)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Agora $conexao é um recurso SQLSRV
// require_once __DIR__ . '/LogHelper.php'; // Descomente se for usar logs, e adapte LogHelper

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE && (false)) { 
    session_start();
}

// $logger = new LogHelper($conexao); // Descomente se for usar logs. $conexao já é SQLSRV.

function fecharConexaoObterColabESair($conexaoSqlsrv, $jsonData) {
    if (isset($conexaoSqlsrv) && is_resource($conexaoSqlsrv)) {
        sqlsrv_close($conexaoSqlsrv);
    }
    echo json_encode($jsonData);
    exit;
}

// Descomente a verificação de sessão se esta informação for sensível
// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
//     fecharConexaoObterColabESair($conexao, ['success' => false, 'message' => 'Acesso não autorizado.']);
// }
// $userId = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : null;

$colaboradores = [];
// SQL Server não usa backticks.
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

if (isset($conexao) && is_resource($conexao)) { // Fallback
    sqlsrv_close($conexao);
}
