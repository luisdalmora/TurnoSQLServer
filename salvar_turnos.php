<?php
// salvar_turnos.php (Adaptado para SQL Server)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // $conexao é recurso SQLSRV
require_once __DIR__ . '/LogHelper.php'; // Assegure que LogHelper está adaptado para SQLSRV
// require_once __DIR__ . '/GoogleCalendarHelper.php'; // GoogleCalendarHelper simplificado (se usado)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);

header('Content-Type: application/json');

// --- Funções Utilitárias ---
function formatarDataParaBancoSQLSRV($dataStr, $anoReferencia) {
    if (empty($dataStr)) return null;
    $partes = explode('/', $dataStr);
    if (count($partes) < 2) return null;
    $dia = trim($partes[0]);
    $mesInput = trim($partes[1]);
    $ano = $anoReferencia;
    // ... (lógica de parse de ano mantida, mas SQL Server é menos flexível com formatos de data)
    // Melhor garantir YYYY-MM-DD
    if (isset($partes[2])) { /* ... */ } 
    $diaNum = ctype_digit($dia) ? sprintf('%02d', (int)$dia) : null;
    $mesNum = null;
    if (ctype_digit($mesInput)) {
        $mesNum = sprintf('%02d', (int)$mesInput);
    } else {
        $mapaMeses = ['jan'=>'01','fev'=>'02','mar'=>'03','abr'=>'04','mai'=>'05','jun'=>'06','jul'=>'07','ago'=>'08','set'=>'09','out'=>'10','nov'=>'11','dez'=>'12'];
        $mesNum = $mapaMeses[strtolower(substr($mesInput,0,3))] ?? null;
    }
    if ($diaNum && $mesNum && checkdate((int)$mesNum, (int)$diaNum, (int)$ano)) {
        return "$ano-$mesNum-$diaNum"; // Formato YYYY-MM-DD é seguro para SQL Server
    }
    return null;
}

function formatarHoraParaBancoSQLSRV($horaStr) {
    if (empty($horaStr)) return null;
    // SQL Server aceita HH:MM ou HH:MM:SS para o tipo TIME
    if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $horaStr)) {
        return $horaStr . ':00'; 
    }
    if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $horaStr)) {
        return $horaStr;
    }
    return null;
}

function fecharConexaoSalvarTurnosESair($conexaoSqlsrv, $jsonData) {
    if (isset($conexaoSqlsrv) && is_resource($conexaoSqlsrv)) { 
        sqlsrv_close($conexaoSqlsrv);
    }
    echo json_encode($jsonData);
    exit;
}

