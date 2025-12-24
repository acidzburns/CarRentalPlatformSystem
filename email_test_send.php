<?php
require_once __DIR__ . '/config/email_test.php';
 // make sure this path points to your email.php file

// Test sending an email
$to = 'abdullahakmalhakim@gmail.com'; // <-- put your personal email here to receive the test
$subject = 'Test Email from Car Rental Platform';
$body = '<p>This is a test email to check if PHPMailer is working!</p>';

if (send_email($to, $subject, $body)) {
    echo "Email sent successfully!";
} else {
    echo "Failed to send email.";
}
?>
