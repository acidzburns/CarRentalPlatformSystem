<?php
/**
 * Admin Verify Lessors Page
 * UC14: Verification, UC15: Approve, UC16: Reject
 * 
 * Allows admin to review and verify lessor registrations
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

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $lessor_id = $_POST['lessor_id'] ?? '';
    
    if ($action === 'approve' && !empty($lessor_id)) {
        // UC15: Approve
        $sql_approve = "UPDATE LESSOR SET VERIFICATION_STATUS = 'VERIFIED' WHERE LESSORID = :lessorid";
        
        if (db_execute($sql_approve, ['lessorid' => $lessor_id])) {
            // Get lessor details for email
            $sql_lessor = "SELECT NAME, EMAIL FROM LESSOR WHERE LESSORID = :lessorid";
            $lessor_data = db_fetch_one($sql_lessor, ['lessorid' => $lessor_id]);
            
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
    elseif ($action === 'reject' && !empty($lessor_id)) {
        // UC16: Reject
        $rejection_reason = trim($_POST['rejection_reason'] ?? '');
        
        // Exception: Missing rejection reason
        if (empty($rejection_reason)) {
            $error = "Please provide a reason for rejection.";
        } else {
            $sql_reject = "UPDATE LESSOR SET VERIFICATION_STATUS = 'REJECTED' WHERE LESSORID = :lessorid";
            
            if (db_execute($sql_reject, ['lessorid' => $lessor_id])) {
                // Get lessor details for email
                $sql_lessor = "SELECT NAME, EMAIL FROM LESSOR WHERE LESSORID = :lessorid";
                $lessor_data = db_fetch_one($sql_lessor, ['lessorid' => $lessor_id]);
                
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

// Get all pending lessors
$sql_pending = "SELECT LESSORID, NAME, EMAIL, PHONE, CREATED_AT FROM LESSOR WHERE VERIFICATION_STATUS = 'PENDING' ORDER BY CREATED_AT DESC";
$pending_lessors = db_fetch_all($sql_pending);

// Get recently verified lessors (last 10)
$sql_verified = "SELECT LESSORID, NAME, EMAIL, VERIFICATION_STATUS, CREATED_AT FROM LESSOR WHERE VERIFICATION_STATUS IN ('VERIFIED', 'REJECTED') ORDER BY CREATED_AT DESC FETCH FIRST 10 ROWS ONLY";
$recent_lessors = db_fetch_all($sql_verified);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Lessors - Admin Panel</title>
    <script>
        function showRejectModal(lessorId, lessorName) {
            document.getElementById('reject_modal').style.display = 'block';
            document.getElementById('reject_lessor_id').value = lessorId;
            document.getElementById('reject_lessor_name').textContent = lessorName;
        }
        
        function closeRejectModal() {
            document.getElementById('reject_modal').style.display = 'none';
            document.getElementById('rejection_reason').value = '';
        }
        
        function confirmApprove(lessorId, lessorName) {
            return confirm('Are you sure you want to APPROVE ' + lessorName + '?\n\nThis will allow them to login and list cars on the platform.');
        }
    </script>
</head>
<body>
    <!-- Navigation Bar -->
    <nav>
        <h2>Car Rental System - Admin Panel</h2>
        <div>
            <span>Welcome, <?php echo htmlspecialchars($admin_name); ?>!</span>
            <a href="dashboard.php">Dashboard</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main>
        <div>
            <h1>Verify Car Owner Registrations</h1>
            <p>Review and approve or reject lessor accounts</p>
        </div>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Pending Verifications -->
        <div>
            <h2>Pending Verifications (<?php echo count($pending_lessors); ?>)</h2>
            
            <?php if (empty($pending_lessors)): ?>
                <p>No pending verifications at this time.</p>
            <?php else: ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Lessor ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_lessors as $lessor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($lessor['LESSORID']); ?></td>
                            <td><?php echo htmlspecialchars($lessor['NAME']); ?></td>
                            <td><?php echo htmlspecialchars($lessor['EMAIL']); ?></td>
                            <td><?php echo htmlspecialchars($lessor['PHONE'] ?? 'N/A'); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($lessor['CREATED_AT'])); ?></td>
                            <td>
                                <a href="verify_lessor_details.php?id=<?php echo $lessor['LESSORID']; ?>">View Details</a>
                                
                                <form method="POST" style="display:inline;" onsubmit="return confirmApprove(<?php echo $lessor['LESSORID']; ?>, '<?php echo htmlspecialchars($lessor['NAME']); ?>')">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="lessor_id" value="<?php echo $lessor['LESSORID']; ?>">
                                    <button type="submit">Approve</button>
                                </form>
                                
                                <button type="button" onclick="showRejectModal(<?php echo $lessor['LESSORID']; ?>, '<?php echo htmlspecialchars($lessor['NAME']); ?>')">Reject</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Recent Verifications -->
        <div>
            <h2>Recent Verifications</h2>
            
            <?php if (empty($recent_lessors)): ?>
                <p>No recent verifications.</p>
            <?php else: ?>
                <table border="1">
                    <thead>
                        <tr>
                            <th>Lessor ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_lessors as $lessor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($lessor['LESSORID']); ?></td>
                            <td><?php echo htmlspecialchars($lessor['NAME']); ?></td>
                            <td><?php echo htmlspecialchars($lessor['EMAIL']); ?></td>
                            <td>
                                <?php 
                                $status = $lessor['VERIFICATION_STATUS'];
                                echo $status === 'VERIFIED' ? '✓ Verified' : '✗ Rejected';
                                ?>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($lessor['CREATED_AT'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Rejection Modal -->
    <div id="reject_modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div style="background:white; width:500px; margin:100px auto; padding:30px; border-radius:10px;">
            <h2>Reject Lessor Account</h2>
            <p>You are about to reject: <strong id="reject_lessor_name"></strong></p>
            
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="lessor_id" id="reject_lessor_id">
                
                <div>
                    <label for="rejection_reason">Reason for Rejection *</label>
                    <textarea id="rejection_reason" name="rejection_reason" rows="4" required placeholder="Please provide a clear reason for rejection..."></textarea>
                </div>
                
                <div>
                    <button type="submit">Confirm Rejection</button>
                    <button type="button" onclick="closeRejectModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>