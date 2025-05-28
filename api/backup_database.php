<?php
// api/backup_database.php
require_once __DIR__ . '/../config/config.php';
// conexao.php define $db_database_sqlsrv, $db_servername_sqlsrv, $db_username_sqlsrv, $db_password_sqlsrv
require_once __DIR__ . '/../config/conexao.php'; 
require_once __DIR__ . '/../lib/LogHelper.php';
require_once __DIR__ . '/api_helpers.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);
$csrfTokenSessionKey = 'csrf_token_backup'; 
$novoCsrfTokenParaCliente = null; 
$userIdLogado = $_SESSION['usuario_id'] ?? null;

// Determina o método da requisição e lida com CSRF e permissões
$requestMethod = $_SERVER['REQUEST_METHOD'];
$inputData = [];

if ($requestMethod === 'GET') {
    checkPermissionApi('executar', 'backup', $conexao, $logger, $csrfTokenSessionKey, $novoCsrfTokenParaCliente);
    if (!isset($_GET['csrf_token']) || !isset($_SESSION[$csrfTokenSessionKey]) || !hash_equals($_SESSION[$csrfTokenSessionKey], $_GET['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha CSRF token em backup_database (GET).', ['user_id' => $userIdLogado, 'get_params' => $_GET]);
        http_response_code(403);
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro de segurança (token GET inválido).']);
    }
    $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];
    // Para GET, não há corpo JSON, os dados (se houver) viriam da query string.
    // No caso do backup, o GET é apenas um gatilho com CSRF.

} elseif ($requestMethod === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido em backup_database (POST).', ['user_id' => $userIdLogado, 'json_error' => json_last_error_msg()]);
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
    }
    checkPermissionApi('executar', 'backup', $conexao, $logger, $csrfTokenSessionKey, $novoCsrfTokenParaCliente); // Preenche $novoCsrfTokenParaCliente por referência
    verifyCsrfTokenApi($inputData, $csrfTokenSessionKey, $conexao, $logger, $novoCsrfTokenParaCliente); // Atualiza $novoCsrfTokenParaCliente
} else {
    http_response_code(405);
    fecharConexaoApiESair($conexao, ["success" => false, "message" => "Método não permitido."]);
}


if (!isset($db_database_sqlsrv) || empty(trim($db_database_sqlsrv))) {
    $logger->log('ERROR', 'Nome do banco de dados SQL Server (db_database_sqlsrv) não definido em conexao.php.', ['user_id' => $userIdLogado]);
    fecharConexaoApiESair($conexao, ["success" => false, "message" => "Erro interno: Configuração de nome de banco de dados ausente.", 'csrf_token' => $novoCsrfTokenParaCliente]);
}
$dbName = $db_database_sqlsrv;


$backupPathConfig = defined('SQL_SERVER_BACKUP_PATH') ? SQL_SERVER_BACKUP_PATH : null;
if (empty(trim($backupPathConfig))) {
    $logger->log('ERROR', 'Caminho de backup SQL_SERVER_BACKUP_PATH não configurado em config.php.', ['user_id' => $userIdLogado]);
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Configuração de caminho de backup ausente.', 'csrf_token' => $novoCsrfTokenParaCliente]);
}

// Normaliza o caminho e garante que termine com uma barra separadora de diretório
$backupDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $backupPathConfig), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

$fileName = $dbName . '_backup_' . date('Ymd_His') . '.bak';
$backupFileFullPath = $backupDir . $fileName;


