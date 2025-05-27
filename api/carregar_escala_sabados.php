<?php
// api/carregar_escala_sabados.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php'; 
require_once __DIR__ . '/../lib/LogHelper.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$logger = new LogHelper($conexao);
header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    if (isset($conexao)) { sqlsrv_close($conexao); }
    exit;
}

$ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: (int)date('Y');
$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('m');
$userId = $_SESSION['usuario_id'] ?? null;

$escala_sabados_db = [];

if ($conexao) {
    $sql = "SELECT FORMAT(data, 'dd/MM', 'pt-BR') as data, colaborador
            FROM turnos
            WHERE YEAR(data) = ? 
              AND MONTH(data) = ? 
              AND DATEPART(dw, data) = 7 
              AND criado_por_usuario_id = ? 
            ORDER BY data ASC, hora_inicio ASC";

    $params = array($ano, $mes, $userId);
    $stmt = sqlsrv_query($conexao, $sql, $params); 

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $escala_sabados_db[] = $row;
        }
        sqlsrv_free_stmt($stmt);
        $logger->log('INFO', 'Busca de escala sábados (SQL Server) realizada.', ['user_id' => $userId, 'ano' => $ano, 'mes' => $mes, 'count' => count($escala_sabados_db)]);
    } else {
        $errors = sqlsrv_errors();
        $logger->log('ERROR', 'Erro ao executar busca escala_sabados (SQL Server): ' . print_r($errors, true), ['user_id' => $userId]);
    }
} else {
    $logger->log('ERROR', 'Sem conexão com o banco de dados em carregar_escala_sabados.php', ['user_id' => $userId]);
}

echo json_encode(['success' => true, 'escala' => $escala_sabados_db]);

if (isset($conexao)) {
    sqlsrv_close($conexao);
}
