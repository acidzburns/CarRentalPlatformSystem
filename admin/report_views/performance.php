<!-- Lessor Performance Report -->
<h2>📈 Lessor Performance Report</h2>
<p style="color: #666;">
    Report Period: <strong><?php echo date('d M Y', strtotime($date_from)); ?></strong> 
    to <strong><?php echo date('d M Y', strtotime($date_to)); ?></strong>
</p>

<!-- Lessor Performance Summary -->
<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px;">
    <h3>🏆 Lessor Performance Summary</h3>
    <?php if (empty($report_data['lessor_performance'])): ?>
        <p>No lessor activity found for the selected period.</p>
    <?php else: ?>
        <table border="1" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f3e5f5;">
                    <th style="padding: 10px;">Rank</th>
                    <th style="padding: 10px;">Lessor Details</th>
                    <th style="padding: 10px;">Cars</th>
                    <th style="padding: 10px;">Bookings</th>
                    <th style="padding: 10px;">Completed</th>
                    <th style="padding: 10px;">Cancelled</th>
                    <th style="padding: 10px;">Total Revenue</th>
                    <th style="padding: 10px;">Earnings (90%)</th>
                    <th style="padding: 10px;">Avg Rating</th>
                    <th style="padding: 10px;">Reviews</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                foreach ($report_data['lessor_performance'] as $lessor): 
                    $completion_rate = $lessor['TOTAL_BOOKINGS'] > 0 ? 
                        ($lessor['COMPLETED_BOOKINGS'] / $lessor['TOTAL_BOOKINGS'] * 100) : 0;
                ?>
                    <tr>
                        <td style="padding: 10px; text-align: center; font-weight: bold; font-size: 18px;">
                            <?php 
                            if ($rank == 1) echo '🥇';
                            elseif ($rank == 2) echo '🥈';
                            elseif ($rank == 3) echo '🥉';
                            else echo '#' . $rank;
                            ?>
                        </td>
                        <td style="padding: 10px;">
                            <strong><?php echo htmlspecialchars($lessor['NAME']); ?></strong><br>
                            <small style="color: #666;">
                                <?php echo htmlspecialchars($lessor['EMAIL']); ?><br>
                                <?php if (!empty($lessor['BUSINESS_NAME'])): ?>
                                    <?php echo htmlspecialchars($lessor['BUSINESS_NAME']); ?><br>
                                <?php endif; ?>
                                Joined: <?php echo htmlspecialchars($lessor['JOINED_DATE']); ?>
                            </small>
                        </td>
                        <td style="padding: 10px; text-align: center; font-size: 18px; font-weight: bold;">
                            <?php echo $lessor['TOTAL_CARS']; ?>
                        </td>
                        <td style="padding: 10px; text-align: center; font-size: 18px; font-weight: bold;">
                            <?php echo $lessor['TOTAL_BOOKINGS']; ?>
                        </td>
                        <td style="padding: 10px; text-align: center; color: #4CAF50;">
                            <?php echo $lessor['COMPLETED_BOOKINGS']; ?><br>
                            <small>(<?php echo number_format($completion_rate, 1); ?>%)</small>
                        </td>
                        <td style="padding: 10px; text-align: center; color: #f44336;">
                            <?php echo $lessor['CANCELLED_BOOKINGS']; ?>
                        </td>
                        <td style="padding: 10px; text-align: right; font-weight: bold;">
                            RM <?php echo number_format($lessor['TOTAL_REVENUE'], 2); ?>
                        </td>
                        <td style="padding: 10px; text-align: right; color: #FF9800; font-weight: bold;">
                            RM <?php echo number_format($lessor['LESSOR_EARNINGS'], 2); ?>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <?php if ($lessor['AVG_RATING'] > 0): ?>
                                <div style="background: #fff3cd; padding: 5px; border-radius: 3px;">
                                    ⭐ <?php echo number_format($lessor['AVG_RATING'], 1); ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #999;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <?php echo $lessor['TOTAL_REVIEWS']; ?>
                        </td>
                    </tr>
                <?php 
                $rank++;
                endforeach; 
                ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Car Performance -->
