<?php
/**
 * Admin - Manage All Bookings
 * View all bookings and cancel if needed (override)
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';
require_once '../config/email_test.php';
// Check if user is admin
check_authentication('admin');

// Use the global $conn from database.php
global $conn;

// Verify connection exists
if (!$conn) {
    die("Database connection not available");
}

// Handle refund completion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_refund_done'])) {
    $booking_id = intval($_POST['booking_id']);
    
    // Get booking details for email notification
    $booking_query = "SELECT B.*, 
                      C.BRAND, C.MODEL,
                      R.NAME as RENTER_NAME, R.EMAIL as RENTER_EMAIL,
                      L.NAME as LESSOR_NAME, L.EMAIL as LESSOR_EMAIL
                      FROM BOOKING B
                      JOIN CAR C ON B.CARID = C.CARID
                      JOIN RENTER R ON B.RENTERID = R.RENTERID
                      JOIN LESSOR L ON C.LESSORID = L.LESSORID
                      WHERE B.BOOKINGID = :booking_id";
    
    $booking_stmt = oci_parse($conn, $booking_query);
    oci_bind_by_name($booking_stmt, ':booking_id', $booking_id);
    oci_execute($booking_stmt);
    $booking_data = oci_fetch_assoc($booking_stmt);
    oci_free_statement($booking_stmt);
    
    // Update payment status to indicate refund is completed
    $update_query = "UPDATE PAYMENT 
                     SET PAYMENT_STATUS = 'REFUNDED'
                     WHERE BOOKINGID = :booking_id";
    
    $update_stmt = oci_parse($conn, $update_query);
    oci_bind_by_name($update_stmt, ':booking_id', $booking_id);
    
    if (oci_execute($update_stmt)) {
        oci_commit($conn);
        
        // Send email notifications if booking data was found
        if ($booking_data) {
            $carName = $booking_data['BRAND'] . ' ' . $booking_data['MODEL'];
            $period = date('M d, Y', strtotime($booking_data['START_DATE'])) . 
                      ' - ' . 
                      date('M d, Y', strtotime($booking_data['END_DATE']));
            $refundAmount = number_format($booking_data['TOTAL_AMOUNT'], 2);
            
            // Email to RENTER
            $renter_subject = "Refund Completed for Booking #$booking_id";
            $renter_body = "
                <p>Hello {$booking_data['RENTER_NAME']},</p>
                <p>We are pleased to inform you that your <strong>refund has been completed</strong> for your cancelled booking.</p>
                <p><strong>Booking Details:</strong></p>
                <ul>
                    <li>Booking ID: <strong>$booking_id</strong></li>
                    <li>Car: <strong>$carName</strong></li>
                    <li>Rental Period: <strong>$period</strong></li>
                    <li>Refund Amount: <strong>RM $refundAmount</strong></li>
                </ul>
                <p>The refund has been processed and should appear in your account within 3-5 business days depending on your bank.</p>
                <p>If you have any questions, please don't hesitate to contact our support team.</p>
                <p>Thank you for your patience,<br>Car Rental Platform Team</p>
            ";
            
            send_email($booking_data['RENTER_EMAIL'], $renter_subject, $renter_body);
            
            // Email to LESSOR
            $lessor_subject = "Refund Completed for Booking #$booking_id";
            $lessor_body = "
                <p>Hello {$booking_data['LESSOR_NAME']},</p>
                <p>This is to notify you that the <strong>refund has been completed</strong> for the cancelled booking of your vehicle.</p>
                <p><strong>Booking Details:</strong></p>
                <ul>
                    <li>Booking ID: <strong>$booking_id</strong></li>
                    <li>Car: <strong>$carName</strong></li>
                    <li>Rental Period: <strong>$period</strong></li>
                    <li>Refund Amount: <strong>RM $refundAmount</strong></li>
                    <li>Renter: <strong>{$booking_data['RENTER_NAME']}</strong></li>
                </ul>
                <p>The refund has been processed to the renter.</p>
                <p>Thank you,<br>Car Rental Platform Team</p>
            ";
            
            send_email($booking_data['LESSOR_EMAIL'], $lessor_subject, $lessor_body);
        }
        
        set_flash_message("Refund marked as completed for Booking #" . $booking_id . ". Notification emails sent.", "success");
    } else {
        $e = oci_error($update_stmt);
        set_flash_message("Failed to update refund status: " . $e['message'], "error");
    }
    
    oci_free_statement($update_stmt);
    
    // Redirect to avoid form resubmission
    header("Location: manage_bookings.php" . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit();
}

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$query = "SELECT 
            B.BOOKINGID,
            B.START_DATE,
            B.END_DATE,
            B.TOTAL_AMOUNT,
            B.BOOKING_STATUS,
            B.CREATED_AT,
            C.BRAND,
            C.MODEL,
            C.LICENSE_PLATE,
            R.NAME as RENTER_NAME,
            R.EMAIL as RENTER_EMAIL,
            R.PHONE as RENTER_PHONE,
            L.NAME as LESSOR_NAME,
            L.EMAIL as LESSOR_EMAIL,
            L.PHONE as LESSOR_PHONE,
            P.PAYMENTID,
            P.PAYMENT_STATUS
          FROM BOOKING B
          JOIN CAR C ON B.CARID = C.CARID
          JOIN RENTER R ON B.RENTERID = R.RENTERID
          JOIN LESSOR L ON C.LESSORID = L.LESSORID
          LEFT JOIN PAYMENT P ON B.BOOKINGID = P.BOOKINGID
          WHERE 1=1";

$bind_params = [];

if (!empty($search)) {
    $query .= " AND (
        UPPER(C.BRAND) LIKE :search OR 
        UPPER(C.MODEL) LIKE :search OR 
        UPPER(C.LICENSE_PLATE) LIKE :search OR
        UPPER(R.NAME) LIKE :search OR
        UPPER(L.NAME) LIKE :search OR
        TO_CHAR(B.BOOKINGID) LIKE :search
    )";
    $search_param = "%".strtoupper($search)."%";
    $bind_params[':search'] = $search_param;
}

if (!empty($status_filter)) {
    $query .= " AND UPPER(B.BOOKING_STATUS) = :status";
    $bind_params[':status'] = strtoupper($status_filter);
}

$query .= " ORDER BY B.CREATED_AT DESC";

$stmt = oci_parse($conn, $query);

// Bind parameters
foreach ($bind_params as $key => $value) {
    oci_bind_by_name($stmt, $key, $bind_params[$key]);
}

oci_execute($stmt);

$bookings = [];
$cancelled_with_payment = []; // Track cancelled bookings that need refund
while ($row = oci_fetch_assoc($stmt)) {
    $bookings[] = $row;
    
    // Check if this is a cancelled booking with payment that needs refund (not yet refunded)
    if (strtoupper($row['BOOKING_STATUS']) == 'CANCELLED' && 
        !empty($row['PAYMENTID']) && 
        strtoupper($row['PAYMENT_STATUS']) == 'SUCCESS') {
        $cancelled_with_payment[] = $row;
    }
}

oci_free_statement($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings | Royal Car Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/manage_bookings.css?v=<?php echo time(); ?>">
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
        
        <a href="manage_users.php">
            <i class="fas fa-users"></i> <span>Manage Users</span>
        </a>
        
        <a href="manage_bookings.php" class="active">
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
                <h2>📅 Booking Management</h2>
                <p>Monitor and manage all platform bookings</p>
            </div>
        </header>

        <!-- Flash Messages -->
        <?php display_flash_message(); ?>

        <!-- Refund Alert -->
        <?php if (!empty($cancelled_with_payment)): ?>
        <div class="refund-alert">
            <h3><i class="fas fa-exclamation-triangle"></i> Refund Required Alert</h3>
            <p><strong><?php echo count($cancelled_with_payment); ?> cancelled booking(s) require refund processing:</strong></p>
            <ul class="refund-list">
                <?php foreach ($cancelled_with_payment as $refund_booking): ?>
                <li class="refund-item">
                    <span>
                        <strong>Booking #<?php echo htmlspecialchars($refund_booking['BOOKINGID']); ?></strong> - 
                        <?php echo htmlspecialchars($refund_booking['BRAND'] . ' ' . $refund_booking['MODEL']); ?> - 
                        <?php echo htmlspecialchars($refund_booking['RENTER_NAME']); ?> - 
                        <strong>RM <?php echo number_format($refund_booking['TOTAL_AMOUNT'], 2); ?></strong>
                        <a href="booking_details_admin.php?id=<?php echo $refund_booking['BOOKINGID']; ?>" style="color: #1864FF; margin-left: 10px;">View Details</a>
                    </span>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Have you completed the refund for this booking?');">
                        <input type="hidden" name="mark_refund_done" value="1">
                        <input type="hidden" name="booking_id" value="<?php echo $refund_booking['BOOKINGID']; ?>">
                        <button type="submit" class="btn-success"><i class="fas fa-check"></i> Done</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" class="filter-grid">
                <input type="text" name="search" placeholder="Search by booking ID, car, renter, or lessor..." value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="status">
                    <option value="">All Status</option>
                    <option value="PENDING" <?php echo strtoupper($status_filter) == 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                    <option value="APPROVED" <?php echo strtoupper($status_filter) == 'APPROVED' ? 'selected' : ''; ?>>Approved</option>
                    <option value="ONGOING" <?php echo strtoupper($status_filter) == 'ONGOING' ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="COMPLETED" <?php echo strtoupper($status_filter) == 'COMPLETED' ? 'selected' : ''; ?>>Completed</option>
                    <option value="CANCELLED" <?php echo strtoupper($status_filter) == 'CANCELLED' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="REJECTED" <?php echo strtoupper($status_filter) == 'REJECTED' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                <a href="manage_bookings.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>

        <!-- Bookings Table -->
        <div class="table-card">
            <?php if (empty($bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>No bookings found.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Car</th>
                            <th>Renter</th>
                            <th>Lessor</th>
                            <th>Dates</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td style="font-weight: 700; color: #64748b;">#<?php echo htmlspecialchars($booking['BOOKINGID']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL']); ?></strong><br>
                                    <small style="color: #64748b;"><?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['RENTER_NAME']); ?></strong><br>
                                    <small style="color: #64748b;"><?php echo htmlspecialchars($booking['RENTER_EMAIL']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['LESSOR_NAME']); ?></strong><br>
                                    <small style="color: #64748b;"><?php echo htmlspecialchars($booking['LESSOR_EMAIL']); ?></small>
                                </td>
                                <td>
                                    <?php 
                                    $start = new DateTime($booking['START_DATE']);
                                    $end = new DateTime($booking['END_DATE']);
                                    echo $start->format('M d, Y') . '<br>' . $end->format('M d, Y');
                                    ?>
                                </td>
                                <td style="font-weight: 700;">RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($booking['BOOKING_STATUS']); ?>">
                                        <?php echo htmlspecialchars($booking['BOOKING_STATUS']); ?>
                                    </span>
                                    <?php 
                                    // Show refund status
                                    if (strtoupper($booking['BOOKING_STATUS']) == 'CANCELLED' && !empty($booking['PAYMENTID'])): 
                                        if (strtoupper($booking['PAYMENT_STATUS']) == 'SUCCESS'):
                                    ?>
                                        <span class="refund-status refund-required"><i class="fas fa-exclamation-circle"></i> REFUND REQUIRED</span>
                                    <?php 
                                        elseif (strtoupper($booking['PAYMENT_STATUS']) == 'REFUNDED'):
                                    ?>
                                        <span class="refund-status refund-completed"><i class="fas fa-check-circle"></i> REFUND COMPLETED</span>
                                    <?php 
                                        endif;
                                    endif; 
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="booking_details_admin.php?id=<?php echo $booking['BOOKINGID']; ?>" class="btn-small btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <?php if (strtoupper($booking['BOOKING_STATUS']) != 'CANCELLED' && strtoupper($booking['BOOKING_STATUS']) != 'COMPLETED'): ?>
                                            <a href="cancel_booking_admin.php?id=<?php echo $booking['BOOKINGID']; ?>" 
                                               class="btn-small btn-cancel"
                                               onclick="return confirm('Are you sure you want to cancel this booking? This action cannot be undone.');">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>