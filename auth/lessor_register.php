<?php
/**
 * Lessor Registration Page
 * UC13: Registration - Car Owner Registration
 * 
 * Allows car owners to register with business details and document uploads
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Redirect if already logged in
redirect_if_logged_in();

// Initialize variables
$error = "";
$success = "";
$name = "";
$email = "";
$phone = "";
$business_name = "";
$bank_account = "";
$uploaded_file_names = [
    'driver_license' => '',
    'vehicle_ownership' => '',
    'insurance' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize input (but keep original values for form display)
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $business_name = trim($_POST['business_name']);
    $bank_account = trim($_POST['bank_account']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $terms = isset($_POST['terms']) ? true : false;
    
    // Exception 5: Check if fields are empty
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all required fields.";
        // Remember uploaded file names
        if (isset($_FILES['driver_license']) && $_FILES['driver_license']['error'] == UPLOAD_ERR_OK) {
            $uploaded_file_names['driver_license'] = $_FILES['driver_license']['name'];
        }
        if (isset($_FILES['vehicle_ownership']) && $_FILES['vehicle_ownership']['error'] == UPLOAD_ERR_OK) {
            $uploaded_file_names['vehicle_ownership'] = $_FILES['vehicle_ownership']['name'];
        }
        if (isset($_FILES['insurance']) && $_FILES['insurance']['error'] == UPLOAD_ERR_OK) {
            $uploaded_file_names['insurance'] = $_FILES['insurance']['name'];
        }
    }
    // Check terms agreement
    elseif (!$terms) {
        $error = "You must agree to the terms and conditions.";
        // Remember uploaded file names
        if (isset($_FILES['driver_license']) && $_FILES['driver_license']['error'] == UPLOAD_ERR_OK) {
            $uploaded_file_names['driver_license'] = $_FILES['driver_license']['name'];
        }
        if (isset($_FILES['vehicle_ownership']) && $_FILES['vehicle_ownership']['error'] == UPLOAD_ERR_OK) {
            $uploaded_file_names['vehicle_ownership'] = $_FILES['vehicle_ownership']['name'];
        }
        if (isset($_FILES['insurance']) && $_FILES['insurance']['error'] == UPLOAD_ERR_OK) {
            $uploaded_file_names['insurance'] = $_FILES['insurance']['name'];
        }
    }
    // Exception 6: Password doesn't meet requirements
    elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = "Password must be at least 8 characters with letters, numbers, and special characters.";
        // Remember uploaded file names
        if (isset($_FILES['driver_license']) && $_FILES['driver_license']['error'] == UPLOAD_ERR_OK) {
            $uploaded_file_names['driver_license'] = $_FILES['driver_license']['name'];
        }
        if (isset($_FILES['vehicle_ownership']) && $_FILES['vehicle_ownership']['error'] == UPLOAD_ERR_OK) {
            $uploaded_file_names['vehicle_ownership'] = $_FILES['vehicle_ownership']['name'];
        }
        if (isset($_FILES['insurance']) && $_FILES['insurance']['error'] == UPLOAD_ERR_OK) {
            $uploaded_file_names['insurance'] = $_FILES['insurance']['name'];
        }
    }
    // Check if passwords match
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
        // Remember uploaded file names
        if (isset($_FILES['driver_license']) && $_FILES['driver_license']['error'] == UPLOAD_ERR_OK) {
            $uploaded_file_names['driver_license'] = $_FILES['driver_license']['name'];
        }
        if (isset($_FILES['vehicle_ownership']) && $_FILES['vehicle_ownership']['error'] == UPLOAD_ERR_OK) {
            $uploaded_file_names['vehicle_ownership'] = $_FILES['vehicle_ownership']['name'];
        }
        if (isset($_FILES['insurance']) && $_FILES['insurance']['error'] == UPLOAD_ERR_OK) {
            $uploaded_file_names['insurance'] = $_FILES['insurance']['name'];
        }
    }
    // Exception 4: Check if required documents are uploaded
    elseif (!isset($_FILES['driver_license']) || $_FILES['driver_license']['error'] == UPLOAD_ERR_NO_FILE ||
            !isset($_FILES['vehicle_ownership']) || $_FILES['vehicle_ownership']['error'] == UPLOAD_ERR_NO_FILE ||
            !isset($_FILES['insurance']) || $_FILES['insurance']['error'] == UPLOAD_ERR_NO_FILE) {
        $error = "Please upload all required documents: Driver's License, Vehicle Ownership Proof, and Insurance.";
    }
    else {
        // Validate documents
        $documents = [
            'driver_license' => $_FILES['driver_license'],
            'vehicle_ownership' => $_FILES['vehicle_ownership'],
            'insurance' => $_FILES['insurance']
        ];
        
        $upload_errors = [];
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        foreach ($documents as $key => $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $upload_errors[] = "Error uploading " . str_replace('_', ' ', $key);
                continue;
            }
            
            // Exception 3: Check file size
            if ($file['size'] > $max_size) {
                $upload_errors[] = "Document size must not exceed 5MB. Please compress and re-upload " . str_replace('_', ' ', $key) . ".";
                continue;
            }
            
            // Exception 2: Check file format
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_extension, $allowed_extensions)) {
                $upload_errors[] = "Please upload " . str_replace('_', ' ', $key) . " in PDF, JPG, or PNG format only.";
                continue;
            }
            
            // Validate MIME type for security
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($mime_type, $allowed_mimes)) {
                $upload_errors[] = "Invalid file type for " . str_replace('_', ' ', $key) . ".";
            }
        }
        
        if (!empty($upload_errors)) {
            $error = implode("<br>", $upload_errors);
        }
        else {
            // Exception 1: Check if email already exists
            $sql_check = "SELECT EMAIL FROM LESSOR WHERE EMAIL = :email";
            $existing = db_fetch_one($sql_check, ['email' => $email]);
            
            if ($existing) {
                $error = "Email already registered. Please login or use a different email.";
            }
            else {
                // Create uploads directory if not exists
                $upload_dir = '../uploads/documents/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Upload documents with unique names
                $uploaded_files = [];
                $upload_success = true;
                
                foreach ($documents as $key => $file) {
                    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $unique_name = uniqid() . '_' . time() . '_' . $key . '.' . $file_extension;
                    $destination = $upload_dir . $unique_name;
                    
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $uploaded_files[$key] = $unique_name;
                    } else {
                        $upload_success = false;
                        break;
                    }
                }
                
                if (!$upload_success) {
                    // Clean up uploaded files if any upload failed
                    foreach ($uploaded_files as $filename) {
                        if (file_exists($upload_dir . $filename)) {
                            unlink($upload_dir . $filename);
                        }
                    }
                    $error = "Failed to upload documents. Please try again.";
                }
                else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new lessor with PENDING verification status including document paths
                    // Don't use sanitize_input here - it converts apostrophes to HTML entities
                    // Database binding already prevents SQL injection
                    $sql_insert = "INSERT INTO LESSOR (LESSORID, NAME, EMAIL, PHONE, PASSWORD, BUSINESS_NAME, BANK_ACCOUNT, 
                                  DRIVER_LICENSE_PATH, VEHICLE_OWNERSHIP_PATH, INSURANCE_PATH, VERIFICATION_STATUS, CREATED_AT) 
                                  VALUES (SEQ_LESSOR.NEXTVAL, :name, :email, :phone, :password, :business_name, :bank_account,
                                  :driver_license, :vehicle_ownership, :insurance, 'PENDING', SYSDATE)";
                    
                    $params = [
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'password' => $hashed_password,
                        'business_name' => $business_name,
                        'bank_account' => $bank_account,
                        'driver_license' => $uploaded_files['driver_license'],
                        'vehicle_ownership' => $uploaded_files['vehicle_ownership'],
                        'insurance' => $uploaded_files['insurance']
                    ];
                    
                    if (db_execute($sql_insert, $params)) {
                        $success = "Registration successful! Your account is pending verification. Admin will verify your account within 24-48 hours. You will receive an email once verified.";
                        
                        // Clear form
                        $name = "";
                        $email = "";
                        $phone = "";
                        $business_name = "";
                        $bank_account = "";
                    }
                    else {
                        // Clean up uploaded files if database insert failed
                        foreach ($uploaded_files as $filename) {
                            if (file_exists($upload_dir . $filename)) {
                                unlink($upload_dir . $filename);
                            }
                        }
                        $error = "Registration failed. Please try again.";
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
    <title>Car Owner Registration - Car Rental</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/lessor_register.css?v=<?php echo time(); ?>">
    <script>
        function clearFile(inputId, displayId) {
            document.getElementById(inputId).value = '';
            document.getElementById(displayId).textContent = '';
        }
        
        function displayFileName(inputId, displayId) {
            const input = document.getElementById(inputId);
            const display = document.getElementById(displayId);
            
            if (input.files && input.files[0]) {
                const fileName = input.files[0].name;
                const fileSize = (input.files[0].size / 1024 / 1024).toFixed(2); // MB
                display.textContent = fileName + ' (' + fileSize + ' MB)';
            } else {
                display.textContent = '';
            }
        }
    </script>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>Car Owner Registration</h1>
            <p>Register to list your vehicles and start earning</p>
        
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success">
                    <?php echo $success; ?>
                    <p style="margin-top: 10px;"><a href="lessor_login.php">Click here to login</a></p>
                </div>
            <?php else: ?>
        
            <form method="POST" action="" enctype="multipart/form-data">
                <h2>Personal Information</h2>
                
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>
                </div>
                
                <h2>Business Details (Optional)</h2>
                
                <div class="form-group">
                    <label for="business_name">Business Name</label>
                    <input type="text" id="business_name" name="business_name" value="<?php echo htmlspecialchars($business_name); ?>">
                </div>
                
                <div class="form-group">
                    <label for="bank_account">Bank Account Number</label>
                    <input type="text" id="bank_account" name="bank_account" value="<?php echo htmlspecialchars($bank_account); ?>">
                    <small>For receiving payments</small>
                </div>
                
                <h2>Required Documents</h2>
                <p>Please upload clear copies of the following documents (PDF, JPG, or PNG, max 5MB each):</p>
                
                <div class="form-group">
                    <label for="driver_license">Driver's License *</label>
                    <input type="file" id="driver_license" name="driver_license" accept=".pdf,.jpg,.jpeg,.png" required onchange="displayFileName('driver_license', 'driver_license_display')">
                    <span id="driver_license_display"></span>
                    <button type="button" onclick="clearFile('driver_license', 'driver_license_display')">Clear</button>
                </div>
                
                <div class="form-group">
                    <label for="vehicle_ownership">Vehicle Ownership Proof *</label>
                    <input type="file" id="vehicle_ownership" name="vehicle_ownership" accept=".pdf,.jpg,.jpeg,.png" required onchange="displayFileName('vehicle_ownership', 'vehicle_ownership_display')">
                    <span id="vehicle_ownership_display"></span>
                    <button type="button" onclick="clearFile('vehicle_ownership', 'vehicle_ownership_display')">Clear</button>
                </div>
                
                <div class="form-group">
                    <label for="insurance">Insurance Document *</label>
                    <input type="file" id="insurance" name="insurance" accept=".pdf,.jpg,.jpeg,.png" required onchange="displayFileName('insurance', 'insurance_display')">
                    <span id="insurance_display"></span>
                    <button type="button" onclick="clearFile('insurance', 'insurance_display')">Clear</button>
                </div>
                
                <h2>Account Security</h2>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required>
                    <small>Must be at least 8 characters with letters, numbers, and special characters</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="terms" required>
                        I agree to the Terms and Conditions *
                    </label>
                </div>
                
                <button type="submit">Register</button>
            </form>
            
            <?php endif; ?>
            
            <div class="auth-links">
                <p>Already have an account? <a href="lessor_login.php">Login here</a></p>
                <p>
                    <a href="renter_login.php">Login as Customer</a> | 
                    <a href="../index.php">Back to Home</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>