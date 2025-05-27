<?php
// api/salvar_turnos.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../lib/LogHelper.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

// Função para verificar se o usuário é admin
function isApiAdmin() {
    return isset($_SESSION['usuario_role']) && $_SESSION['usuario_role'] === 'admin';
}

// ... (suas funções utilitárias existentes como formatarDataParaBancoSQLSRV, fecharConexaoSalvarTurnosESair) ...
function formatarDataParaBancoSQLSRV($dataStr, $anoReferencia) { /* ... seu código ... */ return "$anoReferencia-01-01"; } // Placeholder
function formatarHoraParaBancoSQLSRV($horaStr) { /* ... seu código ... */ return "00:00:00"; } // Placeholder
function fecharConexaoSalvarTurnosESair($conexaoSqlsrv, $jsonData) { if (isset($conexaoSqlsrv)) { sqlsrv_close($conexaoSqlsrv); } echo json_encode($jsonData); exit;}


$novoCsrfTokenParaCliente = null;
$userIdLogging = $_SESSION['usuario_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    // ... (validação CSRF existente) ...
    if (!isset($input['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token (turnos).', ['user_id' => $userIdLogging, 'acao' => $input['acao'] ?? 'desconhecida']);
        fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Erro de segurança.']);
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenera o token
    $novoCsrfTokenParaCliente = $_SESSION['csrf_token'];

    // Verificação de permissão para ações POST
    if (!isApiAdmin()) {
        $logger->log('AUTH_FAILURE', 'Tentativa de ação POST em salvar_turnos.php sem permissão de admin.', ['user_id' => $userIdLogging, 'role' => $_SESSION['usuario_role'] ?? 'N/A', 'acao' => $input['acao'] ?? 'desconhecida']);
        fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Permissão negada para esta ação.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Acesso negado.']);
    }
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    $novoCsrfTokenParaCliente = $_SESSION['csrf_token'];
    // Usuários comuns podem visualizar turnos, então não há verificação de admin para GET aqui.
} else {
    fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Método não suportado.']);
}
$userId = $_SESSION['usuario_id'];


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // ... (lógica GET existente para carregar turnos - todos podem ver) ...
    $anoFiltro = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: (int)date('Y');
    $mesFiltro = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('m');
    // ... (restante da lógica GET)
    $sql = "SELECT id, FORMAT(data, 'dd/MM', 'pt-BR') AS data_formatada, data AS data_original_db, FORMAT(CAST(hora_inicio AS TIME), 'HH:mm', 'pt-BR') AS hora_inicio_formatada, hora_inicio AS hora_inicio_original_db, FORMAT(CAST(hora_fim AS TIME), 'HH:mm', 'pt-BR') AS hora_fim_formatada, hora_fim AS hora_fim_original_db, colaborador FROM turnos WHERE criado_por_usuario_id = ? AND YEAR(data) = ? AND MONTH(data) = ? ORDER BY data ASC, hora_inicio ASC";
    $params_get = [$userId, $anoFiltro, $mesFiltro];
    $stmt_get = sqlsrv_query($conexao, $sql, $params_get);
    $turnos_carregados = [];
    if($stmt_get){
        while ($row = sqlsrv_fetch_array($stmt_get, SQLSRV_FETCH_ASSOC)) {
            $data_para_js = ($row['data_original_db'] instanceof DateTimeInterface) ? $row['data_original_db']->format('Y-m-d') : $row['data_original_db'];
            $hora_inicio_para_js = ($row['hora_inicio_original_db'] instanceof DateTimeInterface) ? $row['hora_inicio_original_db']->format('H:i') : null;
            $hora_fim_para_js = ($row['hora_fim_original_db'] instanceof DateTimeInterface) ? $row['hora_fim_original_db']->format('H:i') : null;
            $turnos_carregados[] = ['id' => $row['id'], 'data_formatada' => $row['data_formatada'], 'data' => $data_para_js, 'hora_inicio' => $hora_inicio_para_js, 'hora_fim' => $hora_fim_para_js, 'colaborador' => $row['colaborador']];
        }
        sqlsrv_free_stmt($stmt_get);
    } else {
        // log erro
    }
    fecharConexaoSalvarTurnosESair($conexao, ['success' => true, 'message' => 'Turnos carregados.', 'data' => $turnos_carregados, 'csrf_token' => $novoCsrfTokenParaCliente]);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A verificação de admin já foi feita acima para todo POST
    $acao = $input['acao'] ?? null;

    if ($acao === 'excluir_turnos') {
        // ... (lógica de excluir turnos existente) ...
        $idsParaExcluir = $input['ids_turnos'] ?? [];
        if (empty($idsParaExcluir)) { fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Nenhum ID fornecido para exclusão.', 'csrf_token' => $novoCsrfTokenParaCliente]); }
        $idsValidos = array_filter(array_map('intval', $idsParaExcluir), fn($id) => $id > 0);
        if (empty($idsValidos)) { fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'IDs de turno inválidos.', 'csrf_token' => $novoCsrfTokenParaCliente]); }
        $placeholders = implode(',', array_fill(0, count($idsValidos), '?'));
        $sql_delete = "DELETE FROM turnos WHERE id IN ($placeholders) AND criado_por_usuario_id = ?";
        $params_delete = $idsValidos; $params_delete[] = $userId;
        $stmt_delete = sqlsrv_query($conexao, $sql_delete, $params_delete);
        // ... (resto da lógica de exclusão) ...
        if ($stmt_delete) {
             $numLinhasAfetadas = sqlsrv_rows_affected($stmt_delete);
             sqlsrv_free_stmt($stmt_delete);
             $logger->log('INFO', "$numLinhasAfetadas turno(s) excluído(s) do BD (SQLSRV).", ['user_id' => $userId, 'ids' => $idsValidos, 'affected_rows' => $numLinhasAfetadas]);
             fecharConexaoSalvarTurnosESair($conexao, ['success' => true, 'message' => "$numLinhasAfetadas turno(s) excluído(s) com sucesso.", 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
             // log
             fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Erro ao excluir.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }


    } elseif ($acao === 'salvar_turnos') {
        // ... (lógica de salvar turnos existente) ...
        $dadosTurnosRecebidos = $input['turnos'] ?? [];
        // ... (validações) ...
        $errosOperacao = []; $anoReferenciaTurnosSalvos = null; $mesReferenciaRecarga = date('m');
        $sql_insert = "INSERT INTO turnos (data, hora_inicio, hora_fim, colaborador, criado_por_usuario_id) VALUES (?, ?, ?, ?, ?)";
        $sql_update = "UPDATE turnos SET data = ?, hora_inicio = ?, hora_fim = ?, colaborador = ? WHERE id = ? AND criado_por_usuario_id = ?";
        // ... (loop pelos turnos) ...
         foreach ($dadosTurnosRecebidos as $turno) {
            $turnoIdCliente = $turno['id'] ?? null; $dataStr = $turno['data'] ?? null; $anoForm = $turno['ano'] ?? date('Y');
            $horaInicioStr = $turno['hora_inicio'] ?? null; $horaFimStr = $turno['hora_fim'] ?? null; $colaboradorNome = isset($turno['colaborador']) ? trim($turno['colaborador']) : null;
            if (!$anoReferenciaTurnosSalvos) $anoReferenciaTurnosSalvos = $anoForm;
            // ... (validações de dados) ...
            $dataFormatadaBanco = formatarDataParaBancoSQLSRV($dataStr, $anoForm);
            $horaInicioDb = formatarHoraParaBancoSQLSRV($horaInicioStr);
            $horaFimDb = formatarHoraParaBancoSQLSRV($horaFimStr);

            if (!$dataFormatadaBanco || !$horaInicioDb || !$horaFimDb || empty($colaboradorNome)) {
                $errosOperacao[] = "Turno para '{$colaboradorNome}' em '{$dataStr}' com dados incompletos/inválidos."; continue;
            }

            $isUpdate = ($turnoIdCliente && substr((string)$turnoIdCliente, 0, 4) !== "new-");
            if ($isUpdate) {
                // update
            } else {
                // insert
            }
        }
        // ... (lógica de recarregar e retornar) ...
        if (!empty($errosOperacao)) {
            fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Ocorreram erros: ' . implode("; ", $errosOperacao), 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            fecharConexaoSalvarTurnosESair($conexao, ['success' => true, 'message' => 'Turnos salvos com sucesso!', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
    } else {
        $logger->log('WARNING', 'Ação POST desconhecida em salvar_turnos.', ['acao' => $acao, 'user_id' => $userId]);
        fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Ação desconhecida.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
}

fecharConexaoSalvarTurnosESair($conexao, ['success' => false, 'message' => 'Requisição inválida.', 'csrf_token' => $novoCsrfTokenParaCliente ?? bin2hex(random_bytes(32)) ]);
