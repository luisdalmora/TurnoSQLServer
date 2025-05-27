<?php
// api/gerar_relatorio_turnos.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php'; 
require_once __DIR__ . '/../lib/LogHelper.php'; 
require_once __DIR__ . '/api_helpers.php'; // Assumindo que seus helpers estão aqui
require_once __DIR__ . '/../vendor/fpdf/fpdf.php'; // Exemplo para FPDF


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); 
// Não definir Content-Type aqui ainda, pois pode ser CSV ou PDF ou JSON

$csrfTokenSessionKey = 'csrf_token_reports';
$novoCsrfTokenParaCliente = null; // Será preenchido por handleGetBase
$userId = $_SESSION['usuario_id'] ?? null;

// Para GET, apenas verificamos login e preparamos CSRF
handleGetBase($csrfTokenSessionKey, $novoCsrfTokenParaCliente, $conexao);

// Validação do CSRF token recebido via GET
if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION[$csrfTokenSessionKey], $_GET['csrf_token'])) {
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em gerar_relatorio_turnos (GET).', ['user_id' => $userId]);
    // Para requisições de exportação, é melhor falhar se o token não bater.
    if (isset($_GET['export'])) {
        http_response_code(403);
        echo "Erro de segurança ao tentar exportar.";
        if(isset($conexao)) sqlsrv_close($conexao);
        exit;
    }
    // Se não for exportação, apenas loga e continua, enviando o novo token
}
// Para exportações, o token CSRF é importante para evitar que um link malicioso seja usado para gerar relatórios
// A cada exportação, um novo token não é necessariamente enviado de volta se for um download direto.
// O token na sessão é o que importa para a próxima *interação do formulário* na página.

$export_type = $_GET['export'] ?? null; // csv ou pdf

$data_inicio_str = $_GET['data_inicio'] ?? null;
$data_fim_str = $_GET['data_fim'] ?? null;
$colaborador_filtro = $_GET['colaborador'] ?? '';

if (empty($data_inicio_str) || empty($data_fim_str)) {
    if ($export_type) {
        header("HTTP/1.1 400 Bad Request");
        echo "Datas de início e fim são obrigatórias para exportação.";
        if(isset($conexao)) sqlsrv_close($conexao);
        exit;
    }
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Datas de início e fim são obrigatórias.', 'csrf_token' => $novoCsrfTokenParaCliente]);
}

try {
    $data_inicio_obj = new DateTime($data_inicio_str);
    $data_fim_obj = new DateTime($data_fim_str);
    if ($data_inicio_obj > $data_fim_obj) {
        if ($export_type) { /* ... erro 400 ... */ exit;}
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Data de início não pode ser posterior à data de fim.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
} catch (Exception $e) {
    if ($export_type) { /* ... erro 400 ... */ exit;}
    $logger->log('WARNING', 'Formato de data inválido para relatório.', ['get_data' => $_GET, 'user_id' => $userId, 'error' => $e->getMessage()]);
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Formato de data inválido (esperado liturgiayyyy-MM-DD).', 'csrf_token' => $novoCsrfTokenParaCliente]);
}

$sql = "SELECT 
            t.data, 
            FORMAT(t.data, 'dd/MM/yyyy', 'pt-BR') AS data_formatada_relatorio, -- Renomeado para evitar conflito
            t.colaborador, 
            t.hora_inicio,
            t.hora_fim,
            FORMAT(CAST(t.hora_inicio AS TIME), 'HH:mm', 'pt-BR') AS hora_inicio_formatada_relatorio, -- Renomeado
            FORMAT(CAST(t.hora_fim AS TIME), 'HH:mm', 'pt-BR') AS hora_fim_formatada_relatorio -- Renomeado
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

$stmt = sqlsrv_query($conexao, $sql, $params_query_values);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    $logger->log('ERROR', 'Falha ao executar query para gerar relatório (SQLSRV).', ['sqlsrv_errors' => $errors, 'user_id' => $userId]);
    if ($export_type) { /* ... erro 500 ... */ exit;}
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro interno ao executar consulta.', 'csrf_token' => $novoCsrfTokenParaCliente]);
}

$turnos_db = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $turnos_db[] = $row;
}
sqlsrv_free_stmt($stmt);

$turnos_processados_para_json = [];
$turnos_para_export = []; // Para CSV/PDF
$total_geral_horas_decimal = 0;

