<?php
/**
 * Test Email Function
 * Logs emails to a file instead of sending them
 * For testing purposes only
 */

/**
 * Test email function - logs to file
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body
 * @return bool Always returns true
 */
function send_test_email($to, $subject, $message) {
    $log_file = __DIR__ . '/../uploads/email_log.txt';
    
    $log_entry = "=====================================\n";
    $log_entry .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $log_entry .= "To: $to\n";
    $log_entry .= "Subject: $subject\n";
    $log_entry .= "Message:\n$message\n";
    $log_entry .= "=====================================\n\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    return true;
}
?>
```

---

## **Step 2: Test Email Functionality**

### **Test Approval Email:**

1. Register a new lessor (or use existing PENDING lessor)
2. Login as admin
3. Go to verify lessors page
4. Click **"Approve"** on a pending lessor
5. **Expected Success Message**: 
```
   "Lessor account has been approved successfully! Confirmation email sent to [email]."
```

6. **Check the email log**:
   - Go to: `CarRentalPlatformSystem/uploads/email_log.txt`
   - Open it with notepad
   - You should see:
```
   =====================================
   Date: 2024-12-21 15:30:45
   To: zulkifli.cars@gmail.com
   Subject: Your Car Rental Account Has Been Approved!
   Message:
   Dear Ahmad Zulkifli Bin Hassan,

   Congratulations! Your car rental lessor account has been verified and approved.

   You can now login and start listing your vehicles at:
   http://localhost/CarRentalPlatformSystem/auth/lessor_login.php

   Thank you for joining our platform!

   Best regards,
   Car Rental Platform Team
   =====================================
```

### **Test Rejection Email:**

1. Register another lessor
2. Login as admin
3. Click **"Reject"** on pending lessor
4. Enter reason: "Documents are unclear"
5. Submit rejection
6. **Expected Success Message**:
```
   "Lessor account has been rejected. Notification email sent to [email]."