// --- INÍCIO DA CRIAÇÃO AUTOMÁTICA DO DIRETÓRIO ---
if (!is_dir($backupDir)) {
    // Tenta criar o diretório recursivamente
    // O @ suprime erros de mkdir, que serão tratados pela verificação is_dir abaixo
    if (@mkdir($backupDir, 0775, true)) { // 0775 permite escrita pelo grupo (útil se Apache e SQL Server rodam sob usuários diferentes no mesmo grupo)
        $logger->log('INFO', 'Diretório de backup criado automaticamente.', ['path' => $backupDir, 'user_id' => $userIdLogado]);
    } else {
        // Se mkdir falhou, verifica se o diretório foi criado por outro processo concorrente
        clearstatcache(); // Limpa o cache de status de arquivo
        if (!is_dir($backupDir)) {
            $error = error_get_last();
            $mkdir_error_message = $error ? $error['message'] : 'desconhecido';
            $logger->log('ERROR', 'Falha ao criar o diretório de backup: ' . $mkdir_error_message, ['path' => $backupDir, 'user_id' => $userIdLogado]);
            fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Falha ao criar o diretório de backup. Verifique as permissões do PHP/Apache no local: ' . htmlspecialchars($backupDir) . ' (Erro: '.htmlspecialchars($mkdir_error_message).')', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
    }
}
// Verifica se o diretório é gravável pelo PHP (não garante para o SQL Server, mas é um bom indicador)
if (!is_writable($backupDir)) {
    $logger->log('WARNING', 'O diretório de backup existe mas não é gravável pelo PHP/Apache. A permissão de escrita para o serviço SQL Server ainda é necessária.', ['path' => $backupDir, 'user_id' => $userIdLogado]);
    // Não vamos falhar aqui, pois a permissão crítica é do SQL Server.
    // Se o SQL Server não conseguir escrever, o erro do BACKUP DATABASE será capturado.
}
// --- FIM DA CRIAÇÃO AUTOMÁTICA DO DIRETÓRIO ---


$sqlBackup = "BACKUP DATABASE [" . $dbName . "] TO DISK = N'" . $backupFileFullPath . "' WITH NOFORMAT, INIT, NAME = N'" . htmlspecialchars($dbName) . "-Full Database Backup " . date('Y-m-d H:i:s') . "', SKIP, NOREWIND, NOUNLOAD, STATS = 10";

$stmt = sqlsrv_query($conexao, $sqlBackup);

$responsePayload = ['csrf_token' => $novoCsrfTokenParaCliente]; 

if ($stmt) {
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS); 
    if ($errors === null || empty($errors)) {
        usleep(500000); 

        if (file_exists($backupFileFullPath) && filesize($backupFileFullPath) > 0) {
            $logger->log('INFO', 'Backup do banco de dados realizado com sucesso.', ['database' => $dbName, 'file' => $backupFileFullPath, 'user_id' => $userIdLogado]);
            $responsePayload['success'] = true;
            $responsePayload['message'] = 'Backup realizado com sucesso!';
            $responsePayload['fileName'] = $fileName;
            $responsePayload['download_url'] = rtrim(SITE_URL, '/') . '/download_backup_file.php?file=' . urlencode($fileName); //

            fecharConexaoApiESair($conexao, $responsePayload);
        } else {
            $logger->log('ERROR', 'Comando de backup executado, mas arquivo de backup não encontrado ou vazio.', ['database' => $dbName, 'expected_file' => $backupFileFullPath, 'user_id' => $userIdLogado, 'sql_error_if_any' => $errors]);
            $responsePayload['success'] = false;
            $responsePayload['message'] = 'Comando de backup executado, mas o arquivo não foi criado ou está vazio. Verifique as permissões do SQL Server no diretório de backup: ' . htmlspecialchars($backupDir);
            fecharConexaoApiESair($conexao, $responsePayload);
        }
    } else {
        $errorMessages = array_map(function($error) { return "SQLSTATE: ".$error['SQLSTATE']." Code: ".$error['code']." Message: ".$error['message']; }, $errors);
        $logger->log('ERROR', 'Falha ao realizar backup do banco de dados (após execução).', ['database' => $dbName, 'errors' => implode("; ", $errorMessages), 'user_id' => $userIdLogado]);
        $responsePayload['success'] = false;
        $responsePayload['message'] = 'Falha no backup: ' . htmlspecialchars($errors[0]['message']);
        fecharConexaoApiESair($conexao, $responsePayload);
    }
    if ($stmt && is_resource($stmt) && get_resource_type($stmt) !== 'Unknown') sqlsrv_free_stmt($stmt);
} else {
    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    $errorMessages = array_map(function($error) { return "SQLSTATE: ".$error['SQLSTATE']." Code: ".$error['code']." Message: ".$error['message']; }, $errors ?: []);
    $logger->log('ERROR', 'Falha ao iniciar comando de backup do banco de dados.', ['database' => $dbName, 'errors' => implode("; ", $errorMessages), 'user_id' => $userIdLogado, 'sql_command' => $sqlBackup]);
    $userMessage = 'Falha ao iniciar o processo de backup.';
    if (!empty($errors)) {
        $userMessage .= ' Detalhe: ' . htmlspecialchars($errors[0]['message']);
    }
    $responsePayload['success'] = false;
    $responsePayload['message'] = $userMessage;
    fecharConexaoApiESair($conexao, $responsePayload);
}