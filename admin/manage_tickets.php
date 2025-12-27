<?php
/**
 * UC25: Customer Support - Admin Manage Tickets
 * Backend functionality only
 */
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';
require_once '../config/email_test.php';

check_authentication('admin');

$admin_id = get_user_id();
$admin_name = get_user_name();

// Handle ticket actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ticket_id = $_POST['ticket_id'] ?? 0;
    
    if ($action === 'resolve' && $ticket_id > 0) {
        // UC25: Resolve ticket
        $resolution = trim($_POST['resolution'] ?? '');
        
        if (empty($resolution)) {
            $_SESSION['error'] = 'Please provide resolution details.';
        } else {
            $update_query = "UPDATE TICKET 
                            SET STATUS = 'RESOLVED'
                            WHERE TICKETID = :ticketid";
            
            if (db_execute($update_query, ['ticketid' => $ticket_id])) {
                $_SESSION['success'] = 'Ticket #' . $ticket_id . ' has been resolved.';
                
                // Log resolution (you could add RESOLUTION field to TICKET table)
                // For now, admin can add notes manually
                // --- EMAIL NOTIFICATION TO USER ---
    // 1. Fetch ticket info (user type and email)
    $ticket_info = db_fetch_one("SELECT RENTERID, LESSORID, SUBJECT, DESCRIPTION 
                                 FROM TICKET WHERE TICKETID = :ticketid", 
                                 ['ticketid' => $ticket_id]);

    if ($ticket_info) {
        if (!empty($ticket_info['RENTERID'])) {
            $user_email = db_fetch_one("SELECT EMAIL, NAME FROM RENTER WHERE RENTERID = :id", ['id' => $ticket_info['RENTERID']]);
            $user_name = $user_email['NAME'] ?? 'Renter';
            $to_email = $user_email['EMAIL'];
        } elseif (!empty($ticket_info['LESSORID'])) {
            $user_email = db_fetch_one("SELECT EMAIL, NAME FROM LESSOR WHERE LESSORID = :id", ['id' => $ticket_info['LESSORID']]);
            $user_name = $user_email['NAME'] ?? 'Lessor';
            $to_email = $user_email['EMAIL'];
        }
        
        if (!empty($to_email)) {
            $subject_email = "Your Support Ticket #$ticket_id has been resolved";
            $body_email = "
                <p>Hello {$user_name},</p>
                <p>Your support ticket <strong>#{$ticket_id}</strong> has been resolved by the admin.</p>
                <p><strong>Subject:</strong> {$ticket_info['SUBJECT']}</p>
                <p><strong>Description:</strong><br>" . nl2br(htmlspecialchars($ticket_info['DESCRIPTION'])) . "</p>
                <p>If you have further issues, please reply or submit a new ticket.</p>
                <p>Thank you,<br>Car Rental Platform Team</p>
            ";

            send_email($to_email, $subject_email, $body_email);
        }
    }
            } else {
                $_SESSION['error'] = 'Failed to resolve ticket.';
            }
        }
    }
    elseif ($action === 'reopen' && $ticket_id > 0) {
        // Reopen ticket
        $update_query = "UPDATE TICKET 
                        SET STATUS = 'OPEN'
                        WHERE TICKETID = :ticketid";
        
        if (db_execute($update_query, ['ticketid' => $ticket_id])) {
            $_SESSION['success'] = 'Ticket #' . $ticket_id . ' has been reopened.';
        } else {
            $_SESSION['error'] = 'Failed to reopen ticket.';
        }
    }
    elseif ($action === 'assign' && $ticket_id > 0) {
        // Assign ticket to current admin
        $update_query = "UPDATE TICKET 
                        SET ADMINID = :adminid
                        WHERE TICKETID = :ticketid";
        
        if (db_execute($update_query, ['adminid' => $admin_id, 'ticketid' => $ticket_id])) {
            $_SESSION['success'] = 'Ticket #' . $ticket_id . ' assigned to you.';
        } else {
            $_SESSION['error'] = 'Failed to assign ticket.';
        }
    }
    
    header('Location: manage_tickets.php');
    exit;
}

// Filter by status
$status_filter = $_GET['status'] ?? 'OPEN';

// Fetch tickets
$query = "SELECT t.*, 
          TO_CHAR(t.CREATED_AT, 'DD-MON-YYYY HH24:MI') as CREATED_AT_F,
          a.NAME as ADMIN_NAME,
          r.NAME as RENTER_NAME,
          r.EMAIL as RENTER_EMAIL,
          l.NAME as LESSOR_NAME,
          l.EMAIL as LESSOR_EMAIL,
          CASE 
            WHEN t.RENTERID IS NOT NULL THEN 'Renter'
            WHEN t.LESSORID IS NOT NULL THEN 'Lessor'
            ELSE 'Unknown'
          END as USER_TYPE
          FROM TICKET t
          LEFT JOIN ADMIN a ON t.ADMINID = a.ADMINID
          LEFT JOIN RENTER r ON t.RENTERID = r.RENTERID
          LEFT JOIN LESSOR l ON t.LESSORID = l.LESSORID";

if ($status_filter !== 'ALL') {
    $query .= " WHERE t.STATUS = :status";
}

$query .= " ORDER BY t.CREATED_AT DESC";

$params = ($status_filter !== 'ALL') ? ['status' => $status_filter] : [];
$tickets = db_fetch_all($query, $params);

