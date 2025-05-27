<?php
// download_backup_file.php

// O config.php pode não ser estritamente necessário aqui se SITE_URL não for usado
// e LogHelper for instanciado com null ou não usado.
require_once __DIR__ . '/config/config.php'; 
// require_once __DIR__ . '/lib/LogHelper.php'; // Descomente se for usar logs

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// $logger = new LogHelper(null); // Ou passe $conexao se LogHelper precisar e conexao.php for incluído

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // if (isset($logger)) $logger->log('SECURITY_WARNING', 'Tentativa de download de backup não autenticada.');
    header("HTTP/1.1 401 Unauthorized");
    die("Acesso não autorizado.");
}
$userIdLogado = $_SESSION['usuario_id'] ?? 'N/A';

if (isset($_GET['file'])) {
    $fileName = basename($_GET['file']); 
    // A pasta 'backups' está na raiz do projeto, um nível acima de 'api', mas este script está na raiz.
    $backupFolder = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;
    $filePath = $backupFolder . $fileName;

    // Verificação de segurança aprimorada
    $realBackupFolder = realpath($backupFolder);
    $realFilePath = realpath($filePath);

    if ($realBackupFolder === false || $realFilePath === false || strpos($realFilePath, $realBackupFolder) !== 0 || !file_exists($filePath)) {
        // if (isset($logger)) $logger->log('SECURITY_WARNING', 'Tentativa de path traversal ou arquivo inexistente no download.', ['requested_file' => $_GET['file'], 'calculated_path' => $filePath, 'user_id' => <span class="math-inline">userIdLogado\]\);
header("HTTP/1\.1 403 Forbidden");
die("Acesso ao arquivo negado ou arquivo não encontrado\.");
}
if (preg_match('/^\(\[a\-zA\-Z0\-9\_\.\-\]\+\)\_backup\_\\d\{8\}\_\\d\{6\}\\\.bak</span>/', $fileName)) {
        // if (isset($logger)) $logger->log('INFO', 'Iniciando download de backup SQL Server.', ['file' => $fileName, 'user_id' => $userIdLogado]);

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream'); 
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);
        exit;
    } else {
        // if (isset($logger)) $logger->log('WARNING', 'Tentativa de download de arquivo de backup SQL Server inválido (formato do nome).', ['requested_file' => $_GET['file'], 'path_checked' => $filePath, 'user_id' => $userIdLogado]);
        header("HTTP/1.1 404 Not Found");
        die("Nome de arquivo de backup inválido ou arquivo não encontrado.");
    }
} else {
    // if (isset($logger)) $logger->log('WARNING', 'Tentativa de acesso a download_backup_file.php sem parâmetro.', ['user_id' => $userIdLogado]);
    header("HTTP/1.1 400 Bad Request");
    die("Nenhum arquivo especificado para download.");
}
