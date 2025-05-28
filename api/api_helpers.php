<?php
// api/api_helpers.php

// Função can() precisa estar definida aqui para uso nas APIs
// Se você criar um lib/PermissionHelper.php, inclua-o aqui.
// Por enquanto, vou replicar a definição de can() de header.php aqui
// MANTENHA ESTA DEFINIÇÃO SINCRONIZADA COM A DE header.php
if (!function_exists('can_api_internal')) { // Renomeado para evitar conflito se header.php for incluído acidentalmente
    function can_api_internal($action, $resource, $resourceOwnerId = null) {
        $role = $_SESSION['usuario_role'] ?? 'guest'; 
        $currentUserId = $_SESSION['usuario_id'] ?? null;
        $permissions = [
            'admin' => [
                'turnos' => ['criar', 'ler_proprio', 'atualizar_proprio', 'excluir_proprio', 'ler_todos', 'gerenciar_todos'],
                'ausencias' => ['criar', 'ler_proprio', 'atualizar_proprio', 'excluir_proprio', 'ler_todos', 'gerenciar_todos'],
                'colaboradores' => ['criar', 'ler', 'atualizar', 'excluir', 'gerenciar'],
                'scripts' => ['criar', 'ler_proprio', 'atualizar_proprio', 'excluir_proprio', 'ler_todos', 'gerenciar_todos'],
                'relatorios' => ['visualizar'],
                'observacoes_gerais' => ['ler', 'editar'],
                'backup' => ['executar'],
                'sistema' => ['acessar_admin_geral']
            ],
            'user' => [ 
                'turnos' => ['criar', 'ler_proprio', 'atualizar_proprio', 'excluir_proprio'],
                'ausencias' => ['criar', 'ler_proprio', 'atualizar_proprio', 'excluir_proprio'],
                'scripts' => ['criar', 'ler_proprio', 'atualizar_proprio', 'excluir_proprio'],
                'observacoes_gerais' => ['ler'],
                'relatorios' => [],
                'colaboradores' => [],
                'backup' => [],
                'sistema' => []
            ],
            'guest' => [
                'turnos' => [], 'ausencias' => [], 'colaboradores' => [], 'scripts' => [],
                'relatorios' => [], 'observacoes_gerais' => [], 'backup' => [], 'sistema' => []
            ]
        ];
        if (!isset($permissions[$role]) || !isset($permissions[$role][$resource])) return false;
        if (str_ends_with($action, '_proprio')) {
            if ($role === 'admin' && in_array('gerenciar_todos', $permissions[$role][$resource])) {
                $baseAction = str_replace('_proprio', '', $action);
                return in_array($baseAction, $permissions[$role][$resource]);
            }
            if ($resourceOwnerId !== null && $currentUserId !== null && (int)$resourceOwnerId === (int)$currentUserId) {
                $baseAction = str_replace('_proprio', '', $action);
                return in_array($baseAction, $permissions[$role][$resource]);
            }
            return false;
        }
        return in_array($action, $permissions[$role][$resource]) || 
               in_array('gerenciar', $permissions[$role][$resource]) ||
               in_array('gerenciar_todos', $permissions[$role][$resource]);
    }
}


if (!function_exists('checkPermissionApi')) {
    function checkPermissionApi($action, $resource, $conexao, $logger, $csrfTokenSessionKey = null, &$novoCsrfTokenParaClienteRef = null, $resourceOwnerId = null) {
        if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
            http_response_code(401); 
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
            if (isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
            exit;
        }
        
        if (!can_api_internal($action, $resource, $resourceOwnerId)) {
            if ($logger && is_object($logger) && method_exists($logger, 'log')) {
                $logger->log('AUTH_FAILURE', "Tentativa de ação API '{$action}' no recurso '{$resource}' sem permissão.", [
                    'user_id' => $_SESSION['usuario_id'] ?? 'N/A',
                    'role' => $_SESSION['usuario_role'] ?? 'N/A',
                    'script' => basename($_SERVER['PHP_SELF'])
                ]);
            }
            http_response_code(403); 
            $response = ['success' => false, 'message' => 'Permissão negada para esta ação.'];
            
            if ($csrfTokenSessionKey && $novoCsrfTokenParaClienteRef !== null) { 
                 $response['csrf_token'] = $novoCsrfTokenParaClienteRef;
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
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida (CSRF check).']);
            if (isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
            exit;
        }
        if (!isset($input['csrf_token']) || !isset($_SESSION[$csrfTokenSessionKey]) || !hash_equals($_SESSION[$csrfTokenSessionKey], $input['csrf_token'])) {
            if ($logger && is_object($logger) && method_exists($logger, 'log')) {
                $logger->log('SECURITY_WARNING', 'Falha na validação do CSRF token (API).', [
                    'user_id' => $_SESSION['usuario_id'] ?? 'N/A',
                    'script' => basename($_SERVER['PHP_SELF']),
                    'session_key' => $csrfTokenSessionKey,
                    'posted_token' => $input['csrf_token'] ?? 'N/A'
                ]);
            }
            http_response_code(403);
            // For API calls, it's better to just fail than to provide a new token on CSRF failure.
            // The client should re-fetch a page or re-authenticate to get a valid token.
            echo json_encode(['success' => false, 'message' => 'Erro de segurança (token CSRF inválido). Por favor, recarregue a página e tente novamente.']);
            if (isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao);
            exit;
        }
        
        // Regenerate token only on successful POST/action, not just for GETs if this helper is used for GET too.
        // $novoCsrfTokenParaClienteRef is passed by reference.
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
    function handleGetBase($csrfTokenSessionKey, &$novoCsrfTokenParaClienteRef, $conexao = null) { 
        if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão inválida.']);
            if (isset($conexao) && is_resource($conexao)) sqlsrv_close($conexao); 
            exit;
        }
        
        if (!isset($_SESSION[$csrfTokenSessionKey]) || empty($_SESSION[$csrfTokenSessionKey])) {
            $_SESSION[$csrfTokenSessionKey] = bin2hex(random_bytes(32));
        }
        $novoCsrfTokenParaClienteRef = $_SESSION[$csrfTokenSessionKey]; // Fornece o token atual para GET requests
    }
}

if (!function_exists('fecharConexaoApiESair')) {
    function fecharConexaoApiESair($conexaoSqlsrv, $jsonData) {
        if (isset($conexaoSqlsrv) && is_resource($conexaoSqlsrv)) {
            sqlsrv_close($conexaoSqlsrv);
        }
        // Garantir que o header de JSON seja enviado apenas uma vez.
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode($jsonData);
        exit;
    }
}
