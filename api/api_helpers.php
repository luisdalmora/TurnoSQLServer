<?php
// api/api_helpers.php

if (!function_exists('isApiAdmin')) { // Evita redefinição se já incluído
    function isApiAdmin() {
        return isset($_SESSION['usuario_role']) && $_SESSION['usuario_role'] === 'admin';
    }
}

if (!function_exists('checkAdminApi')) {
    function checkAdminApi($conexao, $logger, $csrfTokenSessionKey = null, $novoCsrfTokenParaCliente = null) {
        if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
            http_response_code(401); // Unauthorized
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
            if (isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
            exit;
        }
        if (!isApiAdmin()) {
            if ($logger && is_object($logger) && method_exists($logger, 'log')) {
                $logger->log('AUTH_FAILURE', 'Tentativa de ação API sem permissão de admin.', [
                    'user_id' => $_SESSION['usuario_id'] ?? 'N/A',
                    'role' => $_SESSION['usuario_role'] ?? 'N/A',
                    'script' => basename($_SERVER['PHP_SELF'])
                ]);
            }
            http_response_code(403); // Forbidden
            $response = ['success' => false, 'message' => 'Permissão negada para esta ação.'];
            // Retorna o token CSRF atualizado se a sessão for válida mas sem permissão,
            // e se um novo token foi gerado antes desta verificação.
            if ($csrfTokenSessionKey && $novoCsrfTokenParaCliente) {
                 $response['csrf_token'] = $novoCsrfTokenParaCliente;
            } elseif ($csrfTokenSessionKey && isset($_SESSION[$csrfTokenSessionKey])) {
                 $response['csrf_token'] = $_SESSION[$csrfTokenSessionKey];
            }
            echo json_encode($response);
            if (isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
            exit;
        }
    }
}

if (!function_exists('verifyCsrfTokenApi')) {
    function verifyCsrfTokenApi($input, $csrfTokenSessionKey, $conexao, $logger, &$novoCsrfTokenParaClienteRef) {
        if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) { 
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
            if (isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
            exit;
        }
        if (!isset($input['csrf_token']) || !isset($_SESSION[$csrfTokenSessionKey]) || !hash_equals($_SESSION[$csrfTokenSessionKey], $input['csrf_token'])) {
            if ($logger && is_object($logger) && method_exists($logger, 'log')) {
                $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token (API).', [
                    'user_id' => $_SESSION['usuario_id'] ?? 'N/A',
                    'script' => basename($_SERVER['PHP_SELF']),
                    'session_key' => $csrfTokenSessionKey
                ]);
            }
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Erro de segurança. Por favor, recarregue a página e tente novamente.']);
            if (isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
            exit;
        }
        
        if (isset($_SESSION[$csrfTokenSessionKey])) {
            $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
            $novoCsrfTokenParaClienteRef = $_SESSION[$csrfTokenSessionKey];
        } else {
             $novoCsrfTokenParaClienteRef = null; 
             if ($logger && is_object($logger) && method_exists($logger, 'log')) {
                $logger->log('WARNING', "Chave CSRF de sessão '{$csrfTokenSessionKey}' não encontrada ao tentar regenerar token.", [
                    'user_id' => $_SESSION['usuario_id'] ?? 'N/A',
                    'script' => basename($_SERVER['PHP_SELF'])
                ]);
            }
        }
    }
}

if (!function_exists('handleGetBase')) {
    function handleGetBase($csrfTokenSessionKey, &$novoCsrfTokenParaClienteRef, $conexao = null) { // Adicionado $conexao
        if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
            if (isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao); // Fechar conexão se fornecida
            exit;
        }
        // Garante que a chave de sessão CSRF exista e tenha um valor.
        if (!isset($_SESSION[$csrfTokenSessionKey]) || empty($_SESSION[$csrfTokenSessionKey])) {
            $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
        }
        $novoCsrfTokenParaClienteRef = $_SESSION[$csrfTokenSessionKey];
    }
}

if (!function_exists('fecharConexaoApiESair')) {
    function fecharConexaoApiESair($conexaoSqlsrv, $jsonData) {
        if (isset($conexaoSqlsrv) && is_resource($conexaoSqlsrv)) {
            sqlsrv_close($conexaoSqlsrv);
        }
        echo json_encode($jsonData);
        exit;
    }
}
