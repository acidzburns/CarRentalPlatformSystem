<?php
/**
 * Admin - Manage All Bookings
 * View all bookings and cancel if needed (override)
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is admin
check_authentication('admin');

// Use the global $conn from database.php
global $conn;

// Verify connection exists
if (!$conn) {
    die("Database connection not available");
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
            L.PHONE as LESSOR_PHONE
          FROM BOOKING B
          JOIN CAR C ON B.CARID = C.CARID
          JOIN RENTER R ON B.RENTERID = R.RENTERID
          JOIN LESSOR L ON C.LESSORID = L.LESSORID
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
while ($row = oci_fetch_assoc($stmt)) {
    $bookings[] = $row;
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
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <h1>Manage All Bookings</h1>

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