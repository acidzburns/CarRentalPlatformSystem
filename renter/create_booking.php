<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/email_test.php'; // PHPMailer setup

// Check if renter is logged in
require_login('renter');

$renterid = get_user_id();
$errors = [];
$success = "";

// Get car ID from URL
if (!isset($_GET['carid'])) {
    header("Location: search_cars.php");
    exit();
}

$carid = $_GET['carid'];

// Fetch car details (UC7: Car Specification, UC8: Rental Rates, UC9: Photos, UC10: Owner Info)
$sql = "SELECT c.*, l.NAME as LESSOR_NAME, l.EMAIL as LESSOR_EMAIL, l.PHONE as LESSOR_PHONE
        FROM CAR c
        JOIN LESSOR l ON c.LESSORID = l.LESSORID
        WHERE c.CARID = :carid AND c.AVAILABILITY_STATUS = 'AVAILABLE'";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':carid', $carid);
oci_execute($stmt);

$car = oci_fetch_assoc($stmt);

// Exception 1: Car not found or not available
if (!$car) {
    set_flash_message("Car is not available for booking.", "error");
    header("Location: search_cars.php");
    exit();
}

// UC11: Booking History - Get customer's recent bookings (last 5)
$history_sql = "SELECT b.BOOKINGID, b.START_DATE, b.END_DATE, b.BOOKING_STATUS, b.TOTAL_AMOUNT,
                c.BRAND, c.MODEL, c.YEAR
                FROM BOOKING b
                JOIN CAR c ON b.CARID = c.CARID
                WHERE b.RENTERID = :renterid
                ORDER BY b.CREATED_AT DESC
                FETCH FIRST 5 ROWS ONLY";

$stmt_history = oci_parse($conn, $history_sql);
oci_bind_by_name($stmt_history, ':renterid', $renterid);
oci_execute($stmt_history);

