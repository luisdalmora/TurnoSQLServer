<?php
// listar_colaboradores.php (Adaptado para SQL Server)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Agora $conexao é um recurso SQLSRV
require_once __DIR__ . '/LogHelper.php'; // Assegure que LogHelper.php está adaptado para SQLSRV

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

function fecharConexaoListarColabESair($conexaoSqlsrv, $jsonData) {
    if (isset($conexaoSqlsrv)) {
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
// SQL Server não usa backticks. Use colchetes [] se os nomes tiverem espaços ou forem palavras reservadas.
$sql = "SELECT id, nome_completo, email, cargo, ativo FROM colaboradores ORDER BY nome_completo ASC";

// Para SELECTs simples sem parâmetros, sqlsrv_query() é direto.
$stmt = sqlsrv_query($conexao, $sql);

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // 'ativo' no SQL Server provavelmente é BIT (0 ou 1).
        // A conversão para booleano (true/false) é mantida.
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

if (isset($conexao)) { // Fallback
    sqlsrv_close($conexao);
}
