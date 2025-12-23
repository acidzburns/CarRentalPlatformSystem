<?php
// admin/reports.php - UC24: Generate Report
session_start();
require_once '../config/database.php';
require_once '../includes/check_auth.php';

// Check if user is admin
checkAuth('admin');

$conn = Database::getInstance()->getConnection();

// Initialize variables
$reportType = '';
$startDate = '';
$endDate = '';
$userType = '';
$carCategory = '';
$location = '';
$lessorId = '';
$paymentStatus = '';
$reportData = [];
$reportGenerated = false;
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $reportType = $_POST['report_type'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $userType = $_POST['user_type'] ?? '';
    $carCategory = $_POST['car_category'] ?? '';
    $location = $_POST['location'] ?? '';
    $lessorId = $_POST['lessor_id'] ?? '';
    $paymentStatus = $_POST['payment_status'] ?? '';

    // Validate date range (Ex1)
    if (!empty($startDate) && !empty($endDate)) {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        $today = strtotime(date('Y-m-d'));

        if ($end < $start) {
            $error = "Please enter a valid date range. End date cannot be before start date.";
        } elseif ($start > $today || $end > $today) {
            $error = "Please enter a valid date range. Future dates are not allowed.";
        }
    }

    // Generate report if no errors
    if (empty($error)) {
        try {
            switch ($reportType) {
                case 'booking':
                    $reportData = generateBookingReport($conn, $startDate, $endDate, $location, $lessorId);
                    break;
                case 'revenue':
                    $reportData = generateRevenueReport($conn, $startDate, $endDate, $lessorId);
                    break;
                case 'user':
                    $reportData = generateUserReport($conn, $startDate, $endDate, $userType);
                    break;
                case 'car_performance':
                    $reportData = generateCarPerformanceReport($conn, $startDate, $endDate, $carCategory, $location);
                    break;
                case 'payment':
                    $reportData = generatePaymentReport($conn, $startDate, $endDate, $paymentStatus);
                    break;
                default:
                    $error = "Please select a valid report type.";
            }

            if (!empty($reportData) || $reportType !== '') {
                $reportGenerated = true;
            }
        } catch (Exception $e) {
            $error = "Error generating report: " . $e->getMessage();
        }
    }
}

// Export to PDF
if (isset($_GET['export']) && isset($_GET['type'])) {
    $exportType = $_GET['type'];
    $exportStartDate = $_GET['start_date'] ?? '';
    $exportEndDate = $_GET['end_date'] ?? '';
    
    // Regenerate data for export
    try {
        switch ($exportType) {
            case 'booking':
                $exportData = generateBookingReport($conn, $exportStartDate, $exportEndDate, '', '');
                exportToPDF($exportData, 'Booking Report', $exportStartDate, $exportEndDate);
                break;
            case 'revenue':
                $exportData = generateRevenueReport($conn, $exportStartDate, $exportEndDate, '');
                exportToPDF($exportData, 'Revenue Report', $exportStartDate, $exportEndDate);
                break;
            case 'user':
                $exportData = generateUserReport($conn, $exportStartDate, $exportEndDate, '');
                exportToPDF($exportData, 'User Report', $exportStartDate, $exportEndDate);
                break;
            case 'car_performance':
                $exportData = generateCarPerformanceReport($conn, $exportStartDate, $exportEndDate, '', '');
                exportToPDF($exportData, 'Car Performance Report', $exportStartDate, $exportEndDate);
                break;
            case 'payment':
                $exportData = generatePaymentReport($conn, $exportStartDate, $exportEndDate, '');
                exportToPDF($exportData, 'Payment Report', $exportStartDate, $exportEndDate);
                break;
        }
    } catch (Exception $e) {
        $error = "Export failed. Please try again or contact system support.";
    }
}