$booking_history = [];
while ($row = oci_fetch_assoc($stmt_history)) {
    $booking_history[] = $row;
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    $pickup_time = trim($_POST['pickup_time']);
    $return_time = trim($_POST['return_time']);
    $pickup_location = trim($_POST['pickup_location']);
    $add_insurance = isset($_POST['add_insurance']) ? 1 : 0;
    $add_delivery = isset($_POST['add_delivery']) ? 1 : 0;
    $special_requests = trim($_POST['special_requests']);
    
    // Validation
    if (empty($start_date) || empty($end_date)) {
        $errors[] = "Please select pickup and return dates.";
    }
    
    // Exception 2: Invalid dates
    if (strtotime($start_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Please enter valid dates. Pickup date cannot be in the past.";
    }
    
    if (strtotime($end_date) <= strtotime($start_date)) {
        $errors[] = "Please enter valid dates. Return date must be after pickup date.";
    }
    
    // Exception 2: Rental duration less than minimum (1 day)
    $duration_days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
    if ($duration_days < 1) {
        $errors[] = "Minimum rental period is 1 day.";
    }
    
    // Exception 1: Check availability
    $availability_sql = "SELECT COUNT(*) as CONFLICT_COUNT
                        FROM BOOKING
                        WHERE CARID = :carid
                        AND BOOKING_STATUS IN ('PENDING', 'CONFIRMED')
                        AND (
                            (TO_DATE(:start_date, 'YYYY-MM-DD') BETWEEN START_DATE AND END_DATE)
                            OR (TO_DATE(:end_date, 'YYYY-MM-DD') BETWEEN START_DATE AND END_DATE)
                            OR (START_DATE BETWEEN TO_DATE(:start_date2, 'YYYY-MM-DD') AND TO_DATE(:end_date2, 'YYYY-MM-DD'))
                        )";
    
    $stmt_avail = oci_parse($conn, $availability_sql);
    oci_bind_by_name($stmt_avail, ':carid', $carid);
    oci_bind_by_name($stmt_avail, ':start_date', $start_date);
    oci_bind_by_name($stmt_avail, ':end_date', $end_date);
    oci_bind_by_name($stmt_avail, ':start_date2', $start_date);
    oci_bind_by_name($stmt_avail, ':end_date2', $end_date);
    oci_execute($stmt_avail);
    
    $avail_result = oci_fetch_assoc($stmt_avail);
    
    if ($avail_result['CONFLICT_COUNT'] > 0) {
        $errors[] = "Car is not available for these dates. Please select different dates.";
    }
    
    // Calculate total cost
    if (empty($errors)) {
        $rental_cost = $duration_days * $car['DAILY_RATE'];
        $insurance_cost = $add_insurance ? ($car['INSURANCE_FEE'] * $duration_days) : 0;
        $delivery_cost = $add_delivery ? $car['DELIVERY_FEE'] : 0;
        $total_amount = $rental_cost + $insurance_cost + $delivery_cost;
        
        // UC12: Make New Booking
        try {
            $booking_sql = "INSERT INTO BOOKING 
                           (BOOKINGID, RENTERID, CARID, START_DATE, END_DATE, 
                            BOOKING_STATUS, TOTAL_AMOUNT, CREATED_AT)
                           VALUES 
                           (SEQ_BOOKING.NEXTVAL, :renterid, :carid, 
                            TO_DATE(:start_date, 'YYYY-MM-DD'), TO_DATE(:end_date, 'YYYY-MM-DD'),
                            'PENDING', :total_amount, SYSDATE)
                           RETURNING BOOKINGID INTO :new_bookingid";
            
            $stmt_booking = oci_parse($conn, $booking_sql);
            oci_bind_by_name($stmt_booking, ':renterid', $renterid);
            oci_bind_by_name($stmt_booking, ':carid', $carid);
            oci_bind_by_name($stmt_booking, ':start_date', $start_date);
            oci_bind_by_name($stmt_booking, ':end_date', $end_date);
            oci_bind_by_name($stmt_booking, ':total_amount', $total_amount);
            oci_bind_by_name($stmt_booking, ':new_bookingid', $new_bookingid, 32);
            
            if (oci_execute($stmt_booking)) {
                oci_commit($conn);
                
                // Store booking details in session
                $_SESSION['pending_booking'] = [
                    'bookingid' => $new_bookingid,
                    'carid' => $carid,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'total_amount' => $total_amount,
                    'add_insurance' => $add_insurance,
                    'add_delivery' => $add_delivery
                ];

                

                // Redirect to payment page
                header("Location: make_payment.php?bookingid=" . $new_bookingid);
                exit();
            } else {
                $e = oci_error($stmt_booking);
                $errors[] = "Failed to create booking: " . $e['message'];
            }
        } catch (Exception $e) {
            $errors[] = "Booking creation failed. Please try again.";
        }
    }
}

// Get renter info for display
$renter_sql = "SELECT NAME, EMAIL, PHONE FROM RENTER WHERE RENTERID = :renterid";
$stmt_renter = oci_parse($conn, $renter_sql);
oci_bind_by_name($stmt_renter, ':renterid', $renterid);
oci_execute($stmt_renter);
$renter = oci_fetch_assoc($stmt_renter);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Booking - Car Rental System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/create-booking.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <nav>
        <div class="nav-container">
            <h2><a href="dashboard.php">Car Rental System</a></h2>
            <div class="nav-links">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($renter['NAME']); ?>!</span>
                <a href="dashboard.php">Dashboard</a>
                <a href="search_cars.php" class="active">Search Cars</a>
                <a href="booking_history.php">My Bookings</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="hero">
        <h1>Create Your Booking</h1>
        <p>Confirm dates and details for your rental</p>
    </div>

    <main class="details-container">
        <!-- Left Column: Form & History -->
        <div class="left-column">
            <?php display_flash_message(); ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Please correct the following:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Booking Form Card -->
            <div class="details-card">
                <h2>Booking Details</h2>
                <form method="POST" action="" id="bookingForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Pickup Date</label>
                            <input type="date" name="start_date" id="start_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Return Date</label>
                            <input type="date" name="end_date" id="end_date" 
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Pickup Time</label>
                            <input type="time" name="pickup_time" value="09:00" required>
                        </div>
                        <div class="form-group">
                            <label>Return Time</label>
                            <input type="time" name="return_time" value="09:00" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Pickup/Return Location</label>
                            <input type="text" name="pickup_location" 
                                   value="<?php echo htmlspecialchars($car['LOCATION']); ?>" required>
                        </div>
                    </div>

                    <h3>Optional Extras</h3>
                    <label class="extras-item">
                        <input type="checkbox" name="add_insurance" id="add_insurance" value="1">
                        <span>Collision Damage Waiver (RM <?php echo number_format($car['INSURANCE_FEE'], 2); ?>/day)</span>
                    </label>
                    <label class="extras-item">
                        <input type="checkbox" name="add_delivery" id="add_delivery" value="1">
                        <span>Home Delivery & Pickup (RM <?php echo number_format($car['DELIVERY_FEE'], 2); ?> one-time)</span>
                    </label>

                    <div class="form-group full-width" style="margin-top: 20px;">
                        <label>Special Requests</label>
                        <textarea name="special_requests" rows="3" placeholder="Any special requirements..."></textarea>
                    </div>

                    <button type="submit" class="btn-primary">Proceed to Payment</button>
                    <div style="text-align: center;">
                        <a href="car_details.php?carid=<?php echo $carid; ?>" class="back-btn">Cancel and Go Back</a>
                    </div>
                </form>
            </div>

            <!-- Recent History Card -->
            <?php if (!empty($booking_history)): ?>
            <div class="details-card">
                <h2>Your Recent Bookings</h2>
                <div style="overflow-x: auto;">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Car</th>
                                <th>Dates</th>
                                <th>Status</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($booking_history as $history): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($history['BOOKINGID']); ?></td>
                                <td><?php echo htmlspecialchars($history['BRAND'] . ' ' . $history['MODEL']); ?></td>
                                <td><?php echo date('d M', strtotime($history['START_DATE'])) . ' - ' . date('d M', strtotime($history['END_DATE'])); ?></td>
                                <td>
                                    <?php 
                                    $status_class = 'status-' . strtolower($history['BOOKING_STATUS']);
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($history['BOOKING_STATUS']); ?>
                                    </span>
                                </td>
                                <td>RM <?php echo number_format($history['TOTAL_AMOUNT'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Car Summary & Cost -->
        <aside class="summary-card">
            <div class="details-card">
                <h2>Booking Summary</h2>
                <?php if ($car['PHOTO1_PATH']): ?>
                    <img src="../uploads/cars/<?php echo htmlspecialchars($car['PHOTO1_PATH']); ?>" 
                         class="car-summary-img" alt="Car">
                <?php endif; ?>
                
                <h3><?php echo htmlspecialchars($car['BRAND'] . ' ' . $car['MODEL']); ?></h3>
                <div style="font-size: 14px; color: #64748b; margin-bottom: 20px;">
                    <i class="fa fa-gas-pump"></i> <?php echo htmlspecialchars($car['FUEL_TYPE']); ?> &bull; 
                    <i class="fa fa-cog"></i> <?php echo htmlspecialchars($car['TRANSMISSION']); ?>
                </div>

                <table class="cost-table">
                    <tr>
                        <td>Daily Rate</td>
                        <td>RM <?php echo number_format($car['DAILY_RATE'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Rental Duration</td>
                        <td><span id="duration_days">0</span> Days</td>
                    </tr>
                    <tr>
                        <td>Base Rental</td>
                        <td>RM <span id="rental_cost">0.00</span></td>
                    </tr>
                    <tr id="insurance_row" style="display: none;">
                        <td>Insurance Add-on</td>
                        <td>RM <span id="insurance_cost">0.00</span></td>
                    </tr>
                    <tr id="delivery_row" style="display: none;">
                        <td>Delivery Service</td>
                        <td>RM <span id="delivery_cost"><?php echo number_format($car['DELIVERY_FEE'], 2); ?></span></td>
                    </tr>
                    <tr class="total-row">
                        <td>Estimated Total</td>
                        <td>RM <span id="total_amount">0.00</span></td>
                    </tr>
                </table>
                <p style="font-size: 11px; color: #94a3b8; text-align: center;">
                    Final payment including taxes will be calculated at checkout.
                </p>
            </div>
        </aside>
    </main>

    <script>
    const dailyRate = <?php echo (float)$car['DAILY_RATE']; ?>;
    const insuranceFee = <?php echo (float)$car['INSURANCE_FEE']; ?>;
    const deliveryFee = <?php echo (float)$car['DELIVERY_FEE']; ?>;
    
    function calculateCost() {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        const addInsurance = document.getElementById('add_insurance').checked;
        const addDelivery = document.getElementById('add_delivery').checked;
        
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const duration = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
            
            if (duration > 0) {
                document.getElementById('duration_days').textContent = duration;
                
                const rentalCost = duration * dailyRate;
                document.getElementById('rental_cost').textContent = rentalCost.toFixed(2);
                
                let insuranceCost = 0;
                if (addInsurance) {
                    insuranceCost = duration * insuranceFee;
                    document.getElementById('insurance_cost').textContent = insuranceCost.toFixed(2);
                    document.getElementById('insurance_row').style.display = 'table-row';
                } else {
                    document.getElementById('insurance_row').style.display = 'none';
                }
                
                let deliveryCost = 0;
                if (addDelivery) {
                    deliveryCost = deliveryFee;
                    document.getElementById('delivery_row').style.display = 'table-row';
                } else {
                    document.getElementById('delivery_row').style.display = 'none';
                }
                
                const totalAmount = rentalCost + insuranceCost + deliveryCost;
                document.getElementById('total_amount').textContent = totalAmount.toFixed(2);
            } else {
                // Reset if duration invalid
                document.getElementById('duration_days').textContent = "0";
                document.getElementById('rental_cost').textContent = "0.00";
                document.getElementById('total_amount').textContent = "0.00";
            }
        }
    }
    
    document.getElementById('start_date').addEventListener('change', calculateCost);
    document.getElementById('end_date').addEventListener('change', calculateCost);
    document.getElementById('add_insurance').addEventListener('change', calculateCost);
    document.getElementById('add_delivery').addEventListener('change', calculateCost);
    
    // Update min date for return when pickup changes
    document.getElementById('start_date').addEventListener('change', function() {
        const startDate = new Date(this.value);
        startDate.setDate(startDate.getDate() + 1);
        const minReturnDate = startDate.toISOString().split('T')[0];
        const endDateInput = document.getElementById('end_date');
        endDateInput.min = minReturnDate;
        if (endDateInput.value && endDateInput.value < minReturnDate) {
            endDateInput.value = minReturnDate;
        }
    });

    // Initial calculation on load if dates exist (e.g. after error redirect)
    window.addEventListener('load', calculateCost);
    </script>
</body>
</html>