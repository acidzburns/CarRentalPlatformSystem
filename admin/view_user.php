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
    // Fetch renter details
    $sql = "SELECT r.RENTERID, r.NAME, r.EMAIL, r.PHONE, r.CREATED_AT,
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
    <title>View User Details - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; }
        .badge { padding: 8px 16px; border-radius: 5px; font-weight: bold; display: inline-block; }
        .btn { display: inline-block; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; }
    </style>
</head>
<body>
    <h1>👤 User Details</h1>
    
    <nav>
        <a href="dashboard.php">Dashboard</a> |
        <a href="manage_users.php">← Back to Manage Users</a> |
        <a href="../auth/logout.php">Logout</a>
    </nav>
    
    <hr>
    
    <?php display_flash_message(); ?>
    
    <!-- User Type Badge -->
    <div style="margin: 20px 0;">
        <span class="badge" style="<?php echo $user_type == 'renter' ? 'background: #e3f2fd; color: #0277bd;' : 'background: #f3e5f5; color: #7b1fa2;'; ?>">
            <?php echo strtoupper($user_type); ?>
        </span>
    </div>
    
    <!-- User Information -->
    <h2>📋 Personal Information</h2>
    <table border="1" style="width: 100%;">
        <tr>
            <th style="width: 200px;">ID:</th>
            <td><?php echo $user_type == 'renter' ? $user['RENTERID'] : $user['LESSORID']; ?></td>
        </tr>
        <tr>
            <th>Name:</th>
            <td><?php echo htmlspecialchars($user['NAME']); ?></td>
        </tr>
        <tr>
            <th>Email:</th>
            <td><a href="mailto:<?php echo htmlspecialchars($user['EMAIL']); ?>"><?php echo htmlspecialchars($user['EMAIL']); ?></a></td>
        </tr>
        <tr>
            <th>Phone:</th>
            <td><?php echo htmlspecialchars($user['PHONE'] ?? 'N/A'); ?></td>
        </tr>
        <?php if ($user_type == 'lessor'): ?>
        <tr>
            <th>Business Name:</th>
            <td><?php echo htmlspecialchars($user['BUSINESS_NAME'] ?? 'N/A'); ?></td>
        </tr>
        <tr>
            <th>Bank Account:</th>
            <td><?php echo htmlspecialchars($user['BANK_ACCOUNT'] ?? 'N/A'); ?></td>
        </tr>
        <tr>
            <th>Verification Status:</th>
            <td>
                <span style="padding: 5px 10px; border-radius: 3px; font-weight: bold;
                    <?php 
                    echo $user['VERIFICATION_STATUS'] == 'VERIFIED' ? 'background: #d4edda; color: #155724;' : 
                         ($user['VERIFICATION_STATUS'] == 'PENDING' ? 'background: #fff3cd; color: #856404;' : 
                         'background: #f8d7da; color: #721c24;'); 
                    ?>">
                    <?php echo htmlspecialchars($user['VERIFICATION_STATUS']); ?>
                </span>
            </td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Joined:</th>
            <td><?php echo htmlspecialchars($user['CREATED_FMT']); ?></td>
        </tr>
    </table>
    
    <hr>
    
    <!-- User Statistics -->
    <h2>📊 Statistics</h2>
    <table border="1" style="width: 100%;">
        <?php if ($user_type == 'renter'): ?>
            <tr>
                <th style="width: 200px;">Total Bookings:</th>
                <td><?php echo $user_stats['total_bookings']; ?></td>
            </tr>
            <tr>
                <th>Total Spending:</th>
                <td>RM <?php echo number_format($user_stats['total_spending'], 2); ?></td>
            </tr>
            <tr>
                <th>Total Reviews:</th>
                <td><?php echo $user_stats['total_reviews']; ?></td>
            </tr>
        <?php else: ?>
            <tr>
                <th style="width: 200px;">Total Cars Listed:</th>
                <td><?php echo $user_stats['total_cars']; ?></td>
            </tr>
            <tr>
                <th>Total Bookings Received:</th>
                <td><?php echo $user_stats['total_bookings']; ?></td>
            </tr>
            <tr>
                <th>Total Earnings (90%):</th>
                <td>RM <?php echo number_format($user_stats['total_earnings'], 2); ?></td>
            </tr>
        <?php endif; ?>
    </table>
    
    <hr>
    
    <!-- Activity Details -->
    <?php if ($user_type == 'renter'): ?>
        <h2>📜 Recent Booking History (Last 10)</h2>
        <?php if (empty($booking_history)): ?>
            <p>No bookings found.</p>
        <?php else: ?>
            <table border="1" style="width: 100%;">
                <thead>
                    <tr style="background: #f0f0f0;">
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
    <?php else: ?>
        <h2>🚗 Car Listings</h2>
        <?php if (empty($car_listings)): ?>
            <p>No cars listed.</p>
        <?php else: ?>
            <table border="1" style="width: 100%;">
                <thead>
                    <tr style="background: #f0f0f0;">
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
    <?php endif; ?>
    
    <hr>
    
    <!-- Actions -->
    <h2>⚙️ Admin Actions</h2>
    <div style="margin: 20px 0;">
        <a href="edit_user.php?type=<?php echo $user_type; ?>&id=<?php echo $user_id; ?>" 
           class="btn" style="background: #2196F3; color: white;">
            ✏️ Edit User Information
        </a>
        
        <?php if ($user_type == 'lessor'): ?>
            <?php if ($user['VERIFICATION_STATUS'] == 'VERIFIED'): ?>
                <a href="suspend_user.php?type=lessor&id=<?php echo $user_id; ?>" 
                   onclick="return confirm('Are you sure you want to suspend this lessor account?')" 
                   class="btn" style="background: #f44336; color: white;">
                    🚫 Suspend Account
                </a>
            <?php elseif ($user['VERIFICATION_STATUS'] == 'SUSPENDED'): ?>
                <a href="unsuspend_user.php?type=lessor&id=<?php echo $user_id; ?>" 
                   onclick="return confirm('Reactivate this suspended account?')" 
                   class="btn" style="background: #4CAF50; color: white;">
                    ✅ Reactivate Account
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <p><a href="manage_users.php">← Back to Manage Users</a></p>
</body>
</html>