// Function to generate booking report
function generateBookingReport($conn, $startDate, $endDate, $location, $lessorId) {
    $query = "SELECT 
                b.BOOKINGID,
                b.START_DATE,
                b.END_DATE,
                b.BOOKING_STATUS,
                b.TOTAL_AMOUNT,
                b.CREATED_AT,
                r.NAME as RENTER_NAME,
                r.EMAIL as RENTER_EMAIL,
                c.BRAND,
                c.MODEL,
                c.LICENSE_PLATE,
                c.LOCATION,
                l.NAME as LESSOR_NAME
              FROM BOOKING b
              JOIN RENTER r ON b.RENTERID = r.RENTERID
              JOIN CAR c ON b.CARID = c.CARID
              JOIN LESSOR l ON c.LESSORID = l.LESSORID
              WHERE 1=1";
    
    $params = [];
    
    if (!empty($startDate) && !empty($endDate)) {
        $query .= " AND b.CREATED_AT BETWEEN TO_DATE(:start_date, 'YYYY-MM-DD') AND TO_DATE(:end_date, 'YYYY-MM-DD') + INTERVAL '1' DAY";
        $params[':start_date'] = $startDate;
        $params[':end_date'] = $endDate;
    }
    
    if (!empty($location)) {
        $query .= " AND UPPER(c.LOCATION) LIKE UPPER(:location)";
        $params[':location'] = "%$location%";
    }
    
    if (!empty($lessorId)) {
        $query .= " AND c.LESSORID = :lessor_id";
        $params[':lessor_id'] = $lessorId;
    }
    
    $query .= " ORDER BY b.CREATED_AT DESC";
    
    $stmt = oci_parse($conn, $query);
    
    foreach ($params as $key => $value) {
        oci_bind_by_name($stmt, $key, $params[$key]);
    }
    
    oci_execute($stmt);
    
    $data = [];
    $totalBookings = 0;
    $totalRevenue = 0;
    $statusCount = ['PENDING' => 0, 'CONFIRMED' => 0, 'COMPLETED' => 0, 'CANCELLED' => 0];
    
    while ($row = oci_fetch_assoc($stmt)) {
        $data[] = $row;
        $totalBookings++;
        $totalRevenue += $row['TOTAL_AMOUNT'] ?? 0;
        
        if (isset($statusCount[$row['BOOKING_STATUS']])) {
            $statusCount[$row['BOOKING_STATUS']]++;
        }
    }
    
    oci_free_statement($stmt);
    
    return [
        'type' => 'booking',
        'data' => $data,
        'summary' => [
            'total_bookings' => $totalBookings,
            'total_revenue' => $totalRevenue,
            'status_breakdown' => $statusCount
        ]
    ];
}

// Function to generate revenue report
function generateRevenueReport($conn, $startDate, $endDate, $lessorId) {
    $query = "SELECT 
                t.TRANSACTIONID,
                t.AMOUNT,
                t.COMMISSION,
                t.TRANSACTION_DATE,
                b.BOOKINGID,
                l.NAME as LESSOR_NAME,
                l.LESSORID,
                c.BRAND,
                c.MODEL
              FROM TRANSACTION t
              JOIN BOOKING b ON t.BOOKINGID = b.BOOKINGID
              JOIN LESSOR l ON t.LESSORID = l.LESSORID
              JOIN CAR c ON b.CARID = c.CARID
              WHERE 1=1";
    
    $params = [];
    
    if (!empty($startDate) && !empty($endDate)) {
        $query .= " AND t.TRANSACTION_DATE BETWEEN TO_DATE(:start_date, 'YYYY-MM-DD') AND TO_DATE(:end_date, 'YYYY-MM-DD') + INTERVAL '1' DAY";
        $params[':start_date'] = $startDate;
        $params[':end_date'] = $endDate;
    }
    
    if (!empty($lessorId)) {
        $query .= " AND t.LESSORID = :lessor_id";
        $params[':lessor_id'] = $lessorId;
    }
    
    $query .= " ORDER BY t.TRANSACTION_DATE DESC";
    
    $stmt = oci_parse($conn, $query);
    
    foreach ($params as $key => $value) {
        oci_bind_by_name($stmt, $key, $params[$key]);
    }
    
    oci_execute($stmt);
    
    $data = [];
    $totalRevenue = 0;
    $totalCommission = 0;
    $lessorEarnings = [];
    
    while ($row = oci_fetch_assoc($stmt)) {
        $data[] = $row;
        $totalRevenue += $row['AMOUNT'] ?? 0;
        $totalCommission += $row['COMMISSION'] ?? 0;
        
        $lessorId = $row['LESSORID'];
        if (!isset($lessorEarnings[$lessorId])) {
            $lessorEarnings[$lessorId] = [
                'name' => $row['LESSOR_NAME'],
                'total' => 0
            ];
        }
        $lessorEarnings[$lessorId]['total'] += ($row['AMOUNT'] - $row['COMMISSION']);
    }
    
    oci_free_statement($stmt);
    
    return [
        'type' => 'revenue',
        'data' => $data,
        'summary' => [
            'total_revenue' => $totalRevenue,
            'total_commission' => $totalCommission,
            'net_platform_earnings' => $totalCommission,
            'lessor_earnings' => $lessorEarnings
        ]
    ];
}

