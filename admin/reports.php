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
    
    return $data;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Royal Car Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/reports.css?v=<?php echo time(); ?>">
</head>
<body>

    <aside class="sidebar no-print">
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
        
        <a href="manage_bookings.php">
            <i class="fas fa-calendar-alt"></i> <span>Bookings</span>
        </a>
        
        <a href="manage_tickets.php">
            <i class="fas fa-headset"></i> <span>Support</span>
        </a>
        
        <a href="reports.php" class="active">
            <i class="fas fa-chart-line"></i> <span>Reports</span>
        </a>

        <a href="../auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </aside>

    <main class="main-content">
        <!-- Page Header -->
        <header class="page-header no-print">
            <div>
                <h2>📊 System Reports</h2>
                <p>Generate comprehensive reports for bookings, finances, and performance</p>
            </div>
        </header>

        <!-- Report Selection -->
        <div class="report-selection no-print">
            <h2>🔍 Select Report Type & Date Range</h2>
            
            <form method="GET" action="">
                <div class="report-type-cards">
                    <label class="report-type-card booking <?php echo $report_type == 'booking' ? 'active' : ''; ?>">
                        <input type="radio" name="type" value="booking" <?php echo $report_type == 'booking' ? 'checked' : ''; ?> style="display: none;">
                        <div class="report-icon">📅</div>
                        <div class="report-card-title">Booking Report</div>
                        <div class="report-card-desc">Overview of all bookings, cars, renters, and lessors</div>
                    </label>
                    
                    <label class="report-type-card financial <?php echo $report_type == 'financial' ? 'active' : ''; ?>">
                        <input type="radio" name="type" value="financial" <?php echo $report_type == 'financial' ? 'checked' : ''; ?> style="display: none;">
                        <div class="report-icon">💰</div>
                        <div class="report-card-title">Financial Report</div>
                        <div class="report-card-desc">Revenue, payments, transactions, and earnings</div>
                    </label>
                    
                    <label class="report-type-card performance <?php echo $report_type == 'performance' ? 'active' : ''; ?>">
                        <input type="radio" name="type" value="performance" <?php echo $report_type == 'performance' ? 'checked' : ''; ?> style="display: none;">
                        <div class="report-icon">📈</div>
                        <div class="report-card-title">Performance Report</div>
                        <div class="report-card-desc">Lessor performance, bookings, cars, and reviews</div>
                    </label>
                </div>
                
                <div class="date-range-section">
                    <h3>📆 Date Range</h3>
                    <div class="date-inputs">
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" required>
                        <span class="date-separator">to</span>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" required>
                        <button type="submit" class="btn-generate">
                            📊 Generate Report
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Report Display -->
        <?php if ($report_type == 'booking'): ?>
            <!-- BOOKING REPORT -->
            <div class="report-container">
                <div class="report-header">
                    <h2>📅 Booking Report</h2>
                    <div class="report-meta">
                        <span>📆 Period: <?php echo date('d M Y', strtotime($date_from)); ?> - <?php echo date('d M Y', strtotime($date_to)); ?></span>
                        <span>🕐 Generated: <?php echo date('d M Y, H:i'); ?></span>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="summary-grid">
                    <div class="summary-card primary">
                        <div class="summary-value"><?php echo $report_data['summary']['TOTAL']; ?></div>
                        <div class="summary-label">Total Bookings</div>
                    </div>
                    <div class="summary-card success">
                        <div class="summary-value"><?php echo $report_data['summary']['CONFIRMED']; ?></div>
                        <div class="summary-label">Confirmed</div>
                    </div>
                    <div class="summary-card info">
                        <div class="summary-value"><?php echo $report_data['summary']['COMPLETED']; ?></div>
                        <div class="summary-label">Completed</div>
                    </div>
                    <div class="summary-card warning">
                        <div class="summary-value"><?php echo $report_data['summary']['PENDING']; ?></div>
                        <div class="summary-label">Pending</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value">RM <?php echo number_format($report_data['summary']['TOTAL_REVENUE'], 2); ?></div>
                        <div class="summary-label">Total Revenue</div>
                    </div>
                </div>

                <!-- Bookings Table -->
                <div class="report-section">
                    <h3>📋 Booking Details</h3>
                    <?php if (!empty($report_data['bookings'])): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Renter</th>
                                        <th>Car</th>
                                        <th>Lessor</th>
                                        <th>Period</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['bookings'] as $booking): ?>
                                    <tr>
                                        <td><strong>#<?php echo $booking['BOOKINGID']; ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['RENTER_NAME']); ?><br>
                                            <small style="color: #718096;"><?php echo htmlspecialchars($booking['RENTER_EMAIL']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL']); ?><br>
                                            <small style="color: #718096;"><?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['LESSOR_NAME']); ?></td>
                                        <td>
                                            <small><?php echo date('d M', strtotime($booking['START_DATE'])); ?> - <?php echo date('d M', strtotime($booking['END_DATE'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($booking['BOOKING_STATUS']); ?>">
                                                <?php echo $booking['BOOKING_STATUS']; ?>
                                            </span>
                                        </td>
                                        <td><strong>RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></strong></td>
                                        <td><small><?php echo $booking['CREATED_FMT']; ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No bookings found for this period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($report_type == 'financial'): ?>
            <!-- FINANCIAL REPORT -->
            <div class="report-container">
                <div class="report-header">
                    <h2>💰 Financial Report</h2>
                    <div class="report-meta">
                        <span>📆 Period: <?php echo date('d M Y', strtotime($date_from)); ?> - <?php echo date('d M Y', strtotime($date_to)); ?></span>
                        <span>🕐 Generated: <?php echo date('d M Y, H:i'); ?></span>
                    </div>
                </div>

                <!-- Revenue Summary -->
                <div class="summary-grid">
                    <div class="summary-card primary">
                        <div class="summary-value">RM <?php echo number_format($report_data['revenue']['TOTAL_REVENUE'], 2); ?></div>
                        <div class="summary-label">Total Revenue</div>
                    </div>
                    <div class="summary-card success">
                        <div class="summary-value">RM <?php echo number_format($report_data['revenue']['PLATFORM_COMMISSION'], 2); ?></div>
                        <div class="summary-label">Platform Commission (10%)</div>
                    </div>
                    <div class="summary-card info">
                        <div class="summary-value">RM <?php echo number_format($report_data['revenue']['LESSOR_EARNINGS'], 2); ?></div>
                        <div class="summary-label">Lessor Earnings (90%)</div>
                    </div>
                    <div class="summary-card warning">
                        <div class="summary-value"><?php echo $report_data['revenue']['TOTAL_TRANSACTIONS']; ?></div>
                        <div class="summary-label">Total Transactions</div>
                    </div>
                </div>

                <!-- Top Earning Lessors -->
                <div class="report-section">
                    <h3>🏆 Top Earning Lessors</h3>
                    <?php if (!empty($report_data['top_lessors'])): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Lessor Name</th>
                                        <th>Business Name</th>
                                        <th>Bookings</th>
                                        <th>Total Revenue</th>
                                        <th>Lessor Earnings</th>
                                        <th>Commission</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['top_lessors'] as $lessor): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($lessor['NAME']); ?><br>
                                            <small style="color: #718096;"><?php echo htmlspecialchars($lessor['EMAIL']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($lessor['BUSINESS_NAME'] ?? 'N/A'); ?></td>
                                        <td><?php echo $lessor['TOTAL_BOOKINGS']; ?></td>
                                        <td><strong>RM <?php echo number_format($lessor['TOTAL_REVENUE'], 2); ?></strong></td>
                                        <td style="color: #48bb78;">RM <?php echo number_format($lessor['LESSOR_EARNINGS'], 2); ?></td>
                                        <td style="color: #667eea;">RM <?php echo number_format($lessor['COMMISSION_EARNED'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No financial data found for this period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($report_type == 'performance'): ?>
            <!-- PERFORMANCE REPORT -->
            <div class="report-container">
                <div class="report-header">
                    <h2>📈 Lessor Performance Report</h2>
                    <div class="report-meta">
                        <span>📆 Period: <?php echo date('d M Y', strtotime($date_from)); ?> - <?php echo date('d M Y', strtotime($date_to)); ?></span>
                        <span>🕐 Generated: <?php echo date('d M Y, H:i'); ?></span>
                    </div>
                </div>

                <!-- Performance Table -->
                <div class="report-section">
                    <h3>📊 Lessor Performance Metrics</h3>
                    <?php if (!empty($report_data['lessor_performance'])): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Lessor</th>
                                        <th>Business</th>
                                        <th>Cars</th>
                                        <th>Bookings</th>
                                        <th>Revenue</th>
                                        <th>Avg Rating</th>
                                        <th>Reviews</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['lessor_performance'] as $perf): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($perf['NAME']); ?><br>
                                            <small style="color: #718096;"><?php echo htmlspecialchars($perf['EMAIL']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($perf['BUSINESS_NAME'] ?? 'N/A'); ?></td>
                                        <td><?php echo $perf['TOTAL_CARS']; ?></td>
                                        <td><?php echo $perf['TOTAL_BOOKINGS']; ?></td>
                                        <td><strong>RM <?php echo number_format($perf['TOTAL_REVENUE'], 2); ?></strong></td>
                                        <td>⭐ <?php echo number_format($perf['AVG_RATING'], 1); ?></td>
                                        <td><?php echo $perf['TOTAL_REVIEWS']; ?></td>
                                        <td><small><?php echo $perf['JOINED_DATE']; ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No performance data found for this period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Print Button -->
        <div class="print-actions no-print">
            <button onclick="window.print()" class="btn-print">
                🖨️ Print Report
            </button>
        </div>

    </main>

    <script>
        // Auto-submit form when report type is changed
        document.querySelectorAll('input[name="type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Update active card styling
                document.querySelectorAll('.report-type-card').forEach(card => {
                    card.classList.remove('active');
                });
                this.closest('.report-type-card').classList.add('active');
            });
        });
    </script>
</body>
</html>