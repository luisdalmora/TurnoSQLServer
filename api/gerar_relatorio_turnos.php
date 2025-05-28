<?php
// api/gerar_relatorio_turnos.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php'; 
require_once __DIR__ . '/../lib/LogHelper.php'; 
require_once __DIR__ . '/api_helpers.php'; 
// Incluir o autoload do Composer para usar mPDF
require_once __DIR__ . '/../vendor/autoload.php';


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); 

$csrfTokenSessionKey = 'csrf_token_reports';
$novoCsrfTokenParaCliente = null; 
$userId = $_SESSION['usuario_id'] ?? null;


handleGetBase($csrfTokenSessionKey, $novoCsrfTokenParaCliente, $conexao); 

if (!isset($_GET['csrf_token']) || !isset($_SESSION[$csrfTokenSessionKey]) || !hash_equals($_SESSION[$csrfTokenSessionKey], $_GET['csrf_token'])) {
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em gerar_relatorio_turnos (GET).', ['user_id' => $userId, 'get_params' => $_GET]);
    http_response_code(403); 
    echo "Erro de segurança (token inválido). Por favor, tente gerar o relatório novamente a partir da página.";
    if(isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
    exit;
}

$export_type = $_GET['export'] ?? null; 

$data_inicio_str = $_GET['data_inicio'] ?? null;
$data_fim_str = $_GET['data_fim'] ?? null;
$colaborador_filtro = $_GET['colaborador'] ?? '';

if (empty($data_inicio_str) || empty($data_fim_str)) {
    if ($export_type) {
        header("HTTP/1.1 400 Bad Request");
        echo "Datas de início e fim são obrigatórias para exportação.";
        if(isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
        exit;
    }
    $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32)); 
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Datas de início e fim são obrigatórias.', 'csrf_token' => $_SESSION[$csrfTokenSessionKey]]);
}

try {
    $data_inicio_obj = new DateTime($data_inicio_str);
    $data_fim_obj = new DateTime($data_fim_str);
    if ($data_inicio_obj > $data_fim_obj) {
        if ($export_type) { 
            header("HTTP/1.1 400 Bad Request");
            echo "Data de início não pode ser posterior à data de fim.";
            if(isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
            exit;
        }
        $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Data de início não pode ser posterior à data de fim.', 'csrf_token' => $_SESSION[$csrfTokenSessionKey]]);
    }
} catch (Exception $e) {
    if ($export_type) { 
        header("HTTP/1.1 400 Bad Request");
        echo "Formato de data inválido.";
        if(isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
        exit;
    }
    $logger->log('WARNING', 'Formato de data inválido para relatório.', ['get_data' => $_GET, 'user_id' => $userId, 'error' => $e->getMessage()]);
    $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Formato de data inválido (esperado YYYY-MM-DD).', 'csrf_token' => $_SESSION[$csrfTokenSessionKey]]);
}

$sql = "SELECT 
            t.data, 
            FORMAT(t.data, 'dd/MM/yyyy', 'pt-BR') AS data_formatada_relatorio,
            t.colaborador, 
            t.hora_inicio,
            t.hora_fim,
            FORMAT(CAST(t.hora_inicio AS TIME), 'HH:mm', 'pt-BR') AS hora_inicio_formatada_relatorio, 
            FORMAT(CAST(t.hora_fim AS TIME), 'HH:mm', 'pt-BR') AS hora_fim_formatada_relatorio 
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
    if ($export_type) { 
        header("HTTP/1.1 500 Internal Server Error");
        echo "Erro ao buscar dados para o relatório.";
        if(isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
        exit;
    }
    $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro interno ao executar consulta.', 'csrf_token' => $_SESSION[$csrfTokenSessionKey]]);
}

$turnos_db = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $turnos_db[] = $row;
}
sqlsrv_free_stmt($stmt);

$turnos_processados_para_json = [];
$turnos_para_export = []; 
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
                
                $total_horas_no_intervalo = floor($duracao_em_minutos / 60);
                $minutos_restantes = $duracao_em_minutos % 60;
                $duracao_formatada_str = sprintf('%02dh%02dmin', $total_horas_no_intervalo, $minutos_restantes);
            }
        } catch (Exception $e) { 
            $logger->log('WARNING', 'Erro ao calcular duração do turno.', ['turno_id_ou_data' => $data_original_turno_str, 'error' => $e->getMessage()]);
        }
    }

    $item_para_json = [
        'data_formatada'        => $turno_db_row['data_formatada_relatorio'],
        'colaborador'           => $turno_db_row['colaborador'],
        'hora_inicio_formatada' => $turno_db_row['hora_inicio_formatada_relatorio'],
        'hora_fim_formatada'    => $turno_db_row['hora_fim_formatada_relatorio'],
        'duracao_formatada'     => $duracao_formatada_str
    ];
    $turnos_processados_para_json[] = $item_para_json;

    $turnos_para_export[] = [
        'Data' => $turno_db_row['data_formatada_relatorio'],
        'Colaborador' => $turno_db_row['colaborador'],
        'Hora Início' => $turno_db_row['hora_inicio_formatada_relatorio'],
        'Hora Fim' => $turno_db_row['hora_fim_formatada_relatorio'],
        'Duração' => $duracao_formatada_str,
        'Duração Decimal (h)' => number_format($duracao_decimal, 2, ',', '.') 
    ];
}

$_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
$novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];


