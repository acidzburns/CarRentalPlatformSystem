<?php
/**
 * Authentication Checker
 * Include this file at the top of protected pages
 * 
 * Usage:
 * require_once '../includes/check_auth.php';
 * check_authentication('renter'); // or 'lessor' or 'admin'
 */

// Include session management
require_once __DIR__ . '/../config/session.php';

/**
 * Check if user is authenticated and has correct user type
 * Redirects to login if not authenticated or wrong user type
 * 
 * @param string $required_type Required user type: 'renter', 'lessor', or 'admin'
 */
if (!function_exists('check_authentication')) {
    function check_authentication($required_type = null) {
        // Check if user is logged in
        if (!is_logged_in()) {
            // Store the current page to redirect back after login
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            
            // Redirect to appropriate login page
            if ($required_type === 'admin') {
                header('Location: ../auth/admin_login.php');
            } elseif ($required_type === 'lessor') {
                header('Location: ../auth/lessor_login.php');
            } else {
                header('Location: ../auth/renter_login.php');
            }
            exit();
        }
        
        // Check if user has the correct user type
        if ($required_type && get_user_type() !== $required_type) {
            // Redirect to appropriate dashboard
            $user_type = get_user_type();
            
            switch ($user_type) {
                case 'renter':
                    header('Location: ../renter/dashboard.php');
                    break;
                case 'lessor':
                    header('Location: ../lessor/dashboard.php');
                    break;
                case 'admin':
                    header('Location: ../admin/dashboard.php');
                    break;
                default:
                    header('Location: ../index.php');
            }
            exit();
        }
    }
}

/**
 * Redirect to appropriate dashboard based on user type
 */
if (!function_exists('redirect_to_dashboard')) {
    function redirect_to_dashboard() {
        $user_type = get_user_type();
        
        switch ($user_type) {
            case 'renter':
                header('Location: ../renter/dashboard.php');
                break;
            case 'lessor':
                header('Location: ../lessor/dashboard.php');
                break;
            case 'admin':
                header('Location: ../admin/dashboard.php');
                break;
            default:
                header('Location: ../index.php');
        }
        exit();
    }
}

/**
 * Check if user is already logged in and redirect to dashboard
 * Use this on login/register pages
 * Note: Only define if not already defined in session.php
 */
if (!function_exists('redirect_if_logged_in')) {
    function redirect_if_logged_in() {
        if (is_logged_in()) {
            redirect_to_dashboard();
        }
    }
}

?>