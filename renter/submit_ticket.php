<?php
/**
 * UC13: Support Ticket Submission (Renter)
 * Backend functionality only
 */
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

check_authentication('renter');

$renter_id = get_user_id();
$renter_name = get_user_name();

// Process ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit') {
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $category = $_POST['category'] ?? 'General';
    
    // Validation (Exception 2 from SRS)
    if (empty($subject)) {
        $_SESSION['error'] = 'Please fill in all required fields.';
    } elseif (empty($description)) {
        $_SESSION['error'] = 'Please fill in all required fields.';
    } else {
        // Get admin ID (assign to first admin for now)
        $admin_query = "SELECT ADMINID FROM ADMIN WHERE ROWNUM = 1";
        $admin_result = db_fetch_one($admin_query);
        $admin_id = $admin_result ? $admin_result['ADMINID'] : 1;
        
        // Get next ticket ID
        $ticket_id_query = "SELECT SEQ_TICKET.NEXTVAL as NEXTVAL FROM DUAL";
        $ticket_id_result = db_fetch_one($ticket_id_query);
        $ticket_id = $ticket_id_result['NEXTVAL'];
        
        // Insert ticket
        $insert_query = "INSERT INTO TICKET 
                        (TICKETID, ADMINID, RENTERID, LESSORID, SUBJECT, DESCRIPTION, STATUS, CREATED_AT)
                        VALUES 
                        (:ticketid, :adminid, :renterid, NULL, :subject, :description, 'OPEN', SYSDATE)";
        
        $params = [
            'ticketid' => $ticket_id,
            'adminid' => $admin_id,
            'renterid' => $renter_id,
            'subject' => $subject,
            'description' => $description
        ];
        
        if (db_execute($insert_query, $params)) {
            $_SESSION['success'] = 'Support ticket submitted successfully! Reference: TICKET-' . $ticket_id;
            header('Location: submit_ticket.php');
            exit;
        } else {
            $_SESSION['error'] = 'Failed to submit ticket. Please try again.';
        }
    }
}

// Fetch user's existing tickets
$tickets_query = "SELECT t.*, 
                  TO_CHAR(t.CREATED_AT, 'DD-MON-YYYY HH24:MI') as CREATED_AT_F,
                  a.NAME as ADMIN_NAME
                  FROM TICKET t
                  LEFT JOIN ADMIN a ON t.ADMINID = a.ADMINID
                  WHERE t.RENTERID = :renterid
                  ORDER BY t.CREATED_AT DESC";
$tickets = db_fetch_all($tickets_query, ['renterid' => $renter_id]);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Support Tickets - Renter</title>
</head>
<body>
    <h1>Support Center</h1>
    <p>Welcome, <?php echo htmlspecialchars($renter_name); ?></p>
    <a href="dashboard.php">Back to Dashboard</a> | <a href="../auth/logout.php">Logout</a>
    <hr>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div style="background: lightgreen; padding: 10px; margin: 10px 0;">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div style="background: #ffcccc; padding: 10px; margin: 10px 0;">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <h2>Submit New Ticket</h2>
    <form method="POST">
        <input type="hidden" name="action" value="submit">
        
        <label>Category:</label><br>
        <select name="category" style="width: 300px; padding: 5px;">
            <option value="General">General Inquiry</option>
            <option value="Booking">Booking Issue</option>
            <option value="Payment">Payment Problem</option>
            <option value="Account">Account Issue</option>
            <option value="Technical">Technical Support</option>
            <option value="Other">Other</option>
        </select><br><br>
        
        <label>Subject: *</label><br>
        <input type="text" name="subject" required style="width: 500px; padding: 5px;" 
               placeholder="Brief description of your issue"><br><br>
        
        <label>Description: *</label><br>
        <textarea name="description" required rows="6" style="width: 500px; padding: 5px;"
                  placeholder="Please provide detailed information about your issue..."></textarea><br><br>
        
        <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none;">
            Submit Ticket
        </button>
    </form>
    
    <hr>
    
    <h2>My Tickets (<?php echo count($tickets); ?>)</h2>
    
    <?php if (empty($tickets)): ?>
        <p>No tickets submitted yet.</p>
    <?php else: ?>
        <table border="1" cellpadding="10" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th>Ticket ID</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Assigned To</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><strong>TICKET-<?php echo $ticket['TICKETID']; ?></strong></td>
                    <td><?php echo htmlspecialchars($ticket['SUBJECT']); ?></td>
                    <td>
                        <?php if ($ticket['STATUS'] === 'OPEN'): ?>
                            <span style="color: orange;">⚠ OPEN</span>
                        <?php elseif ($ticket['STATUS'] === 'RESOLVED'): ?>
                            <span style="color: green;">✓ RESOLVED</span>
                        <?php else: ?>
                            <?php echo $ticket['STATUS']; ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $ticket['CREATED_AT_F']; ?></td>
                    <td><?php echo htmlspecialchars($ticket['ADMIN_NAME'] ?? 'Not assigned'); ?></td>
                </tr>
                <tr>
                    <td colspan="5" style="background: #fafafa;">
                        <strong>Description:</strong><br>
                        <?php echo nl2br(htmlspecialchars($ticket['DESCRIPTION'])); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>