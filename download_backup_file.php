<?php
// download_backup_file.php (Adaptado para SQL Server)
require_once __DIR__ . '/config.php'; // Define constantes e inicia sessão
// require_once __DIR__ . '/LogHelper.php'; // Para loggar tentativas de download. Adapte LogHelper para SQLSRV se ele usar BD.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Instanciação do Logger:
// Se LogHelper PRECISA de uma conexão SQLSRV para logar:
// require_once __DIR__ . '/conexao.php'; // Deve fornecer $conexao SQLSRV
// $logger = new LogHelper($conexao);
// Se LogHelper pode funcionar sem conexão (ex: loga em arquivo), instancie apropriadamente.
// Ex: $logger = new LogHelper(null); // Ou conforme a necessidade do seu LogHelper adaptado.
// Por simplicidade, as chamadas ao logger estão comentadas abaixo. Se usar, garanta que está configurado.

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // if (isset($logger)) $logger->log('SECURITY_WARNING', 'Tentativa de download de backup não autenticada.');
    header("HTTP/1.1 401 Unauthorized");
    die("Acesso não autorizado.");
}
$userIdLogado = $_SESSION['usuario_id'] ?? 'N/A';

// Adicionar verificação de role de administrador se necessário:
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
//     // if (isset($logger)) $logger->log('SECURITY_WARNING', 'Tentativa de download de backup por usuário não admin.', ['user_id' => $userIdLogado]);
//     header("HTTP/1.1 403 Forbidden");
//     die("Permissão negada.");
// }


if (isset($_GET['file'])) {
    $fileName = basename($_GET['file']); // basename() para segurança
    $backupFolder = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;
    $filePath = $backupFolder . $fileName;

    if (strpos(realpath($filePath), realpath($backupFolder)) !== 0 || !file_exists($filePath)) {
        // if (isset($logger)) $logger->log('SECURITY_WARNING', 'Tentativa de path traversal ou arquivo inexistente no download.', ['requested_file' => $_GET['file'], 'calculated_path' => $filePath, 'user_id' => $userIdLogado]);
        header("HTTP/1.1 403 Forbidden");
        die("Acesso ao arquivo negado ou arquivo não encontrado.");
    }

    // Atualizar a regex para corresponder a arquivos .bak gerados pelo SQL Server
    // Exemplo: simposto_backup_20231027_103045.bak
    if (preg_match('/^([a-zA-Z0-9_.-]+)_backup_\d{8}_\d{6}\.bak$/', $fileName)) {
        // if (isset($logger)) $logger->log('INFO', 'Iniciando download de backup SQL Server.', ['file' => $fileName, 'user_id' => $userIdLogado]);

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream'); // Tipo genérico para .bak ou application/vnd.ms-sql-bak
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);

        // Opcional: Deletar o arquivo após o download
        // if (unlink($filePath)) {
        //     // if (isset($logger)) $logger->log('INFO', 'Arquivo de backup SQL Server removido após download.', ['file' => $fileName, 'user_id' => $userIdLogado]);
        // } else {
        //     // if (isset($logger)) $logger->log('WARNING', 'Falha ao remover arquivo de backup SQL Server após download.', ['file' => $fileName, 'user_id' => $userIdLogado]);
        // }
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

// Fechar conexão SQLSRV se ela foi aberta (ex: se LogHelper a usou e $conexao existe)
// if (isset($conexao) && $conexao) {
//     sqlsrv_close($conexao);
// }
