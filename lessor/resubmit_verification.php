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
</head>
<body>
    <nav>
        <h2>Car Rental System</h2>
        <div>
            <span>Welcome, <?php echo htmlspecialchars(get_user_name()); ?>!</span>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>
    
    <main>
        <div>
            <h1>Account Verification Rejected</h1>
            <p>Your account verification was not approved. Please review the feedback and update your information.</p>
        </div>
        
        <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #856404;">⚠️ Rejection Notice</h3>
            <p style="color: #856404;">Your account was rejected. Please update your information and documents below, then re-submit for verification.</p>
            <p style="color: #856404;"><strong>Note:</strong> Make sure all documents are clear and readable.</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <?php echo $success; ?>
                <p>You will be logged out now. Please login again after admin approves your account.</p>
                <p><a href="../auth/logout.php">Click here to logout</a></p>
            </div>
        <?php else: ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <h2>Update Your Information</h2>
            
            <div>
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
            </div>
            
            <div>
                <label for="email">Email Address (Cannot be changed)</label>
                <input type="email" id="email" value="<?php echo htmlspecialchars($email); ?>" disabled>
                <small>Email cannot be changed. Contact support if you need to change it.</small>
            </div>
            
            <div>
                <label for="phone">Phone Number *</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>
            </div>
            
            <h2>Business Details (Optional)</h2>
            
            <div>
                <label for="business_name">Business Name</label>
                <input type="text" id="business_name" name="business_name" value="<?php echo htmlspecialchars($business_name); ?>">
            </div>
            
            <div>
                <label for="bank_account">Bank Account Number</label>
                <input type="text" id="bank_account" name="bank_account" value="<?php echo htmlspecialchars($bank_account); ?>">
            </div>
            
            <h2>Update Documents (Optional)</h2>
            <p>Leave empty if you don't want to change your documents. Upload new files only if requested by admin.</p>
            
            <div>
                <label for="driver_license">Driver's License</label>
                <input type="file" id="driver_license" name="driver_license" accept=".pdf,.jpg,.jpeg,.png">
                <small>Current: <?php echo htmlspecialchars($lessor['DRIVER_LICENSE_PATH'] ?? 'Not uploaded'); ?></small>
            </div>
            
            <div>
                <label for="vehicle_ownership">Vehicle Ownership Proof</label>
                <input type="file" id="vehicle_ownership" name="vehicle_ownership" accept=".pdf,.jpg,.jpeg,.png">
                <small>Current: <?php echo htmlspecialchars($lessor['VEHICLE_OWNERSHIP_PATH'] ?? 'Not uploaded'); ?></small>
            </div>
            
            <div>
                <label for="insurance">Insurance Document</label>
                <input type="file" id="insurance" name="insurance" accept=".pdf,.jpg,.jpeg,.png">
                <small>Current: <?php echo htmlspecialchars($lessor['INSURANCE_PATH'] ?? 'Not uploaded'); ?></small>
            </div>
            
            <button type="submit">Re-submit for Verification</button>
        </form>
        
        <?php endif; ?>
        
        <div style="margin-top: 20px;">
            <p>Need help? <a href="mailto:support@carrental.com">Contact Support</a></p>
        </div>
    </main>
</body>
</html>