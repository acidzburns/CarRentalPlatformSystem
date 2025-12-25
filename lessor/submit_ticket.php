<?php
/**
 * UC13: Support Ticket Submission - Lessor (SRS Pages 47-48)
 * Lessor submits support tickets and tracks status
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/email_test.php';

// Check if lessor is logged in
require_login('lessor');

$lessorid = get_user_id();
$errors = [];
$success = "";

// Handle ticket submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    
    // UC13 Validation
    // Exception 2: Required fields empty
    if (empty($subject)) {
        $errors[] = "Please fill in all required fields.";
    }
    
    if (empty($description)) {
        $errors[] = "Please fill in all required fields.";
    }
    
    // Validate subject length
    if (strlen($subject) > 100) {
        $errors[] = "Subject must not exceed 100 characters.";
    }
    
    // Validate description length
    if (strlen($description) > 500) {
        $errors[] = "Description must not exceed 500 characters.";
    }
    
    if (empty($errors)) {
        try {
            // Get admin ID (default to 1 if exists)
            $admin_sql = "SELECT ADMINID FROM ADMIN WHERE ROWNUM = 1";
            $stmt_admin = oci_parse($conn, $admin_sql);
            oci_execute($stmt_admin);
            $admin = oci_fetch_assoc($stmt_admin);
            
            if (!$admin) {
                $errors[] = "No admin found. Please contact system administrator.";
            } else {
                $adminid = $admin['ADMINID'];
                
                // UC13 Step 4: System generates unique ID, creates record
                $ticket_sql = "INSERT INTO TICKET 
                              (TICKETID, ADMINID, LESSORID, SUBJECT, DESCRIPTION, STATUS, CREATED_AT)
                              VALUES 
                              (SEQ_TICKET.NEXTVAL, :adminid, :lessorid, :subject, :description, 'OPEN', SYSDATE)";
                
                $stmt_ticket = oci_parse($conn, $ticket_sql);
                oci_bind_by_name($stmt_ticket, ':adminid', $adminid);
                oci_bind_by_name($stmt_ticket, ':lessorid', $lessorid);
                oci_bind_by_name($stmt_ticket, ':subject', $subject);
                oci_bind_by_name($stmt_ticket, ':description', $description);
                
                if (oci_execute($stmt_ticket)) {
    oci_commit($conn);
    
    // UC13 Step 5: Send email notification to admin
    // 1. Fetch admin email (first admin in DB)
    $admin_email_sql = "SELECT EMAIL, NAME FROM ADMIN WHERE ROWNUM = 1";
    $stmt_admin_email = oci_parse($conn, $admin_email_sql);
    oci_execute($stmt_admin_email);
    $admin_info = oci_fetch_assoc($stmt_admin_email);
    
    if ($admin_info) {
        $admin_email = $admin_info['EMAIL'];
        $admin_name = $admin_info['NAME'];

        // 2. Prepare email content
        $email_subject = "New Support Ticket Submitted by Lessor";
        $email_body = "
            <p>Hello {$admin_name},</p>
            <p>A new support ticket has been submitted by Lessor <strong>{$lessor_name}</strong>.</p>
            <p><strong>Category:</strong> {$category}</p>
            <p><strong>Subject:</strong> {$subject}</p>
            <p><strong>Description:</strong><br>" . nl2br(htmlspecialchars($description)) . "</p>
            <p>Please login to the admin panel to review and respond.</p>
            <p>Reference: Ticket submitted by Lessor ID {$lessorid}</p>
        ";

        // 3. Send email
        send_email($admin_email, $email_subject, $email_body);
    }

    set_flash_message("Support ticket submitted successfully. Admin will respond soon.", "success");

    // Redirect to same page to prevent resubmission
    header("Location: submit_ticket.php");
    exit();
}

                 else {
                    $e = oci_error($stmt_ticket);
                    $errors[] = "Failed to submit ticket: " . $e['message'];
                }
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred. Please try again.";
        }
    }
}

// UC13 Step 1: Retrieve existing tickets
$tickets_sql = "SELECT t.TICKETID, t.SUBJECT, t.DESCRIPTION, t.STATUS, t.CREATED_AT,
                TO_CHAR(t.CREATED_AT, 'DD-MON-YYYY HH24:MI') as CREATED_FMT
                FROM TICKET t
                WHERE t.LESSORID = :lessorid
                ORDER BY t.CREATED_AT DESC";

$stmt_tickets = oci_parse($conn, $tickets_sql);
oci_bind_by_name($stmt_tickets, ':lessorid', $lessorid);
oci_execute($stmt_tickets);

$tickets = [];
while ($row = oci_fetch_assoc($stmt_tickets)) {
    $tickets[] = $row;
}

// Get ticket statistics
$stats_sql = "SELECT 
              COUNT(*) as TOTAL,
              COUNT(CASE WHEN STATUS = 'OPEN' THEN 1 END) as OPEN_COUNT,
              COUNT(CASE WHEN STATUS = 'RESOLVED' THEN 1 END) as RESOLVED_COUNT
              FROM TICKET WHERE LESSORID = :lessorid";

$stmt_stats = oci_parse($conn, $stats_sql);
oci_bind_by_name($stmt_stats, ':lessorid', $lessorid);
oci_execute($stmt_stats);
$stats = oci_fetch_assoc($stmt_stats);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets - Lessor</title>
</head>
<body>
    <h1>🎫 Support Tickets</h1>
    
    <nav>
        <a href="dashboard.php">Dashboard</a> |
        <a href="my_cars.php">My Cars</a> |
        <a href="bookings.php">Bookings</a> |
        <a href="earnings.php">Earnings</a> |
        <a href="submit_ticket.php">Support</a> |
        <a href="../auth/logout.php">Logout</a>
    </nav>
    
    <hr>
    
    <?php display_flash_message(); ?>
    
    <!-- Display errors -->
    <?php if (!empty($errors)): ?>
        <div style="background: #ffebee; padding: 10px; margin: 10px 0; border: 1px solid #f44336;">
            <strong>Error:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <!-- Ticket Statistics -->
    <h2>📊 Ticket Statistics</h2>
    <table border="1">
        <tr>
            <th>Total Tickets</th>
            <th>Open</th>
            <th>Resolved</th>
        </tr>
        <tr>
            <td><?php echo $stats['TOTAL']; ?></td>
            <td style="color: orange;"><?php echo $stats['OPEN_COUNT']; ?></td>
            <td style="color: green;"><?php echo $stats['RESOLVED_COUNT']; ?></td>
        </tr>
    </table>
    
    <hr>
    
    <!-- UC13 Step 2: Submit new ticket form -->
    <h2>📝 Submit New Support Ticket</h2>
    
    <div style="background: #e3f2fd; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3;">
        <h3>ℹ️ When to Submit a Ticket:</h3>
        <ul>
            <li>Issues with your car listings</li>
            <li>Problems with bookings or payments</li>
            <li>Account or verification issues</li>
            <li>Technical problems with the platform</li>
            <li>Questions about policies or features</li>
        </ul>
    </div>
    
    <form method="POST" action="">
        <!-- UC13 Step 3: User enters details -->
        <div style="margin: 15px 0;">
            <label><strong>Subject: *</strong></label><br>
            <input type="text" name="subject" required maxlength="100" 
                   placeholder="Brief description of your issue" 
                   style="width: 100%; padding: 8px; max-width: 600px;">
            <p style="font-size: 12px; color: #666;">Maximum 100 characters</p>
        </div>
        
        <div style="margin: 15px 0;">
            <label><strong>Category:</strong></label><br>
            <select name="category" style="padding: 8px; width: 300px;">
                <option value="">-- Select Category --</option>
                <option value="Car Listing">Car Listing Issue</option>
                <option value="Booking">Booking Problem</option>
                <option value="Payment">Payment Issue</option>
                <option value="Account">Account/Verification</option>
                <option value="Technical">Technical Problem</option>
                <option value="Other">Other</option>
            </select>
        </div>
        
        <div style="margin: 15px 0;">
            <label><strong>Description: *</strong></label><br>
            <textarea name="description" required maxlength="500" rows="6"
                      placeholder="Please provide detailed information about your issue..."
                      style="width: 100%; padding: 8px; max-width: 600px;"></textarea>
            <p style="font-size: 12px; color: #666;">Maximum 500 characters</p>
        </div>
        
        <div style="margin: 20px 0;">
            <button type="submit" name="submit_ticket" 
                    style="background: #4CAF50; color: white; padding: 12px 30px; border: none; cursor: pointer; font-size: 16px; font-weight: bold;">
                ✉️ Submit Ticket
            </button>
        </div>
    </form>
    
    <hr>
    
    <!-- UC13 Step 6: View ticket history -->
    <h2>📋 Your Support Tickets (<?php echo count($tickets); ?>)</h2>
    
    <!-- Alternative Flow 1: Search/Filter -->
    <div style="margin: 15px 0;">
        <strong>Filter by Status:</strong>
        <a href="?status=all">All</a> |
        <a href="?status=open">Open</a> |
        <a href="?status=resolved">Resolved</a>
    </div>
    
    <?php if (empty($tickets)): ?>
        <p>No support tickets found. Submit a ticket above if you need assistance.</p>
    <?php else: ?>
        <?php foreach ($tickets as $ticket): ?>
            <div style="border: 1px solid #ccc; padding: 15px; margin: 15px 0; background: #f9f9f9;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 style="margin: 0;">Ticket #<?php echo $ticket['TICKETID']; ?></h3>
                    <span style="padding: 5px 15px; border-radius: 20px; font-weight: bold; 
                        <?php echo $ticket['STATUS'] == 'OPEN' ? 'background: #fff3cd; color: #856404;' : 'background: #d4edda; color: #155724;'; ?>">
                        <?php echo htmlspecialchars($ticket['STATUS']); ?>
                    </span>
                </div>
                
                <table border="1" style="width: 100%; margin: 10px 0;">
                    <tr>
                        <th style="width: 150px;">Subject:</th>
                        <td><strong><?php echo htmlspecialchars($ticket['SUBJECT']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Description:</th>
                        <td><?php echo nl2br(htmlspecialchars($ticket['DESCRIPTION'])); ?></td>
                    </tr>
                    <tr>
                        <th>Submitted:</th>
                        <td><?php echo htmlspecialchars($ticket['CREATED_FMT']); ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <?php if ($ticket['STATUS'] == 'OPEN'): ?>
                                <span style="color: orange;">⏳ Waiting for admin response...</span>
                            <?php else: ?>
                                <span style="color: green;">✓ Resolved by admin</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <!-- UC13 Step 7: View admin response (if exists) -->
                <?php
                // Future: Add TICKET_RESPONSE table to store admin replies
                // For now, just show status
                ?>
                
                <?php if ($ticket['STATUS'] == 'RESOLVED'): ?>
                    <p style="background: #d4edda; padding: 10px; margin: 10px 0;">
                        <strong>✓ This ticket has been resolved.</strong>
                        If you still need help, please submit a new ticket.
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <hr>
    
    <div style="background: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107;">
        <h3>💬 Need Immediate Help?</h3>
        <p><strong>Response Time:</strong> Admin typically responds within 24-48 hours.</p>
        <p><strong>Urgent Issues:</strong> For urgent matters, please contact support@carrental.com</p>
    </div>
    
    <p><a href="dashboard.php">← Back to Dashboard</a></p>
</body>
</html>