<?php
// config/config.php

if (session_status() == PHP_SESSION_NONE) {
    $cookieParams = [
        'lifetime' => 0, // Cookie de sessão, expira quando o navegador é fechado
        'path' => '/',   // Disponível em todo o domínio
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // True se HTTPS
        'httponly' => true, // Acessível apenas via HTTP, não por JavaScript
        'samesite' => 'Lax' // Proteção CSRF
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

// --- Configurações do Google API ---
// Constantes OAuth2 não são mais necessárias para o escopo atual (apenas feriados públicos)
// define('GOOGLE_CLIENT_ID', 'SEU_CLIENT_ID.apps.googleusercontent.com');
// define('GOOGLE_CLIENT_SECRET', 'SEU_CLIENT_SECRET');
// define('GOOGLE_REDIRECT_URI', 'http://localhost/turno/google_oauth_callback.php');
// PATH_TO_CLIENT_SECRET_JSON pode não ser mais necessário se usar apenas API Key.
// define('PATH_TO_CLIENT_SECRET_JSON', __DIR__ . '/client_secret.json'); //

define('GOOGLE_APPLICATION_NAME', 'Sim Posto Gestao de Turnos'); //
// Adicione sua Chave de API do Google Cloud Console aqui.
// Certifique-se de que a API do Google Calendar está habilitada para esta chave.
define('GOOGLE_API_KEY', 'AIzaSyC3zC01xyp9BIKssCp_EFmFzceKKFdaFro'); // Substitua pela sua chave de API

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

// --- Configurações Gerais ---
// Determina a URL base do site dinamicamente
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
// O dirname($_SERVER['SCRIPT_NAME']) pode ser problemático se config.php for incluído de subpastas profundas.
// Uma abordagem mais robusta é definir o caminho do projeto explicitamente ou calculá-lo de forma mais consistente.
// Para este projeto, o dirname(__DIR__) refere-se à pasta 'config', então precisamos subir um nível.
$project_base_path = dirname(dirname($_SERVER['SCRIPT_NAME'])); // Sobe um nível da pasta 'config'
if ($project_base_path === '/' || $project_base_path === '\\') {
    $project_base_path = ''; // Evita barras duplicadas se o projeto estiver na raiz do servidor web
}
define('SITE_URL', rtrim($protocol . $host . $project_base_path, '/'));


// --- Configurações de Erro ---
// Para desenvolvimento:
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT); //

// Para produção, comente as linhas acima e descomente as abaixo:
/*
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Certifique-se de que o diretório e o arquivo de log são graváveis pelo servidor web.
ini_set('error_log', __DIR__ . '/../../php_errors.log'); // Ajuste o caminho se necessário (ex: fora da pasta web)
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
*/
?>