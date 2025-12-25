<?php
/**
 * UC24: Generate Reports (SRS Pages 83-85)
 * Admin generates system reports
 * Reports: Booking, Financial, and Lessor Performance
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Check if admin is logged in
require_login('admin');

$adminid = get_user_id();

// Get report parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'booking';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // First day of current month
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d'); // Today

// UC24 Step 1: Select report type and date range
$report_data = [];

switch ($report_type) {
    case 'booking':
        // UC24: Booking reports (BOOKING, CAR, RENTER, LESSOR)
        $report_data = generateBookingReport($conn, $date_from, $date_to);
        break;
    
    case 'financial':
        // UC24: Financial reports (PAYMENT, TRANSACTION, BOOKING)
        $report_data = generateFinancialReport($conn, $date_from, $date_to);
        break;
    
    case 'performance':
        // UC24: Lessor Performance reports (LESSOR, BOOKING, CAR, REVIEW)
        $report_data = generatePerformanceReport($conn, $date_from, $date_to);
        break;
}

// ========================================
// REPORT GENERATION FUNCTIONS
// ========================================

function generateBookingReport($conn, $date_from, $date_to) {
    $data = [];
    
    // Booking summary by status (BOOKING table)
    $summary_sql = "SELECT 
                    COUNT(*) as TOTAL,
                    COUNT(CASE WHEN BOOKING_STATUS = 'PENDING' THEN 1 END) as PENDING,
                    COUNT(CASE WHEN BOOKING_STATUS = 'CONFIRMED' THEN 1 END) as CONFIRMED,
                    COUNT(CASE WHEN BOOKING_STATUS = 'COMPLETED' THEN 1 END) as COMPLETED,
                    COUNT(CASE WHEN BOOKING_STATUS = 'CANCELLED' THEN 1 END) as CANCELLED,
                    NVL(SUM(TOTAL_AMOUNT), 0) as TOTAL_REVENUE,
                    NVL(AVG(TOTAL_AMOUNT), 0) as AVG_BOOKING_VALUE
                    FROM BOOKING
                    WHERE CREATED_AT BETWEEN TO_DATE(:date_from, 'YYYY-MM-DD') 
                    AND TO_DATE(:date_to, 'YYYY-MM-DD') + 1";
    
    $stmt = oci_parse($conn, $summary_sql);
    oci_bind_by_name($stmt, ':date_from', $date_from);
    oci_bind_by_name($stmt, ':date_to', $date_to);
    oci_execute($stmt);
    $data['summary'] = oci_fetch_assoc($stmt);
    
    // Bookings by date (for trend chart)
    $daily_sql = "SELECT TO_CHAR(CREATED_AT, 'DD-MON') as DATE_LABEL, 
                  COUNT(*) as BOOKING_COUNT,
                  NVL(SUM(TOTAL_AMOUNT), 0) as DAILY_REVENUE
                  FROM BOOKING
                  WHERE CREATED_AT BETWEEN TO_DATE(:date_from, 'YYYY-MM-DD') 
                  AND TO_DATE(:date_to, 'YYYY-MM-DD') + 1
                  GROUP BY TO_CHAR(CREATED_AT, 'DD-MON'), TRUNC(CREATED_AT)
                  ORDER BY TRUNC(CREATED_AT)";
    
    $stmt = oci_parse($conn, $daily_sql);
    oci_bind_by_name($stmt, ':date_from', $date_from);
    oci_bind_by_name($stmt, ':date_to', $date_to);
    oci_execute($stmt);
    
    $data['daily_bookings'] = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $data['daily_bookings'][] = $row;
    }
    
    // Detailed booking list (BOOKING, RENTER, CAR, LESSOR)
    $detail_sql = "SELECT b.BOOKINGID, b.START_DATE, b.END_DATE, b.BOOKING_STATUS, b.TOTAL_AMOUNT,
                   r.NAME as RENTER_NAME, r.EMAIL as RENTER_EMAIL, r.PHONE as RENTER_PHONE,
                   c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE,
                   l.NAME as LESSOR_NAME, l.EMAIL as LESSOR_EMAIL,
                   TO_CHAR(b.CREATED_AT, 'DD-MON-YYYY HH24:MI') as CREATED_FMT
                   FROM BOOKING b
                   JOIN RENTER r ON b.RENTERID = r.RENTERID
                   JOIN CAR c ON b.CARID = c.CARID
                   JOIN LESSOR l ON c.LESSORID = l.LESSORID
                   WHERE b.CREATED_AT BETWEEN TO_DATE(:date_from, 'YYYY-MM-DD') 
                   AND TO_DATE(:date_to, 'YYYY-MM-DD') + 1
                   ORDER BY b.CREATED_AT DESC";
    
    $stmt = oci_parse($conn, $detail_sql);
    oci_bind_by_name($stmt, ':date_from', $date_from);
    oci_bind_by_name($stmt, ':date_to', $date_to);
    oci_execute($stmt);
    
    $data['bookings'] = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $data['bookings'][] = $row;
    }
    
    // Most booked cars (BOOKING, CAR)
    $popular_cars_sql = "SELECT c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE,
                         COUNT(b.BOOKINGID) as TOTAL_BOOKINGS,
                         NVL(SUM(b.TOTAL_AMOUNT), 0) as TOTAL_REVENUE
                         FROM CAR c
                         LEFT JOIN BOOKING b ON c.CARID = b.CARID
                         AND b.CREATED_AT BETWEEN TO_DATE(:date_from, 'YYYY-MM-DD') 
                         AND TO_DATE(:date_to, 'YYYY-MM-DD') + 1
                         WHERE b.BOOKINGID IS NOT NULL
                         GROUP BY c.CARID, c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE
                         ORDER BY TOTAL_BOOKINGS DESC
                         FETCH FIRST 10 ROWS ONLY";
    
    $stmt = oci_parse($conn, $popular_cars_sql);
    oci_bind_by_name($stmt, ':date_from', $date_from);
    oci_bind_by_name($stmt, ':date_to', $date_to);
    oci_execute($stmt);
    
    $data['popular_cars'] = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $data['popular_cars'][] = $row;
    }
    
    return $data;
}

function generateFinancialReport($conn, $date_from, $date_to) {
    $data = [];
    
    // Revenue summary from BOOKING table
    $revenue_sql = "SELECT 
                    NVL(SUM(TOTAL_AMOUNT), 0) as TOTAL_REVENUE,
                    NVL(SUM(TOTAL_AMOUNT * 0.10), 0) as PLATFORM_COMMISSION,
                    NVL(SUM(TOTAL_AMOUNT * 0.90), 0) as LESSOR_EARNINGS,
                    COUNT(*) as TOTAL_TRANSACTIONS,
                    NVL(AVG(TOTAL_AMOUNT), 0) as AVG_TRANSACTION
                    FROM BOOKING
                    WHERE BOOKING_STATUS IN ('CONFIRMED', 'COMPLETED')
                    AND CREATED_AT BETWEEN TO_DATE(:date_from, 'YYYY-MM-DD') 
                    AND TO_DATE(:date_to, 'YYYY-MM-DD') + 1";
    
    $stmt = oci_parse($conn, $revenue_sql);
    oci_bind_by_name($stmt, ':date_from', $date_from);
    oci_bind_by_name($stmt, ':date_to', $date_to);
    oci_execute($stmt);
    $data['revenue'] = oci_fetch_assoc($stmt);
    
    // Payment methods breakdown (PAYMENT table)
    $payment_method_sql = "SELECT p.PAYMENT_METHOD, 
                           COUNT(*) as TRANSACTION_COUNT,
                           NVL(SUM(p.AMOUNT), 0) as TOTAL_AMOUNT
                           FROM PAYMENT p
                           WHERE p.PAYMENT_STATUS = 'SUCCESS'
                           AND p.PAYMENT_DATE BETWEEN TO_DATE(:date_from, 'YYYY-MM-DD') 
                           AND TO_DATE(:date_to, 'YYYY-MM-DD') + 1
                           GROUP BY p.PAYMENT_METHOD
                           ORDER BY TOTAL_AMOUNT DESC";
    
    $stmt = oci_parse($conn, $payment_method_sql);
    oci_bind_by_name($stmt, ':date_from', $date_from);
    oci_bind_by_name($stmt, ':date_to', $date_to);
    oci_execute($stmt);
    
    $data['payment_methods'] = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $data['payment_methods'][] = $row;
    }
    
    // Daily revenue trend (BOOKING table)
    $daily_revenue_sql = "SELECT TO_CHAR(CREATED_AT, 'DD-MON') as DATE_LABEL,
                          NVL(SUM(TOTAL_AMOUNT), 0) as REVENUE,
                          NVL(SUM(TOTAL_AMOUNT * 0.10), 0) as COMMISSION,
                          NVL(SUM(TOTAL_AMOUNT * 0.90), 0) as LESSOR_SHARE
                          FROM BOOKING
                          WHERE BOOKING_STATUS IN ('CONFIRMED', 'COMPLETED')
                          AND CREATED_AT BETWEEN TO_DATE(:date_from, 'YYYY-MM-DD') 
                          AND TO_DATE(:date_to, 'YYYY-MM-DD') + 1
                          GROUP BY TO_CHAR(CREATED_AT, 'DD-MON'), TRUNC(CREATED_AT)
                          ORDER BY TRUNC(CREATED_AT)";
    
    $stmt = oci_parse($conn, $daily_revenue_sql);
    oci_bind_by_name($stmt, ':date_from', $date_from);
    oci_bind_by_name($stmt, ':date_to', $date_to);
    oci_execute($stmt);
    
    $data['daily_revenue'] = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $data['daily_revenue'][] = $row;
    }
    
    // Transaction details (PAYMENT, BOOKING)
    $transactions_sql = "SELECT p.PAYMENTID, p.PAYMENT_METHOD, p.AMOUNT, p.PAYMENT_STATUS,
                         TO_CHAR(p.PAYMENT_DATE, 'DD-MON-YYYY HH24:MI') as PAYMENT_DATE_FMT,
                         b.BOOKINGID, b.TOTAL_AMOUNT as BOOKING_AMOUNT,
                         r.NAME as RENTER_NAME
                         FROM PAYMENT p
                         JOIN BOOKING b ON p.BOOKINGID = b.BOOKINGID
                         JOIN RENTER r ON p.RENTERID = r.RENTERID
                         WHERE p.PAYMENT_DATE BETWEEN TO_DATE(:date_from, 'YYYY-MM-DD') 
                         AND TO_DATE(:date_to, 'YYYY-MM-DD') + 1
                         ORDER BY p.PAYMENT_DATE DESC";
    
    $stmt = oci_parse($conn, $transactions_sql);
    oci_bind_by_name($stmt, ':date_from', $date_from);
    oci_bind_by_name($stmt, ':date_to', $date_to);
    oci_execute($stmt);
    
    $data['transactions'] = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $data['transactions'][] = $row;
    }
    
    // Top earning lessors (BOOKING, CAR, LESSOR)
    $top_lessors_sql = "SELECT l.NAME, l.EMAIL, l.BUSINESS_NAME,
                        COUNT(b.BOOKINGID) as TOTAL_BOOKINGS,
                        NVL(SUM(b.TOTAL_AMOUNT), 0) as TOTAL_REVENUE,
                        NVL(SUM(b.TOTAL_AMOUNT * 0.90), 0) as LESSOR_EARNINGS,
                        NVL(SUM(b.TOTAL_AMOUNT * 0.10), 0) as COMMISSION_EARNED
                        FROM LESSOR l
                        JOIN CAR c ON l.LESSORID = c.LESSORID
                        JOIN BOOKING b ON c.CARID = b.CARID
                        WHERE b.BOOKING_STATUS IN ('CONFIRMED', 'COMPLETED')
                        AND b.CREATED_AT BETWEEN TO_DATE(:date_from, 'YYYY-MM-DD') 
                        AND TO_DATE(:date_to, 'YYYY-MM-DD') + 1
                        GROUP BY l.LESSORID, l.NAME, l.EMAIL, l.BUSINESS_NAME
                        ORDER BY TOTAL_REVENUE DESC
                        FETCH FIRST 10 ROWS ONLY";
    
    $stmt = oci_parse($conn, $top_lessors_sql);
    oci_bind_by_name($stmt, ':date_from', $date_from);
    oci_bind_by_name($stmt, ':date_to', $date_to);
    oci_execute($stmt);
    
    $data['top_lessors'] = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $data['top_lessors'][] = $row;
    }
    
    return $data;
}

function generatePerformanceReport($conn, $date_from, $date_to) {
    $data = [];
    
    // Lessor performance summary (LESSOR, CAR, BOOKING, REVIEW)
    $lessor_perf_sql = "SELECT l.LESSORID, l.NAME, l.EMAIL, l.BUSINESS_NAME,
                        l.VERIFICATION_STATUS,
                        TO_CHAR(l.CREATED_AT, 'DD-MON-YYYY') as JOINED_DATE,
                        COUNT(DISTINCT c.CARID) as TOTAL_CARS,
                        COUNT(DISTINCT b.BOOKINGID) as TOTAL_BOOKINGS,
                        NVL(SUM(b.TOTAL_AMOUNT), 0) as TOTAL_REVENUE,
                        NVL(SUM(b.TOTAL_AMOUNT * 0.90), 0) as LESSOR_EARNINGS,
                        NVL(AVG(b.TOTAL_AMOUNT), 0) as AVG_BOOKING_VALUE,
                        COUNT(DISTINCT CASE WHEN b.BOOKING_STATUS = 'COMPLETED' THEN b.BOOKINGID END) as COMPLETED_BOOKINGS,
                        COUNT(DISTINCT CASE WHEN b.BOOKING_STATUS = 'CANCELLED' THEN b.BOOKINGID END) as CANCELLED_BOOKINGS,
                        COUNT(DISTINCT r.REVIEWID) as TOTAL_REVIEWS,
                        NVL(AVG(r.RATING), 0) as AVG_RATING
                        FROM LESSOR l
                        LEFT JOIN CAR c ON l.LESSORID = c.LESSORID
                        LEFT JOIN BOOKING b ON c.CARID = b.CARID
                        AND b.CREATED_AT BETWEEN TO_DATE(:date_from, 'YYYY-MM-DD') 
                        AND TO_DATE(:date_to, 'YYYY-MM-DD') + 1
                        LEFT JOIN REVIEW r ON b.BOOKINGID = r.BOOKINGID
                        WHERE l.VERIFICATION_STATUS = 'VERIFIED'
                        GROUP BY l.LESSORID, l.NAME, l.EMAIL, l.BUSINESS_NAME, l.VERIFICATION_STATUS, l.CREATED_AT
                        ORDER BY TOTAL_REVENUE DESC";
    
    $stmt = oci_parse($conn, $lessor_perf_sql);
    oci_bind_by_name($stmt, ':date_from', $date_from);
    oci_bind_by_name($stmt, ':date_to', $date_to);
    oci_execute($stmt);
    
    $data['lessor_performance'] = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $data['lessor_performance'][] = $row;
    }
    
    // Car performance by lessor (CAR, BOOKING)
    $car_performance_sql = "SELECT c.CARID, c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE,
                            l.NAME as OWNER_NAME,
                            c.DAILY_RATE,
                            COUNT(b.BOOKINGID) as TOTAL_BOOKINGS,
                            NVL(SUM(b.TOTAL_AMOUNT), 0) as TOTAL_REVENUE
                            FROM CAR c
                            JOIN LESSOR l ON c.LESSORID = l.LESSORID
                            LEFT JOIN BOOKING b ON c.CARID = b.CARID
                            AND b.CREATED_AT BETWEEN TO_DATE(:date_from, 'YYYY-MM-DD') 
                            AND TO_DATE(:date_to, 'YYYY-MM-DD') + 1
                            WHERE l.VERIFICATION_STATUS = 'VERIFIED'
                            GROUP BY c.CARID, c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE, l.NAME, c.DAILY_RATE
                            ORDER BY TOTAL_REVENUE DESC";
    
    $stmt = oci_parse($conn, $car_performance_sql);
    oci_bind_by_name($stmt, ':date_from', $date_from);
    oci_bind_by_name($stmt, ':date_to', $date_to);
    oci_execute($stmt);
    
    $data['car_performance'] = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $data['car_performance'][] = $row;
    }
    
    // Review statistics (REVIEW, BOOKING, LESSOR, CAR)
    $review_stats_sql = "SELECT l.LESSORID, l.NAME,
                         COUNT(r.REVIEWID) as REVIEW_COUNT,
                         NVL(AVG(r.RATING), 0) as AVG_RATING,
                         COUNT(CASE WHEN r.RATING = 5 THEN 1 END) as FIVE_STAR,
                         COUNT(CASE WHEN r.RATING = 4 THEN 1 END) as FOUR_STAR,
                         COUNT(CASE WHEN r.RATING = 3 THEN 1 END) as THREE_STAR,
                         COUNT(CASE WHEN r.RATING = 2 THEN 1 END) as TWO_STAR,
                         COUNT(CASE WHEN r.RATING = 1 THEN 1 END) as ONE_STAR
                         FROM LESSOR l
                         JOIN CAR c ON l.LESSORID = c.LESSORID
                         JOIN BOOKING b ON c.CARID = b.CARID
                         LEFT JOIN REVIEW r ON b.BOOKINGID = r.BOOKINGID
                         AND r.REVIEW_DATE BETWEEN TO_DATE(:date_from, 'YYYY-MM-DD') 
                         AND TO_DATE(:date_to, 'YYYY-MM-DD') + 1
                         WHERE l.VERIFICATION_STATUS = 'VERIFIED'
                         AND r.REVIEWID IS NOT NULL
                         GROUP BY l.LESSORID, l.NAME
                         ORDER BY AVG_RATING DESC, REVIEW_COUNT DESC";
    
    $stmt = oci_parse($conn, $review_stats_sql);
    oci_bind_by_name($stmt, ':date_from', $date_from);
    oci_bind_by_name($stmt, ':date_to', $date_to);
    oci_execute($stmt);
    
    $data['review_stats'] = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $data['review_stats'][] = $row;
    }
    
    return $data;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin</title>
</head>
<body>
    <h1>📊 System Reports</h1>
    
    <nav>
        <a href="dashboard.php">Dashboard</a> |
        <a href="verify_lessors.php">Verify Lessors</a> |
        <a href="manage_users.php">Manage Users</a> |
        <a href="manage_tickets.php">Support Tickets</a> |
        <a href="reports.php">Reports</a> |
        <a href="../auth/logout.php">Logout</a>
    </nav>
    
    <hr>
    
    <?php display_flash_message(); ?>
    
    <!-- UC24 Step 1: Report Type Selection -->
    <h2>🔍 Select Report Type</h2>
    
    <form method="GET" action="">
        <div style="margin: 15px 0;">
            <label><strong>Report Type:</strong></label><br>
            <select name="type" style="padding: 8px; width: 400px; font-size: 16px;">
                <option value="booking" <?php echo $report_type == 'booking' ? 'selected' : ''; ?>>
                    📅 Booking Report (Bookings, Cars, Renters, Lessors)
                </option>
                <option value="financial" <?php echo $report_type == 'financial' ? 'selected' : ''; ?>>
                    💰 Financial Report (Payments, Transactions, Revenue)
                </option>
                <option value="performance" <?php echo $report_type == 'performance' ? 'selected' : ''; ?>>
                    📈 Lessor Performance Report (Lessors, Bookings, Cars, Reviews)
                </option>
            </select>
        </div>
        
        <div style="margin: 15px 0;">
            <label><strong>Date Range:</strong></label><br>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" style="padding: 8px; font-size: 14px;">
            <span style="margin: 0 10px;">to</span>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" style="padding: 8px; font-size: 14px;">
            <button type="submit" style="padding: 10px 25px; background: #2196F3; color: white; border: none; cursor: pointer; border-radius: 5px; margin-left: 10px; font-size: 16px; font-weight: bold;">
                📊 Generate Report
            </button>
        </div>
    </form>
    
    <hr>
    
    <!-- UC24 Step 6: Display Report Results -->
    <?php if ($report_type == 'booking'): ?>
        <?php include 'report_views/booking.php'; ?>
    <?php elseif ($report_type == 'financial'): ?>
        <?php include 'report_views/financial.php'; ?>
    <?php elseif ($report_type == 'performance'): ?>
        <?php include 'report_views/performance.php'; ?>
    <?php endif; ?>
    
    <hr>
    
    <div style="text-align: center; margin: 30px 0;" class="no-print">
        <button onclick="window.print()" style="padding: 12px 30px; background: #4CAF50; color: white; border: none; cursor: pointer; font-size: 16px; border-radius: 5px; font-weight: bold;">
            🖨️ Print Report
        </button>
    </div>
    
    <style>
        @media print {
            .no-print { display: none; }
            nav { display: none; }
        }
    </style>
    
    <p><a href="dashboard.php">← Back to Dashboard</a></p>
</body>
</html>