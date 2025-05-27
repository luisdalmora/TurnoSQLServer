<?php
// api/listar_colaboradores.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php'; 
require_once __DIR__ . '/../lib/LogHelper.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

function fecharConexaoListarColabESair($conexaoSqlsrv, $jsonData) {
    if (isset($conexaoSqlsrv) && $conexaoSqlsrv) {
        sqlsrv_close($conexaoSqlsrv);
    }
    echo json_encode($jsonData);
    exit;
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    fecharConexaoListarColabESair($conexao, ['success' => false, 'message' => 'Acesso não autorizado. Faça login.']);
}
$userId = $_SESSION['usuario_id'];

$colaboradores = [];
$sql = "SELECT id, nome_completo, email, cargo, ativo FROM colaboradores ORDER BY nome_completo ASC";

$stmt = sqlsrv_query($conexao, $sql);

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row['ativo'] = (bool)$row['ativo'];
        $colaboradores[] = $row;
    }
    sqlsrv_free_stmt($stmt); 
    fecharConexaoListarColabESair($conexao, ['success' => true, 'colaboradores' => $colaboradores]);
} else {
    $errors = sqlsrv_errors();
    $logger->log('ERROR', 'Falha ao executar query para listar colaboradores (SQLSRV).', ['sqlsrv_errors' => $errors, 'user_id' => $userId]);
    fecharConexaoListarColabESair($conexao, ['success' => false, 'message' => 'Erro ao buscar lista de colaboradores.']);
}

if (isset($conexao) && $conexao) { 
    sqlsrv_close($conexao);
}
