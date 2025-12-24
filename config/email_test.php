<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php'; // Make sure PHPMailer is installed via Composer

function send_email($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';         // Replace with your SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username   = 'carrentalplatformsystem@gmail.com';   // Your email
        $mail->Password   = 'ggme cyhd unfb epjd';      // App password or SMTP password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // Email sender & recipient
        $mail->setFrom('carrentalplatformsystem@gmail.com', 'Car Rental Platform');
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