<?php if (!empty($report_data['car_performance'])): ?>
<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px;">
    <h3>🚗 Car Performance Breakdown</h3>
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #e3f2fd;">
                <th style="padding: 10px;">Car Details</th>
                <th style="padding: 10px;">Owner</th>
                <th style="padding: 10px;">Daily Rate</th>
                <th style="padding: 10px;">Total Bookings</th>
                <th style="padding: 10px;">Total Revenue</th>
                <th style="padding: 10px;">Avg per Booking</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report_data['car_performance'] as $car): ?>
                <tr>
                    <td style="padding: 10px;">
                        <strong><?php echo htmlspecialchars($car['BRAND'] . ' ' . $car['MODEL'] . ' ' . $car['YEAR']); ?></strong><br>
                        <small style="color: #666;"><?php echo htmlspecialchars($car['LICENSE_PLATE']); ?></small>
                    </td>
                    <td style="padding: 10px;"><?php echo htmlspecialchars($car['OWNER_NAME']); ?></td>
                    <td style="padding: 10px; text-align: right;">
                        RM <?php echo number_format($car['DAILY_RATE'], 2); ?>
                    </td>
                    <td style="padding: 10px; text-align: center; font-weight: bold;">
                        <?php echo $car['TOTAL_BOOKINGS']; ?>
                    </td>
                    <td style="padding: 10px; text-align: right; font-weight: bold; color: #4CAF50;">
                        RM <?php echo number_format($car['TOTAL_REVENUE'], 2); ?>
                    </td>
                    <td style="padding: 10px; text-align: right;">
                        RM <?php 
                        $avg = $car['TOTAL_BOOKINGS'] > 0 ? $car['TOTAL_REVENUE'] / $car['TOTAL_BOOKINGS'] : 0;
                        echo number_format($avg, 2); 
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Review Statistics -->
<?php if (!empty($report_data['review_stats'])): ?>
<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px;">
    <h3>⭐ Customer Reviews & Ratings</h3>
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #fff3cd;">
                <th style="padding: 10px;">Lessor</th>
                <th style="padding: 10px;">Total Reviews</th>
                <th style="padding: 10px;">Average Rating</th>
                <th style="padding: 10px;">5⭐</th>
                <th style="padding: 10px;">4⭐</th>
                <th style="padding: 10px;">3⭐</th>
                <th style="padding: 10px;">2⭐</th>
                <th style="padding: 10px;">1⭐</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report_data['review_stats'] as $review): ?>
                <tr>
                    <td style="padding: 10px;">
                        <strong><?php echo htmlspecialchars($review['NAME']); ?></strong>
                    </td>
                    <td style="padding: 10px; text-align: center; font-weight: bold;">
                        <?php echo $review['REVIEW_COUNT']; ?>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <div style="background: #e8f5e9; padding: 8px; border-radius: 5px; font-size: 18px; font-weight: bold; color: #4CAF50;">
                            ⭐ <?php echo number_format($review['AVG_RATING'], 2); ?>
                        </div>
                    </td>
                    <td style="padding: 10px; text-align: center; color: #4CAF50;">
                        <?php echo $review['FIVE_STAR']; ?>
                    </td>
                    <td style="padding: 10px; text-align: center; color: #8BC34A;">
                        <?php echo $review['FOUR_STAR']; ?>
                    </td>
                    <td style="padding: 10px; text-align: center; color: #FFC107;">
                        <?php echo $review['THREE_STAR']; ?>
                    </td>
                    <td style="padding: 10px; text-align: center; color: #FF9800;">
                        <?php echo $review['TWO_STAR']; ?>
                    </td>
                    <td style="padding: 10px; text-align: center; color: #f44336;">
                        <?php echo $review['ONE_STAR']; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Performance Insights -->
<div style="background: #e3f2fd; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #2196F3;">
    <h3>💡 Performance Insights</h3>
    <?php if (!empty($report_data['lessor_performance'])): ?>
        <?php 
        $total_lessors = count($report_data['lessor_performance']);
        $total_bookings = array_sum(array_column($report_data['lessor_performance'], 'TOTAL_BOOKINGS'));
        $total_revenue = array_sum(array_column($report_data['lessor_performance'], 'TOTAL_REVENUE'));
        $avg_bookings_per_lessor = $total_lessors > 0 ? $total_bookings / $total_lessors : 0;
        $avg_revenue_per_lessor = $total_lessors > 0 ? $total_revenue / $total_lessors : 0;
        
        // Find top performer
        $top_lessor = $report_data['lessor_performance'][0];
        ?>
        <ul style="font-size: 15px; line-height: 2;">
            <li>
                <strong>Total Active Lessors:</strong> <?php echo $total_lessors; ?> lessors
            </li>
            <li>
                <strong>Average Bookings per Lessor:</strong> <?php echo number_format($avg_bookings_per_lessor, 1); ?> bookings
            </li>
            <li>
                <strong>Average Revenue per Lessor:</strong> RM <?php echo number_format($avg_revenue_per_lessor, 2); ?>
            </li>
            <li>
                <strong>Top Performing Lessor:</strong> 
                <?php echo htmlspecialchars($top_lessor['NAME']); ?> 
                (<?php echo $top_lessor['TOTAL_BOOKINGS']; ?> bookings, 
                RM <?php echo number_format($top_lessor['TOTAL_REVENUE'], 2); ?> revenue)
            </li>
            <?php if (!empty($report_data['review_stats'])): ?>
            <li>
                <strong>Average Platform Rating:</strong> 
                ⭐ <?php 
                $total_ratings = array_sum(array_column($report_data['review_stats'], 'AVG_RATING'));
                $rating_count = count($report_data['review_stats']);
                $platform_avg = $rating_count > 0 ? $total_ratings / $rating_count : 0;
                echo number_format($platform_avg, 2); 
                ?> / 5.00
            </li>
            <?php endif; ?>
        </ul>
    <?php else: ?>
        <p>No performance data available for the selected period.</p>
    <?php endif; ?>
</div>