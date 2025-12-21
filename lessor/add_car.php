<?php
/**
 * Lessor Add Car Page
 * UC18: Add New Car
 * 
 * Allows verified lessors to list a new car for rent
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is logged in as lessor
check_authentication('lessor');

$lessor_id = get_user_id();
$lessor_name = get_user_name();

// Initialize variables
$error = "";
$success = "";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get basic car information
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $year = trim($_POST['year']);
    $license_plate = trim($_POST['license_plate']);
    $transmission = trim($_POST['transmission']);
    $fuel_type = trim($_POST['fuel_type']);
    $seats = trim($_POST['seats']);
    $color = trim($_POST['color']);
    $mileage = trim($_POST['mileage']);
    $description = trim($_POST['description']);
    
    // Rental rates
    $daily_rate = trim($_POST['daily_rate']);
    $weekly_rate = trim($_POST['weekly_rate']);
    $monthly_rate = trim($_POST['monthly_rate']);
    
    // Additional charges
    $insurance_fee = trim($_POST['insurance_fee']);
    $delivery_fee = trim($_POST['delivery_fee']);
    
    // Location
    $location = trim($_POST['location']);
    
    // Features (checkboxes)
    $features = [];
    if (isset($_POST['features'])) {
        $features = $_POST['features'];
    }
    $features_string = implode(', ', $features);
    
    // Validate required fields
    if (empty($brand) || empty($model) || empty($year) || empty($license_plate) || empty($daily_rate)) {
        $error = "Please fill in all required fields: Brand, Model, Year, License Plate, and Daily Rate.";
    }
    // Exception 3: Invalid rental rates
    elseif ($daily_rate <= 0 || ($weekly_rate && $weekly_rate <= 0) || ($monthly_rate && $monthly_rate <= 0)) {
        $error = "Please enter valid rental rates greater than zero.";
    }
    // Exception 4: Check if photos are uploaded
    elseif (!isset($_FILES['photos']) || count($_FILES['photos']['name']) < 3) {
        $error = "Please upload at least 3 photos of your car.";
    }
    else {
        // Exception 5: Check if license plate already exists
        $sql_check = "SELECT CARID FROM CAR WHERE LICENSE_PLATE = :license_plate";
        $existing = db_fetch_one($sql_check, ['license_plate' => $license_plate]);
        
        if ($existing) {
            $error = "This license plate is already registered. Each car must have a unique license plate.";
        }
        else {
            // Validate and upload photos
            $photos = $_FILES['photos'];
            $upload_errors = [];
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $photo_count = count($photos['name']);
            
            // Check photo count (min 3, max 10)
            if ($photo_count < 3) {
                $upload_errors[] = "Please upload at least 3 photos.";
            }
            elseif ($photo_count > 10) {
                $upload_errors[] = "Maximum 10 photos allowed.";
            }
            
            // Validate each photo
            $valid_photos = [];
            for ($i = 0; $i < $photo_count && $i < 10; $i++) {
                if ($photos['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                // Exception 2: Check file size
                if ($photos['size'][$i] > $max_size) {
                    $upload_errors[] = "Photo " . ($i + 1) . " exceeds 5MB. Please compress and re-upload.";
                    continue;
                }
                
                // Exception 1: Check file format
                $file_extension = strtolower(pathinfo($photos['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($file_extension, $allowed_extensions)) {
                    $upload_errors[] = "Photo " . ($i + 1) . " must be JPG or PNG format.";
                    continue;
                }
                
                // Validate MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $photos['tmp_name'][$i]);
                finfo_close($finfo);
                
                $allowed_mimes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!in_array($mime_type, $allowed_mimes)) {
                    $upload_errors[] = "Photo " . ($i + 1) . " has invalid file type.";
                    continue;
                }
                
                $valid_photos[] = $i;
            }
            
            if (!empty($upload_errors)) {
                $error = implode("<br>", $upload_errors);
            }
            elseif (count($valid_photos) < 3) {
                $error = "At least 3 valid photos are required.";
            }
            else {
                // Create upload directory if not exists
                $upload_dir = '../uploads/cars/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Upload photos
                $uploaded_photos = [];
                $upload_success = true;
                
                foreach ($valid_photos as $index) {
                    $file_extension = strtolower(pathinfo($photos['name'][$index], PATHINFO_EXTENSION));
                    $unique_name = uniqid() . '_' . time() . '_' . $index . '.' . $file_extension;
                    $destination = $upload_dir . $unique_name;
                    
                    if (move_uploaded_file($photos['tmp_name'][$index], $destination)) {
                        $uploaded_photos[] = $unique_name;
                    } else {
                        $upload_success = false;
                        break;
                    }
                }
                
                if (!$upload_success) {
                    // Clean up uploaded files
                    foreach ($uploaded_photos as $filename) {
                        if (file_exists($upload_dir . $filename)) {
                            unlink($upload_dir . $filename);
                        }
                    }
                    $error = "Failed to upload photos. Please try again.";
                }
                else {
                    // Insert car into database
                    $sql_insert = "INSERT INTO CAR (
                        CARID, LESSORID, BRAND, MODEL, YEAR, TRANSMISSION, FUEL_TYPE, 
                        SEATS, COLOR, LICENSE_PLATE, DAILY_RATE, WEEKLY_RATE, MONTHLY_RATE,
                        INSURANCE_FEE, DELIVERY_FEE, MILEAGE, DESCRIPTION, FEATURES, LOCATION,
                        PHOTO1_PATH, PHOTO2_PATH, PHOTO3_PATH, PHOTO4_PATH, PHOTO5_PATH,
                        PHOTO6_PATH, PHOTO7_PATH, PHOTO8_PATH, PHOTO9_PATH, PHOTO10_PATH,
                        AVAILABILITY_STATUS, CREATED_AT
                    ) VALUES (
                        SEQ_CAR.NEXTVAL, :lessorid, :brand, :model, :year, :transmission, :fuel_type,
                        :seats, :color, :license_plate, :daily_rate, :weekly_rate, :monthly_rate,
                        :insurance_fee, :delivery_fee, :mileage, :description, :features, :location,
                        :photo1, :photo2, :photo3, :photo4, :photo5,
                        :photo6, :photo7, :photo8, :photo9, :photo10,
                        'AVAILABLE', SYSDATE
                    )";
                    
                    $params = [
                        'lessorid' => $lessor_id,
                        'brand' => $brand,
                        'model' => $model,
                        'year' => $year,
                        'transmission' => $transmission,
                        'fuel_type' => $fuel_type,
                        'seats' => $seats ?: null,
                        'color' => $color ?: null,
                        'license_plate' => $license_plate,
                        'daily_rate' => $daily_rate,
                        'weekly_rate' => $weekly_rate ?: null,
                        'monthly_rate' => $monthly_rate ?: null,
                        'insurance_fee' => $insurance_fee ?: null,
                        'delivery_fee' => $delivery_fee ?: null,
                        'mileage' => $mileage ?: null,
                        'description' => $description ?: null,
                        'features' => $features_string ?: null,
                        'location' => $location ?: null,
                        'photo1' => $uploaded_photos[0] ?? null,
                        'photo2' => $uploaded_photos[1] ?? null,
                        'photo3' => $uploaded_photos[2] ?? null,
                        'photo4' => $uploaded_photos[3] ?? null,
                        'photo5' => $uploaded_photos[4] ?? null,
                        'photo6' => $uploaded_photos[5] ?? null,
                        'photo7' => $uploaded_photos[6] ?? null,
                        'photo8' => $uploaded_photos[7] ?? null,
                        'photo9' => $uploaded_photos[8] ?? null,
                        'photo10' => $uploaded_photos[9] ?? null
                    ];
                    
                    if (db_execute($sql_insert, $params)) {
                        $success = "Your car has been listed successfully! It is now available for customers to book.";
                    }
                    else {
                        // Clean up uploaded files if database insert failed
                        foreach ($uploaded_photos as $filename) {
                            if (file_exists($upload_dir . $filename)) {
                                unlink($upload_dir . $filename);
                            }
                        }
                        $error = "Failed to list car. Please try again.";
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Car - Car Rental</title>
    <style>
        .photo-upload-container {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            background: #f9f9f9;
        }
        .photo-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .photo-preview-item {
            position: relative;
            border: 2px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }
        .photo-preview-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            display: block;
        }
        .photo-remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: red;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            font-weight: bold;
            line-height: 1;
        }
        .photo-remove-btn:hover {
            background: darkred;
        }
        .photo-count {
            margin-top: 10px;
            font-weight: bold;
        }
        .photo-count.error {
            color: red;
        }
        .photo-count.success {
            color: green;
        }
    </style>
    <script>
        let selectedFiles = [];
        
        function handlePhotoSelect(event) {
            const files = Array.from(event.target.files);
            
            // Add new files to existing selection
            files.forEach(file => {
                if (selectedFiles.length < 10) {
                    selectedFiles.push(file);
                }
            });
            
            // Clear the input to allow selecting the same file again
            event.target.value = '';
            
            updatePhotoPreview();
        }
        
        function removePhoto(index) {
            selectedFiles.splice(index, 1);
            updatePhotoPreview();
        }
        
        function clearAllPhotos() {
            if (confirm('Remove all selected photos?')) {
                selectedFiles = [];
                updatePhotoPreview();
            }
        }
        
        function updatePhotoPreview() {
            const preview = document.getElementById('photo_preview_grid');
            const countDisplay = document.getElementById('photo_count');
            const hiddenInput = document.getElementById('photos_data');
            
            preview.innerHTML = '';
            
            const count = selectedFiles.length;
            
            // Update count display
            if (count === 0) {
                countDisplay.innerHTML = '<span class="error">No photos selected. Please add 3-10 photos.</span>';
            } else if (count < 3) {
                countDisplay.innerHTML = '<span class="error">' + count + ' photo(s) selected. Please add at least ' + (3 - count) + ' more.</span>';
            } else if (count > 10) {
                countDisplay.innerHTML = '<span class="error">' + count + ' photos selected. Maximum 10 allowed. Please remove ' + (count - 10) + '.</span>';
            } else {
                countDisplay.innerHTML = '<span class="success">✓ ' + count + ' photo(s) selected (Valid)</span>';
            }
            
            // Display photo previews
            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'photo-preview-item';
                    
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'photo-remove-btn';
                    removeBtn.innerHTML = '×';
                    removeBtn.onclick = function() { removePhoto(index); };
                    
                    div.appendChild(img);
                    div.appendChild(removeBtn);
                    preview.appendChild(div);
                }
                
                reader.readAsDataURL(file);
            });
        }
        
        function validateAndSubmit(event) {
            if (selectedFiles.length < 3) {
                alert('Please select at least 3 photos.');
                event.preventDefault();
                return false;
            }
            if (selectedFiles.length > 10) {
                alert('Maximum 10 photos allowed. Please remove ' + (selectedFiles.length - 10) + ' photo(s).');
                event.preventDefault();
                return false;
            }
            
            // Create a new DataTransfer to hold the files
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => {
                dataTransfer.items.add(file);
            });
            
            // Set the files to the actual file input
            document.getElementById('photos_actual').files = dataTransfer.files;
            
            return true;
        }
    </script>
</head>
<body>
    <nav>
        <h2>Car Rental System - Lessor Panel</h2>
        <div>
            <span>Welcome, <?php echo htmlspecialchars($lessor_name); ?>!</span>
            <a href="dashboard.php">Dashboard</a>
            <a href="my_cars.php">My Cars</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>
    
    <main>
        <div>
            <h1>List a New Car</h1>
            <p>Fill in the details below to add your car to the platform</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <?php echo $success; ?>
                <p>
                    <a href="my_cars.php">View My Cars</a> | 
                    <a href="add_car.php">Add Another Car</a> |
                    <a href="dashboard.php">Back to Dashboard</a>
                </p>
            </div>
        <?php else: ?>
        
        <form method="POST" action="" enctype="multipart/form-data" onsubmit="return validateAndSubmit(event)">
            <h2>Basic Information</h2>
            
            <div>
                <label for="brand">Car Brand/Make *</label>
                <input type="text" id="brand" name="brand" placeholder="e.g., Toyota, Honda, Proton" required>
            </div>
            
            <div>
                <label for="model">Car Model *</label>
                <input type="text" id="model" name="model" placeholder="e.g., Camry, City, X70" required>
            </div>
            
            <div>
                <label for="year">Year *</label>
                <input type="number" id="year" name="year" min="1900" max="2025" placeholder="e.g., 2023" required>
            </div>
            
            <div>
                <label for="license_plate">License Plate Number *</label>
                <input type="text" id="license_plate" name="license_plate" placeholder="e.g., WXY1234" required>
            </div>
            
            <h2>Car Specifications</h2>
            
            <div>
                <label for="transmission">Transmission Type *</label>
                <select id="transmission" name="transmission" required>
                    <option value="">Select Transmission</option>
                    <option value="Automatic">Automatic</option>
                    <option value="Manual">Manual</option>
                </select>
            </div>
            
            <div>
                <label for="fuel_type">Fuel Type *</label>
                <select id="fuel_type" name="fuel_type" required>
                    <option value="">Select Fuel Type</option>
                    <option value="Petrol">Petrol</option>
                    <option value="Diesel">Diesel</option>
                    <option value="Hybrid">Hybrid</option>
                    <option value="Electric">Electric</option>
                </select>
            </div>
            
            <div>
                <label for="seats">Number of Seats</label>
                <input type="number" id="seats" name="seats" min="2" max="9" placeholder="e.g., 5">
            </div>
            
            <div>
                <label for="color">Color</label>
                <input type="text" id="color" name="color" placeholder="e.g., Black, White, Silver">
            </div>
            
            <div>
                <label for="mileage">Mileage (km)</label>
                <input type="number" id="mileage" name="mileage" placeholder="e.g., 50000">
            </div>
            
            <h2>Features</h2>
            <p>Select all features that apply to your car:</p>
            
            <div>
                <label><input type="checkbox" name="features[]" value="GPS"> GPS Navigation</label>
                <label><input type="checkbox" name="features[]" value="Bluetooth"> Bluetooth</label>
                <label><input type="checkbox" name="features[]" value="Air Conditioning"> Air Conditioning</label>
                <label><input type="checkbox" name="features[]" value="Sunroof"> Sunroof</label>
                <label><input type="checkbox" name="features[]" value="Parking Sensors"> Parking Sensors</label>
                <label><input type="checkbox" name="features[]" value="Reverse Camera"> Reverse Camera</label>
                <label><input type="checkbox" name="features[]" value="USB Port"> USB Port</label>
                <label><input type="checkbox" name="features[]" value="Child Seat"> Child Seat Available</label>
            </div>
            
            <h2>Car Photos *</h2>
            <p>Upload 3-10 clear photos of your car from different angles (JPG or PNG, max 5MB each)</p>
            
            <div class="photo-upload-container">
                <p><strong>📸 Add Your Car Photos</strong></p>
                <p>Click below to select photos. You can add them one by one or multiple at once.</p>
                
                <input type="file" id="photos_selector" accept=".jpg,.jpeg,.png" multiple onchange="handlePhotoSelect(event)" style="display: inline-block; margin: 10px 0;">
                
                <!-- Hidden input for actual form submission -->
                <input type="file" id="photos_actual" name="photos[]" accept=".jpg,.jpeg,.png" multiple style="display: none;">
                
                <div id="photo_count" class="photo-count">
                    <span class="error">No photos selected. Please add 3-10 photos.</span>
                </div>
                
                <button type="button" onclick="clearAllPhotos()" style="margin-top: 10px; background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
                    Clear All Photos
                </button>
            </div>
            
            <div id="photo_preview_grid" class="photo-preview-grid"></div>
            
            <h2>Rental Rates *</h2>
            
            <div>
                <label for="daily_rate">Daily Rate (RM) *</label>
                <input type="number" id="daily_rate" name="daily_rate" step="0.01" min="0" placeholder="e.g., 150.00" required>
            </div>
            
            <div>
                <label for="weekly_rate">Weekly Rate (RM)</label>
                <input type="number" id="weekly_rate" name="weekly_rate" step="0.01" min="0" placeholder="e.g., 900.00">
            </div>
            
            <div>
                <label for="monthly_rate">Monthly Rate (RM)</label>
                <input type="number" id="monthly_rate" name="monthly_rate" step="0.01" min="0" placeholder="e.g., 3000.00">
            </div>
            
            <h2>Additional Charges (Optional)</h2>
            
            <div>
                <label for="insurance_fee">Insurance Fee (RM per day)</label>
                <input type="number" id="insurance_fee" name="insurance_fee" step="0.01" min="0" placeholder="e.g., 20.00">
            </div>
            
            <div>
                <label for="delivery_fee">Delivery Fee (RM)</label>
                <input type="number" id="delivery_fee" name="delivery_fee" step="0.01" min="0" placeholder="e.g., 50.00">
            </div>
            
            <h2>Location & Description</h2>
            
            <div>
                <label for="location">Pickup/Return Location</label>
                <input type="text" id="location" name="location" placeholder="e.g., Kuala Lumpur City Center">
            </div>
            
            <div>
                <label for="description">Car Description</label>
                <textarea id="description" name="description" rows="4" placeholder="Describe your car, its condition, and any special notes..."></textarea>
            </div>
            
            <button type="submit">List My Car</button>
            <a href="dashboard.php">Cancel</a>
        </form>
        
        <?php endif; ?>
    </main>
</body>
</html>