<?php
/**
 * Account Suspended Notice Page
 * Shown to suspended users when they try to login
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Get user info from session or URL
$user_email = isset($_GET['email']) ? $_GET['email'] : 'your email';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Suspended</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-width: 600px;
            text-align: center;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            color: #d32f2f;
            margin: 0 0 20px 0;
        }
        .info-box {
            background: #fff3cd;
            padding: 20px;
            border-left: 4px solid #ffc107;
            text-align: left;
            margin: 20px 0;
        }
        .contact-box {
            background: #e3f2fd;
            padding: 20px;
            border-left: 4px solid #2196F3;
            text-align: left;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px;
            font-weight: bold;
        }
        .btn:hover {
            background: #1976D2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🚫</div>
        <h1>Account Suspended</h1>
        <p style="font-size: 18px; color: #666;">
            We're sorry, but your account has been temporarily suspended.
        </p>
        
        <div class="info-box">
            <h3 style="margin-top: 0;">⚠️ What This Means</h3>
            <ul style="text-align: left;">
                <li>Your account access has been temporarily restricted</li>
                <li>This action was taken due to policy violations or safety concerns</li>
                <li>You cannot login until the suspension is lifted</li>
                <li>Your account data is safe and preserved</li>
            </ul>
        </div>
        
        <div class="contact-box">
            <h3 style="margin-top: 0;">📞 How to Resolve This</h3>
            <p><strong>To reactivate your account, please contact our support team:</strong></p>
            <ul style="text-align: left;">
                <li><strong>Email:</strong> <a href="mailto:support@carrental.com">support@carrental.com</a></li>
                <li><strong>Phone:</strong> 1-800-RENTAL (738-8259)</li>
                <li><strong>Hours:</strong> Monday - Friday, 9:00 AM - 6:00 PM</li>
            </ul>
            <p><strong>Include in your message:</strong></p>
            <ul style="text-align: left;">
                <li>Your registered email: <strong><?php echo htmlspecialchars($user_email); ?></strong></li>
                <li>Your full name</li>
                <li>Brief explanation of your situation</li>
            </ul>
        </div>
        
        <div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 0; font-size: 14px; color: #666;">
                <strong>Need immediate assistance?</strong><br>
                Our support team typically responds within 24-48 hours.<br>
                We'll review your case and work with you to resolve any issues.
            </p>
        </div>
        
        <a href="../auth/lessor_login.php" class="btn">← Back to Login</a>
        <a href="../index.php" class="btn" style="background: #4CAF50;">Go to Homepage</a>
    </div>
</body>
</html>