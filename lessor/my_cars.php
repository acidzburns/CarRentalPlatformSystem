<?php
/**
 * Lessor My Cars Page
 * UC17: Car Listing
 * Allows verified lessors to view and manage their car listings
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

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'ALL';
$valid_statuses = ['ALL', 'AVAILABLE', 'RENTED', 'INACTIVE', 'MAINTENANCE'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'ALL';
}

// Retrieve cars
if ($status_filter === 'ALL') {
    $sql = "SELECT * FROM CAR WHERE LESSORID = :lessorid ORDER BY CREATED_AT DESC";
    $params = ['lessorid' => $user_id];
} else {
    $sql = "SELECT * FROM CAR WHERE LESSORID = :lessorid AND AVAILABILITY_STATUS = :status ORDER BY CREATED_AT DESC";
    $params = ['lessorid' => $user_id, 'status' => $status_filter];
}

$cars = db_query($sql, $params);
$has_no_cars = empty($cars);

// Get statistics
$stats = [];
foreach ($cars as $car) {
    $sql_bookings = "SELECT COUNT(*) as total_bookings,
                     SUM(CASE WHEN BOOKING_STATUS = 'COMPLETED' THEN TOTAL_AMOUNT ELSE 0 END) as total_revenue
                     FROM BOOKING WHERE CARID = :carid";
    $car_stats = db_fetch_one($sql_bookings, ['carid' => $car['CARID']]);
    
    $sql_rating = "SELECT AVG(RATING) as avg_rating, COUNT(*) as review_count 
                   FROM REVIEW WHERE CARID = :carid";
    $rating_data = db_fetch_one($sql_rating, ['carid' => $car['CARID']]);
    
    $stats[$car['CARID']] = [
        'total_bookings' => $car_stats['TOTAL_BOOKINGS'] ?? 0,
        'total_revenue' => $car_stats['TOTAL_REVENUE'] ?? 0,
        'avg_rating' => $rating_data['AVG_RATING'] ? round($rating_data['AVG_RATING'], 1) : 0,
        'review_count' => $rating_data['REVIEW_COUNT'] ?? 0
    ];
}

// Handle toggle availability
if (isset($_POST['toggle_status']) && isset($_POST['carid'])) {
    $carid = $_POST['carid'];
    $new_status = $_POST['new_status'];
    
    $sql_verify = "SELECT CARID FROM CAR WHERE CARID = :carid AND LESSORID = :lessorid";
    $verify = db_fetch_one($sql_verify, ['carid' => $carid, 'lessorid' => $user_id]);
    
    if ($verify) {
        $sql_check_bookings = "SELECT COUNT(*) as active_count, MAX(END_DATE) as last_booking_date
                               FROM BOOKING WHERE CARID = :carid 
                               AND BOOKING_STATUS IN ('CONFIRMED', 'ONGOING')
                               AND END_DATE >= SYSDATE";
        $booking_check = db_fetch_one($sql_check_bookings, ['carid' => $carid]);
        
        if ($booking_check['ACTIVE_COUNT'] > 0 && $new_status === 'INACTIVE') {
            $error_message = "Cannot deactivate. Active bookings exist until " . date('d M Y', strtotime($booking_check['LAST_BOOKING_DATE'])) . ".";
        } else {
            $sql_update = "UPDATE CAR SET AVAILABILITY_STATUS = :status WHERE CARID = :carid AND LESSORID = :lessorid";
            $result = db_execute($sql_update, [
                'status' => $new_status,
                'carid' => $carid,
                'lessorid' => $user_id
            ]);
            
            if ($result) {
                header("Location: my_cars.php?status=" . $status_filter . "&success=status_updated");
                exit();
            } else {
                $error_message = "Failed to update car status.";
            }
        }
    } else {
        $error_message = "Invalid car selection.";
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'status_updated') $success_message = "Car status updated successfully!";
    elseif ($_GET['success'] === 'car_removed') $success_message = "Car removed successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Car Listings - Car Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/my-car.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <nav>
        <div class="nav-container">
            <h2>Car Rental System <span style="font-size:14px; font-weight:400; opacity:0.8;">| Lessor Panel</span></h2>
            <div class="nav-links">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                <a href="dashboard.php">Dashboard</a>
                <a href="my_cars.php" class="active">My Cars</a>
                <a href="bookings.php">Bookings</a>
                <a href="earnings.php">Earnings</a>
                <a href="submit_ticket.php">Support</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>
    
    <main>
        <div class="page-header">
            <h1>My Car Listings</h1>
            <p>Manage your vehicles, update availability, and track performance</p>
        </div>
        
        <div class="content-container">
            <?php if ($success_message): ?>
                <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <div class="filter-bar">
                <span class="filter-label">Filter by Status:</span>
                <a href="my_cars.php?status=ALL" class="<?php echo $status_filter == 'ALL' ? 'active' : ''; ?>">All</a>
                <a href="my_cars.php?status=AVAILABLE" class="<?php echo $status_filter == 'AVAILABLE' ? 'active' : ''; ?>">Available</a>
                <a href="my_cars.php?status=RENTED" class="<?php echo $status_filter == 'RENTED' ? 'active' : ''; ?>">Rented</a>
                <a href="my_cars.php?status=INACTIVE" class="<?php echo $status_filter == 'INACTIVE' ? 'active' : ''; ?>">Inactive</a>
                <a href="my_cars.php?status=MAINTENANCE" class="<?php echo $status_filter == 'MAINTENANCE' ? 'active' : ''; ?>">Maintenance</a>
            </div>
            
            <hr class="divider">
            
            <div style="margin-bottom: 20px; text-align: right;">
                <a href="add_car.php" class="btn-add">+ Add New Car</a>
            </div>
            
            <?php if ($has_no_cars): ?>
                <div class="empty-state">
                    <h2>You haven't listed any cars yet.</h2>
                    <p>Start earning by listing your first vehicle!</p>
                    <a href="add_car.php" class="btn-primary">Add Your First Car</a>
                </div>
            <?php else: ?>
            
            <div class="car-grid">
                <?php foreach ($cars as $car): 
                    $car_stats = $stats[$car['CARID']];
                    $first_photo = $car['PHOTO1_PATH'] ? '../uploads/cars/' . $car['PHOTO1_PATH'] : '';
                ?>
                
                <div class="car-card">
                    <div class="car-image-box">
                        <?php if ($first_photo): ?>
                            <img src="<?php echo htmlspecialchars($first_photo); ?>" alt="Car Photo">
                        <?php else: ?>
                            <div class="no-photo">No Photo</div>
                        <?php endif; ?>
                        <span class="status-badge status-<?php echo strtolower($car['AVAILABILITY_STATUS']); ?>">
                            <?php echo htmlspecialchars($car['AVAILABILITY_STATUS']); ?>
                        </span>
                    </div>
                    
                    <div class="car-details">
                        <h3><?php echo htmlspecialchars($car['BRAND'] . ' ' . $car['MODEL']); ?> <span class="year">(<?php echo htmlspecialchars($car['YEAR']); ?>)</span></h3>
                        
                        <div class="info-row">
                            <span class="label">License:</span> <?php echo htmlspecialchars($car['LICENSE_PLATE']); ?>
                        </div>
                        <div class="info-row">
                            <span class="label">Rate:</span> <span class="price">RM<?php echo number_format($car['DAILY_RATE'], 2); ?></span> /day
                        </div>
                        
                        <div class="stats-box">
                            <div class="stat">
                                <span class="val"><?php echo $car_stats['total_bookings']; ?></span>
                                <span class="lbl">Bookings</span>
                            </div>
                            <div class="stat">
                                <span class="val">RM<?php echo number_format($car_stats['total_revenue'], 0); ?></span>
                                <span class="lbl">Revenue</span>
                            </div>
                            <div class="stat">
                                <span class="val"><?php echo $car_stats['avg_rating'] > 0 ? $car_stats['avg_rating'] : '-'; ?></span>
                                <span class="lbl">Rating</span>
                            </div>
                        </div>
                        
                        <div class="actions">
                            <a href="edit_car.php?id=<?php echo $car['CARID']; ?>" class="btn-action edit">Edit</a>
                            <a href="remove_car.php?id=<?php echo $car['CARID']; ?>" class="btn-action delete" onclick="return confirm('Are you sure?');">Remove</a>
                            
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="carid" value="<?php echo $car['CARID']; ?>">
                                <input type="hidden" name="toggle_status" value="1">
                                
                                <?php if ($car['AVAILABILITY_STATUS'] === 'AVAILABLE'): ?>
                                    <input type="hidden" name="new_status" value="INACTIVE">
                                    <button type="submit" class="btn-action toggle inactive">Deactivate</button>
                                <?php elseif ($car['AVAILABILITY_STATUS'] === 'INACTIVE' || $car['AVAILABILITY_STATUS'] === 'MAINTENANCE'): ?>
                                    <input type="hidden" name="new_status" value="AVAILABLE">
                                    <button type="submit" class="btn-action toggle active">Activate</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>