<?php
// api/alternar_status_colaborador.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php'; 
require_once __DIR__ . '/../lib/LogHelper.php'; 
require_once __DIR__ . '/api_helpers.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao); 
header('Content-Type: application/json');

$csrfTokenSessionKey = 'csrf_token_colab_manage';
$novoCsrfTokenParaCliente = null;
$userIdLogado = $_SESSION['usuario_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Método não permitido. Use POST.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $logger->log('ERROR', 'JSON de entrada inválido em alternar_status_colaborador.', ['user_id' => $userIdLogado, 'json_error' => json_last_error_msg()]);
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
}

checkAdminApi($conexao, $logger, $csrfTokenSessionKey, $novoCsrfTokenParaCliente);
verifyCsrfTokenApi($input, $csrfTokenSessionKey, $conexao, $logger, $novoCsrfTokenParaCliente);

$colab_id = isset($input['colab_id']) ? (int)$input['colab_id'] : 0;
$novo_status = isset($input['novo_status']) ? (int)$input['novo_status'] : null;

if ($colab_id <= 0) {
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'ID do colaborador inválido.', 'csrf_token' => $novoCsrfTokenParaCliente]);
}
if ($novo_status === null || !in_array($novo_status, [0, 1])) {
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Novo status inválido. Deve ser 0 ou 1.', 'csrf_token' => $novoCsrfTokenParaCliente]);
}

$sql = "UPDATE colaboradores SET ativo = ? WHERE id = ?";
$params = array($novo_status, $colab_id);

$stmt = sqlsrv_prepare($conexao, $sql, $params);

if ($stmt) {
    if (sqlsrv_execute($stmt)) {
        $rows_affected = sqlsrv_rows_affected($stmt);
        if ($rows_affected > 0) {
            $status_texto = $novo_status == 1 ? "ativado" : "desativado";
            $logger->log('INFO', "Status do colaborador ID {$colab_id} alterado para {$status_texto}.", ['admin_user_id' => $userIdLogado]);
            fecharConexaoApiESair($conexao, ['success' => true, 'message' => "Colaborador {$status_texto} com sucesso!", 'novo_status_bool' => (bool)$novo_status, 'csrf_token' => $novoCsrfTokenParaCliente]);
        } elseif ($rows_affected === 0 && sqlsrv_errors() === null) {
            fecharConexaoApiESair($conexao, ['success' => true, 'message' => 'Nenhuma alteração de status necessária (colaborador não encontrado ou status já definido).', 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else { 
            $errors = sqlsrv_errors();
            $logMsg = $rows_affected === false ? 'Erro ao obter linhas afetadas (sqlsrv_rows_affected retornou false).' : 'Atualização de status resultou em 0 linhas afetadas com possíveis erros SQL.';
            $logger->log('ERROR', $logMsg, ['colab_id' => $colab_id, 'errors' => $errors, 'admin_user_id' => $userIdLogado]);
            fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro ao verificar a atualização do status do colaborador.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
    } else {
        $errors = sqlsrv_errors();
        $logger->log('ERROR', 'Erro ao executar atualização de status do colaborador.', ['colab_id' => $colab_id, 'errors' => $errors, 'admin_user_id' => $userIdLogado]);
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro ao atualizar o status do colaborador.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    sqlsrv_free_stmt($stmt);
} else {
    $errors = sqlsrv_errors(); 
    $logger->log('ERROR', 'Erro ao preparar statement para alternar status.', ['errors' => $errors, 'admin_user_id' => $userIdLogado]);
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro no sistema ao tentar preparar a alteração de status.', 'csrf_token' => $novoCsrfTokenParaCliente]);
}
