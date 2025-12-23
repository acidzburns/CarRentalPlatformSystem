<?php
/**
 * UC23: Edit User Information (SRS Pages 79-82)
 * Admin updates user contact details
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

// Fetch user details
$user = null;
if ($user_type == 'renter') {
    $sql = "SELECT RENTERID as USER_ID, NAME, EMAIL, PHONE FROM RENTER WHERE RENTERID = :userid";
} elseif ($user_type == 'lessor') {
    $sql = "SELECT LESSORID as USER_ID, NAME, EMAIL, PHONE FROM LESSOR WHERE LESSORID = :userid";
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    // UC23 Step 8: System validates input
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter valid information format.";
    }
    
    if (!empty($phone) && !preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors[] = "Please enter valid information format.";
    }
    
    // ✅ VALIDATION 1: Check if EMAIL already exists for OTHER users (across BOTH tables)
    if (empty($errors)) {
        $email_exists = false;
        
        if ($user_type == 'renter') {
            // Check if email exists in RENTER table (excluding current user)
            $check_renter_sql = "SELECT COUNT(*) as COUNT FROM RENTER 
                                WHERE EMAIL = :email AND RENTERID != :userid";
            $stmt_check_r = oci_parse($conn, $check_renter_sql);
            oci_bind_by_name($stmt_check_r, ':email', $email);
            oci_bind_by_name($stmt_check_r, ':userid', $user_id);
            oci_execute($stmt_check_r);
            $check_r = oci_fetch_assoc($stmt_check_r);
            
            // Check if email exists in LESSOR table (all lessors)
            $check_lessor_sql = "SELECT COUNT(*) as COUNT FROM LESSOR WHERE EMAIL = :email";
            $stmt_check_l = oci_parse($conn, $check_lessor_sql);
            oci_bind_by_name($stmt_check_l, ':email', $email);
            oci_execute($stmt_check_l);
            $check_l = oci_fetch_assoc($stmt_check_l);
            
            if ($check_r['COUNT'] > 0 || $check_l['COUNT'] > 0) {
                $email_exists = true;
            }
            
        } else { // lessor
            // Check if email exists in LESSOR table (excluding current user)
            $check_lessor_sql = "SELECT COUNT(*) as COUNT FROM LESSOR 
                                WHERE EMAIL = :email AND LESSORID != :userid";
            $stmt_check_l = oci_parse($conn, $check_lessor_sql);
            oci_bind_by_name($stmt_check_l, ':email', $email);
            oci_bind_by_name($stmt_check_l, ':userid', $user_id);
            oci_execute($stmt_check_l);
            $check_l = oci_fetch_assoc($stmt_check_l);
            
            // Check if email exists in RENTER table (all renters)
            $check_renter_sql = "SELECT COUNT(*) as COUNT FROM RENTER WHERE EMAIL = :email";
            $stmt_check_r = oci_parse($conn, $check_renter_sql);
            oci_bind_by_name($stmt_check_r, ':email', $email);
            oci_execute($stmt_check_r);
            $check_r = oci_fetch_assoc($stmt_check_r);
            
            if ($check_l['COUNT'] > 0 || $check_r['COUNT'] > 0) {
                $email_exists = true;
            }
        }
        
        if ($email_exists) {
            $errors[] = "Email already exists for another user.";
        }
    }
    
    // ✅ VALIDATION 2: Check if PHONE already exists for OTHER users (across BOTH tables)
    if (empty($errors) && !empty($phone)) {
        $phone_exists = false;
        
        if ($user_type == 'renter') {
            // Check if phone exists in RENTER table (excluding current user)
            $check_renter_phone_sql = "SELECT COUNT(*) as COUNT FROM RENTER 
                                       WHERE PHONE = :phone AND RENTERID != :userid";
            $stmt_check_rp = oci_parse($conn, $check_renter_phone_sql);
            oci_bind_by_name($stmt_check_rp, ':phone', $phone);
            oci_bind_by_name($stmt_check_rp, ':userid', $user_id);
            oci_execute($stmt_check_rp);
            $check_rp = oci_fetch_assoc($stmt_check_rp);
            
            // Check if phone exists in LESSOR table (all lessors)
            $check_lessor_phone_sql = "SELECT COUNT(*) as COUNT FROM LESSOR WHERE PHONE = :phone";
            $stmt_check_lp = oci_parse($conn, $check_lessor_phone_sql);
            oci_bind_by_name($stmt_check_lp, ':phone', $phone);
            oci_execute($stmt_check_lp);
            $check_lp = oci_fetch_assoc($stmt_check_lp);
            
            if ($check_rp['COUNT'] > 0 || $check_lp['COUNT'] > 0) {
                $phone_exists = true;
            }
            
        } else { // lessor
            // Check if phone exists in LESSOR table (excluding current user)
            $check_lessor_phone_sql = "SELECT COUNT(*) as COUNT FROM LESSOR 
                                       WHERE PHONE = :phone AND LESSORID != :userid";
            $stmt_check_lp = oci_parse($conn, $check_lessor_phone_sql);
            oci_bind_by_name($stmt_check_lp, ':phone', $phone);
            oci_bind_by_name($stmt_check_lp, ':userid', $user_id);
            oci_execute($stmt_check_lp);
            $check_lp = oci_fetch_assoc($stmt_check_lp);
            
            // Check if phone exists in RENTER table (all renters)
            $check_renter_phone_sql = "SELECT COUNT(*) as COUNT FROM RENTER WHERE PHONE = :phone";
            $stmt_check_rp = oci_parse($conn, $check_renter_phone_sql);
            oci_bind_by_name($stmt_check_rp, ':phone', $phone);
            oci_execute($stmt_check_rp);
            $check_rp = oci_fetch_assoc($stmt_check_rp);
            
            if ($check_lp['COUNT'] > 0 || $check_rp['COUNT'] > 0) {
                $phone_exists = true;
            }
        }
        
        if ($phone_exists) {
            $errors[] = "Phone number already exists for another user.";
        }
    }
    
    if (empty($errors)) {
        try {
            // UC23 Step 9: Admin saves changes
            if ($user_type == 'renter') {
                $update_sql = "UPDATE RENTER 
                              SET NAME = :name, EMAIL = :email, PHONE = :phone
                              WHERE RENTERID = :userid";
            } else {
                $update_sql = "UPDATE LESSOR 
                              SET NAME = :name, EMAIL = :email, PHONE = :phone
                              WHERE LESSORID = :userid";
            }
            
            $stmt_update = oci_parse($conn, $update_sql);
            oci_bind_by_name($stmt_update, ':name', $name);
            oci_bind_by_name($stmt_update, ':email', $email);
            oci_bind_by_name($stmt_update, ':phone', $phone);
            oci_bind_by_name($stmt_update, ':userid', $user_id);
            
            if (oci_execute($stmt_update)) {
                oci_commit($conn);
                
                // UC23 Step 10: System sends notification to user (simulated)
                // In production: Send email notification
                
                set_flash_message("User information updated successfully.", "success");
                header("Location: view_user.php?type=" . $user_type . "&id=" . $user_id);
                exit();
            } else {
                $e = oci_error($stmt_update);
                $errors[] = "Failed to update user: " . $e['message'];
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin</title>
</head>
<body>
    <h1>✏️ Edit User Information</h1>
    
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
    
    <!-- User Type Badge -->
    <div style="margin: 20px 0;">
        <span style="padding: 8px 16px; border-radius: 5px; font-weight: bold; font-size: 18px;
            <?php echo $user_type == 'renter' ? 'background: #e3f2fd; color: #0277bd;' : 'background: #f3e5f5; color: #7b1fa2;'; ?>">
            Editing <?php echo strtoupper($user_type); ?> #<?php echo $user['USER_ID']; ?>
        </span>
    </div>
    
    <div style="background: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107;">
        <h3>⚠️ Important</h3>
        <p>You can only edit contact information. Password changes must be requested by the user.</p>
        <p>User will be notified of any changes to their account.</p>
        <p><strong>Note:</strong> Email and phone number must be unique across all users (renters and lessors).</p>
    </div>
    
    <!-- UC23 Step 7: Edit form -->
    <h2>📋 Edit Contact Information</h2>
    
    <form method="POST" action="">
        <div style="margin: 15px 0;">
            <label><strong>Name: *</strong></label><br>
            <input type="text" name="name" required 
                   value="<?php echo htmlspecialchars($user['NAME']); ?>"
                   style="width: 400px; padding: 8px;">
        </div>
        
        <div style="margin: 15px 0;">
            <label><strong>Email: *</strong></label><br>
            <input type="email" name="email" required 
                   value="<?php echo htmlspecialchars($user['EMAIL']); ?>"
                   style="width: 400px; padding: 8px;">
            <p style="font-size: 12px; color: #666;">✅ Must be unique across all users</p>
        </div>
        
        <div style="margin: 15px 0;">
            <label><strong>Phone:</strong></label><br>
            <input type="text" name="phone" 
                   value="<?php echo htmlspecialchars($user['PHONE'] ?? ''); ?>"
                   placeholder="0123456789" maxlength="15"
                   style="width: 400px; padding: 8px;">
            <p style="font-size: 12px; color: #666;">✅ 10-15 digits only, must be unique across all users</p>
        </div>
        
        <div style="margin: 20px 0;">
            <button type="submit" 
                    style="background: #4CAF50; color: white; padding: 12px 30px; border: none; cursor: pointer; font-size: 16px; font-weight: bold; border-radius: 5px;">
                💾 Save Changes
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