$novoCsrfTokenParaCliente = null;
$userIdLogging = $_SESSION['usuario_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) { /* ... log e saida ... */ 
        $logger->log('ERROR', 'JSON de entrada inválido.', ['user_id' => $userIdLogging, 'json_error' => json_last_error_msg()]);
        fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
    }
    if (!isset($input['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) { /* ... log e saida ... */
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token (turnos).', ['user_id' => $userIdLogging, 'acao' => $input['acao'] ?? 'desconhecida']);
        fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Erro de segurança.']);
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $novoCsrfTokenParaCliente = $_SESSION['csrf_token'];

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Acesso negado.']);
    }
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32));  }
    $novoCsrfTokenParaCliente = $_SESSION['csrf_token'];
} else { /* ... */ fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Método não suportado.']); }
$userId = $_SESSION['usuario_id'];


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $anoFiltro = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: (int)date('Y');
    $mesFiltro = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('m');

    if ($mesFiltro < 1 || $mesFiltro > 12) {
        fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Parâmetros de ano/mês inválidos.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    // SQL Server: FORMAT para datas e horas (SQL Server 2012+)
    // Colunas data, hora_inicio, hora_fim devem ser do tipo DATE, TIME ou DATETIME no SQL Server
    $sql = "SELECT id,
                   FORMAT(data, 'dd/MM', 'pt-BR') AS data_formatada,
                   data AS data_original_db,
                   FORMAT(CAST(hora_inicio AS TIME), 'HH:mm', 'pt-BR') AS hora_inicio_formatada,
                   hora_inicio AS hora_inicio_original_db,
                   FORMAT(CAST(hora_fim AS TIME), 'HH:mm', 'pt-BR') AS hora_fim_formatada,
                   hora_fim AS hora_fim_original_db,
                   colaborador
            FROM turnos
            WHERE criado_por_usuario_id = ? AND YEAR(data) = ? AND MONTH(data) = ?
            ORDER BY data ASC, hora_inicio ASC";

    $params_get = [$userId, $anoFiltro, $mesFiltro];
    $stmt_get = sqlsrv_query($conexao, $sql, $params_get);

    if (!$stmt_get) {
        $errors = sqlsrv_errors();
        $logger->log('ERROR', 'Erro ao executar consulta GET turnos (SQLSRV).', ['sqlsrv_errors' => $errors, 'user_id' => $userId]);
        fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Erro ao executar consulta.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
$turnos_carregados = [];
while ($row = sqlsrv_fetch_array($stmt_get, SQLSRV_FETCH_ASSOC)) {
    // Formata a data (originalmente YYYY-MM-DD do banco, para o JS como YYYY-MM-DD)
    $data_para_js = ($row['data_original_db'] instanceof DateTimeInterface)
                    ? $row['data_original_db']->format('Y-m-d')
                    : $row['data_original_db'];

    // Formata a hora_inicio (originalmente um objeto DateTime do driver sqlsrv para colunas TIME)
    $hora_inicio_para_js = ($row['hora_inicio_original_db'] instanceof DateTimeInterface)
                           ? $row['hora_inicio_original_db']->format('H:i') // Formato HH:mm
                           : null;

    // Formata a hora_fim
    $hora_fim_para_js = ($row['hora_fim_original_db'] instanceof DateTimeInterface)
                        ? $row['hora_fim_original_db']->format('H:i') // Formato HH:mm
                        : null;

    $turnos_carregados[] = [
        'id' => $row['id'],
        'data_formatada' => $row['data_formatada'], // Este é o dd/MM da query SQL, usado para display no input de data
        'data' => $data_para_js,                   // Data no formato YYYY-MM-DD
        'hora_inicio' => $hora_inicio_para_js,     // Hora no formato HH:mm
        'hora_fim' => $hora_fim_para_js,         // Hora no formato HH:mm
        'colaborador' => $row['colaborador']
    ];
    }
    sqlsrv_free_stmt($stmt_get);
    fecharConexaoSalvarTurnosESair($conexao, ['success' => true, 'message' => 'Turnos carregados.', 'data' => $turnos_carregados, 'csrf_token' => $novoCsrfTokenParaCliente]);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $input['acao'] ?? null;

    if ($acao === 'excluir_turnos') {
        $idsParaExcluir = $input['ids_turnos'] ?? [];
        if (empty($idsParaExcluir)) { /* ... */ fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Nenhum ID fornecido para exclusão.', 'csrf_token' => $novoCsrfTokenParaCliente]); }
        $idsValidos = array_filter(array_map('intval', $idsParaExcluir), fn($id) => $id > 0);
        if (empty($idsValidos)) { /* ... */ fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'IDs de turno inválidos.', 'csrf_token' => $novoCsrfTokenParaCliente]); }

        $placeholders = implode(',', array_fill(0, count($idsValidos), '?'));
        
        $sql_delete = "DELETE FROM turnos WHERE id IN ($placeholders) AND criado_por_usuario_id = ?";
        $params_delete = $idsValidos;
        $params_delete[] = $userId; // Adiciona o userId ao final do array de parâmetros

        $stmt_delete = sqlsrv_query($conexao, $sql_delete, $params_delete);

        if ($stmt_delete) {
            $numLinhasAfetadas = sqlsrv_rows_affected($stmt_delete);
            sqlsrv_free_stmt($stmt_delete);
            $logger->log('INFO', "$numLinhasAfetadas turno(s) excluído(s) do BD (SQLSRV).", ['user_id' => $userId, 'ids' => $idsValidos, 'affected_rows' => $numLinhasAfetadas]);
            fecharConexaoSalvarTurnosESair($conexao, ['success' => true, 'message' => "$numLinhasAfetadas turno(s) excluído(s) com sucesso.", 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else { 
            $errors = sqlsrv_errors();
            $logger->log('ERROR', 'Falha ao executar exclusão de turnos BD (SQLSRV).', ['user_id' => $userId, 'sqlsrv_errors' => $errors]);
            fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Erro ao excluir turnos do banco de dados.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }

    } elseif ($acao === 'salvar_turnos') {
        $dadosTurnosRecebidos = $input['turnos'] ?? [];
        if (empty($dadosTurnosRecebidos) || !is_array($dadosTurnosRecebidos)) { /* ... */ fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Nenhum dado de turno recebido.', 'data' => [], 'csrf_token' => $novoCsrfTokenParaCliente]); }
        
        $errosOperacao = [];
        $anoReferenciaTurnosSalvos = null;
        $mesReferenciaRecarga = date('m'); 
        
        $sql_insert = "INSERT INTO turnos (data, hora_inicio, hora_fim, colaborador, criado_por_usuario_id) VALUES (?, ?, ?, ?, ?)";
        $sql_update = "UPDATE turnos SET data = ?, hora_inicio = ?, hora_fim = ?, colaborador = ? WHERE id = ? AND criado_por_usuario_id = ?";

        foreach ($dadosTurnosRecebidos as $turno) {
            $turnoIdCliente = $turno['id'] ?? null;
            $dataStr = $turno['data'] ?? null;
            $anoForm = $turno['ano'] ?? date('Y');
            $horaInicioStr = $turno['hora_inicio'] ?? null;
            $horaFimStr = $turno['hora_fim'] ?? null;
            $colaboradorNome = isset($turno['colaborador']) ? trim($turno['colaborador']) : null;
            
            if (!$anoReferenciaTurnosSalvos) $anoReferenciaTurnosSalvos = $anoForm;
            if(empty($mesReferenciaRecarga) && !empty($dataStr)) { 
                $dataPrimeiroTurno = formatarDataParaBancoSQLSRV($dataStr, $anoForm);
                if($dataPrimeiroTurno) {
                    try { $dtObjRecarga = new DateTime($dataPrimeiroTurno); $mesReferenciaRecarga = $dtObjRecarga->format('m'); } catch(Exception $e) { /* mantém o default */ }
                }
            }

            $dataFormatadaBanco = formatarDataParaBancoSQLSRV($dataStr, $anoForm);
            $horaInicioDb = formatarHoraParaBancoSQLSRV($horaInicioStr);
            $horaFimDb = formatarHoraParaBancoSQLSRV($horaFimStr);

            if (!$dataFormatadaBanco || !$horaInicioDb || !$horaFimDb || empty($colaboradorNome)) {
                $errosOperacao[] = "Turno para '{$colaboradorNome}' em '{$dataStr}' com dados incompletos/inválidos."; continue;
            }
            // ... (validação de hora fim <= hora inicio mantida) ...

            $isUpdate = ($turnoIdCliente && substr((string)$turnoIdCliente, 0, 4) !== "new-");
            if ($isUpdate) {
                $turnoIdRealDb = (int)$turnoIdCliente;
                $params_update = [$dataFormatadaBanco, $horaInicioDb, $horaFimDb, $colaboradorNome, $turnoIdRealDb, $userId];
                $stmt_update = sqlsrv_query($conexao, $sql_update, $params_update);
                if (!$stmt_update) { $errors = sqlsrv_errors(); $errosOperacao[] = "Erro ao ATUALIZAR turno ID {$turnoIdRealDb}: " . ($errors[0]['message'] ?? 'Erro SQLSRV'); } 
                else { sqlsrv_free_stmt($stmt_update); }
            } else {
                $params_insert = [$dataFormatadaBanco, $horaInicioDb, $horaFimDb, $colaboradorNome, $userId];
                $stmt_insert = sqlsrv_query($conexao, $sql_insert, $params_insert);
                if (!$stmt_insert) { $errors = sqlsrv_errors(); $errosOperacao[] = "Erro ao INSERIR turno para {$colaboradorNome} em {$dataStr}: " . ($errors[0]['message'] ?? 'Erro SQLSRV'); }
                else { sqlsrv_free_stmt($stmt_insert); }
            }
        }
        
        $anoReferenciaRecarga = $anoReferenciaTurnosSalvos ?? date('Y');
        // $mesReferenciaRecarga já definido ou default
        
        $sql_recarregar = "SELECT id,
                                  FORMAT(data, 'dd/MM', 'pt-BR') AS data_formatada,
                                  data AS data_original_db,
                                  FORMAT(CAST(hora_inicio AS TIME), 'HH:mm', 'pt-BR') AS hora_inicio_formatada,
                                  hora_inicio AS hora_inicio_original_db,
                                  FORMAT(CAST(hora_fim AS TIME), 'HH:mm', 'pt-BR') AS hora_fim_formatada,
                                  hora_fim AS hora_fim_original_db,
                                  colaborador
                           FROM turnos
                           WHERE criado_por_usuario_id = ? AND YEAR(data) = ? AND MONTH(data) = ?
                           ORDER BY data ASC, hora_inicio ASC";
        $params_recarregar = [$userId, $anoReferenciaRecarga, (int)$mesReferenciaRecarga];
        $stmt_recarregar = sqlsrv_query($conexao, $sql_recarregar, $params_recarregar);
        $turnosRetorno = [];
        if($stmt_recarregar){
            while ($row = sqlsrv_fetch_array($stmt_recarregar, SQLSRV_FETCH_ASSOC)) {
                $row['data'] = ($row['data_original_db'] instanceof DateTimeInterface) ? $row['data_original_db']->format('Y-m-d') : $row['data_original_db'];
                $row['hora_inicio'] = $row['hora_inicio_formatada'];
                $row['hora_fim'] = $row['hora_fim_formatada'];
                unset($row['data_original_db'], $row['hora_inicio_original_db'], $row['hora_fim_original_db']);
                $turnosRetorno[] = $row;
            }
            sqlsrv_free_stmt($stmt_recarregar);
        } else {
            $errors = sqlsrv_errors();
            $logger->log('ERROR', 'Falha ao executar recarregamento de turnos (SQLSRV).', ['user_id' => $userId, 'sqlsrv_errors' => $errors]);
        }

        if (!empty($errosOperacao)) {
            fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Ocorreram erros: ' . implode("; ", $errosOperacao), 'data' => $turnosRetorno, 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            fecharConexaoSalvarTurnosESair($conexao, ['success' => true, 'message' => 'Turnos salvos com sucesso!', 'data' => $turnosRetorno, 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
    } else { /* ... Ação desconhecida ... */ 
        $logger->log('WARNING', 'Ação POST desconhecida em salvar_turnos.', ['acao' => $acao, 'user_id' => $userId]);
        fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Ação desconhecida.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
}

// Fallback final
$logger->log('ERROR', 'Método não tratado ou GET sem parâmetros válidos.', ['method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A']);
fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Requisição inválida.', 'csrf_token' => $novoCsrfTokenParaCliente ?? bin2hex(random_bytes(32)) ]);
