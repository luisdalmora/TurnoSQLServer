<?php
// download_backup_file.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/LogHelper.php'; // Para logging, se necessário
// Não precisa de conexão com o banco aqui, apenas acesso ao sistema de arquivos.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inicializa o logger (opcional para este script, mas bom para consistência)
// $logger = new LogHelper(null); // Passa null se não houver conexão DB neste script

// Segurança: Verifique se o usuário está logado e tem permissão para baixar backups
// A permissão para EXECUTAR o backup já foi verificada na API que gerou o arquivo.
// Aqui, verificamos se o usuário logado pode, em geral, acessar funcionalidades de backup.
// Isso é uma segunda camada, caso o link de download seja acessado diretamente.
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // $logger->log('AUTH_FAILURE', 'Tentativa de download de backup sem sessão ativa.');
    http_response_code(401);
    die("Acesso não autorizado. Faça login.");
}

// Replicar a função can() ou incluir um helper de permissão
if (!function_exists('can_download_helper')) { // Nome diferente para evitar conflitos se header.php for incluído em algum lugar
    function can_download_helper($action, $resource) { // Simplificado para este contexto
        $role = $_SESSION['usuario_role'] ?? 'guest';
        $permissions = [ // Mantenha sincronizado com a definição principal
            'admin' => ['backup' => ['executar', 'baixar']], // Adicionada permissão 'baixar'
            'user'  => ['backup' => []],
            'guest' => ['backup' => []]
        ];
        return isset($permissions[$role][$resource]) && in_array($action, $permissions[$role][$resource]);
    }
}

if (!can_download_helper('baixar', 'backup')) {
    // $logger->log('AUTH_FAILURE', 'Tentativa de download de backup sem permissão.', ['user_id' => $_SESSION['usuario_id'], 'role' => $_SESSION['usuario_role']]);
    http_response_code(403);
    die("Permissão negada para baixar arquivos de backup.");
}


$fileName = $_GET['file'] ?? null;

if (empty($fileName)) {
    // $logger->log('WARNING', 'Tentativa de download de backup com nome de arquivo ausente.');
    http_response_code(400);
    die("Nome do arquivo de backup não especificado.");
}

// Validação de segurança no nome do arquivo para evitar Path Traversal
// Permitir apenas nomes de arquivo .bak com caracteres alfanuméricos, underscores e hífens.
if (!preg_match('/^[a-zA-Z0-9_.-]+\.bak$/', $fileName) || strpos($fileName, '..') !== false) {
    // $logger->log('SECURITY_WARNING', 'Nome de arquivo de backup inválido ou malicioso.', ['fileName_received' => $fileName]);
    http_response_code(400);
    die("Nome de arquivo inválido.");
}

$backupPath = SQL_SERVER_BACKUP_PATH; // De config.php
$fullPath = rtrim($backupPath, '\\/') . DIRECTORY_SEPARATOR . basename($fileName); // basename() para segurança adicional

if (file_exists($fullPath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream'); // Tipo MIME genérico para download
    header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($fullPath));
    
    // Limpa o buffer de saída antes de ler o arquivo para evitar corrupção
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    readfile($fullPath);
    // $logger->log('INFO', 'Download do arquivo de backup iniciado.', ['file' => $fullPath, 'user_id' => $_SESSION['usuario_id']]);
    exit;
} else {
    // $logger->log('ERROR', 'Arquivo de backup não encontrado para download.', ['file_path_attempted' => $fullPath, 'user_id' => $_SESSION['usuario_id']]);
    http_response_code(404);
    die("Arquivo de backup não encontrado no servidor: " . htmlspecialchars($fileName));
}