<?php
// gerenciar_observacao_geral.php (Adaptado para SQL Server)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php'; // $conexao é recurso SQLSRV
require_once __DIR__ . '/LogHelper.php'; // Assegure que LogHelper.php está adaptado para SQLSRV

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

$settingKey = 'observacoes_gerais';
$novoCsrfTokenParaCliente = null;
$csrfTokenSessionKey = 'csrf_token_obs_geral';

function fecharConexaoObsGeralESair($conexaoSqlsrv, $jsonData) {
    if (isset($conexaoSqlsrv)) {
        sqlsrv_close($conexaoSqlsrv);
    }
    echo json_encode($jsonData);
    exit;
}

// --- Verificação de Sessão e CSRF Token ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        fecharConexaoObsGeralESair($conexao, ['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido (CSRF check obs geral).', ['user_id' => $_SESSION['usuario_id'] ?? null, 'json_error' => json_last_error_msg()]);
        fecharConexaoObsGeralESair($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
    }
    if (!isset($input['csrf_token']) || !isset($_SESSION[$csrfTokenSessionKey]) || !hash_equals($_SESSION[$csrfTokenSessionKey], $input['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token (obs geral).', ['user_id' => $_SESSION['usuario_id'] ?? null]);
        fecharConexaoObsGeralESair($conexao, ['success' => false, 'message' => 'Erro de segurança. Recarregue a página.']);
    }
    $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        fecharConexaoObsGeralESair($conexao, ['success' => false, 'message' => 'Acesso negado.']);
    }
    if (empty($_SESSION[$csrfTokenSessionKey])) { $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32)); }
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];
} else {
    http_response_code(405);
    fecharConexaoObsGeralESair($conexao, ['success' => false, 'message' => 'Método não suportado.']);
}
$userId = $_SESSION['usuario_id'];


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
    $params = [$settingKey];
    
    $stmt = sqlsrv_query($conexao, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $logger->log('ERROR', 'Falha ao executar SELECT para observação geral (SQLSRV).', ['user_id' => $userId, 'setting_key' => $settingKey, 'sqlsrv_errors' => $errors]);
        fecharConexaoObsGeralESair($conexao, ['success' => false, 'message' => 'Erro ao buscar observação.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    
    $observacao = '';
    if (sqlsrv_fetch($stmt)) { // Tenta buscar a primeira linha
        $observacao = sqlsrv_get_field($stmt, 0); // Pega o valor da primeira coluna
    }
    sqlsrv_free_stmt($stmt);
    fecharConexaoObsGeralESair($conexao, ['success' => true, 'observacao' => ($observacao ?: ''), 'csrf_token' => $novoCsrfTokenParaCliente]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $observacaoConteudo = $input['observacao'] ?? '';

    // SQL Server "upsert" usando MERGE (requer SQL Server 2008+)
    // Assume que `setting_key` é uma PRIMARY KEY ou tem um índice UNIQUE.
    $sql_merge = "
        MERGE INTO system_settings AS target
        USING (SELECT ? AS setting_key_param, ? AS setting_value_param) AS source
        ON target.setting_key = source.setting_key_param
        WHEN MATCHED THEN
            UPDATE SET target.setting_value = source.setting_value_param
        WHEN NOT MATCHED BY TARGET THEN
            INSERT (setting_key, setting_value) VALUES (source.setting_key_param, source.setting_value_param);
    ";
    
    $params_merge = [$settingKey, $observacaoConteudo];
    $stmt_merge = sqlsrv_query($conexao, $sql_merge, $params_merge);

    if ($stmt_merge) {
        // sqlsrv_rows_affected retorna o número de linhas afetadas pela última instrução.
        // Para MERGE, pode ser 1 (para INSERT ou UPDATE) ou mais se a condição de source for complexa.
        $affected_rows = sqlsrv_rows_affected($stmt_merge); 
        sqlsrv_free_stmt($stmt_merge);

        // Não há uma forma simples de distinguir INSERT de UPDATE via affected_rows com MERGE como no MySQL ON DUPLICATE KEY.
        // Se $affected_rows for >= 0, a operação geralmente teve sucesso ou nenhuma linha correspondeu ao update e não havia nada para inserir (se source fosse uma tabela).
        // No nosso caso com VALUES, sempre haverá um match ou um insert.
        if ($affected_rows !== false && $affected_rows >= 0) { 
            $logger->log('INFO', 'Observação geral salva (SQLSRV MERGE).', ['user_id' => $userId, 'setting_key' => $settingKey, 'affected_rows' => $affected_rows]);
            fecharConexaoObsGeralESair($conexao, ['success' => true, 'message' => 'Observação geral salva com sucesso!', 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            // Este caso é menos provável com MERGE e source estático se não houver erro, mas é uma verificação.
            $errors = sqlsrv_errors(); // Pega erros se a execução falhou mas $stmt_merge não foi false.
            $logger->log('ERROR', 'Observação geral salva (SQLSRV MERGE), mas affected_rows indicou problema ou erro.', ['user_id' => $userId, 'setting_key' => $settingKey, 'affected_rows_val' => $affected_rows, 'sqlsrv_errors' => $errors]);
            fecharConexaoObsGeralESair($conexao, ['success' => false, 'message' => 'Erro ao verificar o salvamento da observação.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
    } else {
        $errors = sqlsrv_errors();
        $logger->log('ERROR', 'Falha ao executar MERGE para observação geral (SQLSRV).', ['user_id' => $userId, 'sqlsrv_errors' => $errors]);
        fecharConexaoObsGeralESair($conexao, ['success' => false, 'message' => 'Erro ao salvar observação geral.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
}

if (isset($conexao)) { // Fallback
    sqlsrv_close($conexao);
}
