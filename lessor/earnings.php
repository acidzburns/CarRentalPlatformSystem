<?php
/**
 * UC21: Earning Tracking (SRS Pages 71-74)
 * Lessor tracks earnings, escrow status, and payment history
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Check if lessor is logged in
require_login('lessor');

$lessorid = get_user_id();

// UC21 Step 2: Calculate total earnings, pending, and available balance
$summary_sql = "SELECT 
                COUNT(*) as TOTAL_BOOKINGS,
                COUNT(CASE WHEN b.BOOKING_STATUS = 'CONFIRMED' THEN 1 END) as CONFIRMED_COUNT,
                COUNT(CASE WHEN b.BOOKING_STATUS = 'COMPLETED' THEN 1 END) as COMPLETED_COUNT,
                SUM(CASE WHEN b.BOOKING_STATUS = 'CONFIRMED' THEN b.TOTAL_AMOUNT * 0.90 ELSE 0 END) as PENDING_EARNINGS,
                SUM(CASE WHEN b.BOOKING_STATUS = 'COMPLETED' THEN b.TOTAL_AMOUNT * 0.90 ELSE 0 END) as RELEASED_EARNINGS,
                SUM(CASE WHEN b.BOOKING_STATUS = 'COMPLETED' THEN b.TOTAL_AMOUNT ELSE 0 END) as TOTAL_BOOKING_VALUE,
                SUM(CASE WHEN b.BOOKING_STATUS = 'COMPLETED' THEN b.TOTAL_AMOUNT * 0.10 ELSE 0 END) as TOTAL_COMMISSION
                FROM BOOKING b
                JOIN CAR c ON b.CARID = c.CARID
                WHERE c.LESSORID = :lessorid";

$stmt_summary = oci_parse($conn, $summary_sql);
oci_bind_by_name($stmt_summary, ':lessorid', $lessorid);
oci_execute($stmt_summary);
$summary = oci_fetch_assoc($stmt_summary);

// UC21 Step 6: View detailed transaction history
$transactions_sql = "SELECT t.TRANSACTIONID, t.BOOKINGID, t.AMOUNT, t.COMMISSION, t.TRANSACTION_DATE,
                     b.BOOKING_STATUS, b.TOTAL_AMOUNT, b.START_DATE, b.END_DATE,
                     c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE,
                     r.NAME as RENTER_NAME,
                     TO_CHAR(t.TRANSACTION_DATE, 'DD-MON-YYYY HH24:MI') as TRANS_DATE_FMT,
                     TO_CHAR(b.START_DATE, 'DD-MON-YYYY') as START_FMT,
                     TO_CHAR(b.END_DATE, 'DD-MON-YYYY') as END_FMT
                     FROM TRANSACTION t
                     JOIN BOOKING b ON t.BOOKINGID = b.BOOKINGID
                     JOIN CAR c ON b.CARID = c.CARID
                     JOIN RENTER r ON b.RENTERID = r.RENTERID
                     WHERE t.LESSORID = :lessorid
                     ORDER BY t.TRANSACTION_DATE DESC";

$stmt_trans = oci_parse($conn, $transactions_sql);
oci_bind_by_name($stmt_trans, ':lessorid', $lessorid);
oci_execute($stmt_trans);

$transactions = [];
while ($row = oci_fetch_assoc($stmt_trans)) {
    $transactions[] = $row;
}

// UC21 Step 9: Earnings by car
$by_car_sql = "SELECT c.CARID, c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE,
               COUNT(b.BOOKINGID) as TOTAL_BOOKINGS,
               COUNT(CASE WHEN b.BOOKING_STATUS = 'COMPLETED' THEN 1 END) as COMPLETED_BOOKINGS,
               SUM(CASE WHEN b.BOOKING_STATUS = 'COMPLETED' THEN b.TOTAL_AMOUNT * 0.90 ELSE 0 END) as TOTAL_EARNINGS
               FROM CAR c
               LEFT JOIN BOOKING b ON c.CARID = b.CARID
               WHERE c.LESSORID = :lessorid
               GROUP BY c.CARID, c.BRAND, c.MODEL, c.YEAR, c.LICENSE_PLATE
               ORDER BY TOTAL_EARNINGS DESC";

$stmt_by_car = oci_parse($conn, $by_car_sql);
oci_bind_by_name($stmt_by_car, ':lessorid', $lessorid);
oci_execute($stmt_by_car);

$earnings_by_car = [];
while ($row = oci_fetch_assoc($stmt_by_car)) {
    $earnings_by_car[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Earnings - Lessor</title>
</head>
<body>
    <h1>💰 My Earnings</h1>
    
    <nav>
        <a href="dashboard.php">Dashboard</a> |
        <a href="my_cars.php">My Cars</a> |
        <a href="bookings.php">Bookings</a> |
        <a href="earnings.php">Earnings</a> |
        <a href="../auth/logout.php">Logout</a>
    </nav>
    
    <hr>
    
    <?php display_flash_message(); ?>
    
    <!-- UC21 Step 3: Display earnings summary -->
    <h2>Earnings Overview</h2>
    
    <table border="1" style="width: 100%; margin: 20px 0;">
        <tr style="background: #e8f5e9;">
            <th>💵 Released Earnings (Available)</th>
            <td style="font-size: 24px; font-weight: bold; color: #4CAF50;">
                RM <?php echo number_format($summary['RELEASED_EARNINGS'] ?? 0, 2); ?>
            </td>
        </tr>
        <tr style="background: #fff3cd;">
            <th>⏳ Pending Earnings (In Escrow)</th>
            <td style="font-size: 20px; font-weight: bold; color: #FF9800;">
                RM <?php echo number_format($summary['PENDING_EARNINGS'] ?? 0, 2); ?>
            </td>
        </tr>
        <tr>
            <th>📊 Total Earnings (All Time)</th>
            <td style="font-size: 18px; font-weight: bold;">
                RM <?php echo number_format(($summary['RELEASED_EARNINGS'] ?? 0) + ($summary['PENDING_EARNINGS'] ?? 0), 2); ?>
            </td>
        </tr>
    </table>
    
    <!-- Escrow Explanation -->
    <div style="background: #e3f2fd; padding: 15px; margin: 20px 0; border-left: 4px solid #2196F3;">
        <h3>📋 Understanding Your Earnings</h3>
        <ul>
            <li><strong>Released Earnings:</strong> Money from completed bookings. Platform will manually transfer to your bank account within 24 hours after completion.</li>
            <li><strong>Pending Earnings:</strong> Money from confirmed bookings currently held by platform. Released after you mark booking as completed.</li>
            <li><strong>Platform Commission:</strong> 10% fee automatically deducted from each booking (you receive 90%).</li>
            <li><strong>Note:</strong> This system tracks your earnings. Platform team handles actual bank transfers manually.</li>
        </ul>
    </div>
    
    <hr>
    
    <!-- Statistics -->
    <h2>📊 Booking Statistics</h2>
    <table border="1">
        <tr>
            <th>Total Bookings</th>
            <td><?php echo $summary['TOTAL_BOOKINGS']; ?></td>
        </tr>
        <tr>
            <th>Confirmed (In Progress)</th>
            <td><?php echo $summary['CONFIRMED_COUNT']; ?></td>
        </tr>
        <tr>
            <th>Completed</th>
            <td><?php echo $summary['COMPLETED_COUNT']; ?></td>
        </tr>
    </table>
    
    <hr>
    
    <!-- Detailed Breakdown -->
    <h2>💳 Financial Breakdown</h2>
    <table border="1">
        <tr>
            <th>Total Booking Value (100%)</th>
            <td>RM <?php echo number_format($summary['TOTAL_BOOKING_VALUE'] ?? 0, 2); ?></td>
        </tr>
        <tr style="background: #ffebee;">
            <th>Platform Commission (10%)</th>
            <td>- RM <?php echo number_format($summary['TOTAL_COMMISSION'] ?? 0, 2); ?></td>
        </tr>
        <tr style="background: #e8f5e9;">
            <th>Your Net Earnings (90%)</th>
            <td><strong>RM <?php echo number_format($summary['RELEASED_EARNINGS'] ?? 0, 2); ?></strong></td>
        </tr>
    </table>
    
    <hr>
    
    <!-- UC21 Step 8: Earnings by car -->
    <h2>🚗 Earnings by Car</h2>
    
    <?php if (empty($earnings_by_car)): ?>
        <p>No cars listed yet.</p>
    <?php else: ?>
        <table border="1" style="width: 100%;">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th>Car</th>
                    <th>License Plate</th>
                    <th>Total Bookings</th>
                    <th>Completed</th>
                    <th>Earnings (90%)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($earnings_by_car as $car): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($car['BRAND'] . ' ' . $car['MODEL'] . ' ' . $car['YEAR']); ?></td>
                        <td><?php echo htmlspecialchars($car['LICENSE_PLATE']); ?></td>
                        <td><?php echo $car['TOTAL_BOOKINGS']; ?></td>
                        <td><?php echo $car['COMPLETED_BOOKINGS']; ?></td>
                        <td><strong>RM <?php echo number_format($car['TOTAL_EARNINGS'] ?? 0, 2); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <hr>
    
    <!-- UC21 Step 5: Transaction history -->
    <h2>📜 Transaction History</h2>
    
    <?php if (empty($transactions)): ?>
        <p>No transactions yet.</p>
    <?php else: ?>
        <table border="1" style="width: 100%;">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th>Transaction ID</th>
                    <th>Booking ID</th>
                    <th>Date</th>
                    <th>Car</th>
                    <th>Renter</th>
                    <th>Rental Period</th>
                    <th>Status</th>
                    <th>Booking Value</th>
                    <th>Commission</th>
                    <th>Your Earnings</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $trans): ?>
                    <tr>
                        <td>#<?php echo $trans['TRANSACTIONID']; ?></td>
                        <td>#<?php echo $trans['BOOKINGID']; ?></td>
                        <td><?php echo htmlspecialchars($trans['TRANS_DATE_FMT']); ?></td>
                        <td><?php echo htmlspecialchars($trans['BRAND'] . ' ' . $trans['MODEL'] . ' ' . $trans['YEAR']); ?></td>
                        <td><?php echo htmlspecialchars($trans['RENTER_NAME']); ?></td>
                        <td><?php echo htmlspecialchars($trans['START_FMT'] . ' to ' . $trans['END_FMT']); ?></td>
                        <td>
                            <?php if ($trans['BOOKING_STATUS'] == 'CONFIRMED'): ?>
                                <span style="color: orange;">⏳ IN ESCROW</span>
                            <?php elseif ($trans['BOOKING_STATUS'] == 'COMPLETED'): ?>
                                <span style="color: green;">✓ RELEASED</span>
                            <?php else: ?>
                                <?php echo htmlspecialchars($trans['BOOKING_STATUS']); ?>
                            <?php endif; ?>
                        </td>
                        <td>RM <?php echo number_format($trans['TOTAL_AMOUNT'], 2); ?></td>
                        <td style="color: red;">- RM <?php echo number_format($trans['COMMISSION'], 2); ?></td>
                        <td style="font-weight: bold; color: green;">RM <?php echo number_format($trans['AMOUNT'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <hr>
    
    <!-- UC21 Step 10: Request payout (Future feature) -->
    <div style="background: #fff3cd; padding: 20px; margin: 20px 0; border-left: 4px solid #ffc107;">
        <h3>💳 Payout Information</h3>
        <p><strong>Your Bank Account:</strong> 
            <?php 
            // Get lessor bank details
            $bank_sql = "SELECT BANK_ACCOUNT, BUSINESS_NAME FROM LESSOR WHERE LESSORID = :lessorid";
            $stmt_bank = oci_parse($conn, $bank_sql);
            oci_bind_by_name($stmt_bank, ':lessorid', $lessorid);
            oci_execute($stmt_bank);
            $bank_info = oci_fetch_assoc($stmt_bank);
            
            if ($bank_info && $bank_info['BANK_ACCOUNT']) {
                echo htmlspecialchars($bank_info['BANK_ACCOUNT']) . ' (' . htmlspecialchars($bank_info['BUSINESS_NAME']) . ')';
            } else {
                echo 'Not set - <a href="edit_profile.php">Update your bank details</a>';
            }
            ?>
        </p>
        
        <p><strong>Payment Process:</strong></p>
        <ol>
            <li>You mark booking as completed</li>
            <li>Platform team receives notification</li>
            <li>Platform manually transfers earnings to your bank account <strong>within 24 hours</strong></li>
            <li>You receive payment confirmation</li>
        </ol>
        
        <p style="color: #666;"><em>Note: This system is for tracking earnings only. Platform team handles all bank transfers manually to ensure security and accuracy.</em></p>
        
        <?php if (($summary['RELEASED_EARNINGS'] ?? 0) > 0): ?>
            <p style="color: green; font-weight: bold;">✓ You have RM <?php echo number_format($summary['RELEASED_EARNINGS'], 2); ?> pending manual transfer from platform.</p>
        <?php else: ?>
            <p style="color: #666;">No pending earnings. Complete bookings to earn money!</p>
        <?php endif; ?>
    </div>
    
    <!-- UC21 Step 11: Export reports (Future feature) -->
    <div style="margin: 20px 0;">
        <h3>📊 Export Reports</h3>
        <p>Download your earnings report:</p>
        <button disabled>📄 Export PDF (Coming Soon)</button>
        <button disabled>📊 Export Excel (Coming Soon)</button>
    </div>
    
    <hr>
    
    <p><a href="dashboard.php">← Back to Dashboard</a></p>
</body>
</html>