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
    <title>Account Suspended - Car Rental System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        /* Reset and Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }

        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            padding: 1rem 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-logo {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-logo:hover {
            opacity: 0.9;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
            padding: 0.5rem 1rem;
            border-radius: 6px;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
        }

        .container {
            background: white;
            padding: 3rem;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            max-width: 700px;
            width: 100%;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Icon Section */
        .icon-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border-radius: 50%;
            margin-bottom: 1.5rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .icon-wrapper i {
            font-size: 50px;
            color: #dc2626;
        }

        h1 {
            color: #dc2626;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            font-size: 1.125rem;
            color: #64748b;
            margin-bottom: 2rem;
        }

        /* Info Boxes */
        .info-box {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #f59e0b;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.1);
        }

        .contact-box {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #2563eb;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);
        }

        .info-box h3,
        .contact-box h3 {
            margin: 0 0 1rem 0;
            color: #1e293b;
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-box h3 i {
            color: #f59e0b;
        }

        .contact-box h3 i {
            color: #2563eb;
        }

        .info-box ul,
        .contact-box ul {
            margin: 1rem 0;
            padding-left: 1.5rem;
        }

        .info-box li,
        .contact-box li {
            margin: 0.75rem 0;
            color: #475569;
            line-height: 1.6;
        }

        .info-box p,
        .contact-box p {
            color: #475569;
            margin: 0.75rem 0;
            line-height: 1.6;
        }

        .info-box strong,
        .contact-box strong {
            color: #1e293b;
        }

        .email-highlight {
            background: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-weight: 600;
            color: #2563eb;
            border: 2px solid #e0e7ff;
            text-align: center;
        }

        /* Support Banner */
        .support-banner {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            text-align: center;
            border: 2px solid #cbd5e1;
        }

        .support-banner p {
            margin: 0.5rem 0;
            color: #475569;
            line-height: 1.6;
        }

        .support-banner strong {
            color: #1e293b;
            font-size: 1.125rem;
        }

        /* Contact Links */
        .contact-links {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
            flex-wrap: wrap;
        }

        .contact-link {
            flex: 1;
            min-width: 200px;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            color: #1e293b;
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
        }

        .contact-link:hover {
            border-color: #2563eb;
            background: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }

        .contact-link i {
            display: block;
            font-size: 24px;
            color: #2563eb;
            margin-bottom: 0.5rem;
        }

        .contact-link strong {
            display: block;
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }

        .contact-link span {
            font-weight: 600;
            color: #1e293b;
        }

        /* Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            flex: 1;
            min-width: 180px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #10b981;
            border: 2px solid #10b981;
        }

        .btn-secondary:hover {
            background: #10b981;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        /* Footer */
        .footer {
            background: #1e293b;
            color: white;
            text-align: center;
            padding: 2rem;
            margin-top: auto;
        }

        .footer p {
            margin: 0;
            opacity: 0.8;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }

            h1 {
                font-size: 1.75rem;
            }

            .nav-links {
                display: none;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .contact-links {
                flex-direction: column;
            }

            .contact-link {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="nav-logo">
                <i class="fas fa-car"></i>
                Car Rental System
            </a>
            <div class="nav-links">
                <a href="../index.php"><i class="fas fa-home"></i> Home</a>
                <a href="../index.php#about"><i class="fas fa-info-circle"></i> About</a>
                <a href="../index.php#contact"><i class="fas fa-envelope"></i> Contact</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <div class="icon-section">
                <div class="icon-wrapper">
                    <i class="fas fa-ban"></i>
                </div>
                <h1>Account Suspended</h1>
                <p class="subtitle">
                    We're sorry, but your account has been temporarily suspended.
                </p>
            </div>

            <div class="info-box">
                <h3><i class="fas fa-exclamation-triangle"></i> What This Means</h3>
                <ul>
                    <li><strong>Access Restricted:</strong> Your account access has been temporarily limited</li>
                    <li><strong>Policy Compliance:</strong> This action was taken due to policy violations or safety concerns</li>
                    <li><strong>Login Disabled:</strong> You cannot login until the suspension is lifted</li>
                    <li><strong>Data Protected:</strong> Your account data is safe and preserved</li>
                </ul>
            </div>

            <div class="contact-box">
                <h3><i class="fas fa-headset"></i> How to Resolve This</h3>
                <p><strong>To reactivate your account, please contact our support team:</strong></p>

                <div class="contact-links">
                    <a href="mailto:carrentalplatformsystem@gmail.com" class="contact-link">
                        <i class="fas fa-envelope"></i>
                        <strong>Email Us</strong>
                        <span>carrentalplatformsystem@gmail.com</span>
                    </a>
                    <a href="tel:1-800-800-999" class="contact-link">
                        <i class="fas fa-phone"></i>
                        <strong>Call Us</strong>
                        <span>1-800-800-999</span>
                    </a>
                </div>

                <p style="margin-top: 1rem;"><strong>Business Hours:</strong> Monday - Friday, 9:00 AM - 6:00 PM</p>

                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid rgba(37, 99, 235, 0.2);">
                    <p><strong>📋 Include in your message:</strong></p>
                    <ul>
                        <li>Your full name</li>
                        <li>Brief explanation of your situation</li>
                    </ul>
                    <div class="email-highlight">
                        <i class="fas fa-user"></i> Your Email: <?php echo htmlspecialchars($user_email); ?>
                    </div>
                </div>
            </div>

            <div class="support-banner">
                <p><strong><i class="fas fa-clock"></i> Response Time</strong></p>
                <p>
                    Our support team typically responds within <strong>24-48 hours</strong>.<br>
                    We'll review your case thoroughly and work with you to resolve any issues.
                </p>
            </div>

            <div class="action-buttons">
                <a href="../auth/lessor_login.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
                <a href="../index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Go to Homepage
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2024 Car Rental Platform System. All rights reserved.</p>
    </footer>
</body>
</html>