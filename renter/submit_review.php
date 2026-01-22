<?php
/**
 * UC6: Review and Rating (SRS Pages 31-33)
 * Renter submits review after completed booking
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Check if renter is logged in
require_login('renter');

$renterid = get_user_id();
$renter_name = get_user_name();
$errors = [];
$success = "";

// Get booking ID from URL
if (!isset($_GET['bookingid'])) {
    set_flash_message("Invalid booking ID.", "error");
    header("Location: booking_history.php");
    exit();
}

$bookingid = $_GET['bookingid'];

// UC6 Step 2: Fetch booking details and verify eligibility
$sql = "SELECT b.BOOKINGID, b.CARID, b.RENTERID, b.BOOKING_STATUS, b.END_DATE,
        c.BRAND, c.MODEL, c.YEAR, c.PHOTO1_PATH, c.LICENSE_PLATE,
        l.NAME as LESSOR_NAME,
        (SELECT COUNT(*) FROM REVIEW WHERE BOOKINGID = b.BOOKINGID) as HAS_REVIEW
        FROM BOOKING b
        JOIN CAR c ON b.CARID = c.CARID
        JOIN LESSOR l ON c.LESSORID = l.LESSORID
        WHERE b.BOOKINGID = :bookingid 
        AND b.RENTERID = :renterid";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':bookingid', $bookingid);
oci_bind_by_name($stmt, ':renterid', $renterid);
oci_execute($stmt);

$booking = oci_fetch_assoc($stmt);

// Precondition validation
if (!$booking) {
    set_flash_message("Booking not found or you don't have permission to review this booking.", "error");
    header("Location: booking_history.php");
    exit();
}

// Precondition: Booking must be completed
if ($booking['BOOKING_STATUS'] != 'COMPLETED') {
    set_flash_message("You can only review completed bookings.", "error");
    header("Location: booking_history.php");
    exit();
}

// Alternative Flow: Customer can only submit one review per booking
if ($booking['HAS_REVIEW'] > 0) {
    set_flash_message("You have already submitted a review for this booking.", "warning");
    header("Location: booking_history.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $remark = trim($_POST['remark']);
    
    if (empty($remark)) {
        $errors[] = "Please provide a review comment.";
    }
    
    if ($rating < 1 || $rating > 5) {
        $errors[] = "Please select a rating between 1 and 5 stars.";
    }
    
    if (strlen($remark) > 500) {
        $errors[] = "Review is too long. Maximum 500 characters.";
    }
    
    if (empty($errors)) {
        try {
            $review_sql = "INSERT INTO REVIEW 
                          (REVIEWID, BOOKINGID, RENTERID, CARID, RATING, REMARK, REVIEW_DATE)
                          VALUES 
                          (SEQ_REVIEW.NEXTVAL, :bookingid, :renterid, :carid, :rating, :remark, SYSDATE)";
            
            $stmt_review = oci_parse($conn, $review_sql);
            oci_bind_by_name($stmt_review, ':bookingid', $bookingid);
            oci_bind_by_name($stmt_review, ':renterid', $renterid);
            oci_bind_by_name($stmt_review, ':carid', $booking['CARID']);
            oci_bind_by_name($stmt_review, ':rating', $rating);
            oci_bind_by_name($stmt_review, ':remark', $remark);
            
            if (oci_execute($stmt_review)) {
                oci_commit($conn);
                set_flash_message("Thank you! Your review has been submitted successfully.", "success");
                header("Location: booking_history.php");
                exit();
            } else {
                $e = oci_error($stmt_review);
                $errors[] = "Failed to submit review: " . $e['message'];
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred while submitting your review.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Review - Car Rental System</title>
    <link rel="stylesheet" href="../assets/css/submit-reviwe.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <nav>
        <div class="nav-container">
            <h2><a href="dashboard.php">Car Rental System</a></h2>
            <div class="nav-links">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($renter_name); ?>!</span>
                <a href="dashboard.php">Dashboard</a>
                <a href="booking_history.php" class="active">My Bookings</a>
                <a href="search_cars.php">Search Cars</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <header class="hero">
        <h1>Share Your Experience</h1>
        <p>Booking Reference: #<?php echo htmlspecialchars($booking['BOOKINGID']); ?></p>
    </header>

    <main class="details-container">
        <!-- Left Column: Review Form -->
        <div class="left-column">
            <?php if (!empty($errors)): ?>
                <div class="details-card" style="border-left: 4px solid #ef4444; padding: 20px;">
                    <strong style="color: #b91c1c; display: block; margin-bottom: 10px;">⚠️ Please Correct the Following:</strong>
                    <ul style="color: #b91c1c; font-size: 14px; margin-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="details-card">
                <h2>📝 Write a Review</h2>
                
                <div class="guidelines-card">
                    <h4><i class="fas fa-info-circle"></i> Review Guidelines</h4>
                    <ul>
                        <li>Be honest and constructive in your feedback.</li>
                        <li>Focus on your experience with the car's condition and the owner.</li>
                        <li>Maximum 500 characters allowed.</li>
                    </ul>
                </div>

                <form method="POST" action="" id="reviewForm">
                    <div class="form-group">
                        <label>Overall Rating *</label>
                        <div class="rating-container">
                            <div class="star-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label class="star" data-rating="<?php echo $i; ?>">
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" 
                                               style="display:none;" required>
                                        ★
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <div class="rating-text" id="rating-text">Select stars to rate</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="remark">Your Experience *</label>
                        <textarea name="remark" id="remark" required 
                                  placeholder="How was the car? Was the owner helpful? Share your thoughts..."><?php echo isset($_POST['remark']) ? htmlspecialchars($_POST['remark']) : ''; ?></textarea>
                        <div class="char-counter">
                            <span id="char-count">0</span> / 500 Characters
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn-primary">Submit My Review</button>
                        <a href="booking_history.php" class="btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column: Car Summary -->
        <aside class="summary-card">
            <div class="details-card">
                <h2>Car Details</h2>
                <?php if ($booking['PHOTO1_PATH']): ?>
                    <img src="../uploads/cars/<?php echo htmlspecialchars($booking['PHOTO1_PATH']); ?>" 
                         class="car-summary-img" alt="Car">
                <?php endif; ?>
                
                <h3><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL']); ?></h3>
                
                <div class="summary-details">
                    <p><span>License Plate</span> <?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></p>
                    <p><span>Car Owner</span> <?php echo htmlspecialchars($booking['LESSOR_NAME']); ?></p>
                </div>

                <div class="status-row">
                    <p><span>Booking Status</span></p>
                    <div style="margin-top: 8px;">
                        <span class="badge-success">✓ COMPLETED</span>
                    </div>
                </div>
            </div>
        </aside>
    </main>

    <script>
    // Star rating interaction
    const stars = document.querySelectorAll('.star');
    const ratingText = document.getElementById('rating-text');
    const labels = {
        1: 'Poor - Not recommended',
        2: 'Fair - Could be better',
        3: 'Good - Met expectations',
        4: 'Very Good - Highly satisfied',
        5: 'Excellent - Perfect experience!'
    };
    
    let currentRating = 0;

    stars.forEach(star => {
        star.addEventListener('mouseenter', () => {
            const rating = star.dataset.rating;
            updateStars(rating);
            ratingText.textContent = labels[rating];
        });

        star.addEventListener('mouseleave', () => {
            if (currentRating === 0) {
                resetStars();
                ratingText.textContent = 'Select stars to rate';
            } else {
                updateStars(currentRating);
                ratingText.textContent = labels[currentRating];
            }
        });

        star.addEventListener('click', () => {
            currentRating = star.dataset.rating;
            star.querySelector('input').checked = true;
            updateStars(currentRating);
            ratingText.textContent = labels[currentRating];
            ratingText.style.color = '#1e293b';
        });
    });

    function updateStars(rating) {
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.add('active');
            } else {
                star.classList.remove('active');
            }
        });
    }

    function resetStars() {
        stars.forEach(star => star.classList.remove('active'));
    }

    // Character counter
    const remark = document.getElementById('remark');
    const charCount = document.getElementById('char-count');

    remark.addEventListener('input', () => {
        const length = remark.value.length;
        charCount.textContent = length;
        
        if (length > 450) {
            charCount.style.color = '#ef4444';
        } else if (length > 400) {
            charCount.style.color = '#f59e0b';
        } else {
            charCount.style.color = '#94a3b8';
        }
    });

    // Initial check
    if (remark.value.length > 0) {
        charCount.textContent = remark.value.length;
    }
    </script>
</body>
</html>