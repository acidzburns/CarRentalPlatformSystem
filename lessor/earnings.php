<?php
/**
 * UC21: Earning Tracking (Lessor)
 * Refactored with Royal Blue Theme & Chart.js
 */
require_once '../config/database.php';
require_once '../config/session.php';

require_login('lessor');

$lessorid = get_user_id();
$user_name = get_user_name();

/* --- 1. OVERVIEW SUMMARY --- */
$summary_sql = "SELECT 
                COUNT(*) as TOTAL_BOOKINGS,
                COUNT(CASE WHEN b.BOOKING_STATUS = 'CONFIRMED' THEN 1 END) as CONFIRMED_COUNT,
                COUNT(CASE WHEN b.BOOKING_STATUS = 'COMPLETED' THEN 1 END) as COMPLETED_COUNT,
                SUM(CASE WHEN b.BOOKING_STATUS = 'CONFIRMED' THEN b.TOTAL_AMOUNT * 0.90 ELSE 0 END) as PENDING_EARNINGS,
                SUM(CASE WHEN b.BOOKING_STATUS = 'COMPLETED' THEN b.TOTAL_AMOUNT * 0.90 ELSE 0 END) as RELEASED_EARNINGS
                FROM BOOKING b
                JOIN CAR c ON b.CARID = c.CARID
                WHERE c.LESSORID = :lessorid";

$summary = db_fetch_one($summary_sql, ['lessorid' => $lessorid]);

/* --- 2. LAST 100 TRANSACTIONS FOR CHART --- */
$chart_sql = "SELECT * FROM (
                SELECT 
                p.PAYMENTID as REF,
                (p.AMOUNT * 0.90) as EARNING,
                TO_CHAR(p.PAYMENT_DATE, 'DD/MM') as SHORT_DATE,
                p.PAYMENT_DATE
                FROM PAYMENT p
                JOIN BOOKING b ON p.BOOKINGID = b.BOOKINGID
                JOIN CAR c ON b.CARID = c.CARID
                WHERE c.LESSORID = :lessorid
                AND p.PAYMENT_STATUS = 'SUCCESS'
                ORDER BY p.PAYMENT_DATE DESC
              ) WHERE ROWNUM <= 100
              ORDER BY PAYMENT_DATE ASC";

$chart_data = db_fetch_all($chart_sql, ['lessorid' => $lessorid]);

// Prepare JS labels and data
$chart_labels = [];
$chart_totals = [];
foreach ($chart_data as $row) {
    $chart_labels[] = $row['SHORT_DATE'] . " (#" . $row['REF'] . ")";
    $chart_totals[] = (float)$row['EARNING'];
}

/* --- 3. TRANSACTION HISTORY (Using Payment for backup) --- */
$transactions_sql = "SELECT p.PAYMENTID as TRANSACTIONID, p.PAYMENT_DATE as TRANSACTION_DATE,
                     (p.AMOUNT * 0.90) as AMOUNT, (p.AMOUNT * 0.10) as COMMISSION,
                     b.BOOKINGID, b.BOOKING_STATUS, b.TOTAL_AMOUNT,
                     c.BRAND, c.MODEL, c.LICENSE_PLATE,
                     TO_CHAR(p.PAYMENT_DATE, 'DD-MON-YYYY HH24:MI') as TRANS_DATE_FMT
                     FROM PAYMENT p
                     JOIN BOOKING b ON p.BOOKINGID = b.BOOKINGID
                     JOIN CAR c ON b.CARID = c.CARID
                     WHERE c.LESSORID = :lessorid
                     AND p.PAYMENT_STATUS = 'SUCCESS'
                     ORDER BY p.PAYMENT_DATE DESC";

$transactions = db_fetch_all($transactions_sql, ['lessorid' => $lessorid]);

/* --- 4. CAR BREAKDOWN --- */
$by_car_sql = "SELECT c.BRAND, c.MODEL, c.LICENSE_PLATE,
               COUNT(b.BOOKINGID) as TOTAL_BOOKINGS,
               SUM(CASE WHEN b.BOOKING_STATUS = 'COMPLETED' THEN b.TOTAL_AMOUNT * 0.90 ELSE 0 END) as EARNINGS
               FROM CAR c
               LEFT JOIN BOOKING b ON c.CARID = b.CARID
               WHERE c.LESSORID = :lessorid
               GROUP BY c.BRAND, c.MODEL, c.LICENSE_PLATE
               ORDER BY EARNINGS DESC";

