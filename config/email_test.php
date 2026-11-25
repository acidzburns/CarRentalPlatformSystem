<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

function send_email($to, $subject, $body) {
    // Passwords live in config/secrets.php (not on GitHub). See secrets.example.php.
    $secrets = require __DIR__ . '/secrets.php';

    $mail = new PHPMailer(true);

    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = $secrets['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $secrets['smtp_username'];
        $mail->Password   = $secrets['smtp_password'];
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // Email sender & recipient
        $mail->setFrom($secrets['mail_from'], $secrets['mail_from_name']);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email sending failed: ' . $mail->ErrorInfo);
        return false;
    }
}
?>