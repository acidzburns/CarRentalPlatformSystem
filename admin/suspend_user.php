<?php
/**
 * UC23: Suspend User Account (SRS Pages 79-82)
 * Admin temporarily blocks user access (supports both renters and lessors)
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Check if admin is logged in
require_login('admin');

$adminid = get_user_id();

// Validate request
if ($_SERVER['REQUEST_METHOD'] != 'POST' && !isset($_GET['type']) && !isset($_GET['id'])) {
    set_flash_message("Invalid request.", "error");
    header("Location: manage_users.php");
    exit();
}

$user_type = isset($_POST['type']) ? $_POST['type'] : $_GET['type'];
$user_id = isset($_POST['id']) ? intval($_POST['id']) : intval($_GET['id']);

// Fetch user details
$user = null;
if ($user_type == 'renter') {
    // ✅ NEW: Support renter suspension
    // Note: RENTER table needs STATUS column added
    $sql = "SELECT RENTERID as USER_ID, NAME, EMAIL FROM RENTER WHERE RENTERID = :userid";
} elseif ($user_type == 'lessor') {
    $sql = "SELECT LESSORID as USER_ID, NAME, EMAIL, VERIFICATION_STATUS FROM LESSOR WHERE LESSORID = :userid";
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

// Check if already suspended (for lessors)
if ($user_type == 'lessor' && isset($user['VERIFICATION_STATUS']) && $user['VERIFICATION_STATUS'] == 'SUSPENDED') {
    set_flash_message("This user is already suspended.", "warning");
    header("Location: view_user.php?type=lessor&id=" . $user_id);
    exit();
}

// Exception 2: Cannot suspend user with active bookings
if ($user_type == 'lessor') {
    // Check lessor's cars for active bookings
    $active_sql = "SELECT COUNT(*) as TOTAL FROM BOOKING b
                   JOIN CAR c ON b.CARID = c.CARID
                   WHERE c.LESSORID = :userid 
                   AND b.BOOKING_STATUS IN ('CONFIRMED')
                   AND b.END_DATE >= SYSDATE";
    
    $stmt_active = oci_parse($conn, $active_sql);
    oci_bind_by_name($stmt_active, ':userid', $user_id);
    oci_execute($stmt_active);
    $active_result = oci_fetch_assoc($stmt_active);
    
    if ($active_result['TOTAL'] > 0) {
        set_flash_message("Cannot suspend user. User has active bookings until completion date. Please wait until bookings are completed or contact user to cancel.", "error");
        header("Location: view_user.php?type=" . $user_type . "&id=" . $user_id);
        exit();
    }
} elseif ($user_type == 'renter') {
    // Check renter's active bookings
    $active_sql = "SELECT COUNT(*) as TOTAL FROM BOOKING 
                   WHERE RENTERID = :userid 
                   AND BOOKING_STATUS IN ('CONFIRMED')
                   AND END_DATE >= SYSDATE";
    
    $stmt_active = oci_parse($conn, $active_sql);
    oci_bind_by_name($stmt_active, ':userid', $user_id);
    oci_execute($stmt_active);
    $active_result = oci_fetch_assoc($stmt_active);
    
    if ($active_result['TOTAL'] > 0) {
        log_message("Error: Renter $user_id has {$active_result['TOTAL']} active bookings.");
        set_flash_message("Cannot suspend user. User has active bookings. Please wait until bookings are completed or contact user to cancel.", "error");
        header("Location: view_user.php?type=" . $user_type . "&id=" . $user_id);
        exit();
    }
}
log_message("No active bookings found for user $user_id.");

// Handle suspension
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $reason = trim($_POST['reason']);
    log_message("Suspension triggered. Reason: $reason");
    
    // Exception 3: Missing suspension reason
    if (empty($reason)) {
        log_message("Error: Missing reason for suspension.");
        set_flash_message("Please provide a reason for suspension.", "error");
        header("Location: suspend_user.php?type=" . $user_type . "&id=" . $user_id);
        exit();
    }
    
    try {
        // UC23 Step 11-13: Suspend account
        if ($user_type == 'lessor') {
            log_message("Suspending Lessor $user_id.");
            // For lessor: Change verification status to SUSPENDED
            $suspend_sql = "UPDATE LESSOR 
                           SET VERIFICATION_STATUS = 'SUSPENDED' 
                           WHERE LESSORID = :userid";
        } else {
            log_message("Suspending Renter $user_id.");
            // ✅ NEW: For renter - add STATUS column handling
            // Check if STATUS column exists
            $check_column_sql = "SELECT COLUMN_NAME FROM USER_TAB_COLUMNS 
                                WHERE TABLE_NAME = 'RENTER' AND COLUMN_NAME = 'STATUS'";
            $stmt_check = oci_parse($conn, $check_column_sql);
            oci_execute($stmt_check);
            $column_exists = oci_fetch_assoc($stmt_check);
            
            if (!$column_exists) {
                log_message("Adding STATUS column to RENTER table.");
                // Add STATUS column to RENTER table
                $add_column_sql = "ALTER TABLE RENTER ADD STATUS VARCHAR2(20) DEFAULT 'ACTIVE'";
                $stmt_add = oci_parse($conn, $add_column_sql);
                if (!oci_execute($stmt_add)) {
                    $e = oci_error($stmt_add);
                    log_message("Failed to add STATUS column: " . $e['message']);
                    // Decide if this should be a fatal error or just logged. For now, log and continue.
                } else {
                    oci_commit($conn);
                    log_message("STATUS column added to RENTER table.");
                }
            }
            
            // Now suspend the renter
            $suspend_sql = "UPDATE RENTER 
                           SET STATUS = 'SUSPENDED' 
                           WHERE RENTERID = :userid";
        }
        
        $stmt_suspend = oci_parse($conn, $suspend_sql);
        oci_bind_by_name($stmt_suspend, ':userid', $user_id);
        
        if (oci_execute($stmt_suspend)) {
            oci_commit($conn);
            log_message("Database update successful. User $user_id STATUS set to SUSPENDED.");
            
            try {
                // Check if email file exists
                if (file_exists("../config/email_test.php")) {
                    log_message("Attempting to send suspension email to " . $user['EMAIL'] . ".");
                    require_once "../config/email_test.php";

                    $admin_email = "carrentalplatformsystem@gmail.com"; 
                    $subject = "Your Account Has Been Suspended";

                    $message  = "Dear {$user['NAME']},<br><br>";
                    $message .= "Your account on Car Rental Platform has been temporarily suspended by the administrator.<br><br>";
                    $message .= "<strong>Reason for suspension:</strong> {$reason}<br><br>";
                    $message .= "During this period, you will be unable to login or access any features of the platform.<br><br>";
                    $message .= "If you have questions or would like to appeal, please contact the admin only via email: {$admin_email}<br><br>";
                    $message .= "Thank you for your understanding.<br><br>";
                    $message .= "Best regards,<br>Car Rental Platform Team";

                    if (function_exists('send_email')) {
                        send_email($user['EMAIL'], $subject, $message);
                        log_message("Suspension email sent successfully to " . $user['EMAIL'] . ".");
                    } else {
                        log_message("Error: send_email function not found in email_test.php.");
                    }
                } else {
                    log_message("Warning: ../config/email_test.php not found. Email not sent.");
                }
            } catch (Exception $e) {
                log_message("Email sending error (ignored): " . $e->getMessage());
                // Silently fail email or log it, but don't stop the suspension
                // verify_lessors.php does something similar
            }

            // Log suspension (in production: create ADMIN_ACTIONS table)
            // UC23: System sends suspension notification to user (simulated)
            
            set_flash_message("User account suspended successfully. Reason: " . htmlspecialchars($reason), "success");
            log_message("User $user_id suspended successfully. Redirecting to manage_users.php.");
            header("Location: manage_users.php");
            exit();
        } else {
            $e = oci_error($stmt_suspend);
            log_message("Database update FAILED for user $user_id: " . $e['message']);
            set_flash_message("Failed to suspend user: " . $e['message'], "error");
            header("Location: view_user.php?type=" . $user_type . "&id=" . $user_id);
            exit();
        }
    } catch (Exception $e) {
        log_message("An unexpected exception occurred during suspension for user $user_id: " . $e->getMessage());
        set_flash_message("An error occurred: " . $e->getMessage(), "error");
        header("Location: view_user.php?type=" . $user_type . "&id=" . $user_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suspend User - Admin</title>
</head>
<body>
    <h1>🚫 Suspend User Account</h1>
    
    <nav>
        <a href="dashboard.php">Dashboard</a> |
        <a href="manage_users.php">Manage Users</a> |
        <a href="view_user.php?type=<?php echo $user_type; ?>&id=<?php echo $user_id; ?>">← Back to User Details</a> |
        <a href="../auth/logout.php">Logout</a>
    </nav>
    
    <hr>
    
    <?php display_flash_message(); ?>
    
    <!-- Warning Message -->
    <div style="background: #ffebee; padding: 20px; margin: 20px 0; border-left: 4px solid #f44336;">
        <h2 style="color: #c62828; margin-top: 0;">⚠️ Warning: Account Suspension</h2>
        <p><strong>You are about to suspend the following user account:</strong></p>
        <ul>
            <li><strong>Type:</strong> <?php echo strtoupper($user_type); ?></li>
            <li><strong>ID:</strong> <?php echo $user['USER_ID']; ?></li>
            <li><strong>Name:</strong> <?php echo htmlspecialchars($user['NAME']); ?></li>
            <li><strong>Email:</strong> <?php echo htmlspecialchars($user['EMAIL']); ?></li>
        </ul>
        
        <h3>What happens when you suspend this account?</h3>
        <ul>
            <li>User will be <strong>immediately locked out</strong> of the platform</li>
            <li>Cannot login or access any features</li>
            <?php if ($user_type == 'lessor'): ?>
                <li>All car listings will be <strong>deactivated</strong></li>
                <li>Cannot receive new bookings</li>
            <?php else: ?>
                <li>Cannot make new bookings</li>
                <li>Cannot access booking history</li>
            <?php endif; ?>
            <li>User will receive <strong>suspension notification</strong> via email</li>
            <li>Can be reactivated later by admin</li>
        </ul>
        
        <p style="color: #c62828;"><strong>This action should only be taken for:</strong></p>
        <ul>
            <li>Terms of service violations</li>
            <li>Fraudulent activity</li>
            <li>User disputes requiring investigation</li>
            <li>Repeated complaints from other users</li>
        </ul>
    </div>
    
    <!-- Suspension Form -->
    <h2>📝 Suspension Details</h2>
    
    <form method="POST" action="">
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($user_type); ?>">
        <input type="hidden" name="id" value="<?php echo $user_id; ?>">
        
        <div style="margin: 15px 0;">
            <label><strong>Reason for Suspension: *</strong></label><br>
            <textarea name="reason" required rows="5" 
                      placeholder="Please provide a detailed reason for suspending this account..."
                      style="width: 100%; max-width: 600px; padding: 10px;"></textarea>
            <p style="font-size: 12px; color: #666;">
                This reason will be logged and may be shown to the user. Be professional and specific.
            </p>
        </div>
        
        <div style="margin: 20px 0;">
            <button type="submit" 
                    onclick="return confirm('Are you absolutely sure you want to suspend this user account? This action will immediately lock them out of the platform.')"
                    style="background: #f44336; color: white; padding: 12px 30px; border: none; cursor: pointer; font-size: 16px; font-weight: bold; border-radius: 5px;">
                🚫 Confirm Suspension
            </button>
            <a href="view_user.php?type=<?php echo $user_type; ?>&id=<?php echo $user_id; ?>" 
               style="display: inline-block; padding: 12px 30px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;">
                ✓ Cancel - Keep Account Active
            </a>
        </div>
    </form>
    
    <hr>
    
    <p><a href="manage_users.php">← Back to Manage Users</a></p>
</body>
</html>