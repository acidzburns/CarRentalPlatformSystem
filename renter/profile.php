<?php
/**
 * Renter Profile Page
 * Allow renters to view and edit their profile information
 */
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is logged in as renter
check_authentication('renter');

// Get user information
$user_id = get_user_id();
$success_message = '';
$error_message = '';

// Fetch current user data
$sql = "SELECT NAME, EMAIL, PHONE FROM RENTER WHERE RENTERID = :renterid";
$user_data = db_fetch_one($sql, ['renterid' => $user_id]);

if (!$user_data) {
    header("Location: ../auth/logout.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || empty($phone)) {
        $error_message = "Name, email, and phone are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        // Check if email is already taken by another user
        $check_email = "SELECT RENTERID FROM RENTER WHERE EMAIL = :email AND RENTERID != :renterid";
        $existing = db_fetch_one($check_email, ['email' => $email, 'renterid' => $user_id]);
        
        if ($existing) {
            $error_message = "Email is already in use by another account.";
        } else {
            // Update basic information
            $update_sql = "UPDATE RENTER SET NAME = :name, EMAIL = :email, PHONE = :phone WHERE RENTERID = :renterid";
            $params = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'renterid' => $user_id
            ];
            
            if (db_execute($update_sql, $params)) {
                // Update session data
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                
                // Handle password change if provided
                if (!empty($current_password) && !empty($new_password)) {
                    if ($new_password !== $confirm_password) {
                        $error_message = "Profile updated, but new passwords do not match.";
                    } elseif (strlen($new_password) < 6) {
                        $error_message = "Profile updated, but new password must be at least 6 characters.";
                    } else {
                        // Verify current password
                        $verify_sql = "SELECT PASSWORD FROM RENTER WHERE RENTERID = :renterid";
                        $pass_data = db_fetch_one($verify_sql, ['renterid' => $user_id]);
                        
                        if ($pass_data && password_verify($current_password, $pass_data['PASSWORD'])) {
                            // Update password
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $pass_update = "UPDATE RENTER SET PASSWORD = :password WHERE RENTERID = :renterid";
                            
                            if (db_execute($pass_update, ['password' => $hashed_password, 'renterid' => $user_id])) {
                                $success_message = "Profile and password updated successfully!";
                            } else {
                                $error_message = "Profile updated, but failed to update password.";
                            }
                        } else {
                            $error_message = "Profile updated, but current password is incorrect.";
                        }
                    }
                } else {
                    $success_message = "Profile updated successfully!";
                }
                
                // Refresh user data
                $user_data = db_fetch_one($sql, ['renterid' => $user_id]);
            } else {
                $error_message = "Failed to update profile. Please try again.";
            }
        }
    }
}

$user_name = get_user_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Car Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/renter-dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="../assets/css/renter-profile.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <nav>
        <div class="nav-container">
            <h2><a href="dashboard.php">Car Rental System</a></h2>
            <div class="nav-links">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                <a href="dashboard.php">Dashboard</a>
                <a href="search_cars.php">Search Cars</a>
                <a href="booking_history.php">My Bookings</a>
                <a href="profile.php" class="active">Profile</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>
    
    <main>
        <header class="page-header">
            <h1>My Profile</h1>
            <p>Manage your account information and settings</p>
        </header>
        
        <div class="container">
            <div class="profile-card">
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="profile.php" class="profile-form">
                    <div class="form-section">
                        <h2 class="section-heading">
                            <i class="fas fa-user"></i> Personal Information
                        </h2>
                        
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($user_data['NAME']); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user_data['EMAIL']); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user_data['PHONE'] ?? ''); ?>" 
                                   placeholder="0123456789">
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2 class="section-heading">
                            <i class="fas fa-lock"></i> Change Password
                        </h2>
                        <p class="section-description">Leave blank if you don't want to change your password</p>
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" 
                                   placeholder="Enter current password">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" 
                                   placeholder="Enter new password (min. 6 characters)">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   placeholder="Re-enter new password">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="dashboard.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>