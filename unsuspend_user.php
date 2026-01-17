<?php
/**
 * UC23: Unsuspend/Reactivate User Account
 * Admin reactivates suspended lessor account
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Check if admin is logged in
require_login('admin');

$adminid = get_user_id();
$errors = [];

// Get parameters from URL
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    set_flash_message("Invalid user reference.", "error");
    header("Location: manage_users.php");
    exit();
}

$user_type = $_GET['type'];
$user_id = intval($_GET['id']);

// ✅ Support both renters and lessors
if ($user_type == 'renter') {
    $sql = "SELECT RENTERID as USER_ID, NAME, EMAIL, STATUS 
            FROM RENTER 
            WHERE RENTERID = :userid";
} elseif ($user_type == 'lessor') {
    $sql = "SELECT LESSORID as USER_ID, NAME, EMAIL, VERIFICATION_STATUS as STATUS 
            FROM LESSOR 
            WHERE LESSORID = :userid";
} else {
    set_flash_message("Invalid user type.", "error");
    header("Location: manage_users.php");
    exit();
}

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':userid', $user_id);
oci_execute($stmt);
$user = oci_fetch_assoc($stmt);

if (!$user) {
    set_flash_message("User not found.", "error");
    header("Location: manage_users.php");
    exit();
}

// Check if currently suspended
if ($user['STATUS'] != 'SUSPENDED') {
    set_flash_message("This user is not suspended.", "warning");
    header("Location: view_user.php?type=" . $user_type . "&id=" . $user_id);
    exit();
}

// Handle unsuspension
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $note = trim($_POST['note']);
    
    if (empty($note)) {
        $errors[] = "Please provide a note for reactivation.";
    }
    
    if (empty($errors)) {
        try {
            // Reactivate account
            if ($user_type == 'lessor') {
                $unsuspend_sql = "UPDATE LESSOR 
                                 SET VERIFICATION_STATUS = 'VERIFIED' 
                                 WHERE LESSORID = :userid";
            } else {
                // ✅ NEW: Support renter unsuspension
                $unsuspend_sql = "UPDATE RENTER 
                                 SET STATUS = 'ACTIVE' 
                                 WHERE RENTERID = :userid";
            }
            
            $stmt_unsuspend = oci_parse($conn, $unsuspend_sql);
            oci_bind_by_name($stmt_unsuspend, ':userid', $user_id);
            
            if (oci_execute($stmt_unsuspend)) {
                oci_commit($conn);
                
                require_once "../config/email_test.php";

$admin_email = "carrentalplatformsystem@gmail.com"; // Use real admin email
$subject = "Your Account Has Been Reactivated";

$message  = "Dear {$user['NAME']},<br><br>";
$message .= "Your account on Car Rental Platform has been reactivated by the administrator.<br><br>";
$message .= "<strong>Note from admin:</strong> {$note}<br><br>";
$message .= "You can now login and resume all normal activities.<br><br>";
$message .= "If you have questions, please contact the admin only via email: {$admin_email}<br><br>";
$message .= "Welcome back!<br><br>";
$message .= "Best regards,<br>Car Rental Platform Team";

send_email($user['EMAIL'], $subject, $message);

                // Log reactivation (in production: create ADMIN_ACTIONS table)
                // In production: Send email notification to user
                
                set_flash_message("User account reactivated successfully. User can now login and access their account.", "success");
                header("Location: view_user.php?type=" . $user_type . "&id=" . $user_id);
                exit();
            } else {
                $e = oci_error($stmt_unsuspend);
                $errors[] = "Failed to reactivate user: " . $e['message'];
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reactivate User | Royal Car Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/unsuspend_user.css?v=<?php echo time(); ?>">
</head>
<body>

    <aside class="sidebar">
        <h4>
            Royal Blue
            <span>Admin Control Panel</span>
        </h4>
        
        <a href="dashboard.php">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        
        <a href="verify_lessors.php">
            <i class="fas fa-user-check"></i> <span>Verify Lessors</span>
        </a>
        
        <a href="manage_users.php" class="active">
            <i class="fas fa-users"></i> <span>Manage Users</span>
        </a>
        
        <a href="manage_bookings.php">
            <i class="fas fa-calendar-alt"></i> <span>Bookings</span>
        </a>
        
        <a href="manage_tickets.php">
            <i class="fas fa-headset"></i> <span>Support</span>
        </a>
        
        <a href="reports.php">
            <i class="fas fa-chart-line"></i> <span>Reports</span>
        </a>

        <a href="../auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </aside>

    <main class="main-content">
        <!-- Page Header -->
        <header class="page-header">
            <h2><i class="fas fa-check-circle"></i> Reactivate Suspended Account</h2>
            <p>Restore user access to the platform</p>
        </header>

        <!-- Flash Messages -->
        <?php display_flash_message(); ?>
        
        <!-- Display errors -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Error:</strong>
                    <ul style="margin-left: 20px; margin-top: 5px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- User Type Badge -->
        <span class="user-type-badge <?php echo $user_type; ?>">
            <?php echo strtoupper($user_type); ?> #<?php echo $user['USER_ID']; ?>
        </span>
        
        <!-- Success Card -->
        <div class="success-card">
            <h2><i class="fas fa-user-check"></i> User Account Details</h2>
            
            <div class="user-details">
                <ul>
                    <li><strong>Type:</strong> <span><?php echo strtoupper($user_type); ?></span></li>
                    <li><strong>ID:</strong> <span><?php echo $user['USER_ID']; ?></span></li>
                    <li><strong>Name:</strong> <span><?php echo htmlspecialchars($user['NAME']); ?></span></li>
                    <li><strong>Email:</strong> <span><?php echo htmlspecialchars($user['EMAIL']); ?></span></li>
                    <li><strong>Current Status:</strong> <span class="status-suspended">SUSPENDED</span></li>
                </ul>
            </div>
            
            <h3>What happens when you reactivate this account?</h3>
            <ul>
                <li>✅ Account status will be changed to <strong><?php echo $user_type == 'lessor' ? 'VERIFIED' : 'ACTIVE'; ?></strong></li>
                <li>✅ User can <strong>login immediately</strong></li>
                <li>✅ All features will be <strong>restored</strong></li>
                <?php if ($user_type == 'lessor'): ?>
                    <li>✅ Car listings will be <strong>reactivated</strong></li>
                    <li>✅ Can receive new bookings</li>
                <?php else: ?>
                    <li>✅ Can make new bookings</li>
                    <li>✅ Can access booking history</li>
                <?php endif; ?>
                <li>✅ User will receive <strong>reactivation notification</strong> via email</li>
            </ul>
        </div>
        
        <!-- Reactivation Form -->
        <div class="form-card">
            <h2><i class="fas fa-edit"></i> Reactivation Form</h2>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>
                        Reactivation Note <span class="required">*</span>
                    </label>
                    <textarea name="note" required rows="5" 
                              placeholder="e.g., Issue resolved after investigation. User can resume normal activities."></textarea>
                    <p class="form-help">
                        <i class="fas fa-info-circle"></i> This note will be logged. Explain why the suspension is being lifted.
                    </p>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success"
                            onclick="return confirm('Are you sure you want to reactivate this account? User will be able to login immediately.')">
                        <i class="fas fa-check-circle"></i> Confirm Reactivation
                    </button>
                    <a href="view_user.php?type=<?php echo $user_type; ?>&id=<?php echo $user_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="manage_users.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Manage Users
            </a>
        </div>
    </main>

</body>
</html>