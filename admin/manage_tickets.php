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
                
                // --- EMAIL NOTIFICATION TO USER ---
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tickets | Royal Car Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/manage_tickets.css?v=<?php echo time(); ?>">
</head>
<body>

    <aside class="sidebar">
        <h4>
            Royal Blue
            <span>Admin Control Panel</span>
        </h4>
        
        <a href="dashboard.php">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        
        <a href="verify_lessors.php">
            <i class="fas fa-user-check"></i> <span>Verify Lessors</span>
        </a>
        
        <a href="manage_users.php">
            <i class="fas fa-users"></i> <span>Manage Users</span>
        </a>
        
        <a href="manage_bookings.php">
            <i class="fas fa-calendar-alt"></i> <span>Bookings</span>
        </a>
        
        <a href="manage_tickets.php" class="active">
            <i class="fas fa-headset"></i> <span>Support</span>
        </a>
        
        <a href="reports.php">
            <i class="fas fa-chart-line"></i> <span>Reports</span>
        </a>

        <a href="../auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </aside>

    <main class="main-content">
        <!-- Header -->
        <header class="page-header">
            <div>
                <h2>🎫 Ticket Management</h2>
                <p>Handling support request from active users</p>
            </div>
            <div class="admin-badge">
                <i class="fas fa-headset"></i>
                Rep: <?php echo htmlspecialchars($admin_name); ?>
            </div>
        </header>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div style="background: #ecfdf5; color: #059669; padding: 15px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #d1fae5;">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div style="background: #fef2f2; color: #dc2626; padding: 15px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #fee2e2;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Section -->
        <section class="stats-grid">
            <div class="stat-card">
                <p>All Requests</p>
                <h3><?php echo $total_tickets; ?></h3>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid #ea580c;">
                <p>Pending Issues</p>
                <h3><?php echo $status_counts['OPEN'] ?? 0; ?></h3>
            </div>
            <div class="stat-card" style="border-bottom: 4px solid #059669;">
                <p>Resolved</p>
                <h3><?php echo $status_counts['RESOLVED'] ?? 0; ?></h3>
            </div>
        </section>

        <!-- Filter Section -->
        <nav class="filter-tabs">
            <a href="?status=ALL" class="filter-tab <?php echo $status_filter === 'ALL' ? 'active' : ''; ?>">All Status</a>
            <a href="?status=OPEN" class="filter-tab <?php echo $status_filter === 'OPEN' ? 'active' : ''; ?>">Open (<?php echo $status_counts['OPEN'] ?? 0; ?>)</a>
            <a href="?status=RESOLVED" class="filter-tab <?php echo $status_filter === 'RESOLVED' ? 'active' : ''; ?>">Resolved</a>
        </nav>

        <!-- Tickets Section -->
        <section>
            <?php if (empty($tickets)): ?>
                <div style="padding: 100px; text-align: center; color: #64748b;">
                    <i class="fas fa-inbox-open" style="font-size: 50px; margin-bottom: 20px; opacity: 0.2;"></i>
                    <p>Clean inbox! No support tickets found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tickets as $ticket): ?>
                <div class="ticket-card">
                    <div class="ticket-header">
                        <div class="ticket-info">
                            <h3 class="ticket-title">
                                #<?php echo $ticket['TICKETID']; ?>: <?php echo htmlspecialchars($ticket['SUBJECT']); ?>
                            </h3>
                            <div class="ticket-meta">
                                <span class="meta-item">
                                    <strong>Sender:</strong> 
                                    <?php 
                                    if ($ticket['RENTERID']) {
                                        echo htmlspecialchars($ticket['RENTER_NAME']) . ' (Renter)';
                                    } elseif ($ticket['LESSORID']) {
                                        echo htmlspecialchars($ticket['LESSOR_NAME']) . ' (Lessor)';
                                    }
                                    ?>
                                </span>
                                <span class="meta-item">
                                    <strong>Email:</strong> <?php echo htmlspecialchars($ticket['RENTER_EMAIL'] ?? $ticket['LESSOR_EMAIL']); ?>
                                </span>
                                <span class="meta-item">
                                    <strong>Date:</strong> <?php echo $ticket['CREATED_AT_F']; ?>
                                </span>
                            </div>
                        </div>
                        <span class="status-badge status-<?php echo strtolower($ticket['STATUS']); ?>">
                            <?php echo $ticket['STATUS']; ?>
                        </span>
                    </div>
                    
                    <div class="ticket-body">
                        <strong>Inquiry Details:</strong>
                        <p><?php echo nl2br(htmlspecialchars($ticket['DESCRIPTION'])); ?></p>
                    </div>

                    <div class="ticket-actions">
                        <?php if ($ticket['STATUS'] === 'OPEN'): ?>
                            <?php if ($ticket['ADMINID'] != $admin_id): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="assign">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['TICKETID']; ?>">
                                    <button type="submit" class="btn btn-blue">
                                        <i class="fas fa-user-tag"></i> Assign to Me
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <button onclick="toggleResolutionForm(<?php echo $ticket['TICKETID']; ?>)" class="btn btn-green">
                                <i class="fas fa-check-circle"></i> Resolve
                            </button>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="reopen">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['TICKETID']; ?>">
                                <button type="submit" class="btn btn-orange">
                                    <i class="fas fa-undo"></i> Reopen
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Resolution Panel -->
                    <div id="resolve_<?php echo $ticket['TICKETID']; ?>" class="resolution-panel">
                        <form method="POST">
                            <input type="hidden" name="action" value="resolve">
                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['TICKETID']; ?>">
                            <label>Internal Resolution Notes</label>
                            <textarea name="resolution" rows="3" placeholder="Provide details on how the issue was resolved..."></textarea>
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="btn btn-green">Submit Resolution</button>
                                <button type="button" onclick="toggleResolutionForm(<?php echo $ticket['TICKETID']; ?>)" class="btn btn-secondary">Discard</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

    </main>

    <script>
        function toggleResolutionForm(ticketId) {
            const form = document.getElementById('resolve_' + ticketId);
            form.classList.toggle('active');
        }
    </script>
</body>
</html>

        <p style="text-align: center; margin: 20px 0;">
            <a href="dashboard.php" style="color: white; text-decoration: none; font-weight: 600;">← Back to Dashboard</a>
        </p>
    </div>

    <script>
        function toggleResolutionForm(ticketId) {
            const form = document.getElementById('resolve_' + ticketId);
            if (form.classList.contains('active')) {
                form.classList.remove('active');
            } else {
                // Close all other forms first
                document.querySelectorAll('.resolution-form').forEach(f => {
                    f.classList.remove('active');
                });
                form.classList.add('active');
            }
        }
    </script>
</body>
</html>