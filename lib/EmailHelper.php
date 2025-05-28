<?php
// lib/EmailHelper.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Para carregar PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailHelper {

    /**
     * Envia um e-mail usando PHPMailer.
     *
     * @param string $to Destinatário do e-mail.
     * @param string $subject Assunto do e-mail.
     * @param string $message Corpo do e-mail (HTML).
     * @param string $altMessage Corpo alternativo em texto puro (opcional).
     * @return bool True se o e-mail foi enviado com sucesso, False caso contrário.
     */
    private static function sendConfiguredEmail($to, $subject, $message, $altMessage = '') {
        $mail = new PHPMailer(true); // true habilita exceções

        try {
            // Configurações do Servidor
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Habilite para debug detalhado
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = SMTP_AUTH;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            if (SMTP_SECURE !== false && SMTP_SECURE !== '') {
                $mail->SMTPSecure = SMTP_SECURE; // PHPMailer::ENCRYPTION_STARTTLS ou PHPMailer::ENCRYPTION_SMTPS
            }
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            // Remetente e Destinatários
            $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME); //
            $mail->addAddress($to);
            // $mail->addReplyTo('info@example.com', 'Information');
            // $mail->addCC('cc@example.com');
            // $mail->addBCC('bcc@example.com');

            // Conteúdo
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = !empty($altMessage) ? $altMessage : strip_tags($message);

            $mail->send();
            return true;
        } catch (Exception $e) {
            // Logar o erro em vez de apenas error_log simples
            // Se tiver um LogHelper global ou injetável, use-o aqui.
            // Por enquanto, error_log.
            error_log("EmailHelper (PHPMailer): Falha ao enviar e-mail para {$to} com assunto: {$subject}. Erro: {$mail->ErrorInfo}");
            return false;
        }
    }

    public static function sendPasswordResetEmail($to, $reset_token) {
        $subject = "Redefinição de Senha - Sim Posto";
        $reset_link = SITE_URL . "/resetar_senha.php?token=" . urlencode($reset_token) . "&email=" . urlencode($to); //
        $message_body = "
            <p>Olá,</p>
            <p>Você solicitou a redefinição de sua senha no sistema Sim Posto.</p>
            <p>Clique no link abaixo para criar uma nova senha:</p>
            <p><a href='{$reset_link}'>{$reset_link}</a></p>
            <p>Se você não solicitou isso, por favor ignore este e-mail.</p>
            <br>
            <p>Atenciosamente,<br>
            Equipe Sim Posto</p>
        ";
        $alt_message_body = "Olá,\n\nVocê solicitou a redefinição de sua senha no sistema Sim Posto.\nCopie e cole o seguinte link no seu navegador para criar uma nova senha:\n{$reset_link}\n\nSe você não solicitou isso, por favor ignore este e-mail.\n\nAtenciosamente,\nEquipe Sim Posto";
        
        return self::sendConfiguredEmail($to, $subject, $message_body, $alt_message_body);
    }

    public static function sendRegistrationConfirmationEmail($to, $nome_usuario) {
        $subject = "Bem-vindo ao Sim Posto!";
        $login_link = SITE_URL . "/index.html"; //
        $message_body = "
            <p>Olá {$nome_usuario},</p>
            <p>Seu cadastro no sistema Sim Posto foi realizado com sucesso!</p>
            <p>Você já pode acessar o sistema utilizando seu usuário e senha.</p>
            <p>Acesse em: <a href='{$login_link}'>{$login_link}</a></p>
            <br>
            <p>Atenciosamente,<br>
            Equipe Sim Posto</p>
        ";
        $alt_message_body = "Olá {$nome_usuario},\n\nSeu cadastro no sistema Sim Posto foi realizado com sucesso!\nVocê já pode acessar o sistema utilizando seu usuário e senha.\nAcesse em: {$login_link}\n\nAtenciosamente,\nEquipe Sim Posto";

        return self::sendConfiguredEmail($to, $subject, $message_body, $alt_message_body);
    }

    public static function notifyAdminNewUserRegistration($adminEmail, $novoUsuarioNome, $novoUsuarioEmail, $novoUsuarioLogin) {
        $subject = "Novo Usuário Cadastrado no Sim Posto";
        $message_body = "
            <p>Olá Administrador,</p>
            <p>Um novo usuário acaba de se registrar no sistema Sim Posto:</p>
            <ul>
                <li><strong>Nome Completo:</strong> {$novoUsuarioNome}</li>
                <li><strong>E-mail:</strong> {$novoUsuarioEmail}</li>
                <li><strong>Usuário (login):</strong> {$novoUsuarioLogin}</li>
            </ul>
            <p>Você pode gerenciar os usuários no painel de administração.</p>
            <br>
            <p>Atenciosamente,<br>
            Sistema Sim Posto</p>
        ";
        $alt_message_body = "Olá Administrador,\n\nUm novo usuário acaba de se registrar no sistema Sim Posto:\n- Nome Completo: {$novoUsuarioNome}\n- E-mail: {$novoUsuarioEmail}\n- Usuário (login): {$novoUsuarioLogin}\n\nVocê pode gerenciar os usuários no painel de administração.\n\nAtenciosamente,\nSistema Sim Posto";
        
        return self::sendConfiguredEmail($adminEmail, $subject, $message_body, $alt_message_body);
    }
}