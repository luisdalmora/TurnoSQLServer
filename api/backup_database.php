<?php
// api/backup_database.php
require_once __DIR__ . '/../config/config.php';
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

$requestMethod = $_SERVER['REQUEST_METHOD'];
$inputData = []; 
$csrfTokenFromRequest = null;

if ($requestMethod === 'GET') {
    checkPermissionApi('executar', 'backup', $conexao, $logger, $csrfTokenSessionKey, $novoCsrfTokenParaCliente); 
    $csrfTokenFromRequest = $_GET['csrf_token'] ?? null;
    if (!$csrfTokenFromRequest || !isset($_SESSION[$csrfTokenSessionKey]) || !hash_equals($_SESSION[$csrfTokenSessionKey], $csrfTokenFromRequest)) {
        $logger->log('SECURITY_WARNING', 'Falha CSRF token em backup_database (GET).', ['user_id' => $userIdLogado, 'get_params' => $_GET]);
        http_response_code(403);
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro de segurança (token GET inválido).']);
    }
    $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32)); 
    $novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey]; 
} elseif ($requestMethod === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido em backup_database (POST).', ['user_id' => $userIdLogado, 'json_error' => json_last_error_msg()]);
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
    }
    $csrfTokenFromRequest = $inputData['csrf_token'] ?? null;
    checkPermissionApi('executar', 'backup', $conexao, $logger, $csrfTokenSessionKey, $novoCsrfTokenParaCliente); 
    verifyCsrfTokenApi($inputData, $csrfTokenSessionKey, $conexao, $logger, $novoCsrfTokenParaCliente); 
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

$backupDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $backupPathConfig), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$fileName = $dbName . '_backup_' . date('Ymd_His') . '.bak';
$backupFileFullPath = $backupDir . $fileName;

if (!is_dir($backupDir)) {
    if (@mkdir($backupDir, 0775, true)) {
        $logger->log('INFO', 'Diretório de backup criado automaticamente.', ['path' => $backupDir, 'user_id' => $userIdLogado]);
    } else {
        clearstatcache(); 
        if (!is_dir($backupDir)) {
            $error = error_get_last();
            $mkdir_error_message = $error ? $error['message'] : 'desconhecido';
            $logger->log('ERROR', 'Falha ao criar o diretório de backup: ' . $mkdir_error_message, ['path' => $backupDir, 'user_id' => $userIdLogado]);
            fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Falha ao criar o diretório de backup. Verifique as permissões do PHP/Apache no local: ' . htmlspecialchars($backupDir) . ' (Erro: '.htmlspecialchars($mkdir_error_message).')', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
    }
}

$sqlBackup = "BACKUP DATABASE [" . $dbName . "] TO DISK = N'" . $backupFileFullPath . "' WITH NOFORMAT, INIT, NAME = N'" . htmlspecialchars($dbName) . "-Full Database Backup " . date('Y-m-d H:i:s') . "', SKIP, NOREWIND, NOUNLOAD";

$responsePayload = ['csrf_token' => $novoCsrfTokenParaCliente]; 

// Executa o comando de backup.
$stmt = sqlsrv_query($conexao, $sqlBackup);

// Após a execução, independentemente do valor de retorno de $stmt (que pode ser enganoso para BACKUP DATABASE),
// verificaremos os erros do SQL Server e a existência do arquivo.

$sqlServerErrors = sqlsrv_errors(SQLSRV_ERR_ALL); // Pega todos os erros e avisos
$genuineErrorFound = false;
$firstGenuineErrorMessage = "Falha no backup. Verifique os logs do SQL Server."; // Mensagem padrão

if ($sqlServerErrors !== null && !empty($sqlServerErrors)) {
    foreach ($sqlServerErrors as $error) {
        // Ignora mensagens de sucesso/progresso conhecidas (SQLSTATE 01000 são geralmente informativos/avisos)
        // Código 3014: BACKUP DATABASE ... successfully processed ...
        // Código 4035: Processed ... pages for database ...
        if ($error['SQLSTATE'] === '01000' && 
            ($error['code'] === 3014 || $error['code'] === 4035 || str_contains(strtolower($error['message']), 'processed') || str_contains(strtolower($error['message']), 'successfully'))) {
            $logger->log('INFO', 'Mensagem informativa do SQL Server durante backup (será verificada pela existência do arquivo): ' . $error['message'], ['user_id' => $userIdLogado]);
            // Continue para o próximo erro/aviso, não trate isso como falha ainda
        } else {
            // Encontrou um erro que não é uma simples mensagem de progresso/sucesso.
            $genuineErrorFound = true;
            $firstGenuineErrorMessage = 'Falha no backup (SQL Server): ' . htmlspecialchars($error['message']);
            $logger->log('ERROR', 'Erro REAL do SQL Server detectado durante comando BACKUP: ' . $firstGenuineErrorMessage, ['user_id' => $userIdLogado]);
            break; 
        }
    }
}

if ($genuineErrorFound) {
    // Se um erro genuíno foi encontrado nos retornos do SQL Server, falha o processo.
    $responsePayload['success'] = false;
    $responsePayload['message'] = $firstGenuineErrorMessage;
    fecharConexaoApiESair($conexao, $responsePayload);
} else {
    // Se nenhum erro genuíno foi encontrado nas mensagens do SQL Server (ou não houve mensagens),
    // o indicador final de sucesso é a existência e tamanho do arquivo.
    usleep(1500000); // 1.5 segundos de espera
    clearstatcache(); 

    if (file_exists($backupFileFullPath) && filesize($backupFileFullPath) > 0) {
        $logger->log('INFO', 'Backup do banco de dados realizado com sucesso e arquivo verificado (após filtrar mensagens SQL).', ['database' => $dbName, 'file' => $backupFileFullPath, 'user_id' => $userIdLogado]);
        $responsePayload['success'] = true;
        $responsePayload['message'] = 'Backup realizado com sucesso!';
        $responsePayload['fileName'] = $fileName;
        $responsePayload['download_url'] = rtrim(SITE_URL, '/') . '/download_backup_file.php?file=' . urlencode($fileName);
        fecharConexaoApiESair($conexao, $responsePayload);
    } else {
        // O comando SQL pode ter sido aceito, nenhuma mensagem de erro genuína, mas o backup falhou na escrita (ex: permissão do SQL Server)
        $logger->log('ERROR', 'Comando de backup SQL aceito sem erros explícitos, mas arquivo de backup não encontrado ou vazio no disco.', [
            'database' => $dbName, 
            'expected_file' => $backupFileFullPath, 
            'user_id' => $userIdLogado,
            'sqlsrv_query_returned_true' => ($stmt === true), // Indica se o comando foi aceito
            'possible_reason' => 'Verificar permissões de escrita do SERVIÇO SQL SERVER no diretório de backup ou erros nos logs do SQL Server.'
        ]);
        $responsePayload['success'] = false;
        $responsePayload['message'] = 'Comando de backup processado, mas o arquivo final não foi criado ou está vazio. Verifique as permissões de escrita do serviço SQL Server no diretório: ' . htmlspecialchars($backupDir) . ' e os logs detalhados do SQL Server.';
        fecharConexaoApiESair($conexao, $responsePayload);
    }
}

// Limpa o statement se ele foi criado (embora para BACKUP ele possa não ser um recurso tradicional)
if ($stmt && is_resource($stmt) && get_resource_type($stmt) !== 'Unknown') {
    sqlsrv_free_stmt($stmt);
}