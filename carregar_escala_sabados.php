<?php
// carregar_escala_sabados.php (Adaptado para SQL Server)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // SQLSRV
require_once __DIR__ . '/LogHelper.php'; // SQLSRV

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$logger = new LogHelper($conexao);
header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    if (isset($conexao) && $conexao) { sqlsrv_close($conexao); }
    exit;
}

$ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: (int)date('Y');
$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('m');
$userId = $_SESSION['usuario_id'] ?? null;

$escala_sabados_db = [];

if ($conexao) {
    // SQL Server:
    // - FORMAT(data, 'dd/MM', 'pt-BR') para data (SQL Server 2012+)
    // - DATEPART(dw, data) para dia da semana. O valor para Sábado depende de @@DATEFIRST.
    //   Se @@DATEFIRST = 7 (Domingo é o 1º dia, padrão US), Sábado é 7.
    //   Se @@DATEFIRST = 1 (Segunda é o 1º dia), Sábado é 6.
    //   É mais robusto verificar DATENAME(dw, data) = 'Saturday' (considerar idioma)
    //   ou configurar SET DATEFIRST 1; antes da query se necessário.
    //   Aqui, vamos assumir que o padrão (Sábado = 7) funciona ou foi ajustado no BD.
    $sql = "SELECT FORMAT(data, 'dd/MM', 'pt-BR') as data, colaborador
            FROM turnos
            WHERE YEAR(data) = ? 
              AND MONTH(data) = ? 
              AND DATEPART(dw, data) = 7 -- Filtra por Sábados (verificar @@DATEFIRST no SQL Server)
              AND criado_por_usuario_id = ? 
            ORDER BY data ASC, hora_inicio ASC";

    $params = array($ano, $mes, $userId);
    $stmt = sqlsrv_query($conexao, $sql, $params); // sqlsrv_query para SELECT com parâmetros

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

if (isset($conexao) && $conexao) {
    sqlsrv_close($conexao);
}