$by_car = db_fetch_all($by_car_sql, ['lessorid' => $lessorid]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Earnings - Car Rental System</title>
    <link rel="stylesheet" href="../assets/css/earning.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav>
        <div class="nav-container">
            <h2><a href="dashboard.php" style="color:white; text-decoration:none;">Car Rental System</a></h2>
            <div class="nav-links">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                <a href="dashboard.php">Dashboard</a>
                <a href="my_cars.php">My Cars</a>
                <a href="bookings.php">Bookings</a>
                <a href="earnings.php" class="active">Earnings</a>
                <a href="submit_ticket.php">Support</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <header class="page-header">
        <h1>Financial Dashboard</h1>
        <p>Monitor your performance and track your revenue growth</p>
    </header>

    <main class="content-container">
        <!-- Overview Section -->
        <div class="card" style="margin-bottom: 30px;">
            <div class="stats-grid">
                <div class="stat-card available">
                    <span class="label">Available Balance</span>
                    <span class="value">RM <?php echo number_format($summary['RELEASED_EARNINGS'] ?? 0, 2); ?></span>
                </div>
                <div class="stat-card pending">
                    <span class="label">Pending Escrow</span>
                    <span class="value">RM <?php echo number_format($summary['PENDING_EARNINGS'] ?? 0, 2); ?></span>
                </div>
                <div class="stat-card">
                    <span class="label">Total Bookings</span>
                    <span class="value"><?php echo $summary['TOTAL_BOOKINGS'] ?? 0; ?></span>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Left: Chart & Table -->
            <div class="main-stats">
                <div class="card chart-card">
                    <h2>Recent Earnings (Last 100 Transactions)</h2>
                    <canvas id="earningsChart"></canvas>
                </div>

                <div class="card">
                    <h2>🚗 Revenue by Vehicle</h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Car Model</th>
                                    <th>Plate No.</th>
                                    <th>Bookings</th>
                                    <th>Total Earnings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($by_car as $car): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($car['BRAND'] . ' ' . $car['MODEL']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($car['LICENSE_PLATE']); ?></td>
                                    <td><?php echo $car['TOTAL_BOOKINGS']; ?></td>
                                    <td style="color:#1864FF; font-weight:700;">RM <?php echo number_format($car['EARNINGS'] ?? 0, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right: Payout & info -->
            <aside class="sidebar">
                <div class="card guidelines-box">
                    <h4><i class="fas fa-wallet"></i> Payout Policy</h4>
                    <ul>
                        <li>Platform takes 10% commission.</li>
                        <li>Escrow releases 24h after completion.</li>
                        <li>Manual bank transfers on Fridays.</li>
                    </ul>
                </div>

                <div class="card" style="margin-top:20px;">
                    <h3>Bank Details</h3>
                    <?php 
                    $bank_info = db_fetch_one("SELECT BANK_ACCOUNT, BUSINESS_NAME FROM LESSOR WHERE LESSORID = :id", ['id' => $lessorid]);
                    if ($bank_info && $bank_info['BANK_ACCOUNT']): ?>
                        <p style="font-size:14px; margin-top:10px;">
                            <strong><?php echo htmlspecialchars($bank_info['BUSINESS_NAME']); ?></strong><br>
                            <span style="color:#64748b;"><?php echo htmlspecialchars($bank_info['BANK_ACCOUNT']); ?></span>
                        </p>
                    <?php else: ?>
                        <p style="font-size:13px; color:#ef4444; margin-top:10px;">Bank details missing.</p>
                        <a href="edit_profile.php" style="font-size:13px; color:#1864FF; text-decoration:underline;">Update Profile</a>
                    <?php endif; ?>
                </div>
            </aside>
        </div>

        <!-- Full Width: Transaction History -->
        <div class="card">
            <h2>📜 Transaction History</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Date</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Comm (10%)</th>
                            <th>Earning (90%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">No transactions found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td style="font-family:monospace; font-size:12px;">TXN-<?php echo $t['TRANSACTIONID']; ?></td>
                                <td style="font-size:13px;"><?php echo $t['TRANS_DATE_FMT']; ?></td>
                                <td>
                                    <span style="display:block; font-weight:600;"><?php echo htmlspecialchars($t['BRAND'] . ' ' . $t['MODEL']); ?></span>
                                    <span style="font-size:12px; color:#64748b;">Plate: <?php echo $t['LICENSE_PLATE']; ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $t['BOOKING_STATUS'] === 'COMPLETED' ? 'badge-success' : 'badge-warning'; ?>">
                                        <?php echo $t['BOOKING_STATUS'] === 'COMPLETED' ? 'Released' : 'In Escrow'; ?>
                                    </span>
                                </td>
                                <td style="color:#ef4444;">-RM <?php echo number_format($t['COMMISSION'], 2); ?></td>
                                <td style="font-weight:700; color:#059669;">RM <?php echo number_format($t['AMOUNT'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        const ctx = document.getElementById('earningsChart').getContext('2d');
        const labels = <?php echo json_encode($chart_labels); ?>;
        const data = <?php echo json_encode($chart_totals); ?>;

        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(24, 100, 255, 0.2)');
        gradient.addColorStop(1, 'rgba(24, 100, 255, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Transaction Earning (RM)',
                    data: data,
                    borderColor: '#1864FF',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderWidth: 2,
                    pointBorderColor: '#1864FF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            callback: function(value) { return 'RM' + value; },
                            font: { size: 11 }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        });
    </script>
</body>
</html>