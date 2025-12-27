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
    <title>Manage Bookings - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .filters input,
        .filters select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .filters input[type="text"] {
            flex: 1;
            min-width: 250px;
        }

        .filters select {
            min-width: 150px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background-color: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
        }

        .btn-success {
            background-color: #10b981;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
            margin-left: 10px;
        }

        .btn-success:hover {
            background-color: #059669;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background-color: #f9fafb;
            font-weight: 600;
            color: #374151;
        }

        tr:hover {
            background-color: #f9fafb;
        }

        .status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            text-transform: capitalize;
        }

        .status.pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status.approved {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status.ongoing {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status.completed {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        .status.cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status.rejected {
            background-color: #fecaca;
            color: #7f1d1d;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }

        .btn-view {
            background-color: #3b82f6;
            color: white;
        }

        .btn-cancel {
            background-color: #ef4444;
            color: white;
        }

        .btn-cancel:hover {
            background-color: #dc2626;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .refund-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <h1>Manage All Bookings</h1>

        <?php display_flash_message(); ?>

        <!-- Refund Alert -->
        <?php if (!empty($cancelled_with_payment)): ?>
        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <h3 style="color: #92400e; margin-bottom: 10px;">⚠️ Refund Required Alert</h3>
            <p style="color: #78350f; margin-bottom: 10px;"><strong><?php echo count($cancelled_with_payment); ?> cancelled booking(s) require refund processing:</strong></p>
            <ul style="color: #78350f; margin-left: 0; list-style: none;">
                <?php foreach ($cancelled_with_payment as $refund_booking): ?>
                <li class="refund-item">
                    <span>
                        <strong>Booking #<?php echo htmlspecialchars($refund_booking['BOOKINGID']); ?></strong> - 
                        <?php echo htmlspecialchars($refund_booking['BRAND'] . ' ' . $refund_booking['MODEL']); ?> - 
                        <?php echo htmlspecialchars($refund_booking['RENTER_NAME']); ?> - 
                        <strong>RM <?php echo number_format($refund_booking['TOTAL_AMOUNT'], 2); ?></strong>
                        <a href="booking_details_admin.php?id=<?php echo $refund_booking['BOOKINGID']; ?>" style="color: #1d4ed8; margin-left: 10px;">View Details</a>
                    </span>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Have you completed the refund for this booking?');">
                        <input type="hidden" name="mark_refund_done" value="1">
                        <input type="hidden" name="booking_id" value="<?php echo $refund_booking['BOOKINGID']; ?>">
                        <button type="submit" class="btn-success">✓ Done</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <form method="GET" class="filters">
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
            
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="manage_bookings.php" class="btn" style="background-color: #6b7280; color: white;">Clear</a>
        </form>

        <!-- Bookings Table -->
        <div class="table-container">
            <?php if (empty($bookings)): ?>
                <div class="no-data">No bookings found.</div>
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
                                <td><?php echo htmlspecialchars($booking['BOOKINGID']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['RENTER_NAME']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($booking['RENTER_EMAIL']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['LESSOR_NAME']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($booking['LESSOR_EMAIL']); ?></small>
                                </td>
                                <td>
                                    <?php 
                                    $start = new DateTime($booking['START_DATE']);
                                    $end = new DateTime($booking['END_DATE']);
                                    echo $start->format('M d, Y') . '<br>' . $end->format('M d, Y');
                                    ?>
                                </td>
                                <td>RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></td>
                                <td>
                                    <span class="status <?php echo strtolower($booking['BOOKING_STATUS']); ?>">
                                        <?php echo htmlspecialchars($booking['BOOKING_STATUS']); ?>
                                        <?php 
                                        // Show refund status
                                        if (strtoupper($booking['BOOKING_STATUS']) == 'CANCELLED' && !empty($booking['PAYMENTID'])): 
                                            if (strtoupper($booking['PAYMENT_STATUS']) == 'SUCCESS'):
                                        ?>
                                            <span style="display: block; font-size: 10px; color: #dc2626; margin-top: 2px;">⚠ REFUND REQUIRED</span>
                                        <?php 
                                            elseif (strtoupper($booking['PAYMENT_STATUS']) == 'REFUNDED'):
                                        ?>
                                            <span style="display: block; font-size: 10px; color: #059669; margin-top: 2px;">✓ REFUND COMPLETED</span>
                                        <?php 
                                            endif;
                                        endif; 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="booking_details_admin.php?id=<?php echo $booking['BOOKINGID']; ?>" class="btn-small btn-view">View</a>
                                        
                                        <?php if (strtoupper($booking['BOOKING_STATUS']) != 'CANCELLED' && strtoupper($booking['BOOKING_STATUS']) != 'COMPLETED'): ?>
                                            <a href="cancel_booking_admin.php?id=<?php echo $booking['BOOKINGID']; ?>" 
                                               class="btn-small btn-cancel"
                                               onclick="return confirm('Are you sure you want to cancel this booking? This action cannot be undone.');">
                                                Cancel
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
    </div>
</body>
</html>