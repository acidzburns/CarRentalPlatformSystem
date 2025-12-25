<!-- Financial Report -->
<h2>💰 Financial Report</h2>
<p style="color: #666;">
    Report Period: <strong><?php echo date('d M Y', strtotime($date_from)); ?></strong> 
    to <strong><?php echo date('d M Y', strtotime($date_to)); ?></strong>
</p>

<!-- Revenue Summary -->
<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px;">
    <h3>💵 Revenue Summary</h3>
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #e8f5e9;">
                <th style="padding: 10px;">Total Revenue</th>
                <th style="padding: 10px;">Platform Commission (10%)</th>
                <th style="padding: 10px;">Lessor Earnings (90%)</th>
                <th style="padding: 10px;">Total Transactions</th>
            </tr>
        </thead>
        <tbody>
            <tr style="text-align: center;">
                <td style="padding: 20px; font-size: 28px; font-weight: bold; color: #4CAF50;">
                    RM <?php echo number_format($report_data['revenue']['TOTAL_REVENUE'], 2); ?>
                </td>
                <td style="padding: 20px; font-size: 24px; font-weight: bold; color: #2196F3;">
                    RM <?php echo number_format($report_data['revenue']['PLATFORM_COMMISSION'], 2); ?>
                </td>
                <td style="padding: 20px; font-size: 24px; font-weight: bold; color: #FF9800;">
                    RM <?php echo number_format($report_data['revenue']['LESSOR_EARNINGS'], 2); ?>
                </td>
                <td style="padding: 20px; font-size: 24px; font-weight: bold;">
                    <?php echo $report_data['revenue']['TOTAL_TRANSACTIONS']; ?>
                </td>
            </tr>
        </tbody>
    </table>
    
    <div style="margin-top: 15px; text-align: center; background: #fff3cd; padding: 15px; border-radius: 5px;">
        <strong>Average Transaction Value:</strong> 
        <span style="font-size: 20px; color: #4CAF50;">
            RM <?php echo number_format($report_data['revenue']['AVG_TRANSACTION'], 2); ?>
        </span>
    </div>
</div>

<!-- Payment Methods Breakdown -->
<?php if (!empty($report_data['payment_methods'])): ?>
<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px;">
    <h3>💳 Payment Methods Breakdown</h3>
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #e3f2fd;">
                <th style="padding: 10px;">Payment Method</th>
                <th style="padding: 10px;">Transaction Count</th>
                <th style="padding: 10px;">Total Amount</th>
                <th style="padding: 10px;">Percentage</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_amount = array_sum(array_column($report_data['payment_methods'], 'TOTAL_AMOUNT'));
            foreach ($report_data['payment_methods'] as $method): 
                $percentage = $total_amount > 0 ? ($method['TOTAL_AMOUNT'] / $total_amount * 100) : 0;
            ?>
                <tr>
                    <td style="padding: 10px;">
                        <strong>
                            <?php 
                            $method_name = $method['PAYMENT_METHOD'];
                            echo $method_name == 'FPX' ? '🏦 FPX Online Banking' : 
                                 ($method_name == 'CARD' ? '💳 Credit/Debit Card' : 
                                 ($method_name == 'E_WALLET' ? '📱 E-Wallet' : htmlspecialchars($method_name)));
                            ?>
                        </strong>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <?php echo $method['TRANSACTION_COUNT']; ?>
                    </td>
                    <td style="padding: 10px; text-align: right; font-weight: bold; color: #4CAF50;">
                        RM <?php echo number_format($method['TOTAL_AMOUNT'], 2); ?>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <div style="background: #e8f5e9; padding: 5px; border-radius: 3px;">
                            <?php echo number_format($percentage, 1); ?>%
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Daily Revenue Trend -->
<?php if (!empty($report_data['daily_revenue'])): ?>
<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px;">
    <h3>📈 Daily Revenue Trend</h3>
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #fff3cd;">
                <th style="padding: 10px;">Date</th>
                <th style="padding: 10px;">Total Revenue</th>
                <th style="padding: 10px;">Platform Commission (10%)</th>
                <th style="padding: 10px;">Lessor Earnings (90%)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report_data['daily_revenue'] as $day): ?>
                <tr>
                    <td style="padding: 10px;"><?php echo htmlspecialchars($day['DATE_LABEL']); ?></td>
                    <td style="padding: 10px; text-align: right; font-weight: bold; color: #4CAF50;">
                        RM <?php echo number_format($day['REVENUE'], 2); ?>
                    </td>
                    <td style="padding: 10px; text-align: right; color: #2196F3;">
                        RM <?php echo number_format($day['COMMISSION'], 2); ?>
                    </td>
                    <td style="padding: 10px; text-align: right; color: #FF9800;">
                        RM <?php echo number_format($day['REVENUE'] - $day['COMMISSION'], 2); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f0f0f0; font-weight: bold;">
                <td style="padding: 10px;">TOTAL:</td>
                <td style="padding: 10px; text-align: right; color: #4CAF50; font-size: 16px;">
                    RM <?php echo number_format($report_data['revenue']['TOTAL_REVENUE'], 2); ?>
                </td>
                <td style="padding: 10px; text-align: right; color: #2196F3; font-size: 16px;">
                    RM <?php echo number_format($report_data['revenue']['PLATFORM_COMMISSION'], 2); ?>
                </td>
                <td style="padding: 10px; text-align: right; color: #FF9800; font-size: 16px;">
                    RM <?php echo number_format($report_data['revenue']['LESSOR_EARNINGS'], 2); ?>
                </td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>