foreach ($turnos_db as $turno_db_row) {
    $duracao_decimal = 0;
    $duracao_formatada_str = "00h00min";

    if (!empty($turno_db_row['data']) && !empty($turno_db_row['hora_inicio']) && !empty($turno_db_row['hora_fim'])) {
        try {
            $data_original_turno_str = ($turno_db_row['data'] instanceof DateTimeInterface) ? $turno_db_row['data']->format('Y-m-d') : $turno_db_row['data'];
            $hora_inicio_obj = $turno_db_row['hora_inicio']; 
            $hora_fim_obj = $turno_db_row['hora_fim'];       

            if ($hora_inicio_obj instanceof DateTimeInterface && $hora_fim_obj instanceof DateTimeInterface) {
                $inicio_completo_str = $data_original_turno_str . ' ' . $hora_inicio_obj->format('H:i:s');
                $fim_completo_str = $data_original_turno_str . ' ' . $hora_fim_obj->format('H:i:s');
                $inicio = new DateTime($inicio_completo_str);
                $fim = new DateTime($fim_completo_str);
                if ($fim <= $inicio) { $fim->add(new DateInterval('P1D')); }
                $intervalo = $inicio->diff($fim);
                $duracao_em_minutos = ($intervalo->days * 24 * 60) + ($intervalo->h * 60) + $intervalo->i;
                $duracao_decimal = $duracao_em_minutos / 60.0;
                $total_geral_horas_decimal += $duracao_decimal;
                $total_horas_no_intervalo = ($intervalo->days * 24) + $intervalo->h;
                $duracao_formatada_str = sprintf('%02dh%02dmin', $total_horas_no_intervalo, $intervalo->i);
            }
        } catch (Exception $e) { /* ... log ... */ }
    }

    $item_para_json = [
        'data_formatada'        => $turno_db_row['data_formatada_relatorio'],
        'colaborador'           => $turno_db_row['colaborador'],
        'hora_inicio_formatada' => $turno_db_row['hora_inicio_formatada_relatorio'],
        'hora_fim_formatada'    => $turno_db_row['hora_fim_formatada_relatorio'],
        'duracao_formatada'     => $duracao_formatada_str
    ];
    $turnos_processados_para_json[] = $item_para_json;

    if ($export_type) {
        $turnos_para_export[] = [
            'Data' => $turno_db_row['data_formatada_relatorio'],
            'Colaborador' => $turno_db_row['colaborador'],
            'Hora Início' => $turno_db_row['hora_inicio_formatada_relatorio'],
            'Hora Fim' => $turno_db_row['hora_fim_formatada_relatorio'],
            'Duração' => $duracao_formatada_str,
            'Duração Decimal' => number_format($duracao_decimal, 2, ',', '.') // Para CSV/PDF pode ser útil
        ];
    }
}

if ($export_type === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_turnos_'.date('Ymd_His').'.csv"');
    $output = fopen('php://output', 'w');
    
    // Cabeçalho do CSV (traduzido)
    fputcsv($output, array_keys($turnos_para_export[0] ?? [])); 
    
    foreach ($turnos_para_export as $row_export) {
        fputcsv($output, $row_export);
    }
    fclose($output);
    if(isset($conexao)) sqlsrv_close($conexao);
    exit;

} elseif ($export_type === 'pdf') {
    // Geração de PDF (requer biblioteca como FPDF ou TCPDF)
    // Exemplo conceitual com FPDF (você precisará instalar e configurar FPDF)
    // if (!class_exists('FPDF')) {
    //     $logger->log('ERROR', 'Biblioteca FPDF não encontrada para gerar PDF de relatório.', ['user_id' => $userId]);
    //     header("HTTP/1.1 500 Internal Server Error");
    //     echo "Erro: Biblioteca de geração de PDF não está configurada no servidor.";
    //      if(isset($conexao)) sqlsrv_close($conexao);
    //     exit;
    // }

    // $pdf = new FPDF();
    // $pdf->AddPage();
    // $pdf->SetFont('Arial','B',10);

    // $pdf->Cell(30,10,utf8_decode('Data'),1);
    // $pdf->Cell(50,10,utf8_decode('Colaborador'),1);
    // $pdf->Cell(25,10,utf8_decode('Início'),1);
    // $pdf->Cell(25,10,utf8_decode('Fim'),1);
    // $pdf->Cell(30,10,utf8_decode('Duração'),1);
    // $pdf->Ln();

    // $pdf->SetFont('Arial','',9);
    // foreach ($turnos_para_export as $row_export) {
    //     $pdf->Cell(30,7,utf8_decode($row_export['Data']),1);
    //     $pdf->Cell(50,7,utf8_decode($row_export['Colaborador']),1);
    //     $pdf->Cell(25,7,utf8_decode($row_export['Hora Início']),1);
    //     $pdf->Cell(25,7,utf8_decode($row_export['Hora Fim']),1);
    //     $pdf->Cell(30,7,utf8_decode($row_export['Duração']),1);
    //     $pdf->Ln();
    // }
    // $pdf->Output('D', 'relatorio_turnos_'.date('Ymd_His').'.pdf'); // D: Força download
    // if(isset($conexao)) sqlsrv_close($conexao);
    // exit;

    // Se não tiver FPDF (ou outra lib) instalada, retorne uma mensagem.
    header("HTTP/1.1 501 Not Implemented");
    echo "Funcionalidade de exportar para PDF ainda não implementada no servidor.";
    $logger->log('WARNING', 'Tentativa de exportar PDF sem biblioteca configurada.', ['user_id' => $userId]);
     if(isset($conexao)) sqlsrv_close($conexao);
    exit;

} else { // Resposta JSON padrão
    header('Content-Type: application/json'); // Garantir que é JSON para a resposta padrão
    fecharConexaoApiESair($conexao, [
        'success'             => true,
        'turnos'              => $turnos_processados_para_json,
        'total_geral_horas'   => round($total_geral_horas_decimal, 2),
        'total_turnos'        => count($turnos_processados_para_json),
        'csrf_token'          => $novoCsrfTokenParaCliente
    ]);
}
