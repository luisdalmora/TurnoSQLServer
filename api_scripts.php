<?php
// api_scripts.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/LogHelper.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

// --- Funções Utilitárias ---
function fecharConexaoApiESair($conexaoSqlsrv, $jsonData) {
    if (isset($conexaoSqlsrv) && $conexaoSqlsrv) {
        sqlsrv_close($conexaoSqlsrv);
    }
    echo json_encode($jsonData);
    exit;
}

// --- Verificação de Sessão e CSRF Token ---
$csrfTokenSessionKey = 'csrf_token_scripts_manage';
$novoCsrfTokenParaCliente = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido (POST api_scripts).', ['user_id' => $_SESSION['usuario_id'] ?? null, 'json_error' => json_last_error_msg()]);
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
    }
    if (!isset($input['csrf_token']) || !isset($_SESSION[$csrfTokenSessionKey]) || !hash_equals($_SESSION[$csrfTokenSessionKey], $input['csrf_token'])) {
        $logger->log('SECURITY_WARNING', 'Falha CSRF token (POST api_scripts).', ['user_id' => $_SESSION['usuario_id'] ?? null]);
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro de segurança. Recarregue a página.']);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Acesso negado.']);
    }
    // Para GET, o token CSRF é normalmente verificado se for uma ação sensível,
    // mas para listagem simples, pode ser opcional ou vir via query param e ser checado.
    // Aqui, como a listagem é feita via JS, não passaremos CSRF token na URL para GET,
    // mas o backend irá gerar um novo para o formulário de POST.
} else {
    http_response_code(405); // Method Not Allowed
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Método não suportado.']);
}

// Sempre gerar um novo token para a próxima requisição POST do formulário
$_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
$novoCsrfTokenParaCliente = $_SESSION[$csrfTokenSessionKey];
$userId = $_SESSION['usuario_id'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $input['action'] ?? ($input['script_id'] ? 'atualizar' : 'salvar'); // Determina ação por 'action' ou presença de 'script_id'

    if ($acao === 'salvar' || $acao === 'atualizar') {
        $titulo = trim($input['titulo'] ?? '');
        $conteudo = trim($input['conteudo'] ?? '');
        $script_id = isset($input['script_id']) && !empty($input['script_id']) ? (int)$input['script_id'] : null;

        if (empty($titulo) || empty($conteudo)) {
            fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Título e Conteúdo são obrigatórios.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }

        if ($acao === 'atualizar' && $script_id) {
            $sql = "UPDATE scripts_armazenados SET titulo = ?, conteudo = ?, data_atualizacao = GETDATE() WHERE id = ? AND criado_por_usuario_id = ?";
            $params = [$titulo, $conteudo, $script_id, $userId];
        } else {
            $sql = "INSERT INTO scripts_armazenados (titulo, conteudo, criado_por_usuario_id) VALUES (?, ?, ?)";
            $params = [$titulo, $conteudo, $userId];
        }
        
        $stmt = sqlsrv_query($conexao, $sql, $params);

        if ($stmt) {
            $rows_affected = sqlsrv_rows_affected($stmt);
            sqlsrv_free_stmt($stmt);
            if ($rows_affected > 0 || ($acao === 'atualizar' && $rows_affected === 0)) { // 0 rows pode ser OK se nada mudou
                 $logger->log('INFO', "Script " . ($script_id ? "atualizado (ID: $script_id)" : "salvo") . " com sucesso.", ['user_id' => $userId, 'titulo' => $titulo]);
                fecharConexaoApiESair($conexao, ['success' => true, 'message' => 'Script salvo com sucesso!', 'csrf_token' => $novoCsrfTokenParaCliente]);
            } else if ($rows_affected === false ) { // Erro
                $errors = sqlsrv_errors();
                $logger->log('ERROR', 'Erro ao salvar/atualizar script (rows_affected false).', ['user_id' => $userId, 'titulo' => $titulo, 'sqlsrv_errors' => $errors]);
                fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro ao salvar script no banco de dados.', 'csrf_token' => $novoCsrfTokenParaCliente]);
            } else { // rows_affected foi 0 e era um insert, ou um update que não encontrou o registro
                $logger->log('WARNING', "Script " . ($script_id ? " (ID: $script_id) não encontrado para atualizar ou " : "") . "não pode ser salvo (0 linhas afetadas).", ['user_id' => $userId, 'titulo' => $titulo]);
                fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Não foi possível salvar o script ou nenhuma alteração foi feita.', 'csrf_token' => $novoCsrfTokenParaCliente]);
            }
        } else {
            $errors = sqlsrv_errors();
            $logger->log('ERROR', 'Falha ao executar query para salvar/atualizar script.', ['user_id' => $userId, 'titulo' => $titulo, 'sqlsrv_errors' => $errors]);
            fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro crítico ao salvar script.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }

    } elseif ($acao === 'excluir') {
        $script_id = (int)($input['script_id'] ?? 0);
        if ($script_id <= 0) {
            fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'ID do script inválido para exclusão.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }

        $sql = "DELETE FROM scripts_armazenados WHERE id = ? AND criado_por_usuario_id = ?";
        $params = [$script_id, $userId];
        $stmt = sqlsrv_query($conexao, $sql, $params);

        if ($stmt) {
            $rows_affected = sqlsrv_rows_affected($stmt);
            sqlsrv_free_stmt($stmt);
            if ($rows_affected > 0) {
                $logger->log('INFO', "Script ID {$script_id} excluído.", ['user_id' => $userId]);
                fecharConexaoApiESair($conexao, ['success' => true, 'message' => 'Script excluído com sucesso.', 'csrf_token' => $novoCsrfTokenParaCliente]);
            } else {
                $logger->log('WARNING', "Script ID {$script_id} não encontrado para exclusão ou não pertence ao usuário.", ['user_id' => $userId]);
                fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Script não encontrado ou não autorizado para excluir.', 'csrf_token' => $novoCsrfTokenParaCliente]);
            }
        } else {
            $errors = sqlsrv_errors();
            $logger->log('ERROR', "Erro ao excluir script ID {$script_id}.", ['user_id' => $userId, 'sqlsrv_errors' => $errors]);
            fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro ao excluir o script.', 'csrf_token' => $novoCsrfTokenParaCliente]);
        }
    } else {
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Ação POST desconhecida.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $termoPesquisa = isset($_GET['search']) ? trim($_GET['search']) : '';

    $sql = "SELECT id, titulo, conteudo, FORMAT(data_criacao, 'dd/MM/yyyy HH:mm') AS data_criacao_fmt 
            FROM scripts_armazenados 
            WHERE criado_por_usuario_id = ?";
    $params = [$userId];

    if (!empty($termoPesquisa)) {
        $sql .= " AND (titulo LIKE ? OR conteudo LIKE ?)";
        $params[] = "%" . $termoPesquisa . "%";
        $params[] = "%" . $termoPesquisa . "%";
    }
    $sql .= " ORDER BY data_atualizacao DESC";

    $stmt = sqlsrv_query($conexao, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $logger->log('ERROR', 'Falha ao executar SELECT para listar scripts.', ['user_id' => $userId, 'sqlsrv_errors' => $errors]);
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro ao buscar scripts.', 'csrf_token' => $novoCsrfTokenParaCliente]);
    }

    $scripts = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $scripts[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    fecharConexaoApiESair($conexao, ['success' => true, 'scripts' => $scripts, 'csrf_token' => $novoCsrfTokenParaCliente]);
}

// Fallback
fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Requisição inválida.', 'csrf_token' => $novoCsrfTokenParaCliente]);