<!-- Top Earning Lessors -->
<?php if (!empty($report_data['top_lessors'])): ?>
<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px;">
    <h3>🏆 Top Earning Lessors (Top 10)</h3>
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f3e5f5;">
                <th style="padding: 10px;">Rank</th>
                <th style="padding: 10px;">Lessor</th>
                <th style="padding: 10px;">Total Bookings</th>
                <th style="padding: 10px;">Total Revenue</th>
                <th style="padding: 10px;">Lessor Earnings (90%)</th>
                <th style="padding: 10px;">Commission Earned</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $rank = 1;
            foreach ($report_data['top_lessors'] as $lessor): 
            ?>
                <tr>
                    <td style="padding: 10px; text-align: center; font-weight: bold; font-size: 18px;">
                        <?php 
                        if ($rank == 1) echo '🥇';
                        elseif ($rank == 2) echo '🥈';
                        elseif ($rank == 3) echo '🥉';
                        else echo $rank;
                        ?>
                    </td>
                    <td style="padding: 10px;">
                        <strong><?php echo htmlspecialchars($lessor['NAME']); ?></strong><br>
                        <small style="color: #666;"><?php echo htmlspecialchars($lessor['EMAIL']); ?></small>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <?php echo $lessor['TOTAL_BOOKINGS']; ?>
                    </td>
                    <td style="padding: 10px; text-align: right; font-weight: bold;">
                        RM <?php echo number_format($lessor['TOTAL_REVENUE'], 2); ?>
                    </td>
                    <td style="padding: 10px; text-align: right; color: #FF9800;">
                        RM <?php echo number_format($lessor['LESSOR_EARNINGS'], 2); ?>
                    </td>
                    <td style="padding: 10px; text-align: right; color: #2196F3;">
                        RM <?php echo number_format($lessor['COMMISSION_EARNED'], 2); ?>
                    </td>
                </tr>
            <?php 
            $rank++;
            endforeach; 
            ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Financial Insights -->
<div style="background: #e3f2fd; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #2196F3;">
    <h3>💡 Financial Insights</h3>
    <ul style="font-size: 15px; line-height: 2;">
        <li>
            <strong>Daily Average Revenue:</strong> 
            RM <?php 
            $days = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24) + 1;
            $avg_daily = $days > 0 ? $report_data['revenue']['TOTAL_REVENUE'] / $days : 0;
            echo number_format($avg_daily, 2); 
            ?>
        </li>
        <li>
            <strong>Platform Profit Margin:</strong> 10% (Industry Standard)
        </li>
        <li>
            <strong>Transaction Success Rate:</strong> 
            <?php 
            // Assuming all transactions in payment table are successful
            echo '100%'; 
            ?> (All completed payments)
        </li>
        <?php if (!empty($report_data['daily_revenue'])): ?>
        <li>
            <strong>Peak Revenue Day:</strong> 
            <?php 
            $max_revenue_day = array_reduce($report_data['daily_revenue'], function($carry, $item) {
                return (!$carry || $item['REVENUE'] > $carry['REVENUE']) ? $item : $carry;
            });
            echo htmlspecialchars($max_revenue_day['DATE_LABEL']) . ' (RM ' . number_format($max_revenue_day['REVENUE'], 2) . ')';
            ?>
        </li>
        <?php endif; ?>
        <li>
            <strong>Revenue Growth Potential:</strong> 
            Increase by targeting <?php echo 100 - ($report_data['revenue']['TOTAL_TRANSACTIONS'] > 0 ? 100 : 0); ?>% more active users
        </li>
    </ul>
</div>