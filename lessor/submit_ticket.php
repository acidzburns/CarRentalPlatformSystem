<?php
/**
 * UC13: Support Ticket Submission - Lessor
 */
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/email_test.php';

require_login('lessor');

$lessorid = get_user_id();
$lessor_name = get_user_name();
$errors = [];

/* ---------- HANDLE FORM SUBMISSION ---------- */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $category = $_POST['category'] ?? 'General';

    if (empty($subject) || empty($description)) {
        set_flash_message("Please fill in all required fields.", "error");
    } else {
        try {
            $admin = db_fetch_one("SELECT ADMINID FROM ADMIN WHERE ROWNUM = 1");
            $admin_id = $admin['ADMINID'] ?? 1;

            $insert = "INSERT INTO TICKET 
                      (TICKETID, ADMINID, LESSORID, RENTERID, SUBJECT, DESCRIPTION, STATUS, CREATED_AT)
                      VALUES 
                      (SEQ_TICKET.NEXTVAL, :adminid, :lessorid, NULL, :subject, :description, 'OPEN', SYSDATE)";

            if (db_execute($insert, [
                'adminid' => $admin_id,
                'lessorid' => $lessorid,
                'subject' => $subject,
                'description' => $description
            ])) {
                set_flash_message("Support ticket submitted successfully. Admin will respond soon.", "success");

                // Notification (Simplified)
                $admin_email = db_fetch_one("SELECT EMAIL FROM ADMIN WHERE ADMINID = :id", ['id' => $admin_id])['EMAIL'] ?? null;
                if ($admin_email) {
                    send_email($admin_email, "New Lessor Ticket: $subject", "<p>Lessor <strong>$lessor_name</strong> submitted a new ticket.</p><p>" . nl2br(htmlspecialchars($description)) . "</p>");
                }

                header("Location: submit_ticket.php");
                exit();
            } else {
                set_flash_message("Failed to submit ticket. Please try again.", "error");
            }
        } catch (Exception $e) {
            set_flash_message("An error occurred. Please try again.", "error");
        }
    }
}

/* ---------- FETCH TICKETS ---------- */
$status_filter = isset($_GET['status']) ? strtoupper($_GET['status']) : 'ALL';

$sql = "SELECT t.*, TO_CHAR(t.CREATED_AT, 'DD-MON-YYYY HH24:MI') as CREATED_FMT,
        (SELECT NAME FROM ADMIN WHERE ADMINID = t.ADMINID) as ADMIN_NAME
        FROM TICKET t
        WHERE t.LESSORID = :id";

if ($status_filter !== 'ALL') {
    $sql .= " AND t.STATUS = :status";
}
$sql .= " ORDER BY t.CREATED_AT DESC";

$params = ['id' => $lessorid];
if ($status_filter !== 'ALL') $params['status'] = $status_filter;

$tickets = db_fetch_all($sql, $params);

// Stats
$stats_sql = "SELECT COUNT(*) as TOTAL, 
              COUNT(CASE WHEN STATUS = 'OPEN' THEN 1 END) as OPEN_COUNT,
              COUNT(CASE WHEN STATUS = 'RESOLVED' THEN 1 END) as RESOLVED_COUNT
              FROM TICKET WHERE LESSORID = :id";
