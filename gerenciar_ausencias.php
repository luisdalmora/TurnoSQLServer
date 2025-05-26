<?php
// gerenciar_ausencias.php (Adaptado para SQL Server)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // $conexao é recurso SQLSRV
require_once __DIR__ . '/LogHelper.php'; // Assegure que LogHelper.php está adaptado para SQLSRV

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

function formatarDataParaBanco($dataStr) {
    if (empty($dataStr)) return null;
    try {
        // SQL Server prefere 'Y-m-d'
        $dt = new DateTime($dataStr);
        return $dt->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

$novoCsrfTokenParaCliente = null;
$csrfTokenSessionKey = 'csrf_token_ausencias';

function fecharConexaoAusenciasESair($conexaoSqlsrv, $jsonData) {
    if (isset($conexaoSqlsrv)) {
        sqlsrv_close($conexaoSqlsrv);
    }
    echo json_encode($jsonData);
    exit;
}

$userIdForLog = $_SESSION['usuario_id'] ?? 'N/A_PRE_AUTH';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        $logger->log('SECURITY_WARNING', 'Tentativa de POST não autenticada em gerenciar_ausencias.', ['user_id' => $userIdForLog]);
        fecharConexaoAusenciasESair($conexao, ['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
    }
    $userIdForLog = $_SESSION['usuario_id']; 

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido (POST ausências).', ['user_id' => $userIdForLog, 'json_error' => json_last_error_msg()]);
        fecharConexaoAusenciasESair($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
    }
    if (!isset($input['csrf_token']) || !isset($_SESSION[$csrfTokenSessionKey]) || !hash_equals($_SESSION[$csrfTokenSessionKey], $input['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token (POST ausências).', ['user_id' => $userIdForLog, 'acao' => $input['acao'] ?? 'desconhecida']);
        fecharConexaoAusenciasESair($conexao, ['success' => false, 'message' => 'Erro de segurança. Por favor, recarregue a página e tente novamente.']);
    }
    $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        $logger->log('SECURITY_WARNING', 'Tentativa de GET não autenticada em gerenciar_ausencias.', ['user_id' => $userIdForLog]);
        fecharConexaoAusenciasESair($conexao, ['success' => false, 'message' => 'Acesso negado.']);
    }
    $userIdForLog = $_SESSION['usuario_id']; 

    if (empty($_SESSION[$csrfTokenSessionKey])) {
        $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
    }
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];
} else {
    http_response_code(405); 
    $logger->log('WARNING', 'Método HTTP não suportado em gerenciar_ausencias.', ['method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A']);
    fecharConexaoAusenciasESair($conexao, ['success' => false, 'message' => 'Método não suportado.']);
}
$userId = $_SESSION['usuario_id'];


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $anoFiltro = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
    $mesFiltro = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);

    if ($anoFiltro === false || $mesFiltro === false || $mesFiltro < 1 || $mesFiltro > 12) {
        $logger->log('WARNING', 'Parâmetros de ano/mês inválidos para GET ausências.', ['user_id' => $userId, 'ano' => $_GET['ano'] ?? 'N/A', 'mes' => $_GET['mes'] ?? 'N/A']);
        fecharConexaoAusenciasESair($conexao, ['success' => false, 'message' => 'Parâmetros de ano/mês inválidos.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    
    $primeiroDiaMesFiltro = sprintf('%04d-%02d-01', $anoFiltro, $mesFiltro);
    $ultimoDiaMesFiltro = date('Y-m-t', strtotime($primeiroDiaMesFiltro));

    // SQL Server: datas formatadas como YYYY-MM-DD
    // Colunas 'data_inicio' e 'data_fim' no SQL Server devem ser DATE ou DATETIME
    // Para retornar apenas a data no formato YYYY-MM-DD para o cliente, se forem DATETIME:
    // FORMAT(data_inicio, 'yyyy-MM-dd') AS data_inicio_formatada
    $sql = "SELECT id, data_inicio, data_fim, colaborador_nome, observacoes 
            FROM ausencias
            WHERE criado_por_usuario_id = ? 
            AND (
                 (data_inicio BETWEEN ? AND ?) OR 
                 (data_fim BETWEEN ? AND ?) OR     
                 (data_inicio < ? AND data_fim > ?) 
            )
            ORDER BY data_inicio ASC";

    $params = [
        $userId, 
        $primeiroDiaMesFiltro, $ultimoDiaMesFiltro, 
        $primeiroDiaMesFiltro, $ultimoDiaMesFiltro, 
        $primeiroDiaMesFiltro, $ultimoDiaMesFiltro  
    ];
    
    $stmt = sqlsrv_query($conexao, $sql, $params);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $logger->log('ERROR', 'Erro ao executar consulta GET ausências (SQLSRV).', ['sqlsrv_errors' => $errors, 'user_id' => $userId, 'sql' => $sql]);
        fecharConexaoAusenciasESair($conexao, ['success' => false, 'message' => 'Erro ao consultar ausências.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }

    $itens_carregados = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // SQLSRV pode retornar datas como objetos DateTime. Formatá-los para string se necessário para o cliente.
        if ($row['data_inicio'] instanceof DateTimeInterface) {
            $row['data_inicio'] = $row['data_inicio']->format('Y-m-d');
        }
        if ($row['data_fim'] instanceof DateTimeInterface) {
            $row['data_fim'] = $row['data_fim']->format('Y-m-d');
        }
        $itens_carregados[] = $row;
    }
    sqlsrv_free_stmt($stmt);

    $logger->log('INFO', 'Consulta de ausências executada.', [
        'user_id' => $userId, 
        'ano' => $anoFiltro, 
        'mes' => $mesFiltro, 
        'num_ausencias_encontradas' => count($itens_carregados),
    ]);

    fecharConexaoAusenciasESair($conexao, ['success' => true, 'message' => 'Ausências carregadas.', 'data' => $itens_carregados, 'csrf_token' => $novoCsrfTokenParaCliente]);

}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $input['acao'] ?? null;

    if ($acao === 'excluir_ausencias') {
        $idsParaExcluir = $input['ids_ausencias'] ?? [];
        if (empty($idsParaExcluir)) {
            fecharConexaoAusenciasESair($conexao, ['success' => false, 'message' => 'Nenhum ID fornecido para exclusão.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
        $idsValidos = array_filter(array_map('intval', $idsParaExcluir), fn($id) => $id > 0);
        if (empty($idsValidos)) {
            fecharConexaoAusenciasESair($conexao, ['success' => false, 'message' => 'IDs de ausência inválidos.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }

        $placeholders = implode(',', array_fill(0, count($idsValidos), '?'));
        $sql_delete = "DELETE FROM ausencias WHERE id IN ($placeholders) AND criado_por_usuario_id = ?";
        
        $params_delete = $idsValidos;
        $params_delete[] = $userId;

        $stmt_delete = sqlsrv_query($conexao, $sql_delete, $params_delete);

        if ($stmt_delete) {
            $numLinhasAfetadas = sqlsrv_rows_affected($stmt_delete);
            sqlsrv_free_stmt($stmt_delete);
            $logger->log('INFO', "$numLinhasAfetadas ausência(s) excluída(s) do BD (SQLSRV).", ['user_id' => $userId, 'ids' => $idsValidos, 'affected_rows' => $numLinhasAfetadas]);
            fecharConexaoAusenciasESair($conexao, ['success' => true, 'message' => "$numLinhasAfetadas ausência(s) excluída(s) com sucesso.", 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            $errors = sqlsrv_errors();
            $logger->log('ERROR', 'Falha ao executar exclusão de ausências BD (SQLSRV).', ['user_id' => $userId, 'sqlsrv_errors' => $errors]);
            fecharConexaoAusenciasESair($conexao, ['success' => false, 'message' => 'Erro ao excluir ausências do banco de dados.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }

    } elseif ($acao === 'salvar_ausencias') {
        $dadosItensRecebidos = $input['ausencias'] ?? [];
        if (empty($dadosItensRecebidos) && !isset($input['ausencias'])) {
            $logger->log('WARNING', 'Nenhum dado de ausência recebido para salvar.', ['user_id' => $userId]);
            fecharConexaoAusenciasESair($conexao, ['success' => false, 'message' => 'Nenhum dado de ausência recebido.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
        if (empty($dadosItensRecebidos) && isset($input['ausencias'])) { 
            $logger->log('INFO', 'Array vazio de ausências recebido. Nenhuma ação no BD.', ['user_id' => $userId]);
            fecharConexaoAusenciasESair($conexao, ['success' => true, 'message' => 'Nenhuma ausência para salvar ou atualizar.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }

        $errosOperacao = [];
        $sql_insert = "INSERT INTO ausencias (data_inicio, data_fim, colaborador_nome, observacoes, criado_por_usuario_id) VALUES (?, ?, ?, ?, ?)";
        $sql_update = "UPDATE ausencias SET data_inicio = ?, data_fim = ?, colaborador_nome = ?, observacoes = ? WHERE id = ? AND criado_por_usuario_id = ?";

        foreach ($dadosItensRecebidos as $item) {
            $itemId = $item['id'] ?? null;
            $dataInicioStr = $item['data_inicio'] ?? null;
            $dataFimStr = $item['data_fim'] ?? null;
            $colaboradorNome = isset($item['colaborador_nome']) ? trim($item['colaborador_nome']) : null;
            if($colaboradorNome === '') $colaboradorNome = null;
            $observacoes = isset($item['observacoes']) ? trim($item['observacoes']) : null;
            if ($observacoes === '') $observacoes = null; 

            $dataInicioDb = formatarDataParaBanco($dataInicioStr);
            $dataFimDb = formatarDataParaBanco($dataFimStr);

            if (!$dataInicioDb || !$dataFimDb) {
                $errosOperacao[] = "Ausência com datas incompletas/inválidas (item: " . ($colaboradorNome ?: ($observacoes ?: ($itemId ?: 'Novo'))) . "). Data Início: '{$dataInicioStr}', Data Fim: '{$dataFimStr}'."; continue;
            }
            if (strtotime($dataFimDb) < strtotime($dataInicioDb)) {
                $errosOperacao[] = "Data Fim ('{$dataFimStr}') não pode ser anterior à Data Início ('{$dataInicioStr}') para ausência de '{$colaboradorNome}'."; continue;
            }

            $isUpdate = ($itemId && substr((string)$itemId, 0, 4) !== "new-");

            if ($isUpdate) {
                $itemIdRealDb = (int)$itemId;
                $params_update = [$dataInicioDb, $dataFimDb, $colaboradorNome, $observacoes, $itemIdRealDb, $userId];
                $stmt_update = sqlsrv_query($conexao, $sql_update, $params_update);
                if (!$stmt_update) {
                    $errors = sqlsrv_errors();
                    $errosOperacao[] = "Erro ao ATUALIZAR ausência ID {$itemIdRealDb}: " . ($errors[0]['message'] ?? 'Erro SQLSRV');
                } else {
                    sqlsrv_free_stmt($stmt_update);
                }
            } else {
                $params_insert = [$dataInicioDb, $dataFimDb, $colaboradorNome, $observacoes, $userId];
                $stmt_insert = sqlsrv_query($conexao, $sql_insert, $params_insert);
                if (!$stmt_insert) {
                    $errors = sqlsrv_errors();
                    $errosOperacao[] = "Erro ao INSERIR ausência para '{$colaboradorNome}': " . ($errors[0]['message'] ?? 'Erro SQLSRV');
                } else {
                    sqlsrv_free_stmt($stmt_insert);
                }
            }
        }

        if (!empty($errosOperacao)) {
            $logger->log('WARNING', 'Erros ao salvar ausências (SQLSRV).', ['user_id' => $userId, 'errors' => $errosOperacao]);
            fecharConexaoAusenciasESair($conexao, ['success' => false, 'message' => 'Ocorreram erros: ' . implode("; ", $errosOperacao), 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            $logger->log('INFO', 'Ausências salvas com sucesso (SQLSRV).', ['user_id' => $userId, 'num_items' => count($dadosItensRecebidos)]);
            fecharConexaoAusenciasESair($conexao, ['success' => true, 'message' => 'Ausências salvas com sucesso!', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
    } else {
        $logger->log('WARNING', 'Ação POST desconhecida em gerenciar ausências.', ['acao' => $acao, 'user_id' => $userId]);
        fecharConexaoAusenciasESair($conexao, ['success' => false, 'message' => 'Ação desconhecida.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
}

$logger->log('CRITICAL', 'Fluxo inesperado alcançou o fim de gerenciar_ausencias.php.', ['user_id' => $userIdForLog, 'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A']);
fecharConexaoAusenciasESair($conexao, ['success' => false, 'message' => 'Erro interno no servidor.', 'csrf_token' => $novoCsrfTokenParaCliente ?? bin2hex(random_bytes(32))]);