if ($export_type === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_turnos_'.date('Ymd_His').'.csv"');
    $output = fopen('php://output', 'w');
    
    if (!empty($turnos_para_export)) {
        fputcsv($output, array_keys($turnos_para_export[0])); 
        foreach ($turnos_para_export as $row_export) {
            fputcsv($output, $row_export);
        }
    } else {
        fputcsv($output, ['Nenhum dado encontrado para os filtros selecionados.']);
    }
    fclose($output);
    if(isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
    exit;

} elseif ($export_type === 'pdf') {
    try {
        // CORREÇÃO: Usar new mPDF para mPDF v6.x
        $mpdf = new mPDF([ // Alterado de \Mpdf\Mpdf para mPDF
            'mode' => 'utf-8',
            'format' => 'A4-L', 
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 25, 
            'margin_bottom' => 20, 
            'default_font' => 'sans-serif' 
        ]);

        $mpdf->SetTitle("Relatório de Turnos");
        $mpdf->SetAuthor("Sim Posto Sistema");
        $mpdf->SetCreator("Sim Posto Sistema");
        $dataAtualFormatada = date('d/m/Y H:i:s');

        $mpdf->SetHeader("Relatório de Turnos - Sim Posto|Gerado em: {$dataAtualFormatada}|Página {PAGENO} de {nb}");
        $mpdf->SetFooter("Sim Posto Sistema");

        $html = <<<HTML
        <html>
        <head>
            <style>
                body { font-family: 'Arial', sans-serif; font-size: 9px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #a0a0a0; padding: 5px; text-align: left; }
                th { background-color: #e9e9e9; font-weight: bold; font-size: 10px;}
                h1 { text-align: center; margin-bottom: 15px; font-size: 16px; color: #333; }
                .info-header { margin-bottom: 10px; font-size: 10px; }
                .info-header p { margin: 2px 0; }
            </style>
        </head>
        <body>
            <h1>Relatório de Turnos</h1>
            <div class="info-header">
                <p><strong>Período:</strong> {$data_inicio_obj->format('d/m/Y')} - {$data_fim_obj->format('d/m/Y')}</p>
HTML;
        if (!empty($colaborador_filtro)) {
            $html .= "<p><strong>Colaborador:</strong> " . htmlspecialchars($colaborador_filtro) . "</p>";
        }
        $html .= "<p><strong>Total de Turnos Registrados:</strong> " . count($turnos_para_export) . "</p>";
        $html .= "<p><strong>Total de Horas Trabalhadas (aproximado):</strong> " . number_format($total_geral_horas_decimal, 2, ',', '.') . "h</p>";
        $html .= "</div><table><thead><tr>";

        $headers = ['Data', 'Colaborador', 'Hora Início', 'Hora Fim', 'Duração', 'Duração Decimal (h)'];
        foreach ($headers as $header) {
            $html .= "<th>" . htmlspecialchars($header) . "</th>";
        }
        $html .= "</tr></thead><tbody>";

        if (empty($turnos_para_export)) {
            $html .= "<tr><td colspan='" . count($headers) . "' style='text-align:center; padding: 10px;'>Nenhum turno encontrado para os filtros selecionados.</td></tr>";
        } else {
            foreach ($turnos_para_export as $row_export) {
                $html .= "<tr>";
                $html .= "<td>" . htmlspecialchars($row_export['Data']) . "</td>";
                $html .= "<td>" . htmlspecialchars($row_export['Colaborador']) . "</td>";
                $html .= "<td>" . htmlspecialchars($row_export['Hora Início']) . "</td>";
                $html .= "<td>" . htmlspecialchars($row_export['Hora Fim']) . "</td>";
                $html .= "<td>" . htmlspecialchars($row_export['Duração']) . "</td>";
                $html .= "<td>" . htmlspecialchars($row_export['Duração Decimal (h)']) . "</td>";
                $html .= "</tr>";
            }
        }

        $html .= <<<HTML
        </tbody></table>
        </body></html>
HTML;
        
        $mpdf->WriteHTML($html);
        $logger->log('INFO', 'PDF do relatório de turnos gerado com sucesso.', ['user_id' => $userId, 'filtros' => $_GET]);
        // CORREÇÃO: Para mPDF v6.x, o segundo parâmetro de Output define o destino.
        $mpdf->Output('relatorio_turnos_'.date('Ymd_His').'.pdf', 'D'); 

        if(isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
        exit;

    } catch (\MpdfException $e) { // mPDF v6.x pode não usar \Mpdf\MpdfException, mas sim mPDF_exception
        $logger->log('ERROR', 'Erro ao gerar PDF com mPDF: ' . $e->getMessage(), ['user_id' => $userId, 'filtros' => $_GET, 'trace' => $e->getTraceAsString()]);
        header("HTTP/1.1 500 Internal Server Error");
        echo "Erro ao gerar o arquivo PDF: " . htmlspecialchars($e->getMessage());
        if(isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
        exit;
    } catch (Exception $e) { 
        $logger->log('ERROR', 'Erro inesperado ao gerar PDF: ' . $e->getMessage(), ['user_id' => $userId, 'filtros' => $_GET, 'trace' => $e->getTraceAsString()]);
        header("HTTP/1.1 500 Internal Server Error");
        echo "Ocorreu um erro inesperado ao gerar o PDF.";
        if(isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
        exit;
    }
} else { 
    header('Content-Type: application/json'); 
    fecharConexaoApiESair($conexao, [
        'success'             => true,
        'turnos'              => $turnos_processados_para_json,
        'total_geral_horas'   => round($total_geral_horas_decimal, 2),
        'total_turnos'        => count($turnos_processados_para_json),
        'csrf_token'          => $novoCsrfTokenParaCliente 
    ]);
}

if(isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);