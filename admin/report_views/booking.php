<!-- Booking Report -->
<h2>📅 Booking Report</h2>
<p style="color: #666;">
    Report Period: <strong><?php echo date('d M Y', strtotime($date_from)); ?></strong> 
    to <strong><?php echo date('d M Y', strtotime($date_to)); ?></strong>
</p>

<!-- Summary Statistics -->
<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px;">
    <h3>📊 Summary Statistics</h3>
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #e3f2fd;">
                <th style="padding: 10px;">Total Bookings</th>
                <th style="padding: 10px;">Pending</th>
                <th style="padding: 10px;">Confirmed</th>
                <th style="padding: 10px;">Completed</th>
                <th style="padding: 10px;">Cancelled</th>
            </tr>
        </thead>
        <tbody>
            <tr style="text-align: center;">
                <td style="padding: 15px; font-size: 24px; font-weight: bold;">
                    <?php echo $report_data['summary']['TOTAL']; ?>
                </td>
                <td style="padding: 15px; font-size: 20px; color: #FF9800;">
                    <?php echo $report_data['summary']['PENDING']; ?>
                </td>
                <td style="padding: 15px; font-size: 20px; color: #2196F3;">
                    <?php echo $report_data['summary']['CONFIRMED']; ?>
                </td>
                <td style="padding: 15px; font-size: 20px; color: #4CAF50;">
                    <?php echo $report_data['summary']['COMPLETED']; ?>
                </td>
                <td style="padding: 15px; font-size: 20px; color: #f44336;">
                    <?php echo $report_data['summary']['CANCELLED']; ?>
                </td>
            </tr>
        </tbody>
    </table>
    
    <table border="1" style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <thead>
            <tr style="background: #e8f5e9;">
                <th style="padding: 10px;">Total Revenue</th>
                <th style="padding: 10px;">Average Booking Value</th>
                <th style="padding: 10px;">Completion Rate</th>
                <th style="padding: 10px;">Cancellation Rate</th>
            </tr>
        </thead>
        <tbody>
            <tr style="text-align: center;">
                <td style="padding: 15px; font-size: 24px; font-weight: bold; color: #4CAF50;">
                    RM <?php echo number_format($report_data['summary']['TOTAL_REVENUE'], 2); ?>
                </td>
                <td style="padding: 15px; font-size: 20px;">
                    RM <?php echo number_format($report_data['summary']['AVG_BOOKING_VALUE'], 2); ?>
                </td>
                <td style="padding: 15px; font-size: 20px; color: #4CAF50;">
                    <?php 
                    $completion_rate = $report_data['summary']['TOTAL'] > 0 ? 
                        ($report_data['summary']['COMPLETED'] / $report_data['summary']['TOTAL'] * 100) : 0;
                    echo number_format($completion_rate, 1); 
                    ?>%
                </td>
                <td style="padding: 15px; font-size: 20px; color: #f44336;">
                    <?php 
                    $cancellation_rate = $report_data['summary']['TOTAL'] > 0 ? 
                        ($report_data['summary']['CANCELLED'] / $report_data['summary']['TOTAL'] * 100) : 0;
                    echo number_format($cancellation_rate, 1); 
                    ?>%
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Daily Booking Trend -->
<?php if (!empty($report_data['daily_bookings'])): ?>
<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px;">
    <h3>📈 Daily Booking Trend</h3>
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #fff3cd;">
                <th style="padding: 10px;">Date</th>
                <th style="padding: 10px;">Number of Bookings</th>
                <th style="padding: 10px;">Daily Revenue</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report_data['daily_bookings'] as $day): ?>
                <tr>
                    <td style="padding: 10px;"><?php echo htmlspecialchars($day['DATE_LABEL']); ?></td>
                    <td style="padding: 10px; text-align: center; font-weight: bold;">
                        <?php echo $day['BOOKING_COUNT']; ?>
                    </td>
                    <td style="padding: 10px; text-align: right; color: #4CAF50;">
                        RM <?php echo number_format($day['DAILY_REVENUE'], 2); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Detailed Booking List -->
