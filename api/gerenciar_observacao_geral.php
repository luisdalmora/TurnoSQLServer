<?php
// api/gerenciar_observacao_geral.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php'; 
require_once __DIR__ . '/../lib/LogHelper.php'; 
require_once __DIR__ . '/api_helpers.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

$settingKey = 'observacoes_gerais';
$csrfTokenSessionKey = 'csrf_token_obs_geral';
$novoCsrfTokenParaCliente = null;
$userId = $_SESSION['usuario_id'] ?? null;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido (POST obs geral).', ['user_id' => $userId, 'json_error' => json_last_error_msg()]);
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
    }
    
    checkAdminApi($conexao, $logger, $csrfTokenSessionKey, $novoCsrfTokenParaCliente);
    verifyCsrfTokenApi($input, $csrfTokenSessionKey, $conexao, $logger, $novoCsrfTokenParaCliente);
    
    $observacaoConteudo = $input['observacao'] ?? '';

    $sql_merge = "
        MERGE INTO system_settings AS target
        USING (SELECT ? AS setting_key_param, ? AS setting_value_param) AS source
        ON target.setting_key = source.setting_key_param
        WHEN MATCHED THEN
            UPDATE SET target.setting_value = source.setting_value_param, updated_by_usuario_id = ?, data_atualizacao = GETDATE()
        WHEN NOT MATCHED BY TARGET THEN
            INSERT (setting_key, setting_value, updated_by_usuario_id, data_atualizacao) VALUES (source.setting_key_param, source.setting_value_param, ?, GETDATE());
    "; // Adicionado updated_by_usuario_id e data_atualizacao
    
    $params_merge = [$settingKey, $observacaoConteudo, $userId, $userId]; // $userId duas vezes para insert e update
    $stmt_merge = sqlsrv_query($conexao, $sql_merge, $params_merge);

    if ($stmt_merge) {
        $affected_rows = sqlsrv_rows_affected($stmt_merge); 
        sqlsrv_free_stmt($stmt_merge);

        if ($affected_rows !== false && $affected_rows >= 0) { 
            $logger->log('INFO', 'Observação geral salva (SQLSRV MERGE).', ['user_id' => $userId, 'setting_key' => $settingKey, 'affected_rows' => $affected_rows]);
            fecharConexaoApiESair($conexao, ['success' => true, 'message' => 'Observação geral salva com sucesso!', 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else {
            $errors = sqlsrv_errors(); 
            $logger->log('ERROR', 'Observação geral salva (SQLSRV MERGE), mas affected_rows indicou problema ou erro.', ['user_id' => $userId, 'setting_key' => $settingKey, 'affected_rows_val' => $affected_rows, 'sqlsrv_errors' => $errors]);
            fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro ao verificar o salvamento da observação.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
    } else {
        $errors = sqlsrv_errors();
        $logger->log('ERROR', 'Falha ao executar MERGE para observação geral (SQLSRV).', ['user_id' => $userId, 'sqlsrv_errors' => $errors]);
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro ao salvar observação geral.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetBase($csrfTokenSessionKey, $novoCsrfTokenParaCliente, $conexao);
    
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
    $params = [$settingKey];
    $stmt = sqlsrv_query($conexao, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $logger->log('ERROR', 'Falha ao executar SELECT para observação geral (SQLSRV).', ['user_id' => $userId, 'setting_key' => $settingKey, 'sqlsrv_errors' => $errors]);
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro ao buscar observação.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    
    $observacao = '';
    if (sqlsrv_fetch($stmt)) { 
        $observacao = sqlsrv_get_field($stmt, 0); 
    }
    sqlsrv_free_stmt($stmt);
    fecharConexaoApiESair($conexao, ['success' => true, 'observacao' => ($observacao ?: ''), 'csrf_token' => $novoCsrfTokenParaCliente]);

} else {
    http_response_code(405);
    $logger->log('WARNING', 'Método HTTP não suportado em gerenciar_observacao_geral.', ['method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A', 'user_id' => $userId]);
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Método não suportado.']);
}
