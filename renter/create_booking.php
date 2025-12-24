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
</head>
<body>
    <h1>Create Booking</h1>
    
    <?php display_flash_message(); ?>
    
    <!-- Display errors -->
    <?php if (!empty($errors)): ?>
        <div style="background: #ffebee; padding: 10px; margin: 10px 0; border: 1px solid #f44336;">
            <strong>Error:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <!-- UC11: Booking History -->
    <?php if (!empty($booking_history)): ?>
    <div style="border: 1px solid #ddd; padding: 15px; margin: 20px 0; background: #f9f9f9;">
        <h3>Your Recent Bookings</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #eee;">
                    <th style="padding: 8px; border: 1px solid #ddd;">Booking ID</th>
                    <th style="padding: 8px; border: 1px solid #ddd;">Car</th>
                    <th style="padding: 8px; border: 1px solid #ddd;">Dates</th>
                    <th style="padding: 8px; border: 1px solid #ddd;">Status</th>
                    <th style="padding: 8px; border: 1px solid #ddd;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($booking_history as $history): ?>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;">#<?php echo htmlspecialchars($history['BOOKINGID']); ?></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">
                        <?php echo htmlspecialchars($history['BRAND'] . ' ' . $history['MODEL'] . ' ' . $history['YEAR']); ?>
                    </td>
                    <td style="padding: 8px; border: 1px solid #ddd;">
                        <?php echo date('d M Y', strtotime($history['START_DATE'])) . ' - ' . date('d M Y', strtotime($history['END_DATE'])); ?>
                    </td>
                    <td style="padding: 8px; border: 1px solid #ddd;">
                        <span style="padding: 3px 8px; background: 
                            <?php 
                            echo $history['BOOKING_STATUS'] == 'CONFIRMED' ? '#4CAF50' : 
                                 ($history['BOOKING_STATUS'] == 'PENDING' ? '#FF9800' : '#f44336'); 
                            ?>; 
                            color: white; border-radius: 3px;">
                            <?php echo htmlspecialchars($history['BOOKING_STATUS']); ?>
                        </span>
                    </td>
                    <td style="padding: 8px; border: 1px solid #ddd;">
                        RM <?php echo number_format($history['TOTAL_AMOUNT'], 2); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Car Details Summary -->
    <div style="border: 1px solid #ccc; padding: 20px; margin: 20px 0;">
        <h2>Car Details</h2>
        <div style="display: flex; gap: 20px;">
            <?php if ($car['PHOTO1_PATH']): ?>
                <img src="../uploads/cars/<?php echo htmlspecialchars($car['PHOTO1_PATH']); ?>" 
                     alt="Car" style="width: 250px; height: 180px; object-fit: cover;">
            <?php endif; ?>
            
            <div>
                <h3><?php echo htmlspecialchars($car['BRAND'] . ' ' . $car['MODEL'] . ' ' . $car['YEAR']); ?></h3>
                <p><strong>Daily Rate:</strong> RM <?php echo number_format($car['DAILY_RATE'], 2); ?></p>
                <p><strong>Transmission:</strong> <?php echo htmlspecialchars($car['TRANSMISSION']); ?></p>
                <p><strong>Fuel Type:</strong> <?php echo htmlspecialchars($car['FUEL_TYPE']); ?></p>
                <p><strong>Seats:</strong> <?php echo htmlspecialchars($car['SEATS']); ?></p>
                <p><strong>Location:</strong> <?php echo htmlspecialchars($car['LOCATION']); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Booking Form -->
    <div style="border: 1px solid #ccc; padding: 20px; margin: 20px 0;">
        <h2>Booking Details</h2>
        
        <form method="POST" action="" id="bookingForm">
            <div style="margin: 15px 0;">
                <label><strong>Pickup Date:</strong></label><br>
                <input type="date" name="start_date" id="start_date" 
                       min="<?php echo date('Y-m-d'); ?>" 
                       required style="padding: 8px; width: 250px;">
            </div>
            
            <div style="margin: 15px 0;">
                <label><strong>Return Date:</strong></label><br>
                <input type="date" name="end_date" id="end_date" 
                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                       required style="padding: 8px; width: 250px;">
            </div>
            
            <div style="margin: 15px 0;">
                <label><strong>Pickup Time:</strong></label><br>
                <input type="time" name="pickup_time" value="09:00" required style="padding: 8px;">
            </div>
            
            <div style="margin: 15px 0;">
                <label><strong>Return Time:</strong></label><br>
                <input type="time" name="return_time" value="09:00" required style="padding: 8px;">
            </div>
            
            <div style="margin: 15px 0;">
                <label><strong>Pickup Location:</strong></label><br>
                <input type="text" name="pickup_location" 
                       value="<?php echo htmlspecialchars($car['LOCATION']); ?>" 
                       required style="padding: 8px; width: 400px;">
            </div>
            
            <hr>
            
            <h3>Optional Extras</h3>
            
            <div style="margin: 15px 0;">
                <label>
                    <input type="checkbox" name="add_insurance" id="add_insurance" value="1">
                    Add Insurance (RM <?php echo number_format($car['INSURANCE_FEE'], 2); ?>/day)
                </label>
            </div>
            
            <div style="margin: 15px 0;">
                <label>
                    <input type="checkbox" name="add_delivery" id="add_delivery" value="1">
                    Home Delivery (RM <?php echo number_format($car['DELIVERY_FEE'], 2); ?> one-time)
                </label>
            </div>
            
            <div style="margin: 15px 0;">
                <label><strong>Special Requests:</strong></label><br>
                <textarea name="special_requests" rows="3" style="width: 400px; padding: 8px;"
                          placeholder="Any special requirements or requests..."></textarea>
            </div>
            
            <hr>
            
            <!-- Cost Breakdown -->
            <div style="background: #f5f5f5; padding: 15px; margin: 20px 0;">
                <h3>Cost Breakdown</h3>
                <table style="width: 100%;">
                    <tr>
                        <td>Rental Duration:</td>
                        <td style="text-align: right;"><span id="duration_days">0</span> days</td>
                    </tr>
                    <tr>
                        <td>Rental Cost:</td>
                        <td style="text-align: right;">RM <span id="rental_cost">0.00</span></td>
                    </tr>
                    <tr id="insurance_row" style="display: none;">
                        <td>Insurance:</td>
                        <td style="text-align: right;">RM <span id="insurance_cost">0.00</span></td>
                    </tr>
                    <tr id="delivery_row" style="display: none;">
                        <td>Delivery Fee:</td>
                        <td style="text-align: right;">RM <span id="delivery_cost"><?php echo number_format($car['DELIVERY_FEE'], 2); ?></span></td>
                    </tr>
                    <tr style="border-top: 2px solid #333; font-weight: bold; font-size: 18px;">
                        <td>Total Amount:</td>
                        <td style="text-align: right;">RM <span id="total_amount">0.00</span></td>
                    </tr>
                </table>
            </div>
            
            <div style="margin: 20px 0;">
                <button type="submit" style="background: #4CAF50; color: white; padding: 15px 30px; border: none; cursor: pointer; font-size: 16px;">
                    Proceed to Payment
                </button>
                <a href="car_details.php?carid=<?php echo $carid; ?>" 
                   style="display: inline-block; padding: 15px 30px; background: #ccc; color: #333; text-decoration: none; margin-left: 10px;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
    
    <script>
    const dailyRate = <?php echo $car['DAILY_RATE']; ?>;
    const insuranceFee = <?php echo $car['INSURANCE_FEE']; ?>;
    const deliveryFee = <?php echo $car['DELIVERY_FEE']; ?>;
    
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
        document.getElementById('end_date').min = minReturnDate;
    });
    </script>
    
    <p><a href="dashboard.php">Back to Dashboard</a></p>
</body>
</html>