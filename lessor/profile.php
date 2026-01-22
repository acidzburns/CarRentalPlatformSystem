<?php
/**
 * Lessor Profile - Edit Profile Information
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is logged in as lessor
check_authentication('lessor');

$lessor_id = get_user_id();
$lessor_name = get_user_name();
$error = '';
$success = '';

// Get current lessor information
$sql = "SELECT * FROM LESSOR WHERE LESSORID = :lessorid";
$lessor = db_fetch_one($sql, ['lessorid' => $lessor_id]);

if (!$lessor) {
    header('Location: dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $business_name = trim($_POST['business_name']);
    $bank_account = trim($_POST['bank_account']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || empty($phone)) {
        $error = "Name, email, and phone are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email is already taken by another lessor
        $check_email = "SELECT LESSORID FROM LESSOR WHERE EMAIL = :email AND LESSORID != :lessorid";
        $existing = db_fetch_one($check_email, ['email' => $email, 'lessorid' => $lessor_id]);
        
        if ($existing) {
            $error = "This email is already in use by another account.";
        } else {
            $update_success = true;
            
            // Update basic information
            $update_sql = "UPDATE LESSOR 
                          SET NAME = :name, 
                              EMAIL = :email, 
                              PHONE = :phone, 
                              BUSINESS_NAME = :business_name, 
                              BANK_ACCOUNT = :bank_account 
                          WHERE LESSORID = :lessorid";
            
            $params = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'business_name' => $business_name,
                'bank_account' => $bank_account,
                'lessorid' => $lessor_id
            ];
            
            if (!db_execute($update_sql, $params)) {
                $update_success = false;
                $error = "Failed to update profile information.";
            }
            
            // If password change is requested
            if ($update_success && !empty($new_password)) {
                if (empty($current_password)) {
                    $error = "Please enter your current password to change it.";
                    $update_success = false;
                } elseif (!password_verify($current_password, $lessor['PASSWORD'])) {
                    $error = "Current password is incorrect.";
                    $update_success = false;
                } elseif ($new_password !== $confirm_password) {
                    $error = "New passwords do not match.";
                    $update_success = false;
                } elseif (strlen($new_password) < 6) {
                    $error = "New password must be at least 6 characters.";
                    $update_success = false;
                } else {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                    $password_sql = "UPDATE LESSOR SET PASSWORD = :password WHERE LESSORID = :lessorid";
                    
                    if (!db_execute($password_sql, ['password' => $hashed_password, 'lessorid' => $lessor_id])) {
                        $error = "Failed to update password.";
                        $update_success = false;
                    }
                }
            }
            
            if ($update_success) {
                $success = "Profile updated successfully!";
                // Update session name and email if changed
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $lessor_name = $name;
                
                // Refresh lessor data
                $lessor = db_fetch_one($sql, ['lessorid' => $lessor_id]);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Car Rental</title>
    <link href="../assets/css/lessor-dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #eee;
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .profile-header h2 {
            color: #1e293b;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .profile-header p {
            color: #64748b;
            font-size: 14px;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            color: #1e293b;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1864FF;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #1864FF;
            box-shadow: 0 0 0 3px rgba(24, 100, 255, 0.1);
        }
        
        .form-group input:disabled {
            background-color: #f1f5f9;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #1864FF;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056e0;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #475569;
        }
        
        .verification-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .badge-verified {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .info-text {
            font-size: 13px;
            color: #64748b;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <nav>
        <div class="nav-container">
            <h2>Car Rental System <span style="font-size:14px; opacity:0.8; font-weight:400;">| Lessor Panel</span></h2>
            <div class="nav-links">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($lessor_name); ?>!</span>
                <a href="dashboard.php">Dashboard</a>
                <a href="my_cars.php">My Cars</a>
                <a href="bookings.php">Bookings</a>
                <a href="earnings.php">Earnings</a>
                <a href="profile.php" class="active">Profile</a>
                <a href="submit_ticket.php">Support</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>
    
    <main>
        <div class="hero">
            <h1>My Profile</h1>
            <p>Manage your personal information and account settings</p>
        </div>
        
        <div class="profile-container">
            <div class="profile-card">
                <div class="profile-header">
                    <h2><?php echo htmlspecialchars($lessor['NAME']); ?></h2>
                    <p><?php echo htmlspecialchars($lessor['EMAIL']); ?></p>
                    <span class="verification-badge badge-<?php echo strtolower($lessor['VERIFICATION_STATUS']); ?>">
                        <?php echo htmlspecialchars($lessor['VERIFICATION_STATUS']); ?>
                    </span>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-section">
                        <h3>Personal Information</h3>
                        
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($lessor['NAME']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($lessor['EMAIL']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($lessor['PHONE'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Business Information</h3>
                        
                        <div class="form-group">
                            <label for="business_name">Business Name</label>
                            <input type="text" id="business_name" name="business_name" value="<?php echo htmlspecialchars($lessor['BUSINESS_NAME'] ?? ''); ?>">
                            <p class="info-text">Optional - Your business or company name</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="bank_account">Bank Account Number</label>
                            <input type="text" id="bank_account" name="bank_account" value="<?php echo htmlspecialchars($lessor['BANK_ACCOUNT'] ?? ''); ?>">
                            <p class="info-text">For receiving payments from completed bookings</p>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Change Password</h3>
                        <p class="info-text" style="margin-bottom: 15px;">Leave blank if you don't want to change your password</p>
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" autocomplete="current-password">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" autocomplete="new-password">
                            <p class="info-text">Minimum 6 characters</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">
                        </div>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>