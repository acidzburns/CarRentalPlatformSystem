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
    <title>Verify Lessors | Royal Car Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/verify-lessors.css?v=<?php echo time(); ?>">
    <script>
        function showRejectModal(lessorId, lessorName) {
            document.getElementById('reject_modal').classList.add('active');
            document.getElementById('reject_lessor_id').value = lessorId;
            document.getElementById('reject_lessor_name').textContent = lessorName;
        }
        
        function closeRejectModal() {
            document.getElementById('reject_modal').classList.remove('active');
            document.getElementById('rejection_reason').value = '';
        }
        
        function confirmApprove(lessorId, lessorName) {
            return confirm('Approve ' + lessorName + '?\n\nThis will grant them access to list cars on the platform.');
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
            <?php if (count($pending_lessors) > 0): ?>
                <span class="sidebar-badge"><?php echo count($pending_lessors); ?></span>
            <?php endif; ?>
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

    <main class="main-content">
        <!-- Header -->
        <header class="page-header">
            <div>
                <h2>🏪 Lessor Verification</h2>
                <p>Review and approve car owner registrations</p>
            </div>
            <div class="pending-count">
                <?php echo count($pending_lessors); ?> Pending
            </div>
        </header>

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div style="background: #ecfdf5; color: #059669; padding: 15px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #d1fae5;">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div style="background: #fef2f2; color: #dc2626; padding: 15px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #fee2e2;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Pending Verifications -->
        <section>
            <h3 style="font-size: 20px; font-weight: 800; margin-bottom: 25px; color: #1e293b;">
                <i class="fas fa-clock"></i> Pending Verifications
            </h3>
            
            <?php if (empty($pending_lessors)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-double"></i>
                    <p>All caught up! No pending verifications at this time.</p>
                </div>
            <?php else: ?>
                <div class="lessor-grid">
                    <?php foreach ($pending_lessors as $lessor): ?>
                    <div class="lessor-card">
                        <div class="lessor-header">
                            <div>
                                <div class="lessor-id">ID: <?php echo htmlspecialchars($lessor['LESSORID']); ?></div>
                                <h4 class="lessor-name"><?php echo htmlspecialchars($lessor['NAME']); ?></h4>
                            </div>
                        </div>
                        
                        <div class="lessor-info">
                            <div class="info-row">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($lessor['EMAIL']); ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($lessor['PHONE'] ?? 'Not provided'); ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-calendar"></i>
                                <span>Registered: <?php echo date('M d, Y', strtotime($lessor['CREATED_AT'])); ?></span>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <a href="verify_lessor_details.php?id=<?php echo $lessor['LESSORID']; ?>" class="btn btn-view">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            
                            <form method="POST" style="flex: 1;" onsubmit="return confirmApprove(<?php echo $lessor['LESSORID']; ?>, '<?php echo htmlspecialchars($lessor['NAME']); ?>')">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="lessor_id" value="<?php echo $lessor['LESSORID']; ?>">
                                <button type="submit" class="btn btn-approve" style="width: 100%;">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </form>
                            
                            <button type="button" onclick="showRejectModal(<?php echo $lessor['LESSORID']; ?>, '<?php echo htmlspecialchars($lessor['NAME']); ?>')" class="btn btn-reject">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Recent Verifications -->
        <section class="recent-section">
            <h3><i class="fas fa-history"></i> Recent Verifications</h3>
            
            <?php if (empty($recent_lessors)): ?>
                <p style="color: #64748b;">No recent verifications.</p>
            <?php else: ?>
                <div class="recent-table">
                    <table>
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
                                <td style="font-weight: 700; color: #64748b;">#<?php echo htmlspecialchars($lessor['LESSORID']); ?></td>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($lessor['NAME']); ?></td>
                                <td style="color: #64748b; font-size: 13px;"><?php echo htmlspecialchars($lessor['EMAIL']); ?></td>
                                <td>
                                    <?php 
                                    $status = $lessor['VERIFICATION_STATUS'];
                                    $statusClass = $status === 'VERIFIED' ? 'status-verified' : 'status-rejected';
                                    $statusText = $status === 'VERIFIED' ? 'Verified' : 'Rejected';
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td style="color: #64748b;"><?php echo date('M d, Y', strtotime($lessor['CREATED_AT'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
    
    <!-- Rejection Modal -->
    <div id="reject_modal" class="modal-overlay">
        <div class="modal-content">
            <h3>Reject Lessor Account</h3>
            <p>You are about to reject: <strong id="reject_lessor_name"></strong></p>
            
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="lessor_id" id="reject_lessor_id">
                
                <label for="rejection_reason">Reason for Rejection *</label>
                <textarea id="rejection_reason" name="rejection_reason" rows="4" required placeholder="Provide a clear reason for rejection..."></textarea>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-reject" style="flex: 1;">
                        <i class="fas fa-times"></i> Confirm Rejection
                    </button>
                    <button type="button" onclick="closeRejectModal()" class="btn btn-view" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>