<?php
// carregar_feriados.php (Adaptado para SQL Server onde relevante)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // Mantido caso LogHelper use BD SQLSRV
require_once __DIR__ . '/LogHelper.php'; // Assegure que LogHelper.php está adaptado para SQLSRV
require_once __DIR__ . '/GoogleCalendarHelper.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); // $conexao é um recurso SQLSRV
$gcalHelper = new GoogleCalendarHelper($logger);

header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    if (isset($conexao) && $conexao) { sqlsrv_close($conexao); } // Fechar conexão SQLSRV
    exit;
}

$ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: (int)date('Y');
$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('m');

$calendarId = 'pt-br.brazilian#holiday@group.v.calendar.google.com';

try {
    $timeMin = new DateTimeImmutable("{$ano}-{$mes}-01T00:00:00", new DateTimeZone('America/Sao_Paulo'));
    $timeMax = $timeMin->modify('last day of this month')->setTime(23, 59, 59);
} catch (Exception $e) {
    $logger->log('ERROR', 'Data inválida fornecida para carregar feriados.', ['ano' => $ano, 'mes' => $mes, 'error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'message' => 'Data fornecida inválida.']);
    if (isset($conexao) && $conexao) { sqlsrv_close($conexao); } // Fechar conexão SQLSRV
    exit;
}

$params = [
    'orderBy' => 'startTime',
    'singleEvents' => true,
    'timeMin' => $timeMin->format(DateTimeInterface::RFC3339),
    'timeMax' => $timeMax->format(DateTimeInterface::RFC3339)
];

$eventos = $gcalHelper->listEventsFromCalendar($calendarId, $params);

if ($eventos === null) {
    echo json_encode(['success' => false, 'message' => 'Não foi possível buscar os feriados do Google Calendar. Verifique as configurações da API Key.']);
    if (isset($conexao) && $conexao) { sqlsrv_close($conexao); } // Fechar conexão SQLSRV
    exit;
}

$feriadosFormatados = [];
if (!empty($eventos)) {
    foreach ($eventos as $evento) {
        $dataFeriado = '';
        if (!empty($evento->start->date)) {
            try {
                $dataFeriado = (new DateTime($evento->start->date))->format('d/m/Y');
            } catch (Exception $e) {
                $logger->log('WARNING', 'Data de feriado inválida (all-day).', ['event_start_date' => $evento->start->date, 'error' => $e->getMessage()]);
                $dataFeriado = 'Data inválida';
            }
        } elseif (!empty($evento->start->dateTime)) {
             try {
                $dataFeriado = (new DateTime($evento->start->dateTime))->format('d/m/Y');
            } catch (Exception $e) {
                $logger->log('WARNING', 'Data de feriado inválida (specific time).', ['event_start_dateTime' => $evento->start->dateTime, 'error' => $e->getMessage()]);
                $dataFeriado = 'Data inválida';
            }
        }

        $feriadosFormatados[] = [
            'data' => $dataFeriado,
            'observacao' => $evento->getSummary()
        ];
    }
}

echo json_encode(['success' => true, 'feriados' => $feriadosFormatados]);

if (isset($conexao) && $conexao) {
    sqlsrv_close($conexao); // Fechar conexão SQLSRV
}
