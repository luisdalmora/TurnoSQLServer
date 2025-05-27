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
    if (isset($conexao) && $conexao) { sqlsrv_close($conexao); }
    exit;
}

$ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: (int)date('Y');
$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('m');
$userId = $_SESSION['usuario_id'] ?? null;

$escala_sabados_db = [];
$debug_info = ['query_executada' => '', 'params' => [], 'datefirst' => null, 'error' => null];

if ($conexao) {
    // Descobrir qual é o primeiro dia da semana configurado no SQL Server
    $stmtDateFirst = sqlsrv_query($conexao, "SELECT @@DATEFIRST AS DateFirst");
    if ($stmtDateFirst && ($rowDateFirst = sqlsrv_fetch_array($stmtDateFirst, SQLSRV_FETCH_ASSOC))) {
        $debug_info['datefirst'] = $rowDateFirst['DateFirst'];
    }
    if($stmtDateFirst) sqlsrv_free_stmt($stmtDateFirst);

    // Ajustar o DATEPART(dw, data) com base no @@DATEFIRST
    // @@DATEFIRST:
    // 7 (Domingo) -> Sábado é 7
    // 1 (Segunda) -> Sábado é 6
    // ... e assim por diante.
    // Para uma abordagem mais robusta e independente do idioma/configuração:
    // Usar DATENAME(weekday, data) = 'Saturday' (considerando o idioma do SQL Server)
    // Ou, se você sempre quer Sábado, pode ser mais seguro usar uma condição que não dependa de @@DATEFIRST se possível,
    // ou garanta que @@DATEFIRST seja consistente no seu ambiente.

    // Por simplicidade, vamos continuar com DATEPART, mas adicionaremos DATENAME para depuração.
    // Se DATENAME for mais confiável, substitua a condição DATEPART.

    // Query para depuração:
    // $sql_debug_sabados = "SELECT data, DATEPART(dw, data) as DiaSemanaNumero, DATENAME(weekday, data) as DiaSemanaNome, colaborador, hora_inicio FROM turnos WHERE YEAR(data) = ? AND MONTH(data) = ? AND criado_por_usuario_id = ? ORDER BY data, hora_inicio";
    // $params_debug = [$ano, $mes, $userId];
    // $stmt_debug_run = sqlsrv_query($conexao, $sql_debug_sabados, $params_debug);
    // $dias_semana_no_mes = [];
    // if($stmt_debug_run) { while($r = sqlsrv_fetch_array($stmt_debug_run, SQLSRV_FETCH_ASSOC)) { $dias_semana_no_mes[] = $r; } sqlsrv_free_stmt($stmt_debug_run); }
    // $logger->log('DEBUG', 'Verificação de dias da semana para Escala Sábados', ['user_id' => $userId, 'ano' => $ano, 'mes' => $mes, 'datefirst' => $debug_info['datefirst'], 'turnos_no_mes_com_dia_semana' => $dias_semana_no_mes]);


    // SQL Server: FORMAT(data, 'dd/MM', 'pt-BR') para data (SQL Server 2012+)
    // A condição para Sábado (DATEPART(dw, data) = 7) assume que @@DATEFIRST é 7 (Domingo é o 1º dia).
    // Se for diferente, o número para Sábado mudará.
    // Uma alternativa mais robusta pode ser: DATENAME(weekday, data) = 'Saturday' (mas depende do idioma das datas no servidor)
    // Ou, se o seu DATEFIRST é 1 (Segunda), Sábado seria 6.
    
    $diaDaSemanaParaSabado = 7; // Assumindo @@DATEFIRST = 7 (Domingo é o primeiro dia)
    if ($debug_info['datefirst'] == 1) { // Se Segunda é o primeiro dia da semana
        $diaDaSemanaParaSabado = 6;
    }
    // Adicione outras lógicas para @@DATEFIRST se necessário.

    $sql = "SELECT FORMAT(data, 'dd/MM', 'pt-BR') as data, colaborador
            FROM turnos
            WHERE YEAR(data) = ? 
              AND MONTH(data) = ? 
              AND DATEPART(dw, data) = ? -- Usando a variável ajustada
              AND criado_por_usuario_id = ? 
            ORDER BY data ASC, hora_inicio ASC";

    $params = array($ano, $mes, $diaDaSemanaParaSabado, $userId);
    $debug_info['query_executada'] = $sql;
    $debug_info['params'] = $params;

    $stmt = sqlsrv_query($conexao, $sql, $params); 

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $escala_sabados_db[] = $row;
        }
        sqlsrv_free_stmt($stmt);
        $logger->log('INFO', 'Busca de escala sábados (SQL Server) realizada.', ['user_id' => $userId, 'ano' => $ano, 'mes' => $mes, 'count' => count($escala_sabados_db), 'debug_info' => $debug_info]);
    } else {
        $errors = sqlsrv_errors();
        $debug_info['error'] = $errors;
        $logger->log('ERROR', 'Erro ao executar busca escala_sabados (SQL Server): ' . print_r($errors, true), ['user_id' => $userId, 'debug_info' => $debug_info]);
    }
} else {
    $debug_info['error'] = 'Sem conexão com o banco de dados.';
    $logger->log('ERROR', 'Sem conexão com o banco de dados em carregar_escala_sabados.php', ['user_id' => $userId, 'debug_info' => $debug_info]);
}

// Adiciona informações de depuração ao JSON de resposta SE EM MODO DE DESENVOLVIMENTO
// Em produção, você removeria a chave 'debug_info'.
$responseData = ['success' => true, 'escala' => $escala_sabados_db];
// if (MODO_DESENVOLVIMENTO) { // Supondo que você tenha uma constante MODO_DESENVOLVIMENTO em config.php
//    $responseData['debug_info_escala_sabados'] = $debug_info;
// }

echo json_encode($responseData);

if (isset($conexao) && $conexao) {
    sqlsrv_close($conexao);
}
