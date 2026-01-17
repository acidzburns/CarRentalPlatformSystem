<?php
/**
 * Lessor Re-submit Verification Page
 * Allows rejected lessors to update their information and re-submit for verification
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is logged in as lessor
check_authentication('lessor');

$lessor_id = get_user_id();

// Get current lessor information
$sql = "SELECT * FROM LESSOR WHERE LESSORID = :lessorid";
$lessor = db_fetch_one($sql, ['lessorid' => $lessor_id]);

// If lessor is not rejected, redirect to dashboard
if ($lessor['VERIFICATION_STATUS'] !== 'REJECTED') {
    header('Location: dashboard.php');
    exit();
}

// Initialize variables
$error = "";
$success = "";
$name = $lessor['NAME'];
$email = $lessor['EMAIL'];
$phone = $lessor['PHONE'];
$business_name = $lessor['BUSINESS_NAME'] ?? '';
$bank_account = $lessor['BANK_ACCOUNT'] ?? '';

// Process re-submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $business_name = trim($_POST['business_name']);
    $bank_account = trim($_POST['bank_account']);
    
    // Check if new documents are uploaded
    $upload_new_documents = isset($_FILES['driver_license']) && $_FILES['driver_license']['error'] != UPLOAD_ERR_NO_FILE;
    
    if ($upload_new_documents) {
        // Validate new documents
        $documents = [
            'driver_license' => $_FILES['driver_license'],
            'vehicle_ownership' => $_FILES['vehicle_ownership'],
            'insurance' => $_FILES['insurance']
        ];
        
        $upload_errors = [];
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        foreach ($documents as $key => $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue; // Skip if not uploaded
            }
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $upload_errors[] = "Error uploading " . str_replace('_', ' ', $key);
                continue;
            }
            
            // Check file size
            if ($file['size'] > $max_size) {
                $upload_errors[] = "Document size must not exceed 5MB. Please compress and re-upload " . str_replace('_', ' ', $key) . ".";
                continue;
            }
            
            // Check file format
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_extension, $allowed_extensions)) {
                $upload_errors[] = "Please upload " . str_replace('_', ' ', $key) . " in PDF, JPG, or PNG format only.";
                continue;
            }
            
            // Validate MIME type
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
        } else {
            // Upload new documents
            $upload_dir = '../uploads/documents/';
            $uploaded_files = [];
            $upload_success = true;
            
            foreach ($documents as $key => $file) {
                if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                
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
            
            if ($upload_success) {
                // Update lessor information with new documents
                $sql_update = "UPDATE LESSOR SET 
                              NAME = :name, 
                              PHONE = :phone, 
                              BUSINESS_NAME = :business_name, 
                              BANK_ACCOUNT = :bank_account";
                
                $params = [
                    'name' => $name,
                    'phone' => $phone,
                    'business_name' => $business_name,
                    'bank_account' => $bank_account
                ];
                
                // Add document paths if uploaded
                if (isset($uploaded_files['driver_license'])) {
                    $sql_update .= ", DRIVER_LICENSE_PATH = :driver_license";
                    $params['driver_license'] = $uploaded_files['driver_license'];
                }
                if (isset($uploaded_files['vehicle_ownership'])) {
                    $sql_update .= ", VEHICLE_OWNERSHIP_PATH = :vehicle_ownership";
                    $params['vehicle_ownership'] = $uploaded_files['vehicle_ownership'];
                }
                if (isset($uploaded_files['insurance'])) {
                    $sql_update .= ", INSURANCE_PATH = :insurance";
                    $params['insurance'] = $uploaded_files['insurance'];
                }
                
                // Change status back to PENDING
                $sql_update .= ", VERIFICATION_STATUS = 'PENDING' WHERE LESSORID = :lessorid";
                $params['lessorid'] = $lessor_id;
                
                if (db_execute($sql_update, $params)) {
                    $success = "Your application has been re-submitted successfully! Admin will review your updated information within 24-48 hours.";
                    // Update session name if changed
                    $_SESSION['user_name'] = $name;
                } else {
                    $error = "Re-submission failed. Please try again.";
                }
            } else {
                $error = "Failed to upload documents. Please try again.";
            }
        }
    } else {
        // Just update info without new documents
        $sql_update = "UPDATE LESSOR SET 
                      NAME = :name, 
                      PHONE = :phone, 
                      BUSINESS_NAME = :business_name, 
                      BANK_ACCOUNT = :bank_account,
                      VERIFICATION_STATUS = 'PENDING'
                      WHERE LESSORID = :lessorid";
        
        $params = [
            'name' => $name,
            'phone' => $phone,
            'business_name' => $business_name,
            'bank_account' => $bank_account,
            'lessorid' => $lessor_id
        ];
        
        if (db_execute($sql_update, $params)) {
            $success = "Your application has been re-submitted successfully! Admin will review your information within 24-48 hours.";
            $_SESSION['user_name'] = $name;
        } else {
            $error = "Re-submission failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Re-submit Verification - Car Rental</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #020617);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            padding: 40px 20px;
        }

        /* Navigation */
        nav {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 800px;
            margin: 0 auto 30px auto;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        nav h2 {
            color: white;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        nav h2 i {
            color: #16a34a;
        }

        nav div {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        nav span {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }

        nav a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        nav a:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: #ef4444;
        }

        /* Container */
        .auth-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Main Card */
        .auth-card {
            background: #ffffff;
            padding: 40px;
            border-radius: 14px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
        }

        /* Header */
        .auth-card h1 {
            text-align: center;
            margin-bottom: 6px;
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .auth-card h1 i {
            color: #f59e0b;
        }

        .auth-card > p:first-of-type {
            text-align: center;
            color: #64748b;
            margin-bottom: 25px;
            font-size: 15px;
        }

        /* Rejection Notice */
        .rejection-notice {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 20px;
            border-left: 4px solid #f59e0b;
            margin: 25px 0;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.15);
        }

        .rejection-notice h3 {
            margin: 0 0 10px 0;
            color: #92400e;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rejection-notice p {
            color: #92400e;
            margin: 8px 0;
            line-height: 1.6;
        }

        .rejection-notice strong {
            font-weight: 700;
        }

        /* Section Headers */
        .auth-card h2 {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .auth-card h2:first-of-type {
            margin-top: 0;
        }

        .auth-card h2 i {
            color: #16a34a;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 20px;
        }

        .auth-card label {
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
            color: #1e293b;
            font-size: 14px;
        }

        .auth-card input[type="text"],
        .auth-card input[type="email"],
        .auth-card input[type="tel"] {
            width: 100%;
            padding: 12px 15px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            font-size: 14px;
            transition: all 0.2s;
            font-family: inherit;
        }

        .auth-card input:focus {
            border-color: #16a34a;
            outline: none;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        }

        .auth-card input:disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .auth-card small {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-top: 5px;
        }

        /* File Upload */
        .auth-card input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            background: #f8fafc;
            transition: all 0.2s;
        }

        .auth-card input[type="file"]:hover {
            border-color: #16a34a;
            background: #f0fdf4;
        }

        .auth-card input[type="file"]:focus {
            outline: none;
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        }

        .current-file {
            display: block;
            margin-top: 8px;
            padding: 8px 12px;
            background: #f0fdf4;
            border-left: 3px solid #16a34a;
            border-radius: 4px;
            font-size: 13px;
            color: #166534;
            font-weight: 500;
        }

        .current-file i {
            margin-right: 5px;
        }

        /* Buttons */
        .auth-card button[type="submit"] {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 25px;
            box-shadow: 0 4px 15px rgba(22, 163, 74, 0.3);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .auth-card button[type="submit"]:hover {
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            box-shadow: 0 6px 20px rgba(22, 163, 74, 0.4);
            transform: translateY(-2px);
        }

        /* Success & Error Messages */
        .error,
        .success {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: start;
            gap: 10px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-left: 4px solid #dc2626;
        }

        .error i {
            font-size: 20px;
            margin-top: 2px;
        }

        .success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            border-left: 4px solid #10b981;
        }

        .success i {
            font-size: 20px;
            margin-top: 2px;
        }

        .success p {
            margin: 8px 0;
        }

        .success a {
            color: #047857;
            font-weight: 700;
            text-decoration: underline;
        }

        /* Help Section */
        .help-section {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
            text-align: center;
        }

        .help-section p {
            color: #64748b;
            font-size: 14px;
        }

        .help-section a {
            color: #16a34a;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .help-section a:hover {
            color: #15803d;
            text-decoration: underline;
        }

        /* Info Box */
        .info-box {
            background: #f0fdf4;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #16a34a;
            margin-bottom: 20px;
        }

        .info-box p {
            color: #166534;
            font-size: 14px;
            margin: 0;
            line-height: 1.6;
        }

        .info-box i {
            margin-right: 5px;
        }

        /* Responsive */
        @media (max-width: 640px) {
            body {
                padding: 20px 10px;
            }

            nav {
                flex-direction: column;
                gap: 15px;
                padding: 1rem;
            }

            nav div {
                width: 100%;
                justify-content: center;
            }

            .auth-card {
                padding: 30px 25px;
            }

            .auth-card h1 {
                font-size: 24px;
            }

            .auth-card h2 {
                font-size: 16px;
            }
        }
    </style>
    <script>
        function displayFileName(inputId, displayId) {
            const input = document.getElementById(inputId);
            const display = document.getElementById(displayId);
            
            if (input.files && input.files[0]) {
                const fileName = input.files[0].name;
                const fileSize = (input.files[0].size / 1024 / 1024).toFixed(2);
                display.textContent = fileName + ' (' + fileSize + ' MB)';
            } else {
                display.textContent = '';
            }
        }
    </script>
</head>
<body>
    <nav>
        <h2><i class="fas fa-car"></i> Car Rental System</h2>
        <div>
            <span>Welcome, <?php echo htmlspecialchars(get_user_name()); ?>!</span>
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>
    
    <div class="auth-container">
        <div class="auth-card">
            <h1><i class="fas fa-exclamation-triangle"></i> Verification Rejected</h1>
            <p>Your account verification was not approved. Please review and update your information below.</p>
        
            <div class="rejection-notice">
                <h3>⚠️ Why was my application rejected?</h3>
                <p>Your account verification was rejected by our admin team. This could be due to:</p>
                <ul style="margin: 10px 0 10px 20px; line-height: 1.8;">
                    <li>Unclear or unreadable documents</li>
                    <li>Incomplete information</li>
                    <li>Documents not matching requirements</li>
                    <li>Invalid or expired documents</li>
                </ul>
                <p><strong>What to do next:</strong> Please update your information and upload clear, valid documents below, then re-submit for verification.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-times-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <p><strong><?php echo $success; ?></strong></p>
                        <p>You will be logged out now. Please login again after admin approves your account.</p>
                        <p><a href="../auth/logout.php">Click here to logout →</a></p>
                    </div>
                </div>
            <?php else: ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <h2><i class="fas fa-user-edit"></i> Update Your Information</h2>
                
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address (Cannot be changed)</label>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($email); ?>" disabled>
                    <small><i class="fas fa-info-circle"></i> Email cannot be changed. Contact support if you need to change it.</small>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>
                </div>
                
                <h2><i class="fas fa-briefcase"></i> Business Details (Optional)</h2>
                
                <div class="form-group">
                    <label for="business_name">Business Name</label>
                    <input type="text" id="business_name" name="business_name" value="<?php echo htmlspecialchars($business_name); ?>">
                </div>
                
                <div class="form-group">
                    <label for="bank_account">Bank Account Number</label>
                    <input type="text" id="bank_account" name="bank_account" value="<?php echo htmlspecialchars($bank_account); ?>">
                    <small>For receiving payments</small>
                </div>
                
                <h2><i class="fas fa-file-upload"></i> Update Documents (Optional)</h2>
                
                <div class="info-box">
                    <p><i class="fas fa-lightbulb"></i> <strong>Note:</strong> Leave empty if you don't want to change your documents. Upload new files only if you need to update them or if requested by admin.</p>
                </div>
                
                <div class="form-group">
                    <label for="driver_license">Driver's License</label>
                    <input type="file" id="driver_license" name="driver_license" accept=".pdf,.jpg,.jpeg,.png" onchange="displayFileName('driver_license', 'driver_license_display')">
                    <span id="driver_license_display"></span>
                    <?php if (!empty($lessor['DRIVER_LICENSE_PATH'])): ?>
                        <span class="current-file"><i class="fas fa-file-alt"></i> Current: <?php echo htmlspecialchars(basename($lessor['DRIVER_LICENSE_PATH'])); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="vehicle_ownership">Vehicle Ownership Proof</label>
                    <input type="file" id="vehicle_ownership" name="vehicle_ownership" accept=".pdf,.jpg,.jpeg,.png" onchange="displayFileName('vehicle_ownership', 'vehicle_ownership_display')">
                    <span id="vehicle_ownership_display"></span>
                    <?php if (!empty($lessor['VEHICLE_OWNERSHIP_PATH'])): ?>
                        <span class="current-file"><i class="fas fa-file-alt"></i> Current: <?php echo htmlspecialchars(basename($lessor['VEHICLE_OWNERSHIP_PATH'])); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="insurance">Insurance Document</label>
                    <input type="file" id="insurance" name="insurance" accept=".pdf,.jpg,.jpeg,.png" onchange="displayFileName('insurance', 'insurance_display')">
                    <span id="insurance_display"></span>
                    <?php if (!empty($lessor['INSURANCE_PATH'])): ?>
                        <span class="current-file"><i class="fas fa-file-alt"></i> Current: <?php echo htmlspecialchars(basename($lessor['INSURANCE_PATH'])); ?></span>
                    <?php endif; ?>
                </div>
                
                <button type="submit">
                    <i class="fas fa-paper-plane"></i>
                    Re-submit for Verification
                </button>
            </form>
            
            <?php endif; ?>
            
            <div class="help-section">
                <p>Need help? <a href="mailto:carrentalplatformsystem@gmail.com"><i class="fas fa-envelope"></i> Contact Support</a></p>
            </div>
        </div>
    </div>
</body>
</html>