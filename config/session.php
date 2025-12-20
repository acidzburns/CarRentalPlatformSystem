<?php
/**
 * Session Management
 * Handles user sessions for authentication
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Set session data after successful login
 * 
 * @param int $user_id User ID
 * @param string $name User's name
 * @param string $email User's email
 * @param string $user_type Type: 'renter', 'lessor', or 'admin'
 */
function set_session($user_id, $name, $email, $user_type) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_type'] = $user_type;
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Redirect if user is already logged in
 * Used on login/register pages to prevent logged-in users from accessing them
 * Note: Only define if not already defined in check_auth.php
 */
if (!function_exists('redirect_if_logged_in')) {
    function redirect_if_logged_in() {
        if (is_logged_in()) {
            $user_type = get_user_type();
            
            // Redirect based on user type
            switch ($user_type) {
                case 'renter':
                    header('Location: ../renter/dashboard.php');
                    exit();
                case 'lessor':
                    header('Location: ../lessor/dashboard.php');
                    exit();
                case 'admin':
                    header('Location: ../admin/dashboard.php');
                    exit();
                default:
                    header('Location: ../index.php');
                    exit();
            }
        }
    }
}

/**
 * Sanitize user input to prevent XSS attacks
 * Note: Only define if not already defined in database.php
 * 
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

/**
 * Get current user ID
 * 
 * @return int|null User ID or null if not logged in
 */
function get_user_id() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Get current user name
 * 
 * @return string|null User name or null if not logged in
 */
function get_user_name() {
    return isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;
}

/**
 * Get current user email
 * 
 * @return string|null User email or null if not logged in
 */
function get_user_email() {
    return isset($_SESSION['user_email']) ? $_SESSION['user_email'] : null;
}

/**
 * Get current user type
 * 
 * @return string|null 'renter', 'lessor', 'admin' or null if not logged in
 */
function get_user_type() {
    return isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;
}

/**
 * Check if current user is a renter
 * 
 * @return bool
 */
function is_renter() {
    return get_user_type() === 'renter';
}

/**
 * Check if current user is a lessor
 * 
 * @return bool
 */
function is_lessor() {
    return get_user_type() === 'lessor';
}

/**
 * Check if current user is an admin
 * 
 * @return bool
 */
function is_admin() {
    return get_user_type() === 'admin';
}

/**
 * Destroy session and logout user
 */
function logout() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Redirect to login page if not logged in
 * 
 * @param string $user_type Required user type (optional)
 */
function require_login($user_type = null) {
    if (!is_logged_in()) {
        header('Location: ../auth/renter_login.php');
        exit();
    }
    
    // Check if specific user type is required
    if ($user_type && get_user_type() !== $user_type) {
        header('Location: ../index.php');
        exit();
    }
}

/**
 * Set flash message (one-time display message)
 * 
 * @param string $message Message text
 * @param string $type Message type: 'success', 'error', 'warning', 'info'
 */
function set_flash_message($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get and clear flash message
 * 
 * @return array|null Array with 'message' and 'type' or null
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type']
        ];
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return $message;
    }
    
    return null;
}

/**
 * Display flash message HTML
 */
function display_flash_message() {
    $flash = get_flash_message();
    
    if ($flash) {
        $type_colors = [
            'success' => 'green',
            'error' => 'red',
            'warning' => 'orange',
            'info' => 'blue'
        ];
        
        $color = isset($type_colors[$flash['type']]) ? $type_colors[$flash['type']] : 'gray';
        
        echo '<div style="padding: 10px; margin: 10px 0; background-color: ' . $color . '; color: white; border-radius: 5px;">';
        echo htmlspecialchars($flash['message']);
        echo '</div>';
    }
}

?>