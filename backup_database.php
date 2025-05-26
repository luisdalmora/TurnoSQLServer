<?php
// backup_database.php (Adaptado para SQL Server)
require_once __DIR__ . '/config.php';
// $conexao_sqlsrv é definido em conexao.php usando $db_servername_sqlsrv, etc.
require_once __DIR__ . '/conexao.php'; 
require_once __DIR__ . '/LogHelper.php';

$logger = new LogHelper($conexao); // $conexao é um recurso SQLSRV
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Método não permitido."]);
    if ($conexao) sqlsrv_close($conexao);
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
    if ($conexao) sqlsrv_close($conexao);
    exit;
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    $logger->log('SECURITY_WARNING', 'Tentativa de acesso não autenticado ao backup_database.php.');
    echo json_encode(["success" => false, "message" => "Acesso não autorizado."]);
    if ($conexao) sqlsrv_close($conexao);
    exit;
}
$userIdLogado = $_SESSION['usuario_id'];

// Variáveis de conexao.php (SQL Server)
// $db_servername_sqlsrv, $db_username_sqlsrv, $db_password_sqlsrv, $db_database_sqlsrv
if (!isset($db_database_sqlsrv, $db_servername_sqlsrv)) { // Username/password podem ser omitidos para Autenticação Windows
    $logger->log('ERROR', 'Variáveis de conexão SQL Server não definidas. Verifique conexao.php.', ['user_id' => $userIdLogado]);
    echo json_encode(["success" => false, "message" => "Erro interno: Configuração de conexão incompleta."]);
    if ($conexao) sqlsrv_close($conexao);
    exit;
}

$backupFileBase = $db_database_sqlsrv . '_backup_' . date("Ymd_His");
$backupFile = $backupFileBase . '.bak'; // Backup SQL Server geralmente é .bak
$backupFolder = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;

if (!is_dir($backupFolder)) {
    if (!mkdir($backupFolder, 0775, true)) {
        $logger->log('ERROR', 'Falha ao criar pasta de backups.', ['path' => $backupFolder, 'user_id' => $userIdLogado]);
        echo json_encode(["success" => false, "message" => "Erro interno: Não foi possível criar a pasta de backups."]);
        if ($conexao) sqlsrv_close($conexao);
        exit;
    }
}
if (!is_writable($backupFolder)) {
    $logger->log('ERROR', 'Pasta de backups sem permissão de escrita para o PHP.', ['path' => $backupFolder, 'user_id' => $userIdLogado]);
    echo json_encode(["success" => false, "message" => "Erro interno: A pasta de backups não tem permissão de escrita."]);
    if ($conexao) sqlsrv_close($conexao);
    exit;
}

$fullPathToBackup = $backupFolder . $backupFile;

// Construir o comando sqlcmd para backup
// ATENÇÃO: Segurança é crucial.
// - Autenticação do Windows (-E) é preferível se o PHP rodar com uma conta de usuário apropriada.
// - Passar senha na linha de comando (-P) é um risco.
// - O usuário do SQL Server precisa de permissão para BACKUP DATABASE.
// - O SQL Server precisa ter permissão para escrever no $fullPathToBackup (caminho visto pelo SQL Server, não necessariamente pelo PHP).
//   Se o SQL Server estiver em outra máquina, o $fullPathToBackup deve ser um caminho acessível por ele (ex: UNC path).

$sqlcmdPath = 'sqlcmd'; // Tenta usar o sqlcmd do PATH. Especifique o caminho completo se necessário.
                        // Ex: 'C:\\Program Files\\Microsoft SQL Server\\Client SDK\\ODBC\\170\\Tools\\Binn\\sqlcmd.exe'

$backupCommandTsql = sprintf(
    "BACKUP DATABASE [%s] TO DISK = N'%s' WITH FORMAT, MEDIANAME = N'PHP_Backup_Media', NAME = N'Full Backup of %s';",
    $db_database_sqlsrv,
    $fullPathToBackup, // SQL Server deve ter permissão para escrever neste local.
    $db_database_sqlsrv
);

// Construção do comando sqlcmd
if (!empty($db_username_sqlsrv) && !empty($db_password_sqlsrv)) {
    // Usando login SQL - RISCO DE SEGURANÇA COM SENHA NA LINHA DE COMANDO
    $command = sprintf(
        '%s -S %s -U %s -P %s -Q "%s" -b -r1', // -b para sair em erro, -r1 para mensagens de erro no stdout
        escapeshellcmd($sqlcmdPath),
        escapeshellarg($db_servername_sqlsrv),
        escapeshellarg($db_username_sqlsrv),
        escapeshellarg($db_password_sqlsrv), // RISCO!
        $backupCommandTsql // Não use escapeshellarg aqui pois é parte do -Q
    );
    $command_preview = sprintf(
        '%s -S %s -U %s -P *** -Q "%s" -b -r1',
        $sqlcmdPath, $db_servername_sqlsrv, $db_username_sqlsrv, $backupCommandTsql
    );
} else {
    // Usando Autenticação do Windows (preferível se configurável)
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

// shell_exec pode não retornar stderr adequadamente sem redirecionamento.
// A opção -r1 no sqlcmd envia mensagens de erro para stdout.
$output = shell_exec($command . " 2>&1"); // Redireciona stderr para stdout
$criticalErrorOccurred = false;
$userVisibleError = "Falha ao executar o backup do SQL Server.";

if ($output !== null && $output !== '') {
    // sqlcmd pode retornar mensagens mesmo em sucesso. Procurar por erros explícitos.
    // Ex: "Msg...", "Error:", "failed"
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

        $downloadScriptName = 'download_backup_file.php'; // Este script precisaria ser seguro
        $siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
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

if ($conexao) {
    sqlsrv_close($conexao);
}
exit;
