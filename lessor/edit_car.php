<?php
/**
 * Lessor Edit Car Page
 * UC17: Car Listing - Edit functionality
 * 
 * Allows verified lessors to edit existing car details
 * SRS Page 64 (Step 6: Edit car details)
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

// Get car ID from URL
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
    // Validate rental rates
    elseif ($daily_rate <= 0 || ($weekly_rate && $weekly_rate <= 0) || ($monthly_rate && $monthly_rate <= 0)) {
        $error = "Please enter valid rental rates greater than zero.";
    }
    else {
        // Check if license plate changed and is unique
        if ($license_plate !== $car['LICENSE_PLATE']) {
            $sql_check = "SELECT CARID FROM CAR WHERE LICENSE_PLATE = :license_plate AND CARID != :carid";
            $existing = db_fetch_one($sql_check, ['license_plate' => $license_plate, 'carid' => $car_id]);
            
            if ($existing) {
                $error = "This license plate is already registered to another car.";
            }
        }
        
        if (empty($error)) {
            // Count current photos before removal
            $current_photo_count = 0;
            for ($i = 1; $i <= 10; $i++) {
                $photo_field = 'PHOTO' . $i . '_PATH';
                if (!empty($car[$photo_field])) {
                    $current_photo_count++;
                }
            }
            
            // Handle photo removal
            $photos_to_remove = isset($_POST['remove_photos']) ? array_filter($_POST['remove_photos']) : [];
            
            // Validate minimum photo requirement
            $photos_after_removal = $current_photo_count - count($photos_to_remove);
            $new_photos_count = 0;
            if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
                $new_photos_count = count(array_filter($_FILES['photos']['name']));
            }
            
            // Check if final photo count will be at least 1
            if ($photos_after_removal + $new_photos_count < 1) {
                $error = "You must keep at least 1 photo for your car listing.";
            } else {
                foreach ($photos_to_remove as $photo_num) {
                    if (empty($photo_num)) continue;
                    
                    $photo_field = 'PHOTO' . $photo_num . '_PATH';
                    if (!empty($car[$photo_field])) {
                        // Delete photo file from server
                        $photo_file = '../uploads/cars/' . $car[$photo_field];
                        if (file_exists($photo_file)) {
                            unlink($photo_file);
                        }
                        
                        // Mark photo for removal in database
                        $sql_remove_photo = "UPDATE CAR SET $photo_field = NULL WHERE CARID = :carid AND LESSORID = :lessorid";
                        db_execute($sql_remove_photo, ['carid' => $car_id, 'lessorid' => $lessor_id]);
                    }
                }
            }
            
            // Handle photo updates if new photos uploaded (only if no error)
            $photo_updates = [];
            $uploaded_photos = [];
            
            if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
                $photos = $_FILES['photos'];
                $allowed_extensions = ['jpg', 'jpeg', 'png'];
                $max_size = 5 * 1024 * 1024; // 5MB
                $photo_count = count($photos['name']);
                $upload_errors = [];
                
                // Validate each new photo
                $valid_photos = [];
                for ($i = 0; $i < $photo_count && $i < 10; $i++) {
                    if ($photos['error'][$i] !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    
                    // Check file size
                    if ($photos['size'][$i] > $max_size) {
                        $upload_errors[] = "Photo " . ($i + 1) . " exceeds 5MB.";
                        continue;
                    }
                    
                    // Check file format
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
                } else {
                    // Upload new photos
                    $upload_dir = '../uploads/cars/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
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
                }
            }
            
            if (empty($error)) {
                // Build update query
                $sql_update = "UPDATE CAR SET 
                              BRAND = :brand,
                              MODEL = :model,
                              YEAR = :year,
                              TRANSMISSION = :transmission,
                              FUEL_TYPE = :fuel_type,
                              SEATS = :seats,
                              COLOR = :color,
                              LICENSE_PLATE = :license_plate,
                              DAILY_RATE = :daily_rate,
                              WEEKLY_RATE = :weekly_rate,
                              MONTHLY_RATE = :monthly_rate,
                              INSURANCE_FEE = :insurance_fee,
                              DELIVERY_FEE = :delivery_fee,
                              MILEAGE = :mileage,
                              DESCRIPTION = :description,
                              FEATURES = :features,
                              LOCATION = :location";
                
                $params = [
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
                    'location' => $location ?: null
                ];
                
                // Add photo updates if new photos uploaded
                if (!empty($uploaded_photos)) {
                    // Find available photo slots (including removed ones)
                    $photo_slots = [];
                    for ($i = 1; $i <= 10; $i++) {
                        $photo_field = 'PHOTO' . $i . '_PATH';
                        if (in_array($i, $photos_to_remove) || empty($car[$photo_field])) {
                            $photo_slots[] = $i;
                        }
                    }
                    
                    // Assign new photos to available slots
                    for ($i = 0; $i < count($uploaded_photos) && $i < count($photo_slots); $i++) {
                        $slot = $photo_slots[$i];
                        $photo_field = 'PHOTO' . $slot . '_PATH';
                        $sql_update .= ", $photo_field = :photo" . $slot;
                        $params['photo' . $slot] = $uploaded_photos[$i];
                    }
                }
                
                $sql_update .= " WHERE CARID = :carid AND LESSORID = :lessorid";
                $params['carid'] = $car_id;
                $params['lessorid'] = $lessor_id;
                
                if (db_execute($sql_update, $params)) {
                    $success = "Car details updated successfully!";
                    // Refresh car data
                    $car = db_fetch_one($sql_car, ['carid' => $car_id, 'lessorid' => $lessor_id]);
                } else {
                    $error = "Failed to update car details. Please try again.";
                }
            }
        }
    }
}

// Get current features as array
$current_features = !empty($car['FEATURES']) ? explode(', ', $car['FEATURES']) : [];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Car - Car Rental</title>
    <link rel="stylesheet" href="../assets/css/edit-car.css">
    <script>
        let selectedFiles = [];
        
        function handlePhotoSelect(event) {
            const files = Array.from(event.target.files);
            files.forEach(file => {
                if (selectedFiles.length < 10) {
                    selectedFiles.push(file);
                }
            });
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
            
            preview.innerHTML = '';
            const count = selectedFiles.length;
            
            if (count === 0) {
                countDisplay.innerHTML = 'No new photos selected. Current photos will remain unchanged.';
            } else if (count > 10) {
                countDisplay.innerHTML = count + ' photos selected. Maximum 10 allowed.';
            } else {
                countDisplay.innerHTML = count + ' new photo(s) selected. These will replace existing photos.';
            }
            
            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.width = 150;
                    
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.innerHTML = 'Remove';
                    removeBtn.onclick = function() { removePhoto(index); };
                    
                    div.appendChild(img);
                    div.appendChild(removeBtn);
                    preview.appendChild(div);
                }
                reader.readAsDataURL(file);
            });
        }
        
        function validateAndSubmit(event) {
            if (selectedFiles.length > 10) {
                alert('Maximum 10 photos allowed.');
                event.preventDefault();
                return false;
            }
            
            if (selectedFiles.length > 0) {
                const dataTransfer = new DataTransfer();
                selectedFiles.forEach(file => {
                    dataTransfer.items.add(file);
                });
                document.getElementById('photos_actual').files = dataTransfer.files;
            }
            
            return true;
        }
    </script>
   
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
        <h1>Edit Car Details</h1>
        <p>Update information for: <?php echo htmlspecialchars($car['BRAND'] . ' ' . $car['MODEL']); ?></p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <?php echo $success; ?>
                <p><a href="my_cars.php">Back to My Cars</a></p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data" onsubmit="return validateAndSubmit(event)">
            
            <h2>Basic Information</h2>
            
            <label for="brand">Car Brand/Make *</label>
            <input type="text" id="brand" name="brand" value="<?php echo htmlspecialchars($car['BRAND']); ?>" required>
            
            <label for="model">Car Model *</label>
            <input type="text" id="model" name="model" value="<?php echo htmlspecialchars($car['MODEL']); ?>" required>
            
            <label for="year">Year *</label>
            <input type="number" id="year" name="year" min="1900" max="2025" value="<?php echo htmlspecialchars($car['YEAR']); ?>" required>
            
            <label for="license_plate">License Plate Number *</label>
            <input type="text" id="license_plate" name="license_plate" value="<?php echo htmlspecialchars($car['LICENSE_PLATE']); ?>" required>
            
            <h2>Car Specifications</h2>
            
            <label for="transmission">Transmission Type *</label>
            <select id="transmission" name="transmission" required>
                <option value="">Select Transmission</option>
                <option value="Automatic" <?php echo $car['TRANSMISSION'] === 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
                <option value="Manual" <?php echo $car['TRANSMISSION'] === 'Manual' ? 'selected' : ''; ?>>Manual</option>
            </select>
            
            <label for="fuel_type">Fuel Type *</label>
            <select id="fuel_type" name="fuel_type" required>
                <option value="">Select Fuel Type</option>
                <option value="Petrol" <?php echo $car['FUEL_TYPE'] === 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                <option value="Diesel" <?php echo $car['FUEL_TYPE'] === 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                <option value="Hybrid" <?php echo $car['FUEL_TYPE'] === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                <option value="Electric" <?php echo $car['FUEL_TYPE'] === 'Electric' ? 'selected' : ''; ?>>Electric</option>
            </select>
            
            <label for="seats">Number of Seats</label>
            <input type="number" id="seats" name="seats" min="2" max="9" value="<?php echo htmlspecialchars($car['SEATS']); ?>">
            
            <label for="color">Color</label>
            <input type="text" id="color" name="color" value="<?php echo htmlspecialchars($car['COLOR']); ?>">
            
            <label for="mileage">Mileage (km)</label>
            <input type="number" id="mileage" name="mileage" value="<?php echo htmlspecialchars($car['MILEAGE']); ?>">
            
            <h2>Features</h2>
            <p>Select all features that apply:</p>
            
            <label><input type="checkbox" name="features[]" value="GPS" <?php echo in_array('GPS', $current_features) ? 'checked' : ''; ?>> GPS Navigation</label>
            <label><input type="checkbox" name="features[]" value="Bluetooth" <?php echo in_array('Bluetooth', $current_features) ? 'checked' : ''; ?>> Bluetooth</label>
            <label><input type="checkbox" name="features[]" value="Air Conditioning" <?php echo in_array('Air Conditioning', $current_features) ? 'checked' : ''; ?>> Air Conditioning</label>
            <label><input type="checkbox" name="features[]" value="Sunroof" <?php echo in_array('Sunroof', $current_features) ? 'checked' : ''; ?>> Sunroof</label>
            <label><input type="checkbox" name="features[]" value="Parking Sensors" <?php echo in_array('Parking Sensors', $current_features) ? 'checked' : ''; ?>> Parking Sensors</label>
            <label><input type="checkbox" name="features[]" value="Reverse Camera" <?php echo in_array('Reverse Camera', $current_features) ? 'checked' : ''; ?>> Reverse Camera</label>
            <label><input type="checkbox" name="features[]" value="USB Port" <?php echo in_array('USB Port', $current_features) ? 'checked' : ''; ?>> USB Port</label>
            <label><input type="checkbox" name="features[]" value="Child Seat" <?php echo in_array('Child Seat', $current_features) ? 'checked' : ''; ?>> Child Seat Available</label>
            
            <h2>Current Photos</h2>
            <p>You can remove existing photos individually. (Minimum 1 photo required)</p>
            <div id="current_photos_container">
                <?php 
                $total_current_photos = 0;
                for ($i = 1; $i <= 10; $i++): 
                    $photo_field = 'PHOTO' . $i . '_PATH';
                    if (!empty($car[$photo_field])) {
                        $total_current_photos++;
                    }
                endfor;
                
                for ($i = 1; $i <= 10; $i++): 
                    $photo_field = 'PHOTO' . $i . '_PATH';
                    if (!empty($car[$photo_field])): ?>
                        <div style="display: inline-block; margin: 10px; text-align: center;" id="photo_<?php echo $i; ?>_container">
                            <img src="../uploads/cars/<?php echo htmlspecialchars($car[$photo_field]); ?>" width="150" alt="Car Photo <?php echo $i; ?>" id="photo_<?php echo $i; ?>_img">
                            <br>
                            <button type="button" onclick="confirmRemovePhoto(<?php echo $i; ?>)">Delete Photo <?php echo $i; ?></button>
                            <input type="hidden" name="remove_photos[]" value="" id="remove_photo_<?php echo $i; ?>">
                        </div>
                <?php endif; endfor; ?>
            </div>
            
            <p id="photo_count_display">Current photos: <strong id="current_photo_count"><?php echo $total_current_photos; ?></strong> (Minimum 1 required)</p>
            
            <script>
                let currentPhotoCount = <?php echo $total_current_photos; ?>;
                const removedPhotos = new Set();
                
                function confirmRemovePhoto(photoNum) {
                    // Check if removing would leave less than 1 photo
                    if (currentPhotoCount - removedPhotos.size <= 1) {
                        alert('You must keep at least 1 photo for your car listing.');
                        return;
                    }
                    
                    if (confirm('Are you sure you want to delete Photo ' + photoNum + '?')) {
                        // Mark photo for removal
                        removedPhotos.add(photoNum);
                        document.getElementById('remove_photo_' + photoNum).value = photoNum;
                        
                        // Hide photo visually
                        const container = document.getElementById('photo_' + photoNum + '_container');
                        container.style.opacity = '0.3';
                        container.style.border = '2px solid red';
                        
                        // Update button text
                        const button = container.querySelector('button');
                        button.textContent = 'Undo Delete';
                        button.onclick = function() { undoRemovePhoto(photoNum); };
                        
                        // Update photo count display
                        updatePhotoCount();
                    }
                }
                
                function undoRemovePhoto(photoNum) {
                    // Remove from removed set
                    removedPhotos.delete(photoNum);
                    document.getElementById('remove_photo_' + photoNum).value = '';
                    
                    // Restore photo visually
                    const container = document.getElementById('photo_' + photoNum + '_container');
                    container.style.opacity = '1';
                    container.style.border = 'none';
                    
                    // Restore button
                    const button = container.querySelector('button');
                    button.textContent = 'Delete Photo ' + photoNum;
                    button.onclick = function() { confirmRemovePhoto(photoNum); };
                    
                    // Update photo count display
                    updatePhotoCount();
                }
                
                function updatePhotoCount() {
                    const remaining = currentPhotoCount - removedPhotos.size;
                    document.getElementById('current_photo_count').textContent = remaining;
                    
                    const display = document.getElementById('photo_count_display');
                    if (remaining <= 1) {
                        display.style.color = 'red';
                        display.innerHTML = 'Current photos: <strong id="current_photo_count">' + remaining + '</strong> (Minimum 1 required - Cannot delete more)';
                    } else {
                        display.style.color = 'black';
                        display.innerHTML = 'Current photos: <strong id="current_photo_count">' + remaining + '</strong> (Minimum 1 required)';
                    }
                }
            </script>
            
            <h2>Add New Photos (Optional)</h2>
            <p>Upload new photos (JPG or PNG, max 5MB each, up to 10 photos total)</p>
            
            <input type="file" id="photos_selector" accept=".jpg,.jpeg,.png" multiple onchange="handlePhotoSelect(event)">
            <input type="file" id="photos_actual" name="photos[]" accept=".jpg,.jpeg,.png" multiple style="display: none;">
            
            <div id="photo_count">No new photos selected. Current photos will remain unchanged.</div>
            <button type="button" onclick="clearAllPhotos()">Clear All New Photos</button>
            
            <div id="photo_preview_grid"></div>
            
            <h2>Rental Rates *</h2>
            
            <label for="daily_rate">Daily Rate (RM) *</label>
            <input type="number" id="daily_rate" name="daily_rate" step="0.01" min="0" value="<?php echo htmlspecialchars($car['DAILY_RATE']); ?>" required>
            
            <label for="weekly_rate">Weekly Rate (RM)</label>
            <input type="number" id="weekly_rate" name="weekly_rate" step="0.01" min="0" value="<?php echo htmlspecialchars($car['WEEKLY_RATE']); ?>">
            
            <label for="monthly_rate">Monthly Rate (RM)</label>
            <input type="number" id="monthly_rate" name="monthly_rate" step="0.01" min="0" value="<?php echo htmlspecialchars($car['MONTHLY_RATE']); ?>">
            
            <h2>Additional Charges (Optional)</h2>
            
            <label for="insurance_fee">Insurance Fee (RM per day)</label>
            <input type="number" id="insurance_fee" name="insurance_fee" step="0.01" min="0" value="<?php echo htmlspecialchars($car['INSURANCE_FEE']); ?>">
            
            <label for="delivery_fee">Delivery Fee (RM)</label>
            <input type="number" id="delivery_fee" name="delivery_fee" step="0.01" min="0" value="<?php echo htmlspecialchars($car['DELIVERY_FEE']); ?>">
            
            <h2>Location & Description</h2>
            
            <label for="location">Pickup/Return Location</label>
            <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($car['LOCATION']); ?>">
            
            <label for="description">Car Description</label>
            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($car['DESCRIPTION']); ?></textarea>
            
            <button type="submit">Update Car Details</button>
            <a href="my_cars.php">Cancel</a>
        </form>
    </main>
</body>
</html>