// Function to generate user report
function generateUserReport($conn, $startDate, $endDate, $userType) {
    $data = [];
    
    // Get renters data
    if (empty($userType) || $userType === 'renter') {
        $query = "SELECT 
                    r.RENTERID as ID,
                    r.NAME,
                    r.EMAIL,
                    r.PHONE,
                    r.CREATED_AT,
                    'RENTER' as USER_TYPE,
                    COUNT(b.BOOKINGID) as TOTAL_BOOKINGS,
                    COALESCE(SUM(b.TOTAL_AMOUNT), 0) as TOTAL_SPENT
                  FROM RENTER r
                  LEFT JOIN BOOKING b ON r.RENTERID = b.RENTERID
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($startDate) && !empty($endDate)) {
            $query .= " AND r.CREATED_AT BETWEEN TO_DATE(:start_date, 'YYYY-MM-DD') AND TO_DATE(:end_date, 'YYYY-MM-DD') + INTERVAL '1' DAY";
            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;
        }
        
        $query .= " GROUP BY r.RENTERID, r.NAME, r.EMAIL, r.PHONE, r.CREATED_AT
                    ORDER BY r.CREATED_AT DESC";
        
        $stmt = oci_parse($conn, $query);
        
        foreach ($params as $key => $value) {
            oci_bind_by_name($stmt, $key, $params[$key]);
        }
        
        oci_execute($stmt);
        
        while ($row = oci_fetch_assoc($stmt)) {
            $data[] = $row;
        }
        
        oci_free_statement($stmt);
    }
    
    // Get lessors data
    if (empty($userType) || $userType === 'lessor') {
        $query = "SELECT 
                    l.LESSORID as ID,
                    l.NAME,
                    l.EMAIL,
                    l.PHONE,
                    l.VERIFICATION_STATUS,
                    l.CREATED_AT,
                    'LESSOR' as USER_TYPE,
                    COUNT(c.CARID) as TOTAL_CARS,
                    COALESCE(SUM(t.AMOUNT - t.COMMISSION), 0) as TOTAL_EARNINGS
                  FROM LESSOR l
                  LEFT JOIN CAR c ON l.LESSORID = c.LESSORID
                  LEFT JOIN TRANSACTION t ON l.LESSORID = t.LESSORID
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($startDate) && !empty($endDate)) {
            $query .= " AND l.CREATED_AT BETWEEN TO_DATE(:start_date, 'YYYY-MM-DD') AND TO_DATE(:end_date, 'YYYY-MM-DD') + INTERVAL '1' DAY";
            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;
        }
        
        $query .= " GROUP BY l.LESSORID, l.NAME, l.EMAIL, l.PHONE, l.VERIFICATION_STATUS, l.CREATED_AT
                    ORDER BY l.CREATED_AT DESC";
        
        $stmt = oci_parse($conn, $query);
        
        foreach ($params as $key => $value) {
            oci_bind_by_name($stmt, $key, $params[$key]);
        }
        
        oci_execute($stmt);
        
        while ($row = oci_fetch_assoc($stmt)) {
            $data[] = $row;
        }
        
        oci_free_statement($stmt);
    }
    
    return [
        'type' => 'user',
        'data' => $data,
        'summary' => [
            'total_users' => count($data),
            'user_type_filter' => $userType ?: 'All'
        ]
    ];
}

