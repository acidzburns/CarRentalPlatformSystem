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
    <title>Edit User | Royal Car Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/edit_user.css?v=<?php echo time(); ?>">
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


    <div class="main-content">
        <header class="page-header">
            <h2><i class="fas fa-user-edit"></i> Edit User Information</h2>
        </header>

        <?php display_flash_message(); ?>
    
        <!-- Display errors -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Correction Needed:</strong>
                    <ul style="margin-top: 5px; margin-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- User Type Badge -->
        <span class="user-type-badge badge-<?php echo $user_type; ?>">
            Editing <?php echo strtoupper($user_type); ?> #<?php echo $user['USER_ID']; ?>
        </span>
        
        <div class="alert alert-important">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Important Note</strong>
                <p style="margin-top: 5px; font-weight: normal;">You can only edit contact information. Password changes must be requested by the user. Email and phone number must be unique across all users.</p>
            </div>
        </div>
        
        <!-- Edit Form Card -->
        <div class="form-card">
            <h3><i class="fas fa-list-alt"></i> Edit Contact Information</h3>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Full Name <span style="color: red;">*</span></label>
                    <input type="text" name="name" required 
                           value="<?php echo htmlspecialchars($user['NAME']); ?>"
                           placeholder="Enter full name">
                </div>
                
                <div class="form-group">
                    <label>Email Address <span style="color: red;">*</span></label>
                    <input type="email" name="email" required 
                           value="<?php echo htmlspecialchars($user['EMAIL']); ?>"
                           placeholder="Enter email address">
                    <div class="form-help"><i class="fas fa-check-circle"></i> Must be unique across all users</div>
                </div>
                
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" 
                           value="<?php echo htmlspecialchars($user['PHONE'] ?? ''); ?>"
                           placeholder="0123456789" maxlength="15">
                    <div class="form-help"><i class="fas fa-check-circle"></i> 10-15 digits only, must be unique across all users</div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="view_user.php?type=<?php echo $user_type; ?>&id=<?php echo $user_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>