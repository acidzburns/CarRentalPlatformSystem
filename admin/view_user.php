<?php
/**
 * UC23: View User Details (SRS Pages 79-82)
 * Admin views comprehensive user information
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/session.php';

// Check if admin is logged in
require_login('admin');

$adminid = get_user_id();

// Get parameters from URL
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    set_flash_message("Invalid user reference.", "error");
    header("Location: manage_users.php");
    exit();
}

$user_type = $_GET['type']; // 'renter' or 'lessor'
$user_id = intval($_GET['id']);

$user = null;
$user_stats = [];

// UC23 Step 5: Display comprehensive user information
if ($user_type == 'renter') {
    // Check if STATUS column exists
    $check_status_sql = "SELECT count(*) as COUNT FROM USER_TAB_COLUMNS WHERE TABLE_NAME = 'RENTER' AND COLUMN_NAME = 'STATUS'";
    $stmt_check = oci_parse($conn, $check_status_sql);
    oci_execute($stmt_check);
    $row_check = oci_fetch_assoc($stmt_check);
    $has_status_col = ($row_check['COUNT'] > 0);

    if ($has_status_col) {
        $status_select = "NVL(r.STATUS, 'ACTIVE')";
    } else {
        $status_select = "'ACTIVE'";
    }

    // ✅ FIXED: Fetch renter details with dynamic STATUS check
    $sql = "SELECT r.RENTERID, r.NAME, r.EMAIL, r.PHONE, r.CREATED_AT,
            " . $status_select . " as STATUS,
            TO_CHAR(r.CREATED_AT, 'DD-MON-YYYY HH24:MI') as CREATED_FMT
            FROM RENTER r
            WHERE r.RENTERID = :userid";
    
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':userid', $user_id);
    oci_execute($stmt);
    $user = oci_fetch_assoc($stmt);
    
    if ($user) {
        // Get renter statistics
        // Total bookings
        $booking_sql = "SELECT COUNT(*) as TOTAL FROM BOOKING WHERE RENTERID = :userid";
        $stmt_b = oci_parse($conn, $booking_sql);
        oci_bind_by_name($stmt_b, ':userid', $user_id);
        oci_execute($stmt_b);
        $booking_count = oci_fetch_assoc($stmt_b);
        $user_stats['total_bookings'] = $booking_count['TOTAL'];
        
        // Total spending
        $spending_sql = "SELECT NVL(SUM(TOTAL_AMOUNT), 0) as TOTAL FROM BOOKING 
                        WHERE RENTERID = :userid AND BOOKING_STATUS IN ('CONFIRMED', 'COMPLETED')";
        $stmt_s = oci_parse($conn, $spending_sql);
        oci_bind_by_name($stmt_s, ':userid', $user_id);
        oci_execute($stmt_s);
        $spending = oci_fetch_assoc($stmt_s);
        $user_stats['total_spending'] = $spending['TOTAL'] ?? 0;
        
        // Total reviews
        $review_sql = "SELECT COUNT(*) as TOTAL FROM REVIEW WHERE RENTERID = :userid";
        $stmt_r = oci_parse($conn, $review_sql);
        oci_bind_by_name($stmt_r, ':userid', $user_id);
        oci_execute($stmt_r);
        $review_count = oci_fetch_assoc($stmt_r);
        $user_stats['total_reviews'] = $review_count['TOTAL'];
        
        // Get booking history
        $history_sql = "SELECT b.BOOKINGID, b.START_DATE, b.END_DATE, b.BOOKING_STATUS, b.TOTAL_AMOUNT,
                       c.BRAND, c.MODEL, c.YEAR,
                       TO_CHAR(b.CREATED_AT, 'DD-MON-YYYY') as CREATED_FMT
                       FROM BOOKING b
                       JOIN CAR c ON b.CARID = c.CARID
                       WHERE b.RENTERID = :userid
                       ORDER BY b.CREATED_AT DESC
                       FETCH FIRST 10 ROWS ONLY";
        $stmt_h = oci_parse($conn, $history_sql);
        oci_bind_by_name($stmt_h, ':userid', $user_id);
        oci_execute($stmt_h);
        
        $booking_history = [];
        while ($row = oci_fetch_assoc($stmt_h)) {
            $booking_history[] = $row;
        }
    }
    
} elseif ($user_type == 'lessor') {
    // Fetch lessor details
    $sql = "SELECT l.LESSORID, l.NAME, l.EMAIL, l.PHONE, l.VERIFICATION_STATUS, l.CREATED_AT,
            l.BUSINESS_NAME, l.BANK_ACCOUNT,
            TO_CHAR(l.CREATED_AT, 'DD-MON-YYYY HH24:MI') as CREATED_FMT
            FROM LESSOR l
            WHERE l.LESSORID = :userid";
    
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':userid', $user_id);
    oci_execute($stmt);
    $user = oci_fetch_assoc($stmt);
    
    if ($user) {
        // Get lessor statistics
        // Total cars
        $car_sql = "SELECT COUNT(*) as TOTAL FROM CAR WHERE LESSORID = :userid";
        $stmt_c = oci_parse($conn, $car_sql);
        oci_bind_by_name($stmt_c, ':userid', $user_id);
        oci_execute($stmt_c);
        $car_count = oci_fetch_assoc($stmt_c);
        $user_stats['total_cars'] = $car_count['TOTAL'];
        
        // Total bookings received
        $booking_sql = "SELECT COUNT(*) as TOTAL FROM BOOKING b
                       JOIN CAR c ON b.CARID = c.CARID
                       WHERE c.LESSORID = :userid";
        $stmt_b = oci_parse($conn, $booking_sql);
        oci_bind_by_name($stmt_b, ':userid', $user_id);
        oci_execute($stmt_b);
        $booking_count = oci_fetch_assoc($stmt_b);
        $user_stats['total_bookings'] = $booking_count['TOTAL'];
        
        // Total earnings (90% after commission)
        $earning_sql = "SELECT NVL(SUM(b.TOTAL_AMOUNT * 0.90), 0) as TOTAL FROM BOOKING b
                       JOIN CAR c ON b.CARID = c.CARID
                       WHERE c.LESSORID = :userid AND b.BOOKING_STATUS = 'COMPLETED'";
        $stmt_e = oci_parse($conn, $earning_sql);
        oci_bind_by_name($stmt_e, ':userid', $user_id);
        oci_execute($stmt_e);
        $earning = oci_fetch_assoc($stmt_e);
        $user_stats['total_earnings'] = $earning['TOTAL'] ?? 0;
        
        // Get car listings
        $cars_sql = "SELECT CARID, BRAND, MODEL, YEAR, LICENSE_PLATE, DAILY_RATE, AVAILABILITY_STATUS
                    FROM CAR
                    WHERE LESSORID = :userid
                    ORDER BY CREATED_AT DESC";
        $stmt_cars = oci_parse($conn, $cars_sql);
        oci_bind_by_name($stmt_cars, ':userid', $user_id);
        oci_execute($stmt_cars);
        
        $car_listings = [];
        while ($row = oci_fetch_assoc($stmt_cars)) {
            $car_listings[] = $row;
        }
    }
}

if (!$user) {
    set_flash_message("User not found.", "error");
    header("Location: manage_users.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details | Royal Car Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/view_user.css?v=<?php echo time(); ?>">
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

    <main class="main-content">
        <!-- Page Header -->
        <header class="page-header">
            <h2><i class="fas fa-user-circle"></i> User Details</h2>
            <span class="user-type-badge <?php echo $user_type == 'renter' ? 'badge-renter' : 'badge-lessor'; ?>">
                <?php echo strtoupper($user_type); ?>
            </span>
        </header>

        <?php display_flash_message(); ?>
    
        <!-- Personal Information Card -->
        <div class="info-card">
            <h3><i class="fas fa-id-card"></i> Personal Information</h3>
            <div class="detail-row">
                <span class="detail-label">ID</span>
                <span class="detail-value"><?php echo $user_type == 'renter' ? $user['RENTERID'] : $user['LESSORID']; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Name</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['NAME']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value"><a href="mailto:<?php echo htmlspecialchars($user['EMAIL']); ?>"><?php echo htmlspecialchars($user['EMAIL']); ?></a></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['PHONE'] ?? 'N/A'); ?></span>
            </div>
            <?php if ($user_type == 'lessor'): ?>
            <div class="detail-row">
                <span class="detail-label">Business Name</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['BUSINESS_NAME'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Bank Account</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['BANK_ACCOUNT'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Verification Status</span>
                <span class="detail-value">
                    <span class="status-badge status-<?php echo strtolower($user['VERIFICATION_STATUS']); ?>">
                        <?php echo htmlspecialchars($user['VERIFICATION_STATUS']); ?>
                    </span>
                </span>
            </div>
            <?php else: ?>
            <div class="detail-row">
                <span class="detail-label">Account Status</span>
                <span class="detail-value">
                    <span class="status-badge status-<?php echo strtolower($user['STATUS']); ?>">
                        <?php echo htmlspecialchars($user['STATUS']); ?>
                    </span>
                </span>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <span class="detail-label">Joined</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['CREATED_FMT']); ?></span>
            </div>
        </div>

    
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <?php if ($user_type == 'renter'): ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $user_stats['total_bookings']; ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">RM <?php echo number_format($user_stats['total_spending'], 2); ?></div>
                    <div class="stat-label">Total Spending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $user_stats['total_reviews']; ?></div>
                    <div class="stat-label">Total Reviews</div>
                </div>
            <?php else: ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $user_stats['total_cars']; ?></div>
                    <div class="stat-label">Cars Listed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $user_stats['total_bookings']; ?></div>
                    <div class="stat-label">Bookings Received</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">RM <?php echo number_format($user_stats['total_earnings'], 2); ?></div>
                    <div class="stat-label">Total Earnings</div>
                </div>
            <?php endif; ?>
        </div>
    
        <!-- Activity Section -->
        <?php if ($user_type == 'renter'): ?>
            <div class="activity-section">
                <h3><i class="fas fa-history"></i> Recent Booking History</h3>
                <?php if (empty($booking_history)): ?>
                    <div class="empty-state">No bookings found.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Car</th>
                                <th>Dates</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Booked On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($booking_history as $booking): ?>
                                <tr>
                                    <td>#<?php echo $booking['BOOKINGID']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL'] . ' ' . $booking['YEAR']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($booking['START_DATE'])) . ' - ' . date('d M Y', strtotime($booking['END_DATE'])); ?></td>
                                    <td><?php echo htmlspecialchars($booking['BOOKING_STATUS']); ?></td>
                                    <td>RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($booking['CREATED_FMT']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="activity-section">
                <h3><i class="fas fa-car"></i> Car Listings</h3>
                <?php if (empty($car_listings)): ?>
                    <div class="empty-state">No cars listed.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Car ID</th>
                                <th>Car</th>
                                <th>License Plate</th>
                                <th>Daily Rate</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($car_listings as $car): ?>
                                <tr>
                                    <td>#<?php echo $car['CARID']; ?></td>
                                    <td><?php echo htmlspecialchars($car['BRAND'] . ' ' . $car['MODEL'] . ' ' . $car['YEAR']); ?></td>
                                    <td><?php echo htmlspecialchars($car['LICENSE_PLATE']); ?></td>
                                    <td>RM <?php echo number_format($car['DAILY_RATE'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($car['AVAILABILITY_STATUS']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    
        <!-- Admin Actions -->
        <div class="action-section">
            <h3><i class="fas fa-cog"></i> Admin Actions</h3>
            <div class="action-buttons">
                <!-- DEBUG INFO (Remove after fixing) -->
                <!-- <div style="background: #eee; padding: 5px; margin-bottom: 10px; font-size: 10px;">
                    DEBUG: Type=<?php echo $user_type; ?>, Status=<?php echo $user['STATUS']; ?>
                </div> -->

                <a href="edit_user.php?type=<?php echo $user_type; ?>&id=<?php echo $user_id; ?>" class="btn btn-edit">
                    <i class="fas fa-edit"></i> Edit User Information
                </a>
                
                <?php if ($user_type == 'lessor'): ?>
                    <?php if ($user['VERIFICATION_STATUS'] == 'VERIFIED'): ?>
                        <a href="suspend_user.php?type=lessor&id=<?php echo $user_id; ?>" 
                           onclick="return confirm('Are you sure you want to suspend this lessor account?')" 
                           class="btn btn-suspend">
                            <i class="fas fa-ban"></i> Suspend Account
                        </a>
                    <?php elseif ($user['VERIFICATION_STATUS'] == 'SUSPENDED'): ?>
                        <a href="unsuspend_user.php?type=lessor&id=<?php echo $user_id; ?>" 
                           onclick="return confirm('Reactivate this suspended account?')" 
                           class="btn btn-activate">
                            <i class="fas fa-check-circle"></i> Reactivate Account
                        </a>
                    <?php endif; ?>
                <?php elseif ($user_type == 'renter'): ?>
                    <?php if (strtoupper($user['STATUS']) == 'ACTIVE'): ?>
                        <a href="suspend_user.php?type=renter&id=<?php echo $user_id; ?>" 
                           onclick="return confirm('Are you sure you want to suspend this renter account?')" 
                           class="btn btn-suspend">
                            <i class="fas fa-ban"></i> Suspend Account
                        </a>
                    <?php elseif (strtoupper($user['STATUS']) == 'SUSPENDED'): ?>
                        <a href="unsuspend_user.php?type=renter&id=<?php echo $user_id; ?>" 
                           onclick="return confirm('Reactivate this suspended renter account?')" 
                           class="btn btn-activate">
                            <i class="fas fa-check-circle"></i> Reactivate Account
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="manage_users.php" class="btn btn-secondary" style="padding: 12px 25px; border-radius: 10px; background: #f1f5f9; color: #475569; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-arrow-left"></i> Back to Manage Users
            </a>
        </div>
    </main>
</body>
</html>