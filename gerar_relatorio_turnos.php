<?php
// gerar_relatorio_turnos.php (Adaptado para SQL Server)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Agora $conexao é um recurso SQLSRV
require_once __DIR__ . '/LogHelper.php'; // Assegure que LogHelper.php está adaptado para SQLSRV

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); // $conexao é SQLSRV
header('Content-Type: application/json');

// Função auxiliar para fechar conexão e sair
function fecharConexaoRelatorioESair($conexaoSqlsrv, $jsonData) {
    if (isset($conexaoSqlsrv)) {
        sqlsrv_close($conexaoSqlsrv);
    }
    echo json_encode($jsonData);
    exit;
}

// --- Verificação de Sessão e CSRF Token ---
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    fecharConexaoRelatorioESair($conexao, ['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
}
$userId = $_SESSION['usuario_id'];

if (!isset($_GET['csrf_token']) || !isset($_SESSION['csrf_token_reports']) || !hash_equals($_SESSION['csrf_token_reports'], $_GET['csrf_token'])) {
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em gerar_relatorio_turnos (GET).', ['user_id' => $userId]);
    // Mantendo comportamento original de não sair, apenas logar.
}
$_SESSION['csrf_token_reports'] = bin2hex(random_bytes(32));
$novoCsrfTokenParaCliente = $_SESSION['csrf_token_reports'];

$data_inicio_str = $_GET['data_inicio'] ?? null;
$data_fim_str = $_GET['data_fim'] ?? null;
$colaborador_filtro = $_GET['colaborador'] ?? '';

if (empty($data_inicio_str) || empty($data_fim_str)) {
    fecharConexaoRelatorioESair($conexao, ['success' => false, 'message' => 'Datas de início e fim são obrigatórias.', 'csrf_token' => $novoCsrfTokenParaCliente]);
}

try {
    // Para SQL Server, o formato YYYY-MM-DD é seguro.
    $data_inicio_obj = new DateTime($data_inicio_str);
    $data_fim_obj = new DateTime($data_fim_str);
    if ($data_inicio_obj > $data_fim_obj) {
        fecharConexaoRelatorioESair($conexao, ['success' => false, 'message' => 'Data de início não pode ser posterior à data de fim.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
} catch (Exception $e) {
    $logger->log('WARNING', 'Formato de data inválido para relatório.', ['get_data' => $_GET, 'user_id' => $userId, 'error' => $e->getMessage()]);
    fecharConexaoRelatorioESair($conexao, ['success' => false, 'message' => 'Formato de data inválido (esperado YYYY-MM-DD).', 'csrf_token' => $novoCsrfTokenParaCliente]);
}

// SQL Adaptado para SQL Server
// Usando FORMAT (SQL Server 2012+)
$sql = "SELECT 
            t.data, 
            FORMAT(t.data, 'dd/MM/yyyy', 'pt-BR') AS data_formatada, 
            t.colaborador, 
            t.hora_inicio,
            t.hora_fim,
            FORMAT(CAST(t.hora_inicio AS TIME), 'HH:mm', 'pt-BR') AS hora_inicio_formatada,
            FORMAT(CAST(t.hora_fim AS TIME), 'HH:mm', 'pt-BR') AS hora_fim_formatada
        FROM 
            turnos t
        WHERE 
            t.data BETWEEN ? AND ? 
            AND t.criado_por_usuario_id = ? ";

$params_query_values = [$data_inicio_obj->format('Y-m-d'), $data_fim_obj->format('Y-m-d'), $userId];

if (!empty($colaborador_filtro)) {
    $sql .= " AND t.colaborador = ? ";
    $params_query_values[] = $colaborador_filtro;
}
$sql .= " ORDER BY t.data ASC, t.colaborador ASC, t.hora_inicio ASC";

// Em SQLSRV, os parâmetros são passados diretamente para sqlsrv_query ou sqlsrv_execute.
$stmt = sqlsrv_query($conexao, $sql, $params_query_values);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    $logger->log('ERROR', 'Falha ao executar query para gerar relatório (SQLSRV).', ['sqlsrv_errors' => $errors, 'user_id' => $userId]);
    fecharConexaoRelatorioESair($conexao, ['success' => false, 'message' => 'Erro interno ao executar consulta.', 'csrf_token' => $novoCsrfTokenParaCliente]);
}

$turnos_db = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $turnos_db[] = $row;
}
sqlsrv_free_stmt($stmt);

$turnos_processados = [];
$total_geral_horas_decimal = 0;

foreach ($turnos_db as $turno_db_row) {
    $duracao_decimal = 0;
    $duracao_formatada_str = "00h00min";

    // $turno_db_row['data'] será um objeto DateTime do SQLSRV se o tipo da coluna for DATE/DATETIME
    // $turno_db_row['hora_inicio'], $turno_db_row['hora_fim'] serão objetos DateTime se o tipo for TIME/DATETIME
    if (!empty($turno_db_row['data']) && !empty($turno_db_row['hora_inicio']) && !empty($turno_db_row['hora_fim'])) {
        try {
            // SQLSRV pode retornar objetos DateTime. Se for string, DateTime construtor funciona.
            // Se data for DateTime object, formatamos para string para consistência
            $data_original_turno_str = ($turno_db_row['data'] instanceof DateTimeInterface) ? $turno_db_row['data']->format('Y-m-d') : $turno_db_row['data'];
            
            // hora_inicio e hora_fim podem ser DateTime objects se vierem de um tipo TIME do SQL Server
            $hora_inicio_str_for_calc = ($turno_db_row['hora_inicio'] instanceof DateTimeInterface) ? $turno_db_row['hora_inicio']->format('H:i:s') : $turno_db_row['hora_inicio'];
            $hora_fim_str_for_calc = ($turno_db_row['hora_fim'] instanceof DateTimeInterface) ? $turno_db_row['hora_fim']->format('H:i:s') : $turno_db_row['hora_fim'];

            $inicio = new DateTime($data_original_turno_str . ' ' . $hora_inicio_str_for_calc);
            $fim = new DateTime($data_original_turno_str . ' ' . $hora_fim_str_for_calc);

            if ($fim <= $inicio) { // Turno que passa da meia-noite
                $fim->add(new DateInterval('P1D'));
            }
            $intervalo = $inicio->diff($fim);
            
            $duracao_em_minutos = ($intervalo->days * 24 * 60) + ($intervalo->h * 60) + $intervalo->i;
            $duracao_decimal = $duracao_em_minutos / 60.0;
            
            $total_geral_horas_decimal += $duracao_decimal;
            
            $total_horas_no_intervalo = ($intervalo->days * 24) + $intervalo->h;
            $duracao_formatada_str = sprintf('%02dh%02dmin', $total_horas_no_intervalo, $intervalo->i);

        } catch (Exception $e) {
            $logger->log('WARNING', 'Erro ao calcular duração de turno para relatório.', ['turno_data' => $turno_db_row, 'error' => $e->getMessage(), 'user_id' => $userId]);
        }
    }

    $turnos_processados[] = [
        'data_formatada'        => $turno_db_row['data_formatada'],
        'colaborador'           => $turno_db_row['colaborador'],
        'hora_inicio_formatada' => $turno_db_row['hora_inicio_formatada'],
        'hora_fim_formatada'    => $turno_db_row['hora_fim_formatada'],
        'duracao_formatada'     => $duracao_formatada_str
    ];
}

fecharConexaoRelatorioESair($conexao, [
    'success'             => true,
    'turnos'              => $turnos_processados,
    'total_geral_horas'   => round($total_geral_horas_decimal, 2),
    'total_turnos'        => count($turnos_processados),
    'csrf_token'          => $novoCsrfTokenParaCliente
]);
