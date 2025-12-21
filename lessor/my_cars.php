<?php
/**
 * Lessor My Cars Page
 * UC17: Car Listing
 * 
 * Allows verified lessors to view and manage their car listings
 * SRS Pages 62-64
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is logged in as verified lessor
check_authentication('lessor');

$user_id = get_user_id();
$user_name = get_user_name();

// Check verification status
$sql_check = "SELECT VERIFICATION_STATUS FROM LESSOR WHERE LESSORID = :lessorid";
$lessor = db_fetch_one($sql_check, ['lessorid' => $user_id]);

if (!$lessor || $lessor['VERIFICATION_STATUS'] !== 'VERIFIED') {
    header('Location: dashboard.php');
    exit();
}

// Initialize messages
$success_message = "";
$error_message = "";

// Step 7: Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'ALL';
$valid_statuses = ['ALL', 'AVAILABLE', 'RENTED', 'INACTIVE', 'MAINTENANCE'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'ALL';
}

// Step 2: Retrieve all cars owned by lessor
if ($status_filter === 'ALL') {
    $sql = "SELECT * FROM CAR WHERE LESSORID = :lessorid ORDER BY CREATED_AT DESC";
    $params = ['lessorid' => $user_id];
} else {
    $sql = "SELECT * FROM CAR WHERE LESSORID = :lessorid AND AVAILABILITY_STATUS = :status ORDER BY CREATED_AT DESC";
    $params = ['lessorid' => $user_id, 'status' => $status_filter];
}

$cars = db_query($sql, $params);

// Exception 2: No cars listed
$has_no_cars = empty($cars);

// Step 4: Get statistics for each car
$stats = [];
foreach ($cars as $car) {
    // Get booking statistics
    $sql_bookings = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN BOOKING_STATUS = 'COMPLETED' THEN TOTAL_AMOUNT ELSE 0 END) as total_revenue
                    FROM BOOKING 
                    WHERE CARID = :carid";
    $car_stats = db_fetch_one($sql_bookings, ['carid' => $car['CARID']]);
    
    // Get average rating
    $sql_rating = "SELECT AVG(RATING) as avg_rating, COUNT(*) as review_count 
                  FROM REVIEW 
                  WHERE CARID = :carid";
    $rating_data = db_fetch_one($sql_rating, ['carid' => $car['CARID']]);
    
    $stats[$car['CARID']] = [
        'total_bookings' => $car_stats['TOTAL_BOOKINGS'] ?? 0,
        'total_revenue' => $car_stats['TOTAL_REVENUE'] ?? 0,
        'avg_rating' => $rating_data['AVG_RATING'] ? round($rating_data['AVG_RATING'], 1) : 0,
        'review_count' => $rating_data['REVIEW_COUNT'] ?? 0
    ];
}

// Step 8: Handle toggle availability
if (isset($_POST['toggle_status']) && isset($_POST['carid'])) {
    $carid = $_POST['carid'];
    $new_status = $_POST['new_status'];
    
    // Verify car belongs to this lessor
    $sql_verify = "SELECT CARID FROM CAR WHERE CARID = :carid AND LESSORID = :lessorid";
    $verify = db_fetch_one($sql_verify, ['carid' => $carid, 'lessorid' => $user_id]);
    
    if ($verify) {
        // Exception 1: Check if car has active bookings
        $sql_check_bookings = "SELECT COUNT(*) as active_count, MAX(END_DATE) as last_booking_date
                              FROM BOOKING 
                              WHERE CARID = :carid 
                              AND BOOKING_STATUS IN ('CONFIRMED', 'ONGOING')
                              AND END_DATE >= SYSDATE";
        $booking_check = db_fetch_one($sql_check_bookings, ['carid' => $carid]);
        
        if ($booking_check['ACTIVE_COUNT'] > 0 && $new_status === 'INACTIVE') {
            $error_message = "Cannot deactivate. This car has active bookings until " . date('d M Y', strtotime($booking_check['LAST_BOOKING_DATE'])) . ".";
        } else {
            $sql_update = "UPDATE CAR SET AVAILABILITY_STATUS = :status WHERE CARID = :carid AND LESSORID = :lessorid";
            $result = db_execute($sql_update, [
                'status' => $new_status,
                'carid' => $carid,
                'lessorid' => $user_id
            ]);
            
            if ($result) {
                $success_message = "Car status updated successfully!";
                header("Location: my_cars.php?status=" . $status_filter . "&success=status_updated");
                exit();
            } else {
                $error_message = "Failed to update car status. Please try again.";
            }
        }
    } else {
        $error_message = "Invalid car selection.";
    }
}

// Check for success message from URL
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'status_updated') {
        $success_message = "Car status updated successfully!";
    } elseif ($_GET['success'] === 'car_removed') {
        $success_message = "Car removed successfully!";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Car Listings - Car Rental</title>
</head>
<body>
    <nav>
        <h2>Car Rental System - Lessor Panel</h2>
        <p>Welcome, <?php echo htmlspecialchars($user_name); ?>!</p>
        <a href="dashboard.php">Dashboard</a>
        <a href="add_car.php">Add New Car</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
    
    <main>
        <h1>My Car Listings</h1>
        <p>Manage your vehicles, update availability, and track performance</p>
        
        <?php if ($success_message): ?>
            <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <!-- Step 7: Filter by Status -->
        <h3>Filter by Status:</h3>
        <a href="my_cars.php?status=ALL">All Cars</a>
        <a href="my_cars.php?status=AVAILABLE">Available</a>
        <a href="my_cars.php?status=RENTED">Rented</a>
        <a href="my_cars.php?status=INACTIVE">Inactive</a>
        <a href="my_cars.php?status=MAINTENANCE">Maintenance</a>
        <p>Current filter: <?php echo htmlspecialchars($status_filter); ?></p>
        
        <hr>
        
        <!-- Exception 2: No cars listed -->
        <?php if ($has_no_cars): ?>
            <h2>You haven't listed any cars yet.</h2>
            <p>Start earning by listing your first vehicle!</p>
            <a href="add_car.php">Add Your First Car</a>
        
        <?php else: ?>
        
        <!-- Step 3: Display car listings -->
        <h2>Your Cars (<?php echo count($cars); ?> total)</h2>
        
        <?php foreach ($cars as $car): 
            $car_stats = $stats[$car['CARID']];
            $first_photo = $car['PHOTO1_PATH'] ? '../uploads/cars/' . $car['PHOTO1_PATH'] : '';
        ?>
        
        <div class="car-item">
            <h3><?php echo htmlspecialchars($car['BRAND'] . ' ' . $car['MODEL']); ?></h3>
            
            <?php if ($first_photo): ?>
                <img src="<?php echo htmlspecialchars($first_photo); ?>" 
                     alt="<?php echo htmlspecialchars($car['BRAND'] . ' ' . $car['MODEL']); ?>" 
                     width="300">
            <?php endif; ?>
            
            <p><strong>Year:</strong> <?php echo htmlspecialchars($car['YEAR']); ?></p>
            <p><strong>License Plate:</strong> <?php echo htmlspecialchars($car['LICENSE_PLATE']); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($car['AVAILABILITY_STATUS']); ?></p>
            <p><strong>Daily Rate:</strong> RM<?php echo number_format($car['DAILY_RATE'], 2); ?></p>
            
            <!-- Step 4: Display statistics -->
            <h4>Statistics:</h4>
            <ul>
                <li>Total Bookings: <?php echo $car_stats['total_bookings']; ?></li>
                <li>Total Revenue: RM<?php echo number_format($car_stats['total_revenue'], 2); ?></li>
                <li>Average Rating: <?php echo $car_stats['avg_rating'] > 0 ? $car_stats['avg_rating'] . ' stars' : 'No ratings yet'; ?></li>
                <li>Number of Reviews: <?php echo $car_stats['review_count']; ?></li>
            </ul>
            
            <!-- Step 6: Edit and Remove options -->
            <a href="edit_car.php?id=<?php echo $car['CARID']; ?>">Edit Car</a>
            <a href="remove_car.php?id=<?php echo $car['CARID']; ?>" 
               onclick="return confirm('Are you sure you want to remove this car?');">Remove Car</a>
            
            <!-- Step 8: Toggle Availability -->
            <form method="POST" action="">
                <input type="hidden" name="carid" value="<?php echo $car['CARID']; ?>">
                <input type="hidden" name="toggle_status" value="1">
                
                <?php if ($car['AVAILABILITY_STATUS'] === 'AVAILABLE'): ?>
                    <input type="hidden" name="new_status" value="INACTIVE">
                    <button type="submit">Set to Inactive</button>
                    
                <?php elseif ($car['AVAILABILITY_STATUS'] === 'INACTIVE'): ?>
                    <input type="hidden" name="new_status" value="AVAILABLE">
                    <button type="submit">Set to Available</button>
                    
                <?php elseif ($car['AVAILABILITY_STATUS'] === 'MAINTENANCE'): ?>
                    <input type="hidden" name="new_status" value="AVAILABLE">
                    <button type="submit">Mark as Available</button>
                    
                <?php else: ?>
                    <p><em>Currently Rented - Cannot change status</em></p>
                <?php endif; ?>
            </form>
            
            <hr>
        </div>
        
        <?php endforeach; ?>
        
        <?php endif; ?>
        
    </main>
</body>
</html>