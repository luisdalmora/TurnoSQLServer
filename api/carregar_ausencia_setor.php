<?php
// api/carregar_ausencia_setor.php
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

$ausencias_setor_db = [];

if ($conexao) {
    $primeiroDiaMesFiltro = sprintf('%04d-%02d-01', $ano, $mes);
    $ultimoDiaMesFiltro = date('Y-m-t', strtotime($primeiroDiaMesFiltro));

    $sql = "SELECT FORMAT(a.data_inicio, 'dd/MM', 'pt-BR') as data, 
                   a.colaborador_nome as colaborador 
            FROM ausencias a
            WHERE a.criado_por_usuario_id = ?
              AND ( 
                 (a.data_inicio >= ? AND a.data_inicio <= ?) OR
                 (a.data_fim >= ? AND a.data_fim <= ?) OR
                 (a.data_inicio < ? AND a.data_fim > ?)
              )
            ORDER BY a.data_inicio ASC";
    
    $params = array(
        $userId,
        $primeiroDiaMesFiltro, $ultimoDiaMesFiltro,
        $primeiroDiaMesFiltro, $ultimoDiaMesFiltro,
        $primeiroDiaMesFiltro, $ultimoDiaMesFiltro
    );
    
    $stmt = sqlsrv_query($conexao, $sql, $params);

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $ausencias_setor_db[] = [
                'data' => $row['data'],
                'colaborador' => $row['colaborador'] ?: 'N/A' 
            ];
        }
        sqlsrv_free_stmt($stmt);
        $logger->log('INFO', 'Busca de ausência setor (SQL Server) realizada.', ['user_id' => $userId, 'ano' => $ano, 'mes' => $mes, 'count' => count($ausencias_setor_db)]);
    } else {
        $errors = sqlsrv_errors();
        $logger->log('ERROR', 'Erro ao executar busca ausencias_setor (SQL Server): ' . print_r($errors, true), ['user_id' => $userId]);
    }
} else {
     $logger->log('ERROR', 'Sem conexão com o banco de dados em carregar_ausencia_setor.php', ['user_id' => $userId]);
}

echo json_encode(['success' => true, 'ausencias' => $ausencias_setor_db]);

if (isset($conexao)) {
    sqlsrv_close($conexao);
}
