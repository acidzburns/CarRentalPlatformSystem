<?php
/**
 * UC3: Car Details View & UC4: Booking Details (Renter)
 * View complete booking information for renter
 * Styled: Royal Blue Design System (Renter)
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Check if renter is logged in
require_login('renter');

$renterid = get_user_id();
$user_name = get_user_name();

// Get booking ID from URL
$booking_id = isset($_GET['bookingid']) ? $_GET['bookingid'] : '';

if (empty($booking_id)) {
    header("Location: booking_history.php");
    exit();
}

// Get booking details with security check (must belong to this renter)
$query = "SELECT 
            B.*,
            C.CARID,
            C.BRAND,
            C.MODEL,
            C.YEAR,
            C.LICENSE_PLATE,
            C.DAILY_RATE,
            C.TRANSMISSION,
            C.FUEL_TYPE,
            C.SEATS,
            C.PHOTO1_PATH,
            C.PHOTO2_PATH,
            C.PHOTO3_PATH,
            L.LESSORID,
            L.NAME as LESSOR_NAME,
            L.EMAIL as LESSOR_EMAIL,
            L.PHONE as LESSOR_PHONE,
            P.PAYMENTID,
            P.PAYMENT_METHOD,
            P.PAYMENT_STATUS,
            P.PAYMENT_DATE,
            P.AMOUNT as PAYMENT_AMOUNT,
            TO_CHAR(B.START_DATE, 'DD-MON-YYYY') as START_DATE_FMT,
            TO_CHAR(B.END_DATE, 'DD-MON-YYYY') as END_DATE_FMT,
            TO_CHAR(B.CREATED_AT, 'DD-MON-YYYY HH24:MI') as CREATED_AT_FMT
          FROM BOOKING B
          JOIN CAR C ON B.CARID = C.CARID
          JOIN LESSOR L ON C.LESSORID = L.LESSORID
          LEFT JOIN PAYMENT P ON B.BOOKINGID = P.BOOKINGID
          WHERE B.BOOKINGID = :booking_id 
          AND B.RENTERID = :renterid";

$stmt = oci_parse($conn, $query);
oci_bind_by_name($stmt, ':booking_id', $booking_id);
oci_bind_by_name($stmt, ':renterid', $renterid);
oci_execute($stmt);

$booking = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if (!$booking) {
    header("Location: booking_history.php");
    exit();
}

// Calculate rental duration
$start = new DateTime($booking['START_DATE']);
$end = new DateTime($booking['END_DATE']);
$duration = $start->diff($end)->days + 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - Car Rental System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/renter-dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        /* --- Royal Blue Design System (Renter) --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #1864FF 0%, #0056e0 100%);
            color: white;
            padding: 40px 20px 80px 20px;
            text-align: center;
            position: relative;
        }
        
        .page-header h1 { 
            font-size: 32px; 
            font-weight: 700; 
            margin-bottom: 10px; 
        }
        
        .page-header p { 
            color: rgba(255, 255, 255, 0.9); 
            font-size: 16px;
        }

        .status-badge-header {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 15px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.5);
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: -50px auto 40px auto;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: #1864FF;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.2s;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background: #1864FF;
            color: white;
        }

        /* Alert Boxes */
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: start;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .alert i { font-size: 24px; margin-top: 2px; }
        
        .alert-info {
            background: #eff6ff;
            border-left: 4px solid #1864FF;
            color: #1e40af;
        }

        .alert-warning {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }

        .alert-success {
            background: #ecfdf5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        .alert h3 { font-size: 18px; margin-bottom: 8px; }
        .alert p { font-size: 14px; line-height: 1.6; margin: 0; }

        /* Car Image Gallery */
        .car-gallery {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .thumbnail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }

        .thumbnail {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }

        .thumbnail:hover {
            border-color: #1864FF;
            transform: scale(1.05);
        }

        /* Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        /* Card Styling */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .card h2 {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card h2 i { color: #1864FF; }

        /* Detail Row */
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: start;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .detail-row:last-child { border-bottom: none; }

        .detail-label {
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            flex: 0 0 40%;
        }

        .detail-value {
            font-size: 14px;
            color: #1e293b;
            font-weight: 500;
            text-align: right;
            flex: 1;
        }

        .price-highlight {
            font-size: 24px;
            font-weight: 800;
            color: #1864FF;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending { background: #fffbeb; color: #b45309; }
        .status-confirmed { background: #eff6ff; color: #1d4ed8; }
        .status-completed { background: #ecfdf5; color: #047857; }
        .status-cancelled { background: #fef2f2; color: #b91c1c; }

        /* Full Width Card */
        .card.full-width {
            grid-column: 1 / -1;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #1864FF;
            color: white;
            box-shadow: 0 2px 8px rgba(24, 100, 255, 0.3);
        }

        .btn-primary:hover {
            background: #0056e0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(24, 100, 255, 0.4);
        }

        .btn-success {
            background: #10b981;
            color: white;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: white;
            color: #ef4444;
            border: 2px solid #ef4444;
        }

        .btn-danger:hover {
            background: #ef4444;
            color: white;
        }

        .btn-outline {
            background: white;
            color: #64748b;
            border: 2px solid #cbd5e1;
        }

        .btn-outline:hover {
            background: #f8fafc;
            border-color: #64748b;
            color: #1e293b;
        }

        /* Payment Info Highlight */
        .payment-highlight {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .payment-highlight h3 {
            font-size: 16px;
            margin-bottom: 8px;
            opacity: 0.9;
        }

        .payment-highlight .amount {
            font-size: 32px;
            font-weight: 800;
        }

        /* No Payment State */
        .no-payment {
            background: #f8fafc;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            border: 2px dashed #cbd5e1;
        }

        .no-payment i {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 15px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .main-image {
                height: 250px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <nav>
        <div class="nav-container">
            <h2><a href="dashboard.php" style="text-decoration:none; color:white;">Car Rental System</a></h2>
            <div class="nav-links">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                <a href="dashboard.php">Dashboard</a>
                <a href="search_cars.php">Search Cars</a>
                <a href="booking_history.php" class="active">My Bookings</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="page-header">
        <h1><i class="fas fa-receipt"></i> Booking Details</h1>
        <p>Booking ID: #<?php echo htmlspecialchars($booking['BOOKINGID']); ?></p>
        <span class="status-badge-header status-<?php echo strtolower($booking['BOOKING_STATUS']); ?>">
            <?php echo htmlspecialchars($booking['BOOKING_STATUS']); ?>
        </span>
    </div>

    <div class="container">
        
        

        <!-- Status Alerts -->
        <?php if (strtoupper($booking['BOOKING_STATUS']) == 'PENDING'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-clock"></i>
                <div>
                    <h3>Payment Pending</h3>
                    <p>Your booking is waiting for payment confirmation. Please complete the payment to confirm your reservation.</p>
                </div>
            </div>
        <?php elseif (strtoupper($booking['BOOKING_STATUS']) == 'CONFIRMED'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <h3>Booking Confirmed!</h3>
                    <p>Your booking has been confirmed. The car owner will contact you before the rental period.</p>
                </div>
            </div>
        <?php elseif (strtoupper($booking['BOOKING_STATUS']) == 'COMPLETED'): ?>
            <div class="alert alert-info">
                <i class="fas fa-flag-checkered"></i>
                <div>
                    <h3>Rental Completed</h3>
                    <p>Thank you for renting with us! We hope you had a great experience. Please leave a review.</p>
                </div>
            </div>
        <?php elseif (strtoupper($booking['BOOKING_STATUS']) == 'CANCELLED'): ?>
            <div class="alert alert-info">
                <i class="fas fa-times-circle"></i>
                <div>
                    <h3>Booking Cancelled</h3>
                    <p>
                        This booking has been cancelled.
                        <?php if (!empty($booking['PAYMENTID']) && strtoupper($booking['PAYMENT_STATUS']) == 'SUCCESS'): ?>
                            Refund will be processed within 3-5 business days.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Car Gallery -->
        <div class="car-gallery">
            <?php 
            $main_photo = $booking['PHOTO1_PATH'] ?: 'placeholder.jpg';
            ?>
            <img src="../uploads/cars/<?php echo htmlspecialchars($main_photo); ?>" 
                 alt="Car Image" 
                 class="main-image" 
                 id="mainImage">
            
            <div class="thumbnail-grid">
                <?php if ($booking['PHOTO1_PATH']): ?>
                    <img src="../uploads/cars/<?php echo htmlspecialchars($booking['PHOTO1_PATH']); ?>" 
                         alt="Photo 1" 
                         class="thumbnail" 
                         onclick="changeImage(this.src)">
                <?php endif; ?>
                <?php if ($booking['PHOTO2_PATH']): ?>
                    <img src="../uploads/cars/<?php echo htmlspecialchars($booking['PHOTO2_PATH']); ?>" 
                         alt="Photo 2" 
                         class="thumbnail" 
                         onclick="changeImage(this.src)">
                <?php endif; ?>
                <?php if ($booking['PHOTO3_PATH']): ?>
                    <img src="../uploads/cars/<?php echo htmlspecialchars($booking['PHOTO3_PATH']); ?>" 
                         alt="Photo 3" 
                         class="thumbnail" 
                         onclick="changeImage(this.src)">
                <?php endif; ?>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="details-grid">
            
            <!-- Booking Information -->
            <div class="card">
                <h2><i class="fas fa-calendar-check"></i> Booking Information</h2>
                <div class="detail-row">
                    <span class="detail-label">Booking ID</span>
                    <span class="detail-value">#<?php echo htmlspecialchars($booking['BOOKINGID']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <span class="status-badge status-<?php echo strtolower($booking['BOOKING_STATUS']); ?>">
                            <?php echo htmlspecialchars($booking['BOOKING_STATUS']); ?>
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Start Date</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['START_DATE_FMT']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">End Date</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['END_DATE_FMT']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration</span>
                    <span class="detail-value"><?php echo $duration; ?> Day<?php echo $duration > 1 ? 's' : ''; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Booked On</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['CREATED_AT_FMT']); ?></span>
                </div>
            </div>

            <!-- Car Information -->
            <div class="card">
                <h2><i class="fas fa-car"></i> Car Information</h2>
                <div class="detail-row">
                    <span class="detail-label">Car</span>
                    <span class="detail-value">
                        <?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL']); ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Year</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['YEAR']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">License Plate</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Transmission</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['TRANSMISSION']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Fuel Type</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['FUEL_TYPE']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Seats</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['SEATS']); ?> Passengers</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Daily Rate</span>
                    <span class="detail-value">RM <?php echo number_format($booking['DAILY_RATE'], 2); ?></span>
                </div>
            </div>

            <!-- Pricing Breakdown -->
            <div class="card">
                <h2><i class="fas fa-money-bill-wave"></i> Pricing Details</h2>
                <div class="detail-row">
                    <span class="detail-label">Daily Rate</span>
                    <span class="detail-value">RM <?php echo number_format($booking['DAILY_RATE'], 2); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Number of Days</span>
                    <span class="detail-value"><?php echo $duration; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Subtotal</span>
                    <span class="detail-value">RM <?php echo number_format($booking['DAILY_RATE'] * $duration, 2); ?></span>
                </div>
                <div class="detail-row" style="border-top: 2px solid #1864FF; padding-top: 15px; margin-top: 10px;">
                    <span class="detail-label" style="font-size: 16px; font-weight: 700; color: #1e293b;">Total Amount</span>
                    <span class="detail-value price-highlight">RM <?php echo number_format($booking['TOTAL_AMOUNT'], 2); ?></span>
                </div>
            </div>

            <!-- Lessor Information -->
            <div class="card">
                <h2><i class="fas fa-user-tie"></i> Car Owner Details</h2>
                <div class="detail-row">
                    <span class="detail-label">Name</span>
                    <span class="detail-value"><?php echo htmlspecialchars($booking['LESSOR_NAME']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email</span>
                    <span class="detail-value">
                        <a href="mailto:<?php echo htmlspecialchars($booking['LESSOR_EMAIL']); ?>" 
                           style="color: #1864FF; text-decoration: none;">
                            <?php echo htmlspecialchars($booking['LESSOR_EMAIL']); ?>
                        </a>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value">
                        <a href="tel:<?php echo htmlspecialchars($booking['LESSOR_PHONE']); ?>" 
                           style="color: #1864FF; text-decoration: none;">
                            <?php echo htmlspecialchars($booking['LESSOR_PHONE']); ?>
                        </a>
                    </span>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="card full-width">
                <h2><i class="fas fa-credit-card"></i> Payment Information</h2>
                
                <?php if ($booking['PAYMENTID']): ?>
                    <div class="payment-highlight">
                        <h3>Payment Completed</h3>
                        <div class="amount">RM <?php echo number_format($booking['PAYMENT_AMOUNT'], 2); ?></div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div class="detail-row">
                            <span class="detail-label">Payment ID</span>
                            <span class="detail-value">#<?php echo htmlspecialchars($booking['PAYMENTID']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Method</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['PAYMENT_METHOD']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status</span>
                            <span class="detail-value">
                                <span class="status-badge" style="background: #ecfdf5; color: #047857;">
                                    <?php echo htmlspecialchars($booking['PAYMENT_STATUS']); ?>
                                </span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Payment Date</span>
                            <span class="detail-value">
                                <?php echo $booking['PAYMENT_DATE'] ? date('d M Y, h:i A', strtotime($booking['PAYMENT_DATE'])) : 'N/A'; ?>
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-payment">
                        <i class="fas fa-wallet"></i>
                        <p style="font-size: 16px; color: #64748b; margin: 0;">
                            <?php if (strtoupper($booking['BOOKING_STATUS']) == 'PENDING'): ?>
                                <strong>Payment Pending</strong><br>
                                Please complete the payment to confirm your booking.
                            <?php else: ?>
                                No payment information available.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <?php if (strtoupper($booking['BOOKING_STATUS']) == 'PENDING'): ?>
                <a href="make_payment.php?bookingid=<?php echo $booking['BOOKINGID']; ?>" class="btn btn-primary">
                    <i class="fas fa-credit-card"></i> Complete Payment
                </a>
                <button onclick="cancelBooking(<?php echo $booking['BOOKINGID']; ?>)" class="btn btn-danger">
                    <i class="fas fa-times-circle"></i> Cancel Booking
                </button>
            <?php elseif (strtoupper($booking['BOOKING_STATUS']) == 'COMPLETED'): ?>
                <?php
                // Check if review exists
                $review_check = "SELECT COUNT(*) as CNT FROM REVIEW WHERE BOOKINGID = :booking_id";
                $stmt_review = oci_parse($conn, $review_check);
                oci_bind_by_name($stmt_review, ':booking_id', $booking_id);
                oci_execute($stmt_review);
                $review_data = oci_fetch_assoc($stmt_review);
                oci_free_statement($stmt_review);
                
                if ($review_data['CNT'] == 0):
                ?>
                    <a href="submit_review.php?bookingid=<?php echo $booking['BOOKINGID']; ?>" class="btn btn-success">
                        <i class="fas fa-star"></i> Write a Review
                    </a>
                <?php else: ?>
                    <div style="background: #ecfdf5; color: #047857; padding: 12px 20px; border-radius: 8px; font-weight: 600;">
                        <i class="fas fa-check-circle"></i> You have reviewed this booking
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <a href="booking_history.php" class="btn btn-outline">
                <i class="fas fa-list"></i> View All Bookings
            </a>
        </div>

    </div>

    <script>
    function changeImage(src) {
        document.getElementById('mainImage').src = src;
    }

    function cancelBooking(bookingId) {
        if (confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
            window.location.href = 'cancel_booking.php?id=' + bookingId;
        }
    }
    </script>

</body>
</html>