<?php
/**
 * UC13: Support Ticket Submission (Renter)
 */
session_start();
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';
require_once '../config/email_test.php';

check_authentication('renter');

$renter_id = get_user_id();
$renter_name = get_user_name();

/* ---------- HANDLE FORM SUBMISSION ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $category = $_POST['category'] ?? 'General';

    if (empty($subject) || empty($description)) {
        set_flash_message('Please fill in all required fields.', 'error');
    } else {
        $admin = db_fetch_one("SELECT ADMINID FROM ADMIN WHERE ROWNUM = 1");
        $admin_id = $admin['ADMINID'] ?? 1;

        $ticket_seq = db_fetch_one("SELECT SEQ_TICKET.NEXTVAL AS NEXTVAL FROM DUAL");
        $ticket_id = $ticket_seq['NEXTVAL'];

        $insert = "INSERT INTO TICKET 
                  (TICKETID, ADMINID, RENTERID, LESSORID, SUBJECT, DESCRIPTION, STATUS, CREATED_AT)
                  VALUES 
                  (:ticketid, :adminid, :renterid, NULL, :subject, :description, 'OPEN', SYSDATE)";

        if (db_execute($insert, [
            'ticketid' => $ticket_id,
            'adminid' => $admin_id,
            'renterid' => $renter_id,
            'subject' => $subject,
            'description' => $description
        ])) {
            set_flash_message("Ticket submitted successfully! Ref: TICKET-$ticket_id", "success");

            $admin_email = db_fetch_one(
                "SELECT EMAIL FROM ADMIN WHERE ADMINID = :id",
                ['id' => $admin_id]
            )['EMAIL'] ?? null;

            if ($admin_email) {
                send_email(
                    $admin_email,
                    "New Support Ticket: TICKET-$ticket_id",
                    "<p><strong>Renter:</strong> $renter_name</p>
                     <p><strong>Subject:</strong> $subject</p>
                     <p><strong>Description:</strong><br>" . nl2br(htmlspecialchars($description)) . "</p>"
                );
            }

            header('Location: submit_ticket.php');
            exit;
        } else {
            set_flash_message('Submission failed. Try again.', 'error');
        }
    }
}

/* ---------- FETCH TICKETS ---------- */
$tickets = db_fetch_all(
    "SELECT t.*, 
     TO_CHAR(t.CREATED_AT, 'DD-MON-YYYY HH24:MI') AS CREATED_AT_F,
     a.NAME AS ADMIN_NAME
     FROM TICKET t
     LEFT JOIN ADMIN a ON t.ADMINID = a.ADMINID
     WHERE t.RENTERID = :id
     ORDER BY t.CREATED_AT DESC",
    ['id' => $renter_id]
);

$stats = [
    'TOTAL' => count($tickets),
    'OPEN' => count(array_filter($tickets, fn($t) => $t['STATUS'] === 'OPEN')),
    'RESOLVED' => count(array_filter($tickets, fn($t) => $t['STATUS'] === 'RESOLVED'))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Center - Car Rental System</title>
    <link rel="stylesheet" href="../assets/css/support_ticket.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <nav>
        <div class="nav-container">
            <h2><a href="dashboard.php">Car Rental System</a></h2>
            <div class="nav-links">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($renter_name); ?>!</span>
                <a href="dashboard.php">Dashboard</a>
                <a href="booking_history.php">My Bookings</a>
                <a href="submit_ticket.php" class="active">Support</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <header class="hero">
        <h1>Support Center</h1>
        <p>How can we help you today?</p>
    </header>

    <main class="details-container">
        <!-- Left: Submission Form -->
        <div class="submit-col">
            <?php display_flash_message(); ?>

            <div class="details-card">
                <h2>📝 Submit New Ticket</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="submit">

                    <div class="form-group">
                        <label>Issue Category</label>
                        <select name="category">
                            <option>General</option>
                            <option>Booking Problem</option>
                            <option>Payment Issue</option>
                            <option>Account Settings</option>
                            <option>Technical Issue</option>
                            <option>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Subject *</label>
                        <input type="text" name="subject" required placeholder="Brief summary of the issue">
                    </div>

                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" required placeholder="Please provide details so we can assist you better..."></textarea>
                    </div>

                    <button type="submit" class="btn-primary">Submit Ticket</button>
                </form>
            </div>
        </div>

        <!-- Right: Stats & Guidelines -->
        <aside class="summary-card">
            <div class="details-card">
                <h3>My Ticket Summary</h3>
                <div class="sidebar_stats" style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:20px;">
                    <div style="background:#f8fafc; padding:15px; border-radius:8px; text-align:center; border:1px solid #eef2f6;">
                        <span style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Open</span>
                        <strong style="font-size:20px; color:#b45309;"><?php echo $stats['OPEN']; ?></strong>
                    </div>
                    <div style="background:#f8fafc; padding:15px; border-radius:8px; text-align:center; border:1px solid #eef2f6;">
                        <span style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Resolved</span>
                        <strong style="font-size:20px; color:#047857;"><?php echo $stats['RESOLVED']; ?></strong>
                    </div>
                </div>

                <div class="guidelines-box">
                    <h4><i class="fas fa-info-circle"></i> Support Hours</h4>
                    <ul>
                        <li>Monday - Friday: 9AM - 6PM</li>
                        <li>Saturdays: 9AM - 1PM</li>
                        <li>Expect response within 24 hours.</li>
                    </ul>
                </div>
            </div>
        </aside>
    </main>

    <!-- Bottom: History Records -->
    <section class="ticket-section">
        <h2>📋 My Support History (<?php echo $stats['TOTAL']; ?>)</h2>

        <?php if (!$tickets): ?>
            <div class="details-card" style="text-align:center; color:#64748b; padding:40px;">
                <i class="fas fa-ticket-alt" style="font-size:40px; opacity:0.2; margin-bottom:15px;"></i>
                <p>No support tickets submitted yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($tickets as $t): ?>
                <div class="ticket-card">
                    <div class="ticket-header">
                        <span class="ticket-id">REF: TICKET-<?php echo $t['TICKETID']; ?></span>
                        <span class="status-badge <?php echo $t['STATUS'] === 'OPEN' ? 'status-open' : 'status-resolved'; ?>">
                            <?php echo $t['STATUS']; ?>
                        </span>
                    </div>

                    <div class="ticket-body">
                        <strong class="subject"><?php echo htmlspecialchars($t['SUBJECT']); ?></strong>
                        <span class="meta">
                            Submitted on <?php echo $t['CREATED_AT_F']; ?> 
                            <?php if ($t['ADMIN_NAME']): ?>
                                • Assigned to: <strong><?php echo htmlspecialchars($t['ADMIN_NAME']); ?></strong>
                            <?php endif; ?>
                        </span>
                        <div class="ticket-desc">
                            <?php echo nl2br(htmlspecialchars($t['DESCRIPTION'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

</body>
</html>