// Count by status
$count_query = "SELECT STATUS, COUNT(*) as CNT FROM TICKET GROUP BY STATUS";
$counts = db_fetch_all($count_query);
$status_counts = [];
foreach ($counts as $row) {
    $status_counts[$row['STATUS']] = $row['CNT'];
}
$total_tickets = array_sum($status_counts);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Support Tickets - Admin</title>
</head>
<body>
    <h1>Support Ticket Management</h1>
    <p>Admin: <?php echo htmlspecialchars($admin_name); ?></p>
    <a href="dashboard.php">Dashboard</a> | <a href="../auth/logout.php">Logout</a>
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
    
    <!-- Filter by Status -->
    <h2>Tickets Overview</h2>
    <p>
        <strong>Total Tickets: <?php echo $total_tickets; ?></strong> | 
        Open: <?php echo $status_counts['OPEN'] ?? 0; ?> | 
        Resolved: <?php echo $status_counts['RESOLVED'] ?? 0; ?>
    </p>
    
    <p>Filter by Status:</p>
    <a href="?status=ALL" style="padding: 5px 10px; <?php echo $status_filter === 'ALL' ? 'background: #007bff; color: white;' : 'background: #f0f0f0;'; ?>">
        All (<?php echo $total_tickets; ?>)
    </a>
    <a href="?status=OPEN" style="padding: 5px 10px; <?php echo $status_filter === 'OPEN' ? 'background: #007bff; color: white;' : 'background: #f0f0f0;'; ?>">
        Open (<?php echo $status_counts['OPEN'] ?? 0; ?>)
    </a>
    <a href="?status=RESOLVED" style="padding: 5px 10px; <?php echo $status_filter === 'RESOLVED' ? 'background: #007bff; color: white;' : 'background: #f0f0f0;'; ?>">
        Resolved (<?php echo $status_counts['RESOLVED'] ?? 0; ?>)
    </a>
    
    <hr>
    
    <!-- Tickets List -->
    <h2>Support Tickets (<?php echo count($tickets); ?>)</h2>
    
    <?php if (empty($tickets)): ?>
        <p>No tickets found.</p>
    <?php else: ?>
        <?php foreach ($tickets as $ticket): ?>
        <div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; background: #fafafa;">
            <table width="100%">
                <tr>
                    <td width="70%">
                        <h3 style="margin: 0;">
                            TICKET-<?php echo $ticket['TICKETID']; ?>: 
                            <?php echo htmlspecialchars($ticket['SUBJECT']); ?>
                        </h3>
                        <p style="margin: 5px 0;">
                            <strong>From:</strong> 
                            <?php 
                            if ($ticket['RENTERID']) {
                                echo htmlspecialchars($ticket['RENTER_NAME']) . ' (Renter) - ' . $ticket['RENTER_EMAIL'];
                            } elseif ($ticket['LESSORID']) {
                                echo htmlspecialchars($ticket['LESSOR_NAME']) . ' (Lessor) - ' . $ticket['LESSOR_EMAIL'];
                            }
                            ?>
                        </p>
                        <p style="margin: 5px 0;">
                            <strong>Submitted:</strong> <?php echo $ticket['CREATED_AT_F']; ?>
                        </p>
                        <p style="margin: 5px 0;">
                            <strong>Assigned To:</strong> <?php echo htmlspecialchars($ticket['ADMIN_NAME'] ?? 'Unassigned'); ?>
                        </p>
                    </td>
                    <td width="30%" align="right">
                        <?php if ($ticket['STATUS'] === 'OPEN'): ?>
                            <span style="background: orange; color: white; padding: 5px 15px; border-radius: 3px;">
                                ⚠ OPEN
                            </span>
                        <?php else: ?>
                            <span style="background: green; color: white; padding: 5px 15px; border-radius: 3px;">
                                ✓ RESOLVED
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <div style="background: white; padding: 10px; margin: 10px 0; border-left: 3px solid #007bff;">
                <strong>Description:</strong><br>
                <?php echo nl2br(htmlspecialchars($ticket['DESCRIPTION'])); ?>
            </div>
            
            <!-- Actions -->
            <div style="margin-top: 10px;">
                <?php if ($ticket['STATUS'] === 'OPEN'): ?>
                    <?php if ($ticket['ADMINID'] != $admin_id): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="assign">
                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['TICKETID']; ?>">
                            <button type="submit" style="padding: 5px 15px; background: #17a2b8; color: white; border: none;">
                                Assign to Me
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <button onclick="document.getElementById('resolve_<?php echo $ticket['TICKETID']; ?>').style.display='block'" 
                            style="padding: 5px 15px; background: #28a745; color: white; border: none;">
                        Resolve Ticket
                    </button>
                    
                    <!-- Resolve Form (Hidden) -->
                    <div id="resolve_<?php echo $ticket['TICKETID']; ?>" style="display: none; margin-top: 10px; background: #e9f7ef; padding: 10px;">
                        <form method="POST">
                            <input type="hidden" name="action" value="resolve">
                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['TICKETID']; ?>">
                            
                            <label>Resolution Notes:</label><br>
                            <textarea name="resolution" rows="3" style="width: 100%; padding: 5px;" required
                                      placeholder="Describe how you resolved this issue..."></textarea><br><br>
                            
                            <button type="submit" style="padding: 5px 15px; background: #28a745; color: white; border: none;">
                                Confirm Resolution
                            </button>
                            <button type="button" onclick="document.getElementById('resolve_<?php echo $ticket['TICKETID']; ?>').style.display='none'"
                                    style="padding: 5px 15px; background: #6c757d; color: white; border: none;">
                                Cancel
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="reopen">
                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['TICKETID']; ?>">
                        <button type="submit" style="padding: 5px 15px; background: #ffc107; color: black; border: none;">
                            Reopen Ticket
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>