// Function to generate car performance report
function generateCarPerformanceReport($conn, $startDate, $endDate, $carCategory, $location) {
    $query = "SELECT 
                c.CARID,
                c.BRAND,
                c.MODEL,
                c.YEAR,
                c.LICENSE_PLATE,
                c.LOCATION,
                c.AVAILABILITY_STATUS,
                l.NAME as OWNER_NAME,
                COUNT(b.BOOKINGID) as TOTAL_BOOKINGS,
                COALESCE(SUM(b.TOTAL_AMOUNT), 0) as TOTAL_REVENUE,
                COALESCE(AVG(rev.RATING), 0) as AVG_RATING,
                COUNT(rev.REVIEWID) as TOTAL_REVIEWS
              FROM CAR c
              LEFT JOIN LESSOR l ON c.LESSORID = l.LESSORID
              LEFT JOIN BOOKING b ON c.CARID = b.CARID";
    
    if (!empty($startDate) && !empty($endDate)) {
        $query .= " AND b.CREATED_AT BETWEEN TO_DATE(:start_date, 'YYYY-MM-DD') AND TO_DATE(:end_date, 'YYYY-MM-DD') + INTERVAL '1' DAY";
    }
    
    $query .= " LEFT JOIN REVIEW rev ON c.CARID = rev.CARID
                WHERE 1=1";
    
    $params = [];
    
    if (!empty($startDate) && !empty($endDate)) {
        $params[':start_date'] = $startDate;
        $params[':end_date'] = $endDate;
    }
    
    if (!empty($carCategory)) {
        $query .= " AND UPPER(c.BRAND) LIKE UPPER(:category)";
        $params[':category'] = "%$carCategory%";
    }
    
    if (!empty($location)) {
        $query .= " AND UPPER(c.LOCATION) LIKE UPPER(:location)";
        $params[':location'] = "%$location%";
    }
    
    $query .= " GROUP BY c.CARID, c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE, c.LOCATION, c.AVAILABILITY_STATUS, l.NAME
                ORDER BY TOTAL_REVENUE DESC";
    
    $stmt = oci_parse($conn, $query);
    
    foreach ($params as $key => $value) {
        oci_bind_by_name($stmt, $key, $params[$key]);
    }
    
    oci_execute($stmt);
    
    $data = [];
    $totalCars = 0;
    $totalRevenue = 0;
    
    while ($row = oci_fetch_assoc($stmt)) {
        $data[] = $row;
        $totalCars++;
        $totalRevenue += $row['TOTAL_REVENUE'] ?? 0;
    }
    
    oci_free_statement($stmt);
    
    return [
        'type' => 'car_performance',
        'data' => $data,
        'summary' => [
            'total_cars' => $totalCars,
            'total_revenue' => $totalRevenue,
            'avg_revenue_per_car' => $totalCars > 0 ? $totalRevenue / $totalCars : 0
        ]
    ];
}

// Function to generate payment report
function generatePaymentReport($conn, $startDate, $endDate, $paymentStatus) {
    $query = "SELECT 
                p.PAYMENTID,
                p.PAYMENT_METHOD,
                p.AMOUNT,
                p.PAYMENT_STATUS,
                p.PAYMENT_DATE,
                b.BOOKINGID,
                r.NAME as RENTER_NAME,
                r.EMAIL as RENTER_EMAIL
              FROM PAYMENT p
              JOIN BOOKING b ON p.BOOKINGID = b.BOOKINGID
              JOIN RENTER r ON p.RENTERID = r.RENTERID
              WHERE 1=1";
    
    $params = [];
    
    if (!empty($startDate) && !empty($endDate)) {
        $query .= " AND p.PAYMENT_DATE BETWEEN TO_DATE(:start_date, 'YYYY-MM-DD') AND TO_DATE(:end_date, 'YYYY-MM-DD') + INTERVAL '1' DAY";
        $params[':start_date'] = $startDate;
        $params[':end_date'] = $endDate;
    }
    
    if (!empty($paymentStatus)) {
        $query .= " AND p.PAYMENT_STATUS = :payment_status";
        $params[':payment_status'] = $paymentStatus;
    }
    
    $query .= " ORDER BY p.PAYMENT_DATE DESC";
    
    $stmt = oci_parse($conn, $query);
    
    foreach ($params as $key => $value) {
        oci_bind_by_name($stmt, $key, $params[$key]);
    }
    
    oci_execute($stmt);
    
    $data = [];
    $totalPayments = 0;
    $totalAmount = 0;
    $methodCount = [];
    $statusCount = [];
    
    while ($row = oci_fetch_assoc($stmt)) {
        $data[] = $row;
        $totalPayments++;
        $totalAmount += $row['AMOUNT'] ?? 0;
        
        $method = $row['PAYMENT_METHOD'];
        $methodCount[$method] = ($methodCount[$method] ?? 0) + 1;
        
        $status = $row['PAYMENT_STATUS'];
        $statusCount[$status] = ($statusCount[$status] ?? 0) + 1;
    }
    
    oci_free_statement($stmt);
    
    return [
        'type' => 'payment',
        'data' => $data,
        'summary' => [
            'total_payments' => $totalPayments,
            'total_amount' => $totalAmount,
            'payment_methods' => $methodCount,
            'payment_status' => $statusCount
        ]
    ];
}

// Function to export report to PDF (simplified version)
function exportToPDF($reportData, $reportTitle, $startDate, $endDate) {
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . str_replace(' ', '_', $reportTitle) . '_' . date('Y-m-d') . '.pdf"');
    
    // For now, we'll export as CSV since PDF generation requires external library
    // In production, you would use libraries like TCPDF or FPDF
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . str_replace(' ', '_', $reportTitle) . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write header
    fputcsv($output, [$reportTitle]);
    fputcsv($output, ['Date Range: ' . $startDate . ' to ' . $endDate]);
    fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    // Write data
    if (!empty($reportData['data'])) {
        // Write column headers
        fputcsv($output, array_keys($reportData['data'][0]));
        
        // Write rows
        foreach ($reportData['data'] as $row) {
            fputcsv($output, $row);
        }
    }
    
    // Write summary
    if (!empty($reportData['summary'])) {
        fputcsv($output, []);
        fputcsv($output, ['SUMMARY']);
        foreach ($reportData['summary'] as $key => $value) {
            if (is_array($value)) {
                fputcsv($output, [strtoupper(str_replace('_', ' ', $key))]);
                foreach ($value as $subKey => $subValue) {
                    fputcsv($output, ['  ' . $subKey, $subValue]);
                }
            } else {
                fputcsv($output, [strtoupper(str_replace('_', ' ', $key)), $value]);
            }
        }
    }
    
    fclose($output);
    exit;
}

// Get list of lessors for filter
$lessorQuery = "SELECT LESSORID, NAME FROM LESSOR WHERE VERIFICATION_STATUS = 'VERIFIED' ORDER BY NAME";
$lessorStmt = oci_parse($conn, $lessorQuery);
oci_execute($lessorStmt);
$lessors = [];
while ($lessor = oci_fetch_assoc($lessorStmt)) {
    $lessors[] = $lessor;
}
oci_free_statement($lessorStmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports - Admin Dashboard</title>
</head>
<body>
    <h1>Reports & Analytics</h1>
    
    <?php if (!empty($error)): ?>
        <div style="color: red; border: 1px solid red; padding: 10px; margin: 10px 0;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Report Configuration Form -->
    <form method="POST" action="">
        <h2>Report Configuration</h2>
        
        <div>
            <label>Report Type: *</label>
            <select name="report_type" required>
                <option value="">-- Select Report Type --</option>
                <option value="booking" <?php echo $reportType === 'booking' ? 'selected' : ''; ?>>Booking Report</option>
                <option value="revenue" <?php echo $reportType === 'revenue' ? 'selected' : ''; ?>>Revenue Report</option>
                <option value="user" <?php echo $reportType === 'user' ? 'selected' : ''; ?>>User Activity Report</option>
                <option value="car_performance" <?php echo $reportType === 'car_performance' ? 'selected' : ''; ?>>Car Performance Report</option>
                <option value="payment" <?php echo $reportType === 'payment' ? 'selected' : ''; ?>>Payment Report</option>
            </select>
        </div>

        <div>
            <label>Start Date:</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" max="<?php echo date('Y-m-d'); ?>">
        </div>

        <div>
            <label>End Date:</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" max="<?php echo date('Y-m-d'); ?>">
        </div>

        <!-- Additional Filters -->
        <h3>Additional Filters (Optional)</h3>
        
        <div id="userTypeFilter" style="display: none;">
            <label>User Type:</label>
            <select name="user_type">
                <option value="">All Users</option>
                <option value="renter" <?php echo $userType === 'renter' ? 'selected' : ''; ?>>Renters Only</option>
                <option value="lessor" <?php echo $userType === 'lessor' ? 'selected' : ''; ?>>Lessors Only</option>
            </select>
        </div>

        <div id="carCategoryFilter" style="display: none;">
            <label>Car Category (Brand):</label>
            <input type="text" name="car_category" value="<?php echo htmlspecialchars($carCategory); ?>" placeholder="e.g., Toyota, Honda">
        </div>

        <div id="locationFilter" style="display: none;">
            <label>Location:</label>
            <input type="text" name="location" value="<?php echo htmlspecialchars($location); ?>" placeholder="e.g., Kuala Lumpur">
        </div>

        <div id="lessorFilter" style="display: none;">
            <label>Lessor:</label>
            <select name="lessor_id">
                <option value="">All Lessors</option>
                <?php foreach ($lessors as $lessor): ?>
                    <option value="<?php echo $lessor['LESSORID']; ?>" <?php echo $lessorId == $lessor['LESSORID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lessor['NAME']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="paymentStatusFilter" style="display: none;">
            <label>Payment Status:</label>
            <select name="payment_status">
                <option value="">All Status</option>
                <option value="SUCCESS" <?php echo $paymentStatus === 'SUCCESS' ? 'selected' : ''; ?>>Success</option>
                <option value="PENDING" <?php echo $paymentStatus === 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                <option value="FAILED" <?php echo $paymentStatus === 'FAILED' ? 'selected' : ''; ?>>Failed</option>
            </select>
        </div>

        <button type="submit" name="generate_report">Generate Report</button>
    </form>

    <?php if ($reportGenerated && !empty($reportData)): ?>
        <hr>
        <h2>Report Results</h2>
        
        <!-- Export Button -->
        <div style="margin: 20px 0;">
            <a href="?export=pdf&type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
               style="padding: 10px 20px; background: green; color: white; text-decoration: none;">
                Export to CSV
            </a>
        </div>

        <!-- Display Summary -->
        <?php if (!empty($reportData['summary'])): ?>
            <h3>Summary</h3>
            <div style="border: 1px solid #ccc; padding: 15px; margin: 10px 0; background: #f9f9f9;">
                <?php foreach ($reportData['summary'] as $key => $value): ?>
                    <?php if (!is_array($value)): ?>
                        <p><strong><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</strong> 
                        <?php 
                            if (strpos($key, 'revenue') !== false || strpos($key, 'amount') !== false || strpos($key, 'earnings') !== false) {
                                echo 'RM ' . number_format($value, 2);
                            } else {
                                echo $value;
                            }
                        ?>
                        </p>
                    <?php else: ?>
                        <p><strong><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</strong></p>
                        <ul>
                            <?php foreach ($value as $subKey => $subValue): ?>
                                <li><?php echo htmlspecialchars($subKey); ?>: 
                                    <?php 
                                        if (is_array($subValue)) {
                                            echo 'RM ' . number_format($subValue['total'], 2);
                                        } else {
                                            echo $subValue;
                                        }
                                    ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Display Data Table -->
        <?php if (!empty($reportData['data'])): ?>
            <h3>Detailed Data</h3>
            <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <?php foreach (array_keys($reportData['data'][0]) as $column): ?>
                            <th><?php echo ucwords(str_replace('_', ' ', $column)); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData['data'] as $row): ?>
                        <tr>
                            <?php foreach ($row as $key => $value): ?>
                                <td>
                                    <?php 
                                        // Format currency fields
                                        if (strpos(strtolower($key), 'amount') !== false || 
                                            strpos(strtolower($key), 'revenue') !== false || 
                                            strpos(strtolower($key), 'commission') !== false ||
                                            strpos(strtolower($key), 'earnings') !== false ||
                                            strpos(strtolower($key), 'spent') !== false) {
                                            echo 'RM ' . number_format($value, 2);
                                        }
                                        // Format date fields
                                        elseif (strpos(strtolower($key), 'date') !== false && !empty($value)) {
                                            echo date('Y-m-d H:i', strtotime($value));
                                        }
                                        // Format rating
                                        elseif (strpos(strtolower($key), 'rating') !== false && !empty($value)) {
                                            echo number_format($value, 2) . ' / 5';
                                        }
                                        else {
                                            echo htmlspecialchars($value ?? 'N/A');
                                        }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <!-- Ex2: No data available -->
            <p style="color: orange; padding: 20px; border: 1px solid orange; background: #fff3cd;">
                No data available for the selected criteria. Please adjust your filters.
            </p>
        <?php endif; ?>
    <?php endif; ?>

    <br><br>
    <a href="dashboard.php">← Back to Admin Dashboard</a>

    <!-- JavaScript for dynamic filter display -->
    <script>
        const reportTypeSelect = document.querySelector('select[name="report_type"]');
        const userTypeFilter = document.getElementById('userTypeFilter');
        const carCategoryFilter = document.getElementById('carCategoryFilter');
        const locationFilter = document.getElementById('locationFilter');
        const lessorFilter = document.getElementById('lessorFilter');
        const paymentStatusFilter = document.getElementById('paymentStatusFilter');

        function updateFilters() {
            // Hide all filters first
            userTypeFilter.style.display = 'none';
            carCategoryFilter.style.display = 'none';
            locationFilter.style.display = 'none';
            lessorFilter.style.display = 'none';
            paymentStatusFilter.style.display = 'none';

            const selectedType = reportTypeSelect.value;

            // Show relevant filters based on report type
            switch(selectedType) {
                case 'booking':
                    locationFilter.style.display = 'block';
                    lessorFilter.style.display = 'block';
                    break;
                case 'revenue':
                    lessorFilter.style.display = 'block';
                    break;
                case 'user':
                    userTypeFilter.style.display = 'block';
                    break;
                case 'car_performance':
                    carCategoryFilter.style.display = 'block';
                    locationFilter.style.display = 'block';
                    break;
                case 'payment':
                    paymentStatusFilter.style.display = 'block';
                    break;
            }
        }

        reportTypeSelect.addEventListener('change', updateFilters);
        
        // Initialize filters on page load
        updateFilters();
    </script>
</body>
</html>