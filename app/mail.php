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


/*
 * Master delivery switch.
 *
 * Add `'enabled' => false` to private/mail.php when SMTP is
 * intentionally unavailable. Mail workers will pause without
 * consuming queued work or filling the application error log.
 *
 * Existing installations that do not define the key remain enabled.
 */
function llama_mail_delivery_enabled(
    ?array $config = null
): bool {
    $config =
        $config
        ?? llama_mail_config();

    if (!array_key_exists('enabled', $config)) {
        return true;
    }

    $value = $config['enabled'];

    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return (int) $value !== 0;
    }

    $normalized =
        strtolower(
            trim((string) $value)
        );

    return !in_array(
        $normalized,
        [
            '',
            '0',
            'false',
            'off',
            'no',
            'disabled',
            'paused',
        ],
        true
    );
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

    if (!llama_mail_delivery_enabled($config)) {
        return false;
    }

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
