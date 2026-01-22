<?php
/**
 * Admin View Lessor Details
 * UC14: Verification - View detailed information and documents
 * 
 * Allows admin to view lessor details and uploaded documents before verification
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is logged in as admin
check_authentication('admin');

// Get admin information
$admin_name = get_user_name();
$admin_id = get_user_id();

// Initialize variables
$success = "";
$error = "";
$lessor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle approval/rejection from this page
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $post_lessor_id = $_POST['lessor_id'] ?? '';
    
    if ($action === 'approve' && !empty($post_lessor_id)) {
        // UC15: Approve
        $sql_approve = "UPDATE LESSOR SET VERIFICATION_STATUS = 'VERIFIED' WHERE LESSORID = :lessorid";
        
        if (db_execute($sql_approve, ['lessorid' => $post_lessor_id])) {
            // Get lessor details for email
            $sql_lessor = "SELECT NAME, EMAIL FROM LESSOR WHERE LESSORID = :lessorid";
            $lessor_data = db_fetch_one($sql_lessor, ['lessorid' => $post_lessor_id]);
            
            if ($lessor_data) {
                // Send approval email
                require_once '../config/email_test.php';
                
                $subject = "Your Car Rental Account Has Been Approved!";
                $message = "Dear {$lessor_data['NAME']},\n\n";
                $message .= "Congratulations! Your car rental lessor account has been verified and approved.\n\n";
                $message .= "You can now login and start listing your vehicles at:\n";
                $message .= "http://localhost/CarRentalPlatformSystem/auth/lessor_login.php\n\n";
                $message .= "Thank you for joining our platform!\n\n";
                $message .= "Best regards,\nCar Rental Platform Team";
                
                send_email($lessor_data['EMAIL'], $subject, $message);
                
                $success = "Lessor account has been approved successfully! Confirmation email sent to {$lessor_data['EMAIL']}.";
            } else {
                $success = "Lessor account has been approved successfully!";
            }
        } else {
            $error = "Approval failed due to system error. Please try again.";
        }
    }
    elseif ($action === 'reject' && !empty($post_lessor_id)) {
        // UC16: Reject
        $rejection_reason = trim($_POST['rejection_reason'] ?? '');
        
        if (empty($rejection_reason)) {
            $error = "Please provide a reason for rejection.";
        } else {
            $sql_reject = "UPDATE LESSOR SET VERIFICATION_STATUS = 'REJECTED' WHERE LESSORID = :lessorid";
            
            if (db_execute($sql_reject, ['lessorid' => $post_lessor_id])) {
                // Get lessor details for email
                $sql_lessor = "SELECT NAME, EMAIL FROM LESSOR WHERE LESSORID = :lessorid";
                $lessor_data = db_fetch_one($sql_lessor, ['lessorid' => $post_lessor_id]);
                
                if ($lessor_data) {
                    // Send rejection email
                    require_once '../config/email_test.php';
                    
                    $subject = "Car Rental Account - Verification Update";
                    $message = "Dear {$lessor_data['NAME']},\n\n";
                    $message .= "Thank you for registering with our Car Rental Platform.\n\n";
                    $message .= "Unfortunately, your account verification was not approved.\n\n";
                    $message .= "Reason: $rejection_reason\n\n";
                    $message .= "You may re-apply after addressing the issues mentioned above.\n\n";
                    $message .= "If you have questions, please contact our support team.\n\n";
                    $message .= "Best regards,\nCar Rental Platform Team";
                    
                    send_email($lessor_data['EMAIL'], $subject, $message);
                    
                    $success = "Lessor account has been rejected. Notification email sent to {$lessor_data['EMAIL']}.";
                } else {
                    $success = "Lessor account has been rejected. Rejection reason has been recorded.";
                }
            } else {
                $error = "Rejection failed due to system error. Please try again.";
            }
        }
    }
}

// Get lessor details
if ($lessor_id > 0) {
    $sql = "SELECT LESSORID, NAME, EMAIL, PHONE, BUSINESS_NAME, BANK_ACCOUNT, 
            DRIVER_LICENSE_PATH, VEHICLE_OWNERSHIP_PATH, INSURANCE_PATH, 
            VERIFICATION_STATUS, CREATED_AT 
            FROM LESSOR WHERE LESSORID = :lessorid";
    $lessor = db_fetch_one($sql, ['lessorid' => $lessor_id]);
    
    if (!$lessor) {
        $error = "Lessor not found.";
    }
} else {
    $error = "Invalid lessor ID.";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Lessor Details | Royal Car Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/verify-lessor-details.css?v=<?php echo time(); ?>" />
    <script>
        function showRejectModal() {
            document.getElementById('reject_modal').style.display = 'block';
        }
        
        function closeRejectModal() {
            document.getElementById('reject_modal').style.display = 'none';
            document.getElementById('rejection_reason').value = '';
        }
        
        function confirmApprove(lessorName) {
            return confirm('Are you sure you want to APPROVE ' + lessorName + '?\n\nThis will allow them to login and list cars on the platform.');
        }
    </script>
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
        
        <a href="verify_lessors.php" class="active">
            <i class="fas fa-user-check"></i> <span>Verify Lessors</span>
        </a>
        
        <a href="manage_users.php">
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
    
<!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Lessor Verification Details</h1>
            <p>Review all information and documents before making a decision</p>
        </div>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error && !$lessor): ?>
            <div class="error">
                <?php echo $error; ?>
                <p><a href="verify_lessors.php">Back to Verification List</a></p>
            </div>
        <?php endif; ?>
        
        <?php if ($lessor): ?>
            <!-- Personal Information -->
            <div class="details-card">
                <h2><i class="fas fa-user"></i> Personal Information</h2>
                <table>
                    <tr>
                        <th>Lessor ID</th>
                        <td><?php echo htmlspecialchars($lessor['LESSORID']); ?></td>
                    </tr>
                    <tr>
                        <th>Full Name</th>
                        <td><?php echo htmlspecialchars($lessor['NAME']); ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?php echo htmlspecialchars($lessor['EMAIL']); ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?php echo htmlspecialchars($lessor['PHONE'] ?? 'Not provided'); ?></td>
                    </tr>
                    <tr>
                        <th>Registration Date</th>
                        <td><?php echo date('F j, Y, g:i a', strtotime($lessor['CREATED_AT'])); ?></td>
                    </tr>
                    <tr>
                        <th>Current Status</th>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($lessor['VERIFICATION_STATUS']); ?>">
                                <?php echo htmlspecialchars($lessor['VERIFICATION_STATUS']); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Business Details -->
            <div class="details-card">
                <h2><i class="fas fa-briefcase"></i> Business Details</h2>
                <table>
                    <tr>
                        <th>Business Name</th>
                        <td><?php echo htmlspecialchars($lessor['BUSINESS_NAME'] ?? 'Not provided'); ?></td>
                    </tr>
                    <tr>
                        <th>Bank Account</th>
                        <td><?php echo htmlspecialchars($lessor['BANK_ACCOUNT'] ?? 'Not provided'); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Uploaded Documents -->
            <div class="details-card">
                <h2><i class="fas fa-file-alt"></i> Uploaded Documents</h2>
                
                <!-- Driver's License -->
                <div>
                    <h3><i class="fas fa-id-card"></i> Driver's License</h3>
                    <?php if (!empty($lessor['DRIVER_LICENSE_PATH'])): ?>
                        <?php 
                        $file_path = '../uploads/documents/' . $lessor['DRIVER_LICENSE_PATH'];
                        $file_extension = strtolower(pathinfo($lessor['DRIVER_LICENSE_PATH'], PATHINFO_EXTENSION));
                        ?>
                        <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png'])): ?>
                            <img src="<?php echo htmlspecialchars($file_path); ?>" alt="Driver's License" style="max-width: 600px; border: 1px solid #ddd; padding: 10px;">
                        <?php elseif ($file_extension === 'pdf'): ?>
                            <embed src="<?php echo htmlspecialchars($file_path); ?>" type="application/pdf" width="600" height="400">
                            <p><a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank">Open PDF in new tab</a></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No document uploaded</p>
                    <?php endif; ?>
                </div>
                
                <!-- Vehicle Ownership Proof -->
                <div>
                    <h3><i class="fas fa-car"></i> Vehicle Ownership Proof</h3>
                    <?php if (!empty($lessor['VEHICLE_OWNERSHIP_PATH'])): ?>
                        <?php 
                        $file_path = '../uploads/documents/' . $lessor['VEHICLE_OWNERSHIP_PATH'];
                        $file_extension = strtolower(pathinfo($lessor['VEHICLE_OWNERSHIP_PATH'], PATHINFO_EXTENSION));
                        ?>
                        <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png'])): ?>
                            <img src="<?php echo htmlspecialchars($file_path); ?>" alt="Vehicle Ownership" style="max-width: 600px; border: 1px solid #ddd; padding: 10px;">
                        <?php elseif ($file_extension === 'pdf'): ?>
                            <embed src="<?php echo htmlspecialchars($file_path); ?>" type="application/pdf" width="600" height="400">
                            <p><a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank">Open PDF in new tab</a></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No document uploaded</p>
                    <?php endif; ?>
                </div>
                
                <!-- Insurance Document -->
                <div>
                    <h3><i class="fas fa-shield-alt"></i> Insurance Document</h3>
                    <?php if (!empty($lessor['INSURANCE_PATH'])): ?>
                        <?php 
                        $file_path = '../uploads/documents/' . $lessor['INSURANCE_PATH'];
                        $file_extension = strtolower(pathinfo($lessor['INSURANCE_PATH'], PATHINFO_EXTENSION));
                        ?>
                        <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png'])): ?>
                            <img src="<?php echo htmlspecialchars($file_path); ?>" alt="Insurance Document" style="max-width: 600px; border: 1px solid #ddd; padding: 10px;">
                        <?php elseif ($file_extension === 'pdf'): ?>
                            <embed src="<?php echo htmlspecialchars($file_path); ?>" type="application/pdf" width="600" height="400">
                            <p><a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank">Open PDF in new tab</a></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No document uploaded</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Verification Actions -->
            <?php if ($lessor['VERIFICATION_STATUS'] === 'PENDING'): ?>
                <div class="details-card">
                    <h2><i class="fas fa-check-circle"></i> Verification Actions</h2>
                    <p style="color: #64748b; margin-bottom: 20px;">Review all documents carefully before approving or rejecting this lessor.</p>
                    
                    <div class="action-buttons">
                        <form method="POST" style="display:inline;" onsubmit="return confirmApprove('<?php echo htmlspecialchars($lessor['NAME']); ?>')">
                            <input type="hidden" name="action" value="approve" />
                            <input type="hidden" name="lessor_id" value="<?php echo $lessor['LESSORID']; ?>" />
                            <button type="submit" class="btn btn-approve">
                                <i class="fas fa-check"></i> Approve Lessor
                            </button>
                        </form>
                        
                        <button type="button" class="btn btn-reject" onclick="showRejectModal()">
                            <i class="fas fa-times"></i> Reject Lessor
                        </button>
                        
                        <a href="verify_lessors.php" class="btn btn-back">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="details-card">
                    <h2><i class="fas fa-info-circle"></i> Verification Status</h2>
                    <p style="color: #64748b; margin-bottom: 15px;">
                        This lessor has already been 
                        <span class="status-badge status-<?php echo strtolower($lessor['VERIFICATION_STATUS']); ?>">
                            <?php echo strtolower($lessor['VERIFICATION_STATUS']); ?>
                        </span>
                    </p>
                    <a href="verify_lessors.php" class="btn btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Verification List
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    
    <!-- Rejection Modal -->
    <div id="reject_modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div style="background:white; width:500px; margin:100px auto; padding:30px; border-radius:10px;">
            <h2>Reject Lessor Account</h2>
            <p>You are about to reject: <strong><?php echo isset($lessor) ? htmlspecialchars($lessor['NAME']) : ''; ?></strong></p>
            
            <form method="POST">
                <input type="hidden" name="action" value="reject" />
                <input type="hidden" name="lessor_id" value="<?php echo isset($lessor) ? $lessor['LESSORID'] : ''; ?>" />
                
                <div>
                    <label for="rejection_reason">Reason for Rejection *</label>
                    <textarea id="rejection_reason" name="rejection_reason" rows="4" required placeholder="Please provide a clear reason for rejection (e.g., unclear documents, invalid information)..."></textarea>
                </div>
                
                <div style="margin-top: 15px;">
                    <button type="submit">Confirm Rejection</button>
                    <button type="button" onclick="closeRejectModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