<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px;">
    <h3>📋 Detailed Booking List</h3>
    <?php if (empty($report_data['bookings'])): ?>
        <p>No bookings found for the selected period.</p>
    <?php else: ?>
        <table border="1" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #e3f2fd;">
                    <th style="padding: 8px;">ID</th>
                    <th style="padding: 8px;">Renter</th>
                    <th style="padding: 8px;">Car</th>
                    <th style="padding: 8px;">Owner</th>
                    <th style="padding: 8px;">Rental Period</th>
                    <th style="padding: 8px;">Status</th>
                    <th style="padding: 8px;">Amount</th>
                    <th style="padding: 8px;">Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data['bookings'] as $booking): ?>
                    <tr>
                        <td style="padding: 8px;">#<?php echo $booking['BOOKINGID']; ?></td>
                        <td style="padding: 8px;">
                            <?php echo htmlspecialchars($booking['RENTER_NAME']); ?><br>
                            <small style="color: #666;"><?php echo htmlspecialchars($booking['RENTER_EMAIL']); ?></small>
                        </td>
                        <td style="padding: 8px;">
                            <?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL']); ?><br>
                            <small style="color: #666;"><?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></small>
                        </td>
                        <td style="padding: 8px;"><?php echo htmlspecialchars($booking['LESSOR_NAME']); ?></td>
                        <td style="padding: 8px; font-size: 12px;">
                            <?php echo date('d M Y', strtotime($booking['START_DATE'])); ?><br>
                            to<br>
                            <?php echo date('d M Y', strtotime($booking['END_DATE'])); ?>
                        </td>
                        <td style="padding: 8px;">
                            <span style="padding: 3px 8px; border-radius: 3px; font-size: 11px;
                                <?php 
                                echo $booking['BOOKING_STATUS'] == 'CONFIRMED' ? 'background: #e3f2fd; color: #1976d2;' : 
                                     ($booking['BOOKING_STATUS'] == 'COMPLETED' ? 'background: #e8f5e9; color: #388e3c;' : 
                                     ($booking['BOOKING_STATUS'] == 'CANCELLED' ? 'background: #ffebee; color: #d32f2f;' : 
                                     'background: #fff3cd; color: #f57c00;')); 
                                ?>">
                                <?php echo $booking['BOOKING_STATUS']; ?>
                            </span>
                        </td>
                        <td style="padding: 8px; text-align: right; font-weight: bold;">
                            RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?>
                        </td>
                        <td style="padding: 8px; font-size: 11px;">
                            <?php echo htmlspecialchars($booking['CREATED_FMT']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f0f0f0; font-weight: bold;">
                    <td colspan="6" style="padding: 10px; text-align: right;">TOTAL:</td>
                    <td style="padding: 10px; text-align: right; color: #4CAF50; font-size: 16px;">
                        RM <?php echo number_format($report_data['summary']['TOTAL_REVENUE'], 2); ?>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        
        <p style="margin-top: 15px; font-size: 14px; color: #666;">
            <strong>Total Records:</strong> <?php echo count($report_data['bookings']); ?> bookings
        </p>
    <?php endif; ?>
</div>

<!-- Key Insights -->
<div style="background: #e8f5e9; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #4CAF50;">
    <h3>💡 Key Insights</h3>
    <ul style="font-size: 15px; line-height: 2;">
        <li>
            <strong>Peak Booking Day:</strong> 
            <?php 
            if (!empty($report_data['daily_bookings'])) {
                $max_day = array_reduce($report_data['daily_bookings'], function($carry, $item) {
                    return (!$carry || $item['BOOKING_COUNT'] > $carry['BOOKING_COUNT']) ? $item : $carry;
                });
                echo htmlspecialchars($max_day['DATE_LABEL']) . ' (' . $max_day['BOOKING_COUNT'] . ' bookings)';
            } else {
                echo 'N/A';
            }
            ?>
        </li>
        <li>
            <strong>Success Rate:</strong> 
            <?php 
            $success = $report_data['summary']['CONFIRMED'] + $report_data['summary']['COMPLETED'];
            $success_rate = $report_data['summary']['TOTAL'] > 0 ? ($success / $report_data['summary']['TOTAL'] * 100) : 0;
            echo number_format($success_rate, 1); 
            ?>% (<?php echo $success; ?> out of <?php echo $report_data['summary']['TOTAL']; ?> bookings)
        </li>
        <li>
            <strong>Average Daily Bookings:</strong> 
            <?php 
            $days = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24) + 1;
            $avg_daily = $days > 0 ? $report_data['summary']['TOTAL'] / $days : 0;
            echo number_format($avg_daily, 1); 
            ?> bookings per day
        </li>
        <li>
            <strong>Average Daily Revenue:</strong> 
            RM <?php 
            $avg_daily_revenue = $days > 0 ? $report_data['summary']['TOTAL_REVENUE'] / $days : 0;
            echo number_format($avg_daily_revenue, 2); 
            ?>
        </li>
    </ul>
</div>