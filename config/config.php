<?php
// config/config.php

if (session_status() == PHP_SESSION_NONE) {
    $cookieParams = [
        'lifetime' => 0, 
        'path' => '/',   
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', 
        'httponly' => true, 
        'samesite' => 'Lax' 
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

// --- Caminho Base do Projeto na Web ---
// Defina como '/nome_da_pasta_do_projeto' se estiver em uma subpasta do DocumentRoot.
// Defina como '' (string vazia) se o projeto estiver na raiz do DocumentRoot.
// Para o seu caso, como o referer é http://localhost/turno/, o caminho é '/turno'.
define('BASE_PROJECT_WEB_PATH', '/turno'); // <<< VERIFIQUE E AJUSTE SE NECESSÁRIO

// --- URLs do Site ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST']; // Ex: localhost

// SITE_URL: Usada para redirecionamentos PHP e lógica de backend.
// Garante que não termine com barra, a menos que seja a raiz do servidor (BASE_PROJECT_WEB_PATH vazio)
define('SITE_URL', rtrim($protocol . $host . BASE_PROJECT_WEB_PATH, '/')); 
                                                              
// --- Configurações de E-mail (Remetente Padrão) ---
define('EMAIL_FROM_ADDRESS', 'postosim8@gmail.com'); //
define('EMAIL_FROM_NAME', 'Sim Posto Sistema'); //

// --- Configurações de E-mail (PHPMailer - SMTP) ---
// **IMPORTANTE: Substitua pelos seus dados reais de SMTP**
define('SMTP_HOST', 'smtp.gmail.com');             // Ex: 'smtp.gmail.com' ou o host do seu provedor
define('SMTP_USERNAME', 'luisdalmora@gmail.com');  // Seu endereço de e-mail SMTP completo
define('SMTP_PASSWORD', 'nqatdfouxhadztgp'); // Sua senha SMTP ou senha de aplicativo (para Gmail, use senha de app)
define('SMTP_PORT', 587);                             // Porta SMTP: 587 para TLS (comum), 465 para SSL
define('SMTP_SECURE', 'tls');                         // Tipo de segurança: 'tls' (PHPMailer::ENCRYPTION_STARTTLS) ou 'ssl' (PHPMailer::ENCRYPTION_SMTPS) ou false
define('SMTP_AUTH', true);                            // Requer autenticação SMTP? (geralmente true)

// E-mail do administrador para receber notificações de novos cadastros
define('ADMIN_EMAIL_NOTIFICATIONS', 'postosim8@gmail.com'); // Substitua pelo e-mail do administrador


define('GOOGLE_APPLICATION_NAME', 'Sim Posto Gestao de Turnos'); //
// Adicione sua Chave de API do Google Cloud Console aqui.
// Certifique-se de que a API do Google Calendar está habilitada para esta chave.
define('GOOGLE_API_KEY', 'AIzaSyC3zC01xyp9BIKssCp_EFmFzceKKFdaFro'); // Substitua pela sua chave de API

// --- Configurações de Erro ---
// Para desenvolvimento:
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT); //

// Para produção, comente as linhas acima e descomente as abaixo:

ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Certifique-se de que o diretório e o arquivo de log são graváveis pelo servidor web.
ini_set('error_log', __DIR__ . '/../../php_errors.log'); // Ajuste o caminho se necessário (ex: fora da pasta web)
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// --- Configuração de Backup SQL Server ---
// Caminho no SERVIDOR SQL onde os arquivos .bak serão salvos.
// O serviço do SQL Server DEVE ter permissão de escrita neste diretório.
// Use barras invertidas duplas (\\) ou barras normais (/) para caminhos no Windows.
define('SQL_SERVER_BACKUP_PATH', 'C:\\Backup_SQL_Server\\'); // Exemplo para Windows
// define('SQL_SERVER_BACKUP_PATH', '/var/opt/mssql/backup/'); // Exemplo para Linux