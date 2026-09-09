<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;


/* =========================================================
   PHPMailer
   ========================================================= */

require_once dirname(__DIR__, 2) . '/private/phpmailer/Exception.php';
require_once dirname(__DIR__, 2) . '/private/phpmailer/PHPMailer.php';
require_once dirname(__DIR__, 2) . '/private/phpmailer/SMTP.php';


/* =========================================================
   MAIL CONFIG
   ========================================================= */

function llama_mail_config(): array
{
    $path =
        dirname(__DIR__, 2)
        . '/private/mail.php';

    if (!is_file($path)) {
        throw new RuntimeException(
            'Llama Scout mail configuration is missing.'
        );
    }

    $config = require $path;

    if (!is_array($config)) {
        throw new RuntimeException(
            'Llama Scout mail configuration is invalid.'
        );
    }

    return $config;
}


/* =========================================================
   SMTP TRANSPORT
   ========================================================= */

function send_llama_mail(
    string $to,
    string $subject,
    string $message,
    ?string $html = null
): bool {
    $config = llama_mail_config();

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();

        $mail->Host =
            (string) $config['host'];

        $mail->Port =
            (int) $config['port'];

        $mail->SMTPAuth = true;

        $mail->Username =
            (string) $config['username'];

        $mail->Password =
            (string) $config['password'];

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->setFrom(
            (string) $config['from_email'],
            (string) $config['from_name']
        );

        $mail->addReplyTo(
            (string) $config['from_email'],
            (string) $config['from_name']
        );

        $mail->addAddress($to);

        $mail->Subject = $subject;

        if ($html !== null) {
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $message;
        } else {
            $mail->isHTML(false);
            $mail->Body = $message;
        }

        $mail->send();

        return true;

    } catch (Exception $error) {
        error_log(
            'Llama Scout mail error: '
            . $mail->ErrorInfo
        );

        return false;
    }
}


/*
 * Existing account code already includes app/mail.php before
 * calling send_verification_email(). Keep those public function
 * names available while moving all template/event logic out of
 * this transport file.
 */
require_once __DIR__ . '/email/events.php';
