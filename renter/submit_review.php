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
    set_flash_message("You can only review completed bookings. This booking status is: " . $booking['BOOKING_STATUS'], "error");
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
    // UC6 Step 4: Customer enters rating and review
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $remark = trim($_POST['remark']);
    
    // UC6 Step 5: System validates input
    
    // Exception 1: Review text is empty
    if (empty($remark)) {
        $errors[] = "Please provide a review comment.";
    }
    
    // Validate rating (1-5)
    if ($rating < 1 || $rating > 5) {
        $errors[] = "Please select a rating between 1 and 5 stars.";
    }
    
    // Exception 3: Review text exceeds maximum length (500 characters)
    if (strlen($remark) > 500) {
        $errors[] = "Review is too long. Maximum 500 characters.";
    }
    
    // Exception 2: Inappropriate content check (basic)
    $inappropriate_words = ['spam', 'scam', 'fake']; // Simple check
    $lower_remark = strtolower($remark);
    foreach ($inappropriate_words as $word) {
        if (strpos($lower_remark, $word) !== false) {
            $errors[] = "Review contains inappropriate language. Please revise.";
            break;
        }
    }
    
    // If no errors, submit review
    if (empty($errors)) {
        try {
            // UC6 Step 6: Customer submits review
            // UC6 Step 7: System saves review to database
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
                
                // UC6 Step 8: System recalculates car's average rating
                // (This happens automatically when fetching reviews)
                
                // UC6 Step 9: System sends notification to car owner (simulated)
                // In production: Send email to lessor
                
                // UC6 Step 10: System displays success message
                set_flash_message("Thank you! Your review has been submitted successfully.", "success");
                header("Location: booking_history.php");
                exit();
            } else {
                $e = oci_error($stmt_review);
                $errors[] = "Failed to submit review: " . $e['message'];
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred while submitting your review. Please try again.";
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .car-info {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .car-info img {
            width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: 5px;
        }
        .error-box {
            background: #ffebee;
            border: 1px solid #f44336;
            color: #c62828;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .error-box ul {
            margin-left: 20px;
        }
        .form-group {
            margin: 20px 0;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }
        .rating-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .star-rating {
            display: flex;
            gap: 5px;
            font-size: 40px;
        }
        .star {
            cursor: pointer;
            color: #ddd;
            transition: color 0.2s;
        }
        .star:hover,
        .star.active {
            color: #ffd700;
        }
        input[type="radio"] {
            display: none;
        }
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: Arial, sans-serif;
            font-size: 14px;
            resize: vertical;
            min-height: 150px;
        }
        textarea:focus {
            outline: none;
            border-color: #4CAF50;
        }
        .char-counter {
            text-align: right;
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        .guidelines {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .guidelines h3 {
            margin-bottom: 10px;
            color: #1976D2;
        }
        .guidelines ul {
            margin-left: 20px;
        }
        .guidelines li {
            margin: 5px 0;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            flex: 1;
        }
        .btn-submit {
            background: #4CAF50;
            color: white;
        }
        .btn-submit:hover {
            background: #45a049;
        }
        .btn-cancel {
            background: #f44336;
            color: white;
        }
        .btn-cancel:hover {
            background: #da190b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📝 Submit Your Review</h1>
        <p style="color: #666; margin-top: 5px;">Share your experience with other renters</p>
        
        <?php display_flash_message(); ?>
        
        <!-- Display errors -->
        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <strong>⚠️ Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- UC6 Step 2: Display booking details -->
        <div class="car-info">
            <?php if ($booking['PHOTO1_PATH']): ?>
                <img src="../uploads/cars/<?php echo htmlspecialchars($booking['PHOTO1_PATH']); ?>" 
                     alt="Car Photo">
            <?php endif; ?>
            
            <div>
                <h3><?php echo htmlspecialchars($booking['BRAND'] . ' ' . $booking['MODEL'] . ' ' . $booking['YEAR']); ?></h3>
                <p><strong>License Plate:</strong> <?php echo htmlspecialchars($booking['LICENSE_PLATE']); ?></p>
                <p><strong>Booking ID:</strong> #<?php echo htmlspecialchars($booking['BOOKINGID']); ?></p>
                <p><strong>Owner:</strong> <?php echo htmlspecialchars($booking['LESSOR_NAME']); ?></p>
                <p><strong>Status:</strong> <span style="color: #4CAF50;">✓ <?php echo htmlspecialchars($booking['BOOKING_STATUS']); ?></span></p>
            </div>
        </div>
        
        <!-- Review Guidelines -->
        <div class="guidelines">
            <h3>📋 Review Guidelines</h3>
            <ul>
                <li>Be honest and constructive in your feedback</li>
                <li>Focus on your experience with the car and owner</li>
                <li>Keep your review respectful and appropriate</li>
                <li>Maximum 500 characters</li>
                <li>You can only submit one review per booking</li>
            </ul>
        </div>
        
        <!-- UC6 Step 3: Review Form -->
        <form method="POST" action="" id="reviewForm">
            
            <!-- UC6 Step 4: Rating Input -->
            <div class="form-group">
                <label>⭐ Overall Rating *</label>
                <p style="color: #666; font-size: 14px; margin-bottom: 10px;">
                    How would you rate your overall experience?
                </p>
                
                <div class="rating-container">
                    <div class="star-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label class="star" data-rating="<?php echo $i; ?>">
                                <input type="radio" name="rating" value="<?php echo $i; ?>" required>
                                ★
                            </label>
                        <?php endfor; ?>
                    </div>
                    <span id="rating-text" style="color: #666; font-size: 16px;"></span>
                </div>
            </div>
            
            <!-- UC6 Step 4: Review Text Input -->
            <div class="form-group">
                <label for="remark">📖 Your Review *</label>
                <p style="color: #666; font-size: 14px; margin-bottom: 10px;">
                    Tell us about your experience with this car and the owner
                </p>
                <textarea name="remark" id="remark" required 
                          placeholder="Share details about the car condition, cleanliness, owner communication, and overall experience..."
                          maxlength="500"></textarea>
                <div class="char-counter">
                    <span id="char-count">0</span> / 500 characters
                </div>
            </div>
            
            <!-- UC6 Step 6: Submit Button -->
            <div class="btn-group">
                <button type="submit" class="btn btn-submit">
                    ✓ Submit Review
                </button>
                <a href="booking_history.php" class="btn btn-cancel">
                    ✗ Cancel
                </a>
            </div>
        </form>
    </div>
    
    <script>
    // Star rating functionality
    const stars = document.querySelectorAll('.star');
    const ratingText = document.getElementById('rating-text');
    const ratingLabels = {
        1: '⭐ Poor',
        2: '⭐⭐ Fair',
        3: '⭐⭐⭐ Good',
        4: '⭐⭐⭐⭐ Very Good',
        5: '⭐⭐⭐⭐⭐ Excellent'
    };
    
    let selectedRating = 0;
    
    stars.forEach(star => {
        // Hover effect
        star.addEventListener('mouseenter', function() {
            const rating = this.dataset.rating;
            highlightStars(rating);
            ratingText.textContent = ratingLabels[rating];
        });
        
        // Click to select
        star.addEventListener('click', function() {
            selectedRating = this.dataset.rating;
            this.querySelector('input').checked = true;
        });
    });
    
    // Reset on mouse leave
    document.querySelector('.star-rating').addEventListener('mouseleave', function() {
        if (selectedRating > 0) {
            highlightStars(selectedRating);
            ratingText.textContent = ratingLabels[selectedRating];
        } else {
            resetStars();
            ratingText.textContent = '';
        }
    });
    
    function highlightStars(rating) {
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
    const remarkTextarea = document.getElementById('remark');
    const charCount = document.getElementById('char-count');
    
    remarkTextarea.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = length;
        
        // Change color when approaching limit
        if (length > 450) {
            charCount.style.color = '#f44336';
        } else if (length > 400) {
            charCount.style.color = '#ff9800';
        } else {
            charCount.style.color = '#666';
        }
    });
    
    // Form validation before submit
    document.getElementById('reviewForm').addEventListener('submit', function(e) {
        const rating = document.querySelector('input[name="rating"]:checked');
        const remark = remarkTextarea.value.trim();
        
        if (!rating) {
            e.preventDefault();
            alert('Please select a rating.');
            return false;
        }
        
        if (remark.length === 0) {
            e.preventDefault();
            alert('Please write a review.');
            return false;
        }
        
        if (remark.length > 500) {
            e.preventDefault();
            alert('Review is too long. Maximum 500 characters.');
            return false;
        }
        
        // Confirm submission
        if (!confirm('Submit this review? You cannot edit it later.')) {
            e.preventDefault();
            return false;
        }
    });
    </script>
    
    <p style="text-align: center; margin-top: 30px;">
        <a href="booking_history.php" style="color: #2196F3; text-decoration: none;">
            ← Back to Booking History
        </a>
    </p>
</body>
</html>