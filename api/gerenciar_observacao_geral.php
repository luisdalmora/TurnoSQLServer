<?php
// api/gerenciar_observacao_geral.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php'; 
require_once __DIR__ . '/../lib/LogHelper.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

$settingKey = 'observacoes_gerais';
$novoCsrfTokenParaCliente = null;
$csrfTokenSessionKey = 'csrf_token_obs_geral';

function fecharConexaoObsGeralESair($conexaoSqlsrv, $jsonData) {
    if (isset($conexaoSqlsrv) && $conexaoSqlsrv) { // Checa se é um recurso válido
        sqlsrv_close($conexaoSqlsrv);
    }
    echo json_encode($jsonData);
    exit;
}

$userIdForLog = $_SESSION['usuario_id'] ?? 'N/A_PRE_AUTH';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        $logger->log('SECURITY_WARNING', 'Tentativa de POST não autenticada em gerenciar_observacao_geral.', ['user_id' => $userIdForLog]);
        fecharConexaoObsGeralESair($conexao, ['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
    }
    $userIdForLog = $_SESSION['usuario_id']; 

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido (CSRF check obs geral).', ['user_id' => $userIdForLog, 'json_error' => json_last_error_msg()]);
        fecharConexaoObsGeralESair($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
    }
    if (!isset($input['csrf_token']) || !isset($_SESSION[$csrfTokenSessionKey]) || !hash_equals($_SESSION[$csrfTokenSessionKey], $input['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token (obs geral).', ['user_id' => $userIdForLog]);
        fecharConexaoObsGeralESair($conexao, ['success' => false, 'message' => 'Erro de segurança. Recarregue a página.']);
    }
    if(isset($_SESSION[$csrfTokenSessionKey])) {
        $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
        $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];
    }


} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        $logger->log('SECURITY_WARNING', 'Tentativa de GET não autenticada em gerenciar_observacao_geral.', ['user_id' => $userIdForLog]);
        fecharConexaoObsGeralESair($conexao, ['success' => false, 'message' => 'Acesso negado.']);
    }
    $userIdForLog = $_SESSION['usuario_id']; 

    if (empty($_SESSION[$csrfTokenSessionKey])) { $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32)); }
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];
} else {
    http_response_code(405);
    $logger->log('WARNING', 'Método HTTP não suportado em gerenciar_observacao_geral.', ['method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A']);
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
    if (sqlsrv_fetch($stmt)) { 
        $observacao = sqlsrv_get_field($stmt, 0); 
    }
    sqlsrv_free_stmt($stmt);
    fecharConexaoObsGeralESair($conexao, ['success' => true, 'observacao' => ($observacao ?: ''), 'csrf_token' => $novoCsrfTokenParaCliente]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $observacaoConteudo = $input['observacao'] ?? '';

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
        $affected_rows = sqlsrv_rows_affected($stmt_merge); 
        sqlsrv_free_stmt($stmt_merge);

        if ($affected_rows !== false && $affected_rows >= 0) { 
            $logger->log('INFO', 'Observação geral salva (SQLSRV MERGE).', ['user_id' => $userId, 'setting_key' => $settingKey, 'affected_rows' => $affected_rows]);
            fecharConexaoObsGeralESair($conexao, ['success' => true, 'message' => 'Observação geral salva com sucesso!', 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            $errors = sqlsrv_errors(); 
            $logger->log('ERROR', 'Observação geral salva (SQLSRV MERGE), mas affected_rows indicou problema ou erro.', ['user_id' => $userId, 'setting_key' => $settingKey, 'affected_rows_val' => $affected_rows, 'sqlsrv_errors' => $errors]);
            fecharConexaoObsGeralESair($conexao, ['success' => false, 'message' => 'Erro ao verificar o salvamento da observação.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
    } else {
        $errors = sqlsrv_errors();
        $logger->log('ERROR', 'Falha ao executar MERGE para observação geral (SQLSRV).', ['user_id' => $userId, 'sqlsrv_errors' => $errors]);
        fecharConexaoObsGeralESair($conexao, ['success' => false, 'message' => 'Erro ao salvar observação geral.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
}

if (isset($conexao) && $conexao) { 
    sqlsrv_close($conexao);
}
