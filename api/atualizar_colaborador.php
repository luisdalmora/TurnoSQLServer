<?php
// api/atualizar_colaborador.php
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
    $logger->log('ERROR', 'JSON de entrada inválido em atualizar_colaborador.', ['user_id' => $userIdLogado, 'json_error' => json_last_error_msg()]);
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
}

checkAdminApi($conexao, $logger, $csrfTokenSessionKey, $novoCsrfTokenParaCliente);
verifyCsrfTokenApi($input, $csrfTokenSessionKey, $conexao, $logger, $novoCsrfTokenParaCliente);

$colab_id = isset($input['colab_id']) ? (int)$input['colab_id'] : 0;
$nome_completo = isset($input['nome_completo']) ? trim($input['nome_completo']) : '';
$email = isset($input['email']) ? trim($input['email']) : null;
$cargo = isset($input['cargo']) ? trim($input['cargo']) : null;

if ($colab_id <= 0) {
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'ID do colaborador inválido.', 'csrf_token' => $novoCsrfTokenParaCliente]);
}
if (empty($nome_completo)) {
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Nome completo é obrigatório.', 'csrf_token' => $novoCsrfTokenParaCliente]);
}

if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Formato de e-mail inválido.', 'csrf_token' => $novoCsrfTokenParaCliente]);
}

if (is_string($email) && trim($email) === '') {
    $email = null; 
}
if (is_string($cargo) && trim($cargo) === '') {
    $cargo = null; 
}

$sql = "UPDATE colaboradores SET nome_completo = ?, email = ?, cargo = ? WHERE id = ?";
$params = array($nome_completo, $email, $cargo, $colab_id);

$stmt = sqlsrv_prepare($conexao, $sql, $params);

if ($stmt) {
    if (sqlsrv_execute($stmt)) {
        $rows_affected = sqlsrv_rows_affected($stmt);
        if ($rows_affected > 0) {
            $logger->log('INFO', 'Colaborador atualizado com sucesso.', ['colab_id' => $colab_id, 'admin_user_id' => $userIdLogado]);
            fecharConexaoApiESair($conexao, ['success' => true, 'message' => 'Colaborador atualizado com sucesso!', 'csrf_token' => $novoCsrfTokenParaCliente]);
        } elseif ($rows_affected === 0 && sqlsrv_errors() === null) { // 0 rows é ok se nada mudou
            fecharConexaoApiESair($conexao, ['success' => true, 'message' => 'Nenhuma alteração detectada ou colaborador não encontrado.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        } else { 
            $errors = sqlsrv_errors(); // Pode ser null se rows_affected for 0 sem erro SQL
            $logMsg = $rows_affected === false ? 'Erro ao obter linhas afetadas (sqlsrv_rows_affected retornou false).' : 'Atualização resultou em 0 linhas afetadas com possíveis erros SQL.';
            $logger->log('ERROR', $logMsg, ['colab_id' => $colab_id, 'errors' => $errors, 'admin_user_id' => $userIdLogado]);
            fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro ao verificar a atualização do colaborador.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
    } else {
        $errors = sqlsrv_errors();
        $error_log_details = ['colab_id' => $colab_id, 'sqlsrv_errors' => $errors, 'admin_user_id' => $userIdLogado];
        $logger->log('ERROR', 'Erro ao executar atualização de colaborador.', $error_log_details);
        
        $user_message = "Erro ao atualizar o colaborador.";
        if ($errors && isset($errors[0]['code']) && ($errors[0]['code'] == 2627 || $errors[0]['code'] == 2601)) { // Unique constraint violation
            $errorMessageText = strtolower($errors[0]['message']);
            if (strpos($errorMessageText, 'email') !== false) { 
                 $user_message = "Erro: O e-mail informado ('" . htmlspecialchars($email) . "') já existe para outro colaborador.";
            } else {
                 $user_message = "Erro: Um valor único (como e-mail ou outro campo) já está em uso.";
            }
        }
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => $user_message, 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
    sqlsrv_free_stmt($stmt);
} else {
    $errors = sqlsrv_errors();
    $logger->log('ERROR', 'Erro ao preparar statement para atualizar colaborador.', ['sqlsrv_errors' => $errors, 'admin_user_id' => $userIdLogado]);
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro no sistema ao tentar preparar a atualização.', 'csrf_token' => $novoCsrfTokenParaCliente]);
}
