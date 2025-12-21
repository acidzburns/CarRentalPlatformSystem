<?php
/**
 * Lessor Remove Car Page
 * UC19: Remove Car
 * 
 * Allows verified lessors to remove (deactivate/delete) a car from their listing
 * SRS Pages 69-71
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is logged in as lessor
check_authentication('lessor');

$lessor_id = get_user_id();
$lessor_name = get_user_name();

// Check verification status
$sql_check = "SELECT VERIFICATION_STATUS FROM LESSOR WHERE LESSORID = :lessorid";
$lessor = db_fetch_one($sql_check, ['lessorid' => $lessor_id]);

if (!$lessor || $lessor['VERIFICATION_STATUS'] !== 'VERIFIED') {
    header('Location: dashboard.php');
    exit();
}

// Step 1: Get car ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: my_cars.php');
    exit();
}

$car_id = $_GET['id'];

// Retrieve car details and verify ownership
$sql_car = "SELECT * FROM CAR WHERE CARID = :carid AND LESSORID = :lessorid";
$car = db_fetch_one($sql_car, ['carid' => $car_id, 'lessorid' => $lessor_id]);

if (!$car) {
    header('Location: my_cars.php?error=invalid_car');
    exit();
}

// Initialize variables
$error = "";
$success = "";
$removal_type = "";

// Step 2: Check for active or upcoming bookings (Exception 1)
$sql_check_bookings = "SELECT COUNT(*) as active_count, MAX(END_DATE) as last_booking_date
                      FROM BOOKING 
                      WHERE CARID = :carid 
                      AND BOOKING_STATUS IN ('CONFIRMED', 'ONGOING')
                      AND END_DATE >= SYSDATE";
$booking_check = db_fetch_one($sql_check_bookings, ['carid' => $car_id]);

$has_active_bookings = $booking_check['ACTIVE_COUNT'] > 0;
$last_booking_date = $booking_check['LAST_BOOKING_DATE'];

// Exception 2: Check for pending payments
$sql_check_payments = "SELECT COUNT(*) as pending_count
                      FROM PAYMENT 
                      WHERE BOOKINGID IN (
                          SELECT BOOKINGID FROM BOOKING WHERE CARID = :carid
                      )
                      AND PAYMENT_STATUS = 'PENDING'";
$payment_check = db_fetch_one($sql_check_payments, ['carid' => $car_id]);

$has_pending_payments = $payment_check['PENDING_COUNT'] > 0;

// Process removal request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $removal_type = $_POST['removal_type']; // 'deactivate' or 'delete'
    $deletion_reason = isset($_POST['deletion_reason']) ? trim($_POST['deletion_reason']) : '';
    
    // Step 5: Validate removal eligibility
    
    // Exception 1: Active bookings exist
    if ($has_active_bookings) {
        $error = "Cannot remove. This car has bookings until " . date('d M Y', strtotime($last_booking_date)) . ".";
    }
    // Exception 2: Pending payments exist
    elseif ($has_pending_payments) {
        $error = "Cannot remove. Please resolve pending payments first.";
    }
    // Alternative Flow 3: Cancel removal if confirmed = 'no'
    elseif (!isset($_POST['confirm']) || $_POST['confirm'] !== 'yes') {
        header('Location: my_cars.php');
        exit();
    }
    else {
        // Step 6: Perform removal based on type
        
        if ($removal_type === 'deactivate') {
            // Alternative Flow 1: Deactivate temporarily
            // Step 7: Update car status to "INACTIVE"
            $sql_update = "UPDATE CAR SET AVAILABILITY_STATUS = 'INACTIVE' WHERE CARID = :carid AND LESSORID = :lessorid";
            $result = db_execute($sql_update, ['carid' => $car_id, 'lessorid' => $lessor_id]);
            
            if ($result) {
                // Step 10: Display success message
                $success = "Car has been deactivated successfully.";
            } else {
                $error = "Failed to deactivate car. Please try again.";
            }
        }
        elseif ($removal_type === 'delete') {
            // Step 8: Permanently delete car
            
            // Step 7: Deletion reason required
            if (empty($deletion_reason)) {
                $error = "Please provide a reason for deletion.";
            } else {
                // Delete car photos from server
                for ($i = 1; $i <= 10; $i++) {
                    $photo_field = 'PHOTO' . $i . '_PATH';
                    if (!empty($car[$photo_field])) {
                        $photo_file = '../uploads/cars/' . $car[$photo_field];
                        if (file_exists($photo_file)) {
                            unlink($photo_file);
                        }
                    }
                }
                
                // Step 12: Log removal action
                // Note: In real system, you'd log this to an audit table
                // For now, we'll just delete the car
                
                $sql_delete = "DELETE FROM CAR WHERE CARID = :carid AND LESSORID = :lessorid";
                $result = db_execute($sql_delete, ['carid' => $car_id, 'lessorid' => $lessor_id]);
                
                if ($result) {
                    // Step 10: Display success message
                    header('Location: my_cars.php?success=car_removed');
                    exit();
                } else {
                    $error = "Failed to delete car. Please try again.";
                }
            }
        }
    }
}

// Alternative Flow 1: Schedule removal (for future implementation)
$can_schedule_removal = $has_active_bookings;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remove Car - Car Rental</title>
</head>
<body>
    <nav>
        <h2>Car Rental System - Lessor Panel</h2>
        <p>Welcome, <?php echo htmlspecialchars($lessor_name); ?>!</p>
        <a href="dashboard.php">Dashboard</a>
        <a href="my_cars.php">My Cars</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
    
    <main>
        <h1>Remove Car</h1>
        
        <!-- Step 3: Display removal options -->
        <h2>Car Details:</h2>
        <p><strong>Brand:</strong> <?php echo htmlspecialchars($car['BRAND']); ?></p>
        <p><strong>Model:</strong> <?php echo htmlspecialchars($car['MODEL']); ?></p>
        <p><strong>Year:</strong> <?php echo htmlspecialchars($car['YEAR']); ?></p>
        <p><strong>License Plate:</strong> <?php echo htmlspecialchars($car['LICENSE_PLATE']); ?></p>
        <p><strong>Current Status:</strong> <?php echo htmlspecialchars($car['AVAILABILITY_STATUS']); ?></p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <?php echo $success; ?>
                <p><a href="my_cars.php">Back to My Cars</a></p>
            </div>
        <?php else: ?>
        
        <!-- Step 2: Check for active bookings -->
        <?php if ($has_active_bookings): ?>
            <div class="warning">
                <h3>⚠️ Warning: Active Bookings Exist</h3>
                <p>This car has active or upcoming bookings until <strong><?php echo date('d M Y', strtotime($last_booking_date)); ?></strong>.</p>
                <p>You cannot remove this car until all bookings are completed.</p>
                
                <!-- Alternative Flow 1: Schedule removal -->
                <?php if ($can_schedule_removal): ?>
                    <p><em>Alternative: You can schedule removal for after <?php echo date('d M Y', strtotime($last_booking_date)); ?> (future feature)</em></p>
                <?php endif; ?>
                
                <p><a href="my_cars.php">Back to My Cars</a></p>
            </div>
        
        <!-- Exception 2: Check for pending payments -->
        <?php elseif ($has_pending_payments): ?>
            <div class="warning">
                <h3>⚠️ Warning: Pending Payments</h3>
                <p>This car has pending payments that need to be resolved first.</p>
                <p>Please contact customers or wait for payment completion before removing.</p>
                <p><a href="my_cars.php">Back to My Cars</a></p>
            </div>
        
        <?php else: ?>
        
        <!-- Step 3: Display removal options -->
        <h2>Removal Options:</h2>
        
        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to proceed with this action?');">
            
            <!-- Step 4: Confirmation dialog -->
            <h3>Choose Removal Type:</h3>
            
            <!-- Option 1: Deactivate temporarily -->
            <div>
                <input type="radio" id="deactivate" name="removal_type" value="deactivate" checked>
                <label for="deactivate">
                    <strong>Deactivate Temporarily</strong><br>
                    Car will be hidden from customers but you can reactivate it later.
                </label>
            </div>
            
            <br>
            
            <!-- Option 2: Delete permanently -->
            <div>
                <input type="radio" id="delete" name="removal_type" value="delete">
                <label for="delete">
                    <strong>Delete Permanently</strong><br>
                    Car will be completely removed from the system. This action cannot be undone.
                </label>
            </div>
            
            <br>
            
            <!-- Step 7: Deletion reason (required for permanent delete) -->
            <div id="deletion_reason_container" style="display: none;">
                <label for="deletion_reason"><strong>Reason for Deletion: *</strong></label>
                <textarea id="deletion_reason" name="deletion_reason" rows="3" placeholder="Please provide a reason for deleting this car..."></textarea>
                <p><em>This helps us improve our service.</em></p>
            </div>
            
            <script>
                // Show/hide deletion reason based on selection
                document.querySelectorAll('input[name="removal_type"]').forEach(radio => {
                    radio.addEventListener('change', function() {
                        const reasonContainer = document.getElementById('deletion_reason_container');
                        if (this.value === 'delete') {
                            reasonContainer.style.display = 'block';
                            document.getElementById('deletion_reason').required = true;
                        } else {
                            reasonContainer.style.display = 'none';
                            document.getElementById('deletion_reason').required = false;
                        }
                    });
                });
            </script>
            
            <br>
            
            <!-- Step 5: Final confirmation -->
            <div>
                <input type="checkbox" id="confirm" name="confirm" value="yes" required>
                <label for="confirm">I understand this action and want to proceed</label>
            </div>
            
            <br>
            
            <button type="submit">Proceed with Removal</button>
            
            <!-- Alternative Flow 3: Cancel removal -->
            <a href="my_cars.php">Cancel</a>
        </form>
        
        <!-- Alternative Flow 2: Reactivate option (if already deactivated) -->
        <?php if ($car['AVAILABILITY_STATUS'] === 'INACTIVE'): ?>
            <hr>
            <h3>Alternative: Reactivate This Car</h3>
            <p>This car is currently deactivated. You can reactivate it anytime.</p>
            <form method="POST" action="my_cars.php">
                <input type="hidden" name="carid" value="<?php echo $car_id; ?>">
                <input type="hidden" name="new_status" value="AVAILABLE">
                <input type="hidden" name="toggle_status" value="1">
                <button type="submit">Reactivate Car</button>
            </form>
        <?php endif; ?>
        
        <?php endif; ?>
        
        <?php endif; ?>
        
    </main>
</body>
</html>