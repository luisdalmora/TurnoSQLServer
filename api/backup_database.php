<?php
// api/backup_database.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php'; 
require_once __DIR__ . '/../lib/LogHelper.php';

$logger = new LogHelper($conexao); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método não permitido."]);
    if (isset($conexao)) sqlsrv_close($conexao);
    exit;
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['csrf_token_backup']) || !isset($_SESSION['csrf_token_backup']) || !hash_equals($_SESSION['csrf_token_backup'], $input['csrf_token_backup'])) {
    $userIdForLog = $_SESSION['usuario_id'] ?? 'N/A_CSRF_FAIL';
    $logger->log('SECURITY_WARNING', 'Falha CSRF token em backup_database.php.', ['user_id' => $userIdForLog]);
    echo json_encode(['success' => false, 'message' => 'Erro de segurança (token inválido).']);
    if (isset($conexao)) sqlsrv_close($conexao);
    exit;
}
// Gerar novo token CSRF para backup após uso
$_SESSION['csrf_token_backup'] = bin2hex(random_bytes(32));
// Não é necessário enviar o novo token de volta aqui, pois o modal de backup fecha.
// A próxima vez que a página home for carregada, o novo token estará no input hidden.


if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    $logger->log('SECURITY_WARNING', 'Tentativa de acesso não autenticado ao backup_database.php.');
    echo json_encode(["success" => false, "message" => "Acesso não autorizado."]);
    if (isset($conexao)) sqlsrv_close($conexao);
    exit;
}
$userIdLogado = $_SESSION['usuario_id'];

// Variáveis de conexao.php já foram carregadas.
// Elas são $db_servername_sqlsrv, $db_username_sqlsrv, $db_password_sqlsrv, $db_database_sqlsrv
if (!isset($db_database_sqlsrv, $db_servername_sqlsrv)) { 
    $logger->log('ERROR', 'Variáveis de conexão SQL Server não definidas. Verifique config/conexao.php.', ['user_id' => $userIdLogado]);
    echo json_encode(["success" => false, "message" => "Erro interno: Configuração de conexão incompleta."]);
    if (isset($conexao)) sqlsrv_close($conexao);
    exit;
}

$backupFileBase = $db_database_sqlsrv . '_backup_' . date("Ymd_His");
$backupFile = $backupFileBase . '.bak'; 
$backupFolder = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR; // Volta um nível de 'api' para a raiz do projeto

if (!is_dir($backupFolder)) {
    if (!mkdir($backupFolder, 0775, true)) {
        $logger->log('ERROR', 'Falha ao criar pasta de backups.', ['path' => $backupFolder, 'user_id' => $userIdLogado]);
        echo json_encode(["success" => false, "message" => "Erro interno: Não foi possível criar a pasta de backups."]);
        if (isset($conexao)) sqlsrv_close($conexao);
        exit;
    }
}
if (!is_writable($backupFolder)) {
    $logger->log('ERROR', 'Pasta de backups sem permissão de escrita para o PHP.', ['path' => $backupFolder, 'user_id' => $userIdLogado]);
    echo json_encode(["success" => false, "message" => "Erro interno: A pasta de backups não tem permissão de escrita."]);
    if (isset($conexao)) sqlsrv_close($conexao);
    exit;
}

$fullPathToBackup = $backupFolder . $backupFile;

$sqlcmdPath = 'sqlcmd'; 

$backupCommandTsql = sprintf(
    "BACKUP DATABASE [%s] TO DISK = N'%s' WITH FORMAT, MEDIANAME = N'PHP_Backup_Media', NAME = N'Full Backup of %s';",
    $db_database_sqlsrv,
    $fullPathToBackup, 
    $db_database_sqlsrv
);

if (!empty($db_username_sqlsrv) && !empty($db_password_sqlsrv)) {
    $command = sprintf(
        '%s -S %s -U %s -P %s -Q "%s" -b -r1', 
        escapeshellcmd($sqlcmdPath),
        escapeshellarg($db_servername_sqlsrv),
        escapeshellarg($db_username_sqlsrv),
        escapeshellarg($db_password_sqlsrv), 
        $backupCommandTsql 
    );
    $command_preview = sprintf(
        '%s -S %s -U %s -P *** -Q "%s" -b -r1',
        $sqlcmdPath, $db_servername_sqlsrv, $db_username_sqlsrv, $backupCommandTsql
    );
} else {
    $command = sprintf(
        '%s -S %s -E -Q "%s" -b -r1',
        escapeshellcmd($sqlcmdPath),
        escapeshellarg($db_servername_sqlsrv),
        $backupCommandTsql
    );
     $command_preview = sprintf(
        '%s -S %s -E -Q "%s" -b -r1',
        $sqlcmdPath, $db_servername_sqlsrv, $backupCommandTsql
    );
}

$logger->log('INFO', 'Tentando executar comando sqlcmd para backup SQL Server.', [
    'user_id' => $userIdLogado,
    'command_preview' => $command_preview
]);

$output = shell_exec($command . " 2>&1"); 
$criticalErrorOccurred = false;
$userVisibleError = "Falha ao executar o backup do SQL Server.";

if ($output !== null && $output !== '') {
    if (preg_match('/Msg|Error|failed|Cannot open backup device|Access is denied/i', $output) && 
        !preg_match('/Processed \d+ pages for database|BACKUP DATABASE successfully processed/i', $output) ) {
        $criticalErrorOccurred = true;
        $logger->log('ERROR', 'sqlcmd produziu saída que parece ser um erro de backup.', ['user_id' => $userIdLogado, 'output' => $output, 'file_path' => $fullPathToBackup]);
        $userVisibleError .= " Detalhe da saída: " . htmlentities(substr($output, 0, 350));
    } else {
        $logger->log('INFO', 'sqlcmd produziu saída.', ['user_id' => $userIdLogado, 'output' => $output, 'file_path' => $fullPathToBackup]);
    }
}

if (!$criticalErrorOccurred) {
    if (file_exists($fullPathToBackup) && filesize($fullPathToBackup) > 0) {
        $logger->log('INFO', 'Backup SQL Server (.bak) realizado com sucesso e arquivo verificado.', ['user_id' => $userIdLogado, 'file' => $fullPathToBackup, 'size' => filesize($fullPathToBackup)]);

        $downloadScriptName = 'download_backup_file.php'; 
        // BASE_URL é definida em config.php (que foi incluído)
        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : ''; 
        // Se SITE_URL aponta para /TurnoSQLServer, então o download_backup_file.php estaria em /TurnoSQLServer/download_backup_file.php
        // Se a pasta api está dentro de TurnoSQLServer, o script de download deve estar na raiz do projeto web.
        $downloadUrl = $siteUrl . '/' . $downloadScriptName . '?file=' . urlencode(basename($fullPathToBackup));


        echo json_encode([
            "success" => true,
            "message" => "Backup do banco de dados SQL Server concluído com sucesso!",
            "download_url" => $downloadUrl,
            "filename" => basename($fullPathToBackup)
        ]);
    } else {
        $criticalErrorOccurred = true;
        $logMessage = 'Arquivo de backup SQL Server (.bak) NÃO encontrado ou está vazio após comando sqlcmd.';
        $logContext = [
            'user_id' => $userIdLogado,
            'file_expected' => $fullPathToBackup,
            'exists' => file_exists($fullPathToBackup),
            'size' => file_exists($fullPathToBackup) ? filesize($fullPathToBackup) : 'N/A',
            'sqlcmd_output' => $output
        ];
        $logger->log('ERROR', $logMessage, $logContext);
        $userVisibleError = "Erro: O arquivo de backup SQL Server não foi gerado corretamente ou está vazio.";
        if (!empty($output)) {
             $userVisibleError .= " Detalhe da saída: " . htmlentities(substr($output,0,200));
        }
    }
}

if ($criticalErrorOccurred) {
    echo json_encode(["success" => false, "message" => $userVisibleError]);
}

if (isset($conexao)) {
    sqlsrv_close($conexao);
}
exit;
