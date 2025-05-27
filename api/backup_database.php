<?php
// api/backup_database.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php'; 
require_once __DIR__ . '/../lib/LogHelper.php';
require_once __DIR__ . '/api_helpers.php'; 

$logger = new LogHelper($conexao); 
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$csrfTokenSessionKey = 'csrf_token_backup';
$novoCsrfTokenParaCliente = null;
$userIdLogado = $_SESSION['usuario_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    fecharConexaoApiESair($conexao, ["success" => false, "message" => "Método não permitido."]);
}

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $logger->log('ERROR', 'JSON de entrada inválido em backup_database.', ['user_id' => $userIdLogado, 'json_error' => json_last_error_msg()]);
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
}

// Verifica se o usuário está logado e é admin ANTES de verificar o CSRF
checkAdminApi($conexao, $logger, $csrfTokenSessionKey, $novoCsrfTokenParaCliente); // $novoCsrfTokenParaCliente é passado por referência mas não será usado se checkAdminApi sair

// Verifica CSRF. $novoCsrfTokenParaCliente será atualizado pela função.
// A chave CSRF aqui é 'csrf_token_backup' e o campo no JSON é 'csrf_token_backup'
verifyCsrfTokenApi($input, $csrfTokenSessionKey, $conexao, $logger, $novoCsrfTokenParaCliente);


if (!isset($db_database_sqlsrv, $db_servername_sqlsrv)) { 
    $logger->log('ERROR', 'Variáveis de conexão SQL Server não definidas para backup.', ['user_id' => $userIdLogado]);
    fecharConexaoApiESair($conexao, ["success" => false, "message" => "Erro interno: Configuração de conexão incompleta."]);
}

$backupFileBase = $db_database_sqlsrv . '_backup_' . date("Ymd_His");
$backupFile = $backupFileBase . '.bak'; 
$backupFolder = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;

if (!is_dir($backupFolder)) {
    if (!mkdir($backupFolder, 0775, true)) {
        $logger->log('ERROR', 'Falha ao criar pasta de backups.', ['path' => $backupFolder, 'user_id' => $userIdLogado]);
        fecharConexaoApiESair($conexao, ["success" => false, "message" => "Erro interno: Não foi possível criar a pasta de backups."]);
    }
}
if (!is_writable($backupFolder)) {
    $logger->log('ERROR', 'Pasta de backups sem permissão de escrita.', ['path' => $backupFolder, 'user_id' => $userIdLogado]);
    fecharConexaoApiESair($conexao, ["success" => false, "message" => "Erro interno: A pasta de backups não tem permissão de escrita."]);
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
        escapeshellcmd($sqlcmdPath), escapeshellarg($db_servername_sqlsrv),
        escapeshellarg($db_username_sqlsrv), escapeshellarg($db_password_sqlsrv), 
        $backupCommandTsql 
    );
} else { // Autenticação do Windows
    $command = sprintf(
        '%s -S %s -E -Q "%s" -b -r1',
        escapeshellcmd($sqlcmdPath), escapeshellarg($db_servername_sqlsrv),
        $backupCommandTsql
    );
}

$logger->log('INFO', 'Tentando executar comando sqlcmd para backup SQL Server.', ['user_id' => $userIdLogado, 'command_preview' => str_replace($db_password_sqlsrv, '***', $command)]);

$output = shell_exec($command . " 2>&1"); 
$criticalErrorOccurred = false;
$userVisibleError = "Falha ao executar o backup do SQL Server.";

if ($output !== null && $output !== '') {
    if (preg_match('/Msg|Error|failed|Cannot open backup device|Access is denied/i', $output) && 
        !preg_match('/Processed \d+ pages for database|BACKUP DATABASE successfully processed/i', $output) ) {
        $criticalErrorOccurred = true;
        $logger->log('ERROR', 'sqlcmd produziu saída que parece ser um erro de backup.', ['user_id' => $userIdLogado, 'output' => $output, 'file_path' => $fullPathToBackup]);
        $userVisibleError .= " Detalhe: " . htmlentities(substr($output, 0, 350));
    } else {
        $logger->log('INFO', 'sqlcmd produziu saída.', ['user_id' => $userIdLogado, 'output' => $output, 'file_path' => $fullPathToBackup]);
    }
}

if (!$criticalErrorOccurred) {
    if (file_exists($fullPathToBackup) && filesize($fullPathToBackup) > 0) {
        $logger->log('INFO', 'Backup SQL Server (.bak) realizado com sucesso e arquivo verificado.', ['user_id' => $userIdLogado, 'file' => $fullPathToBackup, 'size' => filesize($fullPathToBackup)]);
        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : ''; 
        $downloadUrl = $siteUrl . '/download_backup_file.php?file=' . urlencode(basename($fullPathToBackup));

        fecharConexaoApiESair($conexao, [
            "success" => true,
            "message" => "Backup do banco de dados SQL Server concluído com sucesso!",
            "download_url" => $downloadUrl,
            "filename" => basename($fullPathToBackup),
            "csrf_token" => $novoCsrfTokenParaCliente // Envia o token atualizado de volta
        ]);
    } else {
        $criticalErrorOccurred = true;
        // ... (log de erro como estava) ...
        $userVisibleError = "Erro: O arquivo de backup SQL Server não foi gerado corretamente ou está vazio.";
    }
}

if ($criticalErrorOccurred) {
    fecharConexaoApiESair($conexao, ["success" => false, "message" => $userVisibleError, "csrf_token" => $novoCsrfTokenParaCliente]);
}
