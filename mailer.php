<?php
// mailer.php - simple wrapper using PHPMailer
// Requires composer dependency: phpmailer/phpmailer

require_once __DIR__ . '/mail_config.php';

function send_mail($to, $subject, $body, $altBody = null) {
    // attempt to autoload PHPMailer
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        error_log('PHPMailer autoload not found. Run: composer require phpmailer/phpmailer');
        return false;
    }
    require_once __DIR__ . '/vendor/autoload.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        // If no username provided, use unauthenticated SMTP
        if (!empty(SMTP_USER)) {
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
        } else {
            $mail->SMTPAuth = false;
        }
        if (!empty(SMTP_SECURE)) $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(MAIL_FROM, defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : '');
        $mail->addAddress($to);

        $mail->isHTML(MAIL_IS_HTML);
        $mail->Subject = $subject;
        $mail->Body = $body;
        if ($altBody) $mail->AltBody = $altBody;

        $sent = $mail->send();
        if ($sent) return true;

        // If PHPMailer failed (for example local MTA refused), fall back to PHP mail()
        error_log('PHPMailer send failed: ' . $mail->ErrorInfo);
        $headers = 'From: ' . MAIL_FROM . "\r\n";
        if (MAIL_IS_HTML) $headers .= 'MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n';
        return @mail($to, $subject, $altBody ? $altBody : $body, $headers);
    } catch (Exception $e) {
        error_log('Mailer exception: ' . $e->getMessage());
        $headers = 'From: ' . MAIL_FROM . "\r\n";
        if (MAIL_IS_HTML) $headers .= 'MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n';
        return @mail($to, $subject, $altBody ? $altBody : $body, $headers);
    }
}