$stats = db_fetch_one($stats_sql, ['id' => $lessorid]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets - Lessor Portal</title>
    <link rel="stylesheet" href="../assets/css/submit-ticket-lessor.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <nav>
        <div class="nav-container">
            <h2>Car Rental System <span style="font-size:14px; opacity:0.8; font-weight:400;">| Lessor Panel</span></h2>
            <div class="nav-links">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($lessor_name); ?>!</span>
                <a href="dashboard.php">Dashboard</a>
                <a href="my_cars.php">My Cars</a>
                <a href="bookings.php">Bookings</a>
                <a href="earnings.php">Earnings</a>
                <a href="profile.php">Profile</a>
                <a href="submit_ticket.php" class="active">Support</a>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <header class="hero">
        <h1>Lessor Support Center</h1>
        <p>Get assistance with your car listings and shop management</p>
    </header>

    <main class="details-container">
        <!-- Left: Form -->
        <div class="submit-col">
            <?php display_flash_message(); ?>

            <div class="details-card">
                <h2>📝 Submit Support Ticket</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Issue Category</label>
                        <select name="category">
                            <option>Car Listing Issue</option>
                            <option>Booking Problem</option>
                            <option>Payment/Earnings</option>
                            <option>Account Verification</option>
                            <option>Technical Issue</option>
                            <option>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Subject *</label>
                        <input type="text" name="subject" required placeholder="Brief summary of your issue">
                    </div>

                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" required placeholder="Describe your issue in detail..."></textarea>
                    </div>

                    <button type="submit" name="submit_ticket" class="btn-primary">Submit Ticket</button>
                </form>
            </div>
        </div>

        <!-- Right: Stats & Guidelines -->
        <aside class="summary-card">
            <div class="details-card">
                <h3>System Overview</h3>
                <div class="sidebar-stats" style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:20px;">
                    <div style="background:#f8fafc; padding:15px; border-radius:8px; text-align:center; border:1px solid #eef2f6;">
                        <span style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Open Tickets</span>
                        <strong style="font-size:20px; color:#c2410c;"><?php echo $stats['OPEN_COUNT']; ?></strong>
                    </div>
                    <div style="background:#f8fafc; padding:15px; border-radius:8px; text-align:center; border:1px solid #eef2f6;">
                        <span style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Resolved</span>
                        <strong style="font-size:20px; color:#047857;"><?php echo $stats['RESOLVED_COUNT']; ?></strong>
                    </div>
                </div>

                <div class="guidelines-box">
                    <h4><i class="fas fa-question-circle"></i> Helpful Tips</h4>
                    <p style="font-size:13px; color:#1e40af; margin-bottom:10px;">For faster resolution:</p>
                    <ul style="font-size:12px; color:#1e40af; margin-left:15px;">
                        <li>Include Car Plate or Booking ID if relevant.</li>
                        <li>Attach clear descriptions of errors.</li>
                        <li>Check the FAQ section before submitting.</li>
                    </ul>
                </div>
            </div>
        </aside>
    </main>

    <section class="ticket-section">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0; border:none; padding:0;">📋 Your Ticket History</h2>
            <div class="filters">
                <a href="?status=all" class="<?php echo $status_filter === 'ALL' ? 'active' : ''; ?>">All Tickets</a>
                <a href="?status=open" class="<?php echo $status_filter === 'OPEN' ? 'active' : ''; ?>">Open</a>
                <a href="?status=resolved" class="<?php echo $status_filter === 'RESOLVED' ? 'active' : ''; ?>">Resolved</a>
            </div>
        </div>

        <?php if (empty($tickets)): ?>
            <div class="details-card" style="text-align:center; color:#64748b; padding:40px;">
                <p>No tickets matching your filter.</p>
            </div>
        <?php else: ?>
            <?php foreach ($tickets as $t): ?>
                <div class="ticket-card">
                    <div class="ticket-header">
                        <span class="ticket-id">#<?php echo $t['TICKETID']; ?></span>
                        <span class="status-badge <?php echo $t['STATUS'] === 'OPEN' ? 'status-open' : 'status-resolved'; ?>">
                            <?php echo $t['STATUS']; ?>
                        </span>
                    </div>

                    <div class="ticket-body">
                        <strong class="subject"><?php echo htmlspecialchars($t['SUBJECT']); ?></strong>
                        <span class="meta">Submitted: <?php echo $t['CREATED_FMT']; ?> • Admin: <?php echo htmlspecialchars($t['ADMIN_NAME'] ?? 'Pending'); ?></span>
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