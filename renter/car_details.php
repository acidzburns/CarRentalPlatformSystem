<?php
/**
 * Renter Car Details Page
 * UC3: Car Details View (includes UC7, UC8, UC9, UC10)
 * 
 * Display detailed car information including photos, specs, rates, and owner info
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is logged in as renter
check_authentication('renter');

$renter_name = get_user_name();
$car_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Exception 1: Car is no longer available
$error = "";

if ($car_id <= 0) {
    $error = "Invalid car ID.";
} else {
    // UC7: Car Specification - Retrieve car details
    $sql_car = "SELECT c.CARID, c.BRAND, c.MODEL, c.YEAR, c.TRANSMISSION, c.FUEL_TYPE, 
                       c.SEATS, c.COLOR, c.LICENSE_PLATE, c.MILEAGE, c.DESCRIPTION,
                       c.DAILY_RATE, c.WEEKLY_RATE, c.MONTHLY_RATE, c.INSURANCE_FEE, c.DELIVERY_FEE,
                       c.FEATURES, c.LOCATION, c.AVAILABILITY_STATUS,
                       c.PHOTO1_PATH, c.PHOTO2_PATH, c.PHOTO3_PATH, c.PHOTO4_PATH, c.PHOTO5_PATH,
                       c.PHOTO6_PATH, c.PHOTO7_PATH, c.PHOTO8_PATH, c.PHOTO9_PATH, c.PHOTO10_PATH,
                       l.LESSORID, l.NAME as LESSOR_NAME, l.EMAIL as LESSOR_EMAIL, l.PHONE as LESSOR_PHONE
                FROM CAR c
                JOIN LESSOR l ON c.LESSORID = l.LESSORID
                WHERE c.CARID = :carid";
    
    $car = db_fetch_one($sql_car, ['carid' => $car_id]);
    
    if (!$car) {
        $error = "Car not found.";
    } elseif ($car['AVAILABILITY_STATUS'] !== 'AVAILABLE') {
        // Exception 1: Car is no longer available
        $error = "This car is currently unavailable. Please check other options.";
    }
    
    // Get car reviews and ratings (UC6 related)
    if ($car) {
        $sql_reviews = "SELECT r.RATING, r.REMARK, r.REVIEW_DATE, 
                               re.NAME as RENTER_NAME
                        FROM REVIEW r
                        JOIN RENTER re ON r.RENTERID = re.RENTERID
                        WHERE r.CARID = :carid
                        ORDER BY r.REVIEW_DATE DESC";
        $reviews = db_fetch_all($sql_reviews, ['carid' => $car_id]);
        
        // Calculate average rating
        $sql_avg = "SELECT AVG(RATING) as AVG_RATING, COUNT(*) as TOTAL_REVIEWS 
                    FROM REVIEW WHERE CARID = :carid";
        $rating_data = db_fetch_one($sql_avg, ['carid' => $car_id]);
        
        // Get booking count
        $sql_bookings = "SELECT COUNT(*) as TOTAL FROM BOOKING 
                        WHERE CARID = :carid AND BOOKING_STATUS IN ('CONFIRMED', 'COMPLETED')";
        $booking_data = db_fetch_one($sql_bookings, ['carid' => $car_id]);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Details - Car Rental</title>
</head>
<body>
    <nav>
        <h2>Car Rental System</h2>
        <div>
            <span>Welcome, <?php echo htmlspecialchars($renter_name); ?>!</span>
            <a href="dashboard.php">Dashboard</a>
            <a href="search_cars.php">Search Cars</a>
            <a href="booking_history.php">My Bookings</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>
    
    <main>
        <?php if ($error): ?>
            <div>
                <h2>Error</h2>
                <p><?php echo $error; ?></p>
                <a href="search_cars.php">Back to Search</a>
            </div>
        <?php else: ?>
            <h1><?php echo htmlspecialchars($car['BRAND'] . ' ' . $car['MODEL'] . ' ' . $car['YEAR']); ?></h1>
            
            <!-- UC9: Photos - Display all car photos -->
            <div>
                <h2>Car Photos</h2>
                <?php
                $photos = [];
                for ($i = 1; $i <= 10; $i++) {
                    $photo_key = 'PHOTO' . $i . '_PATH';
                    if (!empty($car[$photo_key])) {
                        $photos[] = $car[$photo_key];
                    }
                }
                
                if (empty($photos)):
                    // Exception 1 from UC9: Photos not available
                    echo "<p>No photos available for this car.</p>";
                else:
                    foreach ($photos as $index => $photo):
                ?>
                    <img src="../uploads/cars/<?php echo htmlspecialchars($photo); ?>" 
                         alt="Car Photo <?php echo ($index + 1); ?>" 
                         width="400" height="300">
                <?php 
                    endforeach;
                endif;
                ?>
            </div>
            
            <!-- UC7: Car Specification -->
            <div>
                <h2>Car Specifications</h2>
                <table border="1">
                    <tr>
                        <th>Brand</th>
                        <td><?php echo htmlspecialchars($car['BRAND']); ?></td>
                    </tr>
                    <tr>
                        <th>Model</th>
                        <td><?php echo htmlspecialchars($car['MODEL']); ?></td>
                    </tr>
                    <tr>
                        <th>Year</th>
                        <td><?php echo htmlspecialchars($car['YEAR']); ?></td>
                    </tr>
                    <tr>
                        <th>Transmission</th>
                        <td><?php echo htmlspecialchars($car['TRANSMISSION']); ?></td>
                    </tr>
                    <tr>
                        <th>Fuel Type</th>
                        <td><?php echo htmlspecialchars($car['FUEL_TYPE']); ?></td>
                    </tr>
                    <tr>
                        <th>Seats</th>
                        <td><?php echo htmlspecialchars($car['SEATS'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Color</th>
                        <td><?php echo htmlspecialchars($car['COLOR'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Mileage</th>
                        <td><?php echo $car['MILEAGE'] ? number_format($car['MILEAGE']) . ' km' : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <th>License Plate</th>
                        <td><?php echo htmlspecialchars($car['LICENSE_PLATE']); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Features -->
            <?php if (!empty($car['FEATURES'])): ?>
            <div>
                <h2>Features</h2>
                <p><?php echo htmlspecialchars($car['FEATURES']); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Description -->
            <?php if (!empty($car['DESCRIPTION'])): ?>
            <div>
                <h2>Description</h2>
                <p><?php echo nl2br(htmlspecialchars($car['DESCRIPTION'])); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- UC8: Rental Rates -->
            <div>
                <h2>Rental Rates</h2>
                <table border="1">
                    <tr>
                        <th>Daily Rate</th>
                        <td>RM <?php echo number_format($car['DAILY_RATE'], 2); ?></td>
                    </tr>
                    <?php if ($car['WEEKLY_RATE']): ?>
                    <tr>
                        <th>Weekly Rate</th>
                        <td>RM <?php echo number_format($car['WEEKLY_RATE'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($car['MONTHLY_RATE']): ?>
                    <tr>
                        <th>Monthly Rate</th>
                        <td>RM <?php echo number_format($car['MONTHLY_RATE'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <h3>Additional Charges</h3>
                <table border="1">
                    <?php if ($car['INSURANCE_FEE']): ?>
                    <tr>
                        <th>Insurance Fee (per day)</th>
                        <td>RM <?php echo number_format($car['INSURANCE_FEE'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($car['DELIVERY_FEE']): ?>
                    <tr>
                        <th>Delivery Fee</th>
                        <td>RM <?php echo number_format($car['DELIVERY_FEE'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- Location -->
            <?php if (!empty($car['LOCATION'])): ?>
            <div>
                <h2>Pickup/Return Location</h2>
                <p><?php echo htmlspecialchars($car['LOCATION']); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- UC10: Owner Information -->
            <div>
                <h2>Owner Information</h2>
                <table border="1">
                    <tr>
                        <th>Owner Name</th>
                        <td><?php echo htmlspecialchars($car['LESSOR_NAME']); ?></td>
                    </tr>
                    <tr>
                        <th>Contact Email</th>
                        <td><?php echo htmlspecialchars($car['LESSOR_EMAIL']); ?></td>
                    </tr>
                    <tr>
                        <th>Contact Phone</th>
                        <td><?php echo htmlspecialchars($car['LESSOR_PHONE'] ?? 'Not provided'); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Ratings & Reviews -->
            <?php if ($rating_data && $rating_data['TOTAL_REVIEWS'] > 0): ?>
            <div>
                <h2>Ratings & Reviews</h2>
                <p>
                    <strong>Average Rating: <?php echo number_format($rating_data['AVG_RATING'], 1); ?>/5</strong>
                    (<?php echo $rating_data['TOTAL_REVIEWS']; ?> reviews)
                </p>
                
                <?php if (!empty($reviews)): ?>
                    <h3>Customer Reviews</h3>
                    <?php foreach ($reviews as $review): ?>
                        <div>
                            <p><strong><?php echo htmlspecialchars($review['RENTER_NAME']); ?></strong> - Rating: <?php echo $review['RATING']; ?>/5</p>
                            <p><?php echo htmlspecialchars($review['REMARK']); ?></p>
                            <p><small><?php echo date('F j, Y', strtotime($review['REVIEW_DATE'])); ?></small></p>
                            <hr>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Booking Statistics -->
            <?php if ($booking_data && $booking_data['TOTAL'] > 0): ?>
            <div>
                <p><strong>This car has been booked <?php echo $booking_data['TOTAL']; ?> times</strong></p>
            </div>
            <?php endif; ?>
            
            <!-- Book Now Button -->
            <div>
                <h2>Book This Car</h2>
                <a href="create_booking.php?carid=<?php echo $car['CARID']; ?>">
                    <button type="button">Book Now</button>
                </a>
                <a href="search_cars.php">Back to Search</a>
            </div>
            
        <?php endif; ?>
    </main>
</body>
</html>