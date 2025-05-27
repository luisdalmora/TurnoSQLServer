<?php
// api/carregar_feriados.php
require_once __DIR__ . '/../config/config.php';
// A conexão com o BD SQLSRV pode ser opcional aqui se LogHelper não a usar para este script,
// ou se o logger for instanciado com null.
// Se LogHelper *sempre* precisar de conexão, descomente a linha abaixo:
// require_once __DIR__ . '/../config/conexao.php'; 
require_once __DIR__ . '/../lib/LogHelper.php'; 
require_once __DIR__ . '/../lib/GoogleCalendarHelper.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Se LogHelper precisar da conexão, passe $conexao. Caso contrário, null ou ajuste LogHelper.
// Para este exemplo, assumiremos que $conexao pode não ser necessário para este script específico
// se LogHelper puder lidar com $conexao sendo null (ex: logar em arquivo).
// Se $conexao for necessário: $logger = new LogHelper($conexao);
$logger = new LogHelper(isset($conexao) ? $conexao : null); 
$gcalHelper = new GoogleCalendarHelper($logger);

header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    if (isset($conexao) && $conexao) { sqlsrv_close($conexao); } 
    exit;
}

$ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: (int)date('Y');
$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('m');

$calendarId = 'pt-br.brazilian#holiday@group.v.calendar.google.com';

try {
    $timeMin = new DateTimeImmutable("{$ano}-{$mes}-01T00:00:00", new DateTimeZone('America/Sao_Paulo'));
    $timeMax = $timeMin->modify('last day of this month')->setTime(23, 59, 59);
} catch (Exception $e) {
    $logger->log('ERROR', 'Data inválida fornecida para carregar feriados.', ['ano' => $ano, 'mes' => $mes, 'error' => $e->getMessage(), 'user_id' => $_SESSION['usuario_id'] ?? 'N/A']);
    echo json_encode(['success' => false, 'message' => 'Data fornecida inválida.']);
    if (isset($conexao) && $conexao) { sqlsrv_close($conexao); } 
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
    echo json_encode(['success' => false, 'message' => 'Não foi possível buscar os feriados do Google Calendar. Verifique as configurações da API Key ou a conexão com a internet.']);
    if (isset($conexao) && $conexao) { sqlsrv_close($conexao); } 
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
                $logger->log('WARNING', 'Data de feriado inválida (all-day).', ['event_start_date' => $evento->start->date, 'error' => $e->getMessage(), 'user_id' => $_SESSION['usuario_id'] ?? 'N/A']);
                $dataFeriado = 'Data inválida';
            }
        } elseif (!empty($evento->start->dateTime)) {
             try {
                $dataFeriado = (new DateTime($evento->start->dateTime))->format('d/m/Y');
            } catch (Exception $e) {
                $logger->log('WARNING', 'Data de feriado inválida (specific time).', ['event_start_dateTime' => $evento->start->dateTime, 'error' => $e->getMessage(), 'user_id' => $_SESSION['usuario_id'] ?? 'N/A']);
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
    sqlsrv_close($conexao); 
}
