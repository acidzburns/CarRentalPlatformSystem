<?php
/**
 * Renter Search Cars Page
 * UC2: Car Search and Filter
 * * Allows renters to search and filter available cars
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is logged in as renter
check_authentication('renter');

$renter_name = get_user_name();

// Get search parameters
$search_brand = isset($_GET['brand']) ? trim($_GET['brand']) : '';
$search_location = isset($_GET['location']) ? trim($_GET['location']) : '';
$search_transmission = isset($_GET['transmission']) ? trim($_GET['transmission']) : '';
$search_fuel_type = isset($_GET['fuel_type']) ? trim($_GET['fuel_type']) : '';
$search_seats = isset($_GET['seats']) ? trim($_GET['seats']) : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'newest';

// Build SQL query with filters
$sql = "SELECT c.CARID, c.BRAND, c.MODEL, c.YEAR, c.TRANSMISSION, c.FUEL_TYPE, 
               c.SEATS, c.COLOR, c.DAILY_RATE, c.WEEKLY_RATE, c.MONTHLY_RATE,
               c.LOCATION, c.PHOTO1_PATH, c.FEATURES, c.AVAILABILITY_STATUS,
               l.NAME as LESSOR_NAME, l.LESSORID,
               (SELECT COUNT(*) FROM BOOKING WHERE CARID = c.CARID AND BOOKING_STATUS IN ('CONFIRMED', 'COMPLETED')) as BOOKING_COUNT,
               (SELECT AVG(RATING) FROM REVIEW WHERE CARID = c.CARID) as AVG_RATING
        FROM CAR c
        JOIN LESSOR l ON c.LESSORID = l.LESSORID
        WHERE c.AVAILABILITY_STATUS = 'AVAILABLE'
        AND l.VERIFICATION_STATUS = 'VERIFIED'";

$params = [];

// Add filters
if (!empty($search_brand)) {
    $sql .= " AND UPPER(c.BRAND) LIKE UPPER(:brand)";
    $params['brand'] = '%' . $search_brand . '%';
}

if (!empty($search_location)) {
    $sql .= " AND UPPER(c.LOCATION) LIKE UPPER(:location)";
    $params['location'] = '%' . $search_location . '%';
}

if (!empty($search_transmission)) {
    $sql .= " AND c.TRANSMISSION = :transmission";
    $params['transmission'] = $search_transmission;
}

if (!empty($search_fuel_type)) {
    $sql .= " AND c.FUEL_TYPE = :fuel_type";
    $params['fuel_type'] = $search_fuel_type;
}

if (!empty($search_seats)) {
    $sql .= " AND c.SEATS = :seats";
    $params['seats'] = $search_seats;
}

if ($min_price > 0) {
    $sql .= " AND c.DAILY_RATE >= :min_price";
    $params['min_price'] = $min_price;
}

if ($max_price > 0) {
    $sql .= " AND c.DAILY_RATE <= :max_price";
    $params['max_price'] = $max_price;
}

// Add sorting
switch ($sort_by) {
    case 'price_low':
        $sql .= " ORDER BY c.DAILY_RATE ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY c.DAILY_RATE DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY AVG_RATING DESC NULLS LAST";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY c.CREATED_AT DESC";
        break;
}

// Execute search
$cars = db_fetch_all($sql, $params);

// Get filter options for dropdowns
$sql_brands = "SELECT DISTINCT BRAND FROM CAR WHERE AVAILABILITY_STATUS = 'AVAILABLE' ORDER BY BRAND";
$brands = db_fetch_all($sql_brands);

$sql_locations = "SELECT DISTINCT LOCATION FROM CAR WHERE AVAILABILITY_STATUS = 'AVAILABLE' AND LOCATION IS NOT NULL ORDER BY LOCATION";
$locations = db_fetch_all($sql_locations);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Cars - Car Rental</title>
    <link href="../assets/css/search-cars.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="../assets/css/renter-dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav>
        <div class="nav-container">
            <h2><a href="dashboard.php" style="text-decoration:none; color:white;">Car Rental System</a></h2>
            <div class="nav-links">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($renter_name); ?>!</span>
                <a href="dashboard.php">Dashboard</a>
                <a href="search_cars.php" class="active">Search Cars</a>
                <a href="booking_history.php">My Bookings</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>
    
    <main>
        <header class="page-header">
            <h1>Search Available Cars</h1>
            <p>Find your perfect rental car</p>
        </header>
        
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div>
                        <label for="brand">Brand</label>
                        <input type="text" id="brand" name="brand" value="<?php echo htmlspecialchars($search_brand); ?>" placeholder="e.g., Toyota, Honda">
                    </div>
                    
                    <div>
                        <label for="location">Location</label>
                        <select id="location" name="location">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $loc): ?>
                                <?php if (!empty($loc['LOCATION'])): ?>
                                    <option value="<?php echo htmlspecialchars($loc['LOCATION']); ?>" 
                                        <?php echo $search_location === $loc['LOCATION'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc['LOCATION']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="transmission">Transmission</label>
                        <select id="transmission" name="transmission">
                            <option value="">Any</option>
                            <option value="Automatic" <?php echo $search_transmission === 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
                            <option value="Manual" <?php echo $search_transmission === 'Manual' ? 'selected' : ''; ?>>Manual</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="fuel_type">Fuel Type</label>
                        <select id="fuel_type" name="fuel_type">
                            <option value="">Any</option>
                            <option value="Petrol" <?php echo $search_fuel_type === 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                            <option value="Diesel" <?php echo $search_fuel_type === 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                            <option value="Hybrid" <?php echo $search_fuel_type === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                            <option value="Electric" <?php echo $search_fuel_type === 'Electric' ? 'selected' : ''; ?>>Electric</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="seats">Seats</label>
                        <select id="seats" name="seats">
                            <option value="">Any</option>
                            <option value="2" <?php echo $search_seats === '2' ? 'selected' : ''; ?>>2 Seats</option>
                            <option value="4" <?php echo $search_seats === '4' ? 'selected' : ''; ?>>4 Seats</option>
                            <option value="5" <?php echo $search_seats === '5' ? 'selected' : ''; ?>>5 Seats</option>
                            <option value="7" <?php echo $search_seats === '7' ? 'selected' : ''; ?>>7 Seats</option>
                            <option value="9" <?php echo $search_seats === '9' ? 'selected' : ''; ?>>9 Seats</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="min_price">Min Price (RM)</label>
                        <input type="number" id="min_price" name="min_price" value="<?php echo $min_price > 0 ? $min_price : ''; ?>" placeholder="0" step="10">
                    </div>
                    
                    <div>
                        <label for="max_price">Max Price (RM)</label>
                        <input type="number" id="max_price" name="max_price" value="<?php echo $max_price > 0 ? $max_price : ''; ?>" placeholder="1000" step="10">
                    </div>
                    
                    <div>
                        <label for="sort_by">Sort By</label>
                        <select id="sort_by" name="sort_by">
                            <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="price_low" <?php echo $sort_by === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort_by === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="rating" <?php echo $sort_by === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <a href="search_cars.php" class="clear-btn">Clear Filters</a>
                    <button type="submit" class="search-btn">Search Cars</button>
                </div>
            </form>
        </div>
        
        <div class="results-summary">
            <h2>Available Cars (<?php echo count($cars); ?> results)</h2>
        </div>
        
        <div class="cars-grid">
        <?php if (empty($cars)): ?>
            <div class="empty-state">
                <i class="fas fa-search" style="font-size:48px; color:#cbd5e1; margin-bottom:20px;"></i>
                <h3>No cars found</h3>
                <p>No cars match your search criteria. Please try different filters.</p>
                <a href="search_cars.php" class="btn-reset">View All Cars</a>
            </div>
        <?php else: ?>
            <?php foreach ($cars as $car): ?>
                <div class="car-card">
                    <div class="car-image-container">
                        <?php if (!empty($car['PHOTO1_PATH'])): ?>
                            <img src="../uploads/cars/<?php echo htmlspecialchars($car['PHOTO1_PATH']); ?>" 
                                 alt="<?php echo htmlspecialchars($car['BRAND'] . ' ' . $car['MODEL']); ?>">
                        <?php else: ?>
                            <div class="no-image">No Image Available</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="car-details">
                        <div class="car-header">
                            <h3><?php echo htmlspecialchars($car['BRAND'] . ' ' . $car['MODEL']); ?> <span style="font-size:14px; font-weight:400; color:#666;">(<?php echo $car['YEAR']; ?>)</span></h3>
                            <?php if ($car['AVG_RATING']): ?>
                                <div class="rating">
                                    <i class="fas fa-star"></i> <?php echo number_format($car['AVG_RATING'], 1); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="price">RM <?php echo number_format($car['DAILY_RATE'], 2); ?> <span style="font-size:14px; color:#666; font-weight:400;">/day</span></div>
                        
                        <div class="specs">
                            <span><i class="fas fa-cogs"></i> <?php echo htmlspecialchars($car['TRANSMISSION']); ?></span>
                            <span><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($car['FUEL_TYPE']); ?></span>
                            <?php if ($car['SEATS']): ?>
                                <span><i class="fas fa-chair"></i> <?php echo $car['SEATS']; ?> Seats</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($car['LOCATION'])): ?>
                            <div class="location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($car['LOCATION']); ?></div>
                        <?php endif; ?>
                        
                        <a href="car_details.php?id=<?php echo $car['CARID']; ?>" class="book-btn">View Details & Book</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </main>
</body>
</html>