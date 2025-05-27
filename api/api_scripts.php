<?php
// api/api_scripts.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../lib/LogHelper.php';
require_once __DIR__ . '/api_helpers.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logger = new LogHelper($conexao);
header('Content-Type: application/json');

$csrfTokenSessionKey = 'csrf_token_scripts_manage';
$novoCsrfTokenParaCliente = null;
$userId = $_SESSION['usuario_id'] ?? null;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->log('ERROR', 'JSON de entrada inválido (POST api_scripts).', ['user_id' => $userId, 'json_error' => json_last_error_msg()]);
        fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Requisição inválida (JSON).']);
    }

    checkAdminApi($conexao, $logger, $csrfTokenSessionKey, $novoCsrfTokenParaCliente);
    verifyCsrfTokenApi($input, $csrfTokenSessionKey, $conexao, $logger, $novoCsrfTokenParaCliente);

    $acao = $input['action'] ?? ($input['script_id'] ? 'atualizar' : 'salvar');

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
            $sql = "INSERT INTO scripts_armazenados (titulo, conteudo, criado_por_usuario_id, data_atualizacao) VALUES (?, ?, ?, GETDATE())";
            $params = [$titulo, $conteudo, $userId];
        }
        
        $stmt = sqlsrv_query($conexao, $sql, $params);

        if ($stmt) {
            $rows_affected = sqlsrv_rows_affected($stmt);
            sqlsrv_free_stmt($stmt);
            if ($rows_affected > 0 || ($acao === 'atualizar' && $rows_affected === 0 && sqlsrv_errors() === null) ) { // Para update, 0 rows pode ser ok se nada mudou
                 $logger->log('INFO', "Script " . ($script_id ? "atualizado (ID: $script_id)" : "salvo") . " com sucesso.", ['user_id' => $userId, 'titulo' => $titulo]);
                fecharConexaoApiESair($conexao, ['success' => true, 'message' => 'Script salvo com sucesso!', 'csrf_token' => $novoCsrfTokenParaCliente]);
            } else if ($rows_affected === false ) { 
                $errors = sqlsrv_errors();
                $logger->log('ERROR', 'Erro ao salvar/atualizar script (rows_affected false).', ['user_id' => $userId, 'titulo' => $titulo, 'sqlsrv_errors' => $errors]);
                fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Erro ao salvar script no banco de dados.', 'csrf_token' => $novoCsrfTokenParaCliente]);
            } else { 
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
    handleGetBase($csrfTokenSessionKey, $novoCsrfTokenParaCliente, $conexao);
    
    $termoPesquisa = isset($_GET['search']) ? trim($_GET['search']) : '';

    $sql = "SELECT id, titulo, conteudo, FORMAT(data_criacao, 'dd/MM/yyyy HH:mm') AS data_criacao_fmt, FORMAT(data_atualizacao, 'dd/MM/yyyy HH:mm') AS data_atualizacao_fmt 
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

} else {
    http_response_code(405);
    $logger->log('WARNING', 'Método HTTP não suportado em api_scripts.', ['method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A', 'user_id' => $userId]);
    fecharConexaoApiESair($conexao, ['success' => false, 'message' => 'Método não suportado.']);
}
