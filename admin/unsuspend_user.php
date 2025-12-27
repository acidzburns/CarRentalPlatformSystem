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
    <title>Reactivate User - Admin</title>
</head>
<body>
    <h1>✅ Reactivate Suspended Account</h1>
    
    <nav>
        <a href="dashboard.php">Dashboard</a> |
        <a href="manage_users.php">Manage Users</a> |
        <a href="view_user.php?type=<?php echo $user_type; ?>&id=<?php echo $user_id; ?>">← Back to User Details</a> |
        <a href="../auth/logout.php">Logout</a>
    </nav>
    
    <hr>
    
    <?php display_flash_message(); ?>
    
    <!-- Display errors -->
    <?php if (!empty($errors)): ?>
        <div style="background: #ffebee; padding: 10px; margin: 10px 0; border: 1px solid #f44336;">
            <strong>Error:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <!-- User Details -->
    <div style="background: #e8f5e9; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50;">
        <h2 style="color: #2e7d32; margin-top: 0;">👤 User Account Details</h2>
        <ul>
            <li><strong>Type:</strong> <?php echo strtoupper($user_type); ?></li>
            <li><strong>ID:</strong> <?php echo $user['USER_ID']; ?></li>
            <li><strong>Name:</strong> <?php echo htmlspecialchars($user['NAME']); ?></li>
            <li><strong>Email:</strong> <?php echo htmlspecialchars($user['EMAIL']); ?></li>
            <li><strong>Current Status:</strong> <span style="color: #d32f2f; font-weight: bold;">SUSPENDED</span></li>
        </ul>
        
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
    <h2>📝 Reactivation Form</h2>
    
    <form method="POST" action="">
        <div style="margin: 15px 0;">
            <label><strong>Reactivation Note: *</strong></label><br>
            <textarea name="note" required rows="5" 
                      placeholder="e.g., Issue resolved after investigation. User can resume normal activities."
                      style="width: 100%; max-width: 600px; padding: 10px;"></textarea>
            <p style="font-size: 12px; color: #666;">
                This note will be logged. Explain why the suspension is being lifted.
            </p>
        </div>
        
        <div style="margin: 20px 0;">
            <button type="submit" 
                    onclick="return confirm('Are you sure you want to reactivate this account? User will be able to login immediately.')"
                    style="background: #4CAF50; color: white; padding: 12px 30px; border: none; cursor: pointer; font-size: 16px; font-weight: bold; border-radius: 5px;">
                ✅ Confirm Reactivation
            </button>
            <a href="view_user.php?type=<?php echo $user_type; ?>&id=<?php echo $user_id; ?>" 
               style="display: inline-block; padding: 12px 30px; background: #ccc; color: #333; text-decoration: none; border-radius: 5px; margin-left: 10px;">
                Cancel
            </a>
        </div>
    </form>
    
    <hr>
    
    <p><a href="manage_users.php">← Back to Manage Users</a></p>
</body>
</html>