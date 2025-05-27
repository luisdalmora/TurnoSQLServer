<?php
// lib/LogHelper.php (Adaptado para SQL Server)

class LogHelper {
    private $conexao; // Agora será um recurso de conexão SQLSRV

    public function __construct($db_connection) {
        // $db_connection deve ser um recurso sqlsrv se a conexão com o BD for usada.
        $this->conexao = $db_connection;
    }

    /**
     * Registra uma mensagem de log no banco de dados.
     *
     * @param string $level Nível do log (e.g., INFO, ERROR, WARNING, AUTH_SUCCESS, AUTH_FAILURE, GCAL_SUCCESS, GCAL_ERROR)
     * @param string $message A mensagem de log.
     * @param array $context Dados contextuais adicionais (serão convertidos para JSON).
     * @param int|null $userId ID do usuário associado ao log (opcional).
     */
    public function log($level, $message, $context = [], $userId = null) {
        // Verifica se é um recurso de conexão sqlsrv válido
        if (!$this->conexao || !is_resource($this->conexao) || get_resource_type($this->conexao) !== 'SQL Server Connection') {
            $timestamp = date('Y-m-d H:i:s');
            error_log("{$timestamp} LogHelper: Falha ao logar - Sem conexão com BD ou tipo de conexão inválida (esperado SQLSRV). Nível: {$level}, Mensagem: {$message}, Contexto: " . json_encode($context));
            return;
        }

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $context_json = !empty($context) ? json_encode($context) : null;

        $sql = "INSERT INTO system_logs (log_level, message, context, ip_address, user_id) VALUES (?, ?, ?, ?, ?)";
        
        $params = [$level, $message, $context_json, $ip_address, $userId];

        $stmt = sqlsrv_query($this->conexao, $sql, $params);

        if ($stmt === false) {
            $sqlsrv_errors = sqlsrv_errors();
            $timestamp = date('Y-m-d H:i:s');
            $error_message_sqlsrv = "";
            if ($sqlsrv_errors) {
                foreach($sqlsrv_errors as $error) {
                    $error_message_sqlsrv .= "SQLSTATE: ".$error['SQLSTATE'].", Code: ".$error['code'].", Message: ".$error['message']."\n";
                }
            }
            error_log("{$timestamp} LogHelper: Falha ao executar statement de log no BD (SQLSRV). Nível: {$level}, Mensagem Original: {$message}, Erro SQLSRV: " . $error_message_sqlsrv . ", Contexto: " . json_encode($context));
        } else {
            sqlsrv_free_stmt($stmt);
        }
    }
}
