<?php
/**
 * Admin Dashboard
 * Linked to external CSS file
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/check_auth.php';

// Check if user is logged in as admin
check_authentication('admin');

// Get admin information
$admin_name = get_user_name();

// --- STATISTICS ---
// 1. Pending Lessors
$sql_pending = "SELECT COUNT(*) as total FROM LESSOR WHERE VERIFICATION_STATUS = 'PENDING'";
$pending_result = db_fetch_one($sql_pending);
$total_pending_lessors = $pending_result ? $pending_result['TOTAL'] : 0;

// 2. Open Tickets
$sql_tickets = "SELECT COUNT(*) as total FROM TICKET WHERE STATUS = 'OPEN'";
$tickets_result = db_fetch_one($sql_tickets);
$total_tickets = $tickets_result ? $tickets_result['TOTAL'] : 0;

// 3. Total Renters
$sql_renters = "SELECT COUNT(*) as total FROM RENTER";
$renters_result = db_fetch_one($sql_renters);
$total_renters = $renters_result ? $renters_result['TOTAL'] : 0;

// 4. Active Lessors
$sql_lessors = "SELECT COUNT(*) as total FROM LESSOR WHERE VERIFICATION_STATUS = 'VERIFIED'";
$lessors_result = db_fetch_one($sql_lessors);
$total_verified_lessors = $lessors_result ? $lessors_result['TOTAL'] : 0;

// 5. Total Cars
$sql_cars = "SELECT COUNT(*) as total FROM CAR";
$cars_result = db_fetch_one($sql_cars);
$total_cars = $cars_result ? $cars_result['TOTAL'] : 0;

// 6. Total Bookings
$sql_bookings = "SELECT COUNT(*) as total FROM BOOKING";
$bookings_result = db_fetch_one($sql_bookings);
$total_bookings = $bookings_result ? $bookings_result['TOTAL'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Royal Car Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>

    <aside class="sidebar">
        <h4>
            Royal Blue
            <span>Admin Control Panel</span>
        </h4>
        
        <a href="dashboard.php" class="active">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        
        <a href="verify_lessors.php">
            <i class="fas fa-user-check"></i> <span>Verify Lessors</span>
            <?php if ($total_pending_lessors > 0): ?>
                <span class="sidebar-badge"><?php echo $total_pending_lessors; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="manage_users.php">
            <i class="fas fa-users"></i> <span>Manage Users</span>
        </a>
        
        <a href="manage_bookings.php">
            <i class="fas fa-calendar-alt"></i> <span>Bookings</span>
        </a>
        
        <a href="manage_tickets.php">
            <i class="fas fa-headset"></i> <span>Support</span>
            <?php if ($total_tickets > 0): ?>
                <span class="sidebar-badge" style="background: #ffffff; color: #1864FF;"><?php echo $total_tickets; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="reports.php">
            <i class="fas fa-chart-line"></i> <span>Reports</span>
        </a>

        <a href="../auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </aside>

    <main class="main-content">
        
        <header class="page-header">
            <div>
                <h2>Dashboard Overview</h2>
                <p>Welcome back, <strong><?php echo htmlspecialchars($admin_name); ?></strong></p>
            </div>
            <div class="admin-badge">
                <i class="fas fa-user-shield"></i>
                System Administrator
            </div>
        </header>

        <?php if ($total_pending_lessors > 0 || $total_tickets > 0): ?>
        <section style="margin-bottom: 40px;">
            <span class="section-label">Action Required</span>
            <div class="stat-grid">
                <?php if ($total_pending_lessors > 0): ?>
                <div class="stat-card" style="border-left: 4px solid #f59e0b; cursor: pointer;" onclick="window.location='verify_lessors.php'">
                    <div class="stat-card-header">
                        <div class="stat-icon bg-orange">
                            <i class="fas fa-id-card"></i>
                        </div>
                    </div>
                    <h6>Pending Verifications</h6>
                    <h3 style="color: #ea580c;"><?php echo $total_pending_lessors; ?></h3>
                </div>
                <?php endif; ?>

                <?php if ($total_tickets > 0): ?>
                <div class="stat-card" style="border-left: 4px solid #ef4444; cursor: pointer;" onclick="window.location='manage_tickets.php'">
                    <div class="stat-card-header">
                        <div class="stat-icon bg-red">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                    </div>
                    <h6>Open Support Tickets</h6>
                    <h3 style="color: #dc2626;"><?php echo $total_tickets; ?></h3>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <section>
            <span class="section-label">System Performance</span>
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon bg-blue">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <h6>Total Renters</h6>
                    <h3><?php echo $total_renters; ?></h3>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon bg-green">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <h6>Active Lessors</h6>
                    <h3><?php echo $total_verified_lessors; ?></h3>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon bg-cyan">
                            <i class="fas fa-car"></i>
                        </div>
                    </div>
                    <h6>Total Fleet Size</h6>
                    <h3><?php echo $total_cars; ?></h3>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon bg-orange">
                            <i class="fas fa-bookmark"></i>
                        </div>
                    </div>
                    <h6>Total Bookings</h6>
                    <h3><?php echo $total_bookings; ?></h3>
                </div>
            </div>
        </section>

        <section>
            <span class="section-label">Quick Management</span>
            <div class="action-grid">
                <a href="verify_lessors.php" class="action-card">
                    <i class="fas fa-shield-alt"></i>
                    <h6>Verification</h6>
                </a>
                
                <a href="manage_users.php" class="action-card">
                    <i class="fas fa-users-cog"></i>
                    <h6>User Control</h6>
                </a>

                <a href="manage_bookings.php" class="action-card">
                    <i class="fas fa-calendar-check"></i>
                    <h6>Bookings</h6>
                </a>

                <a href="reports.php" class="action-card">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <h6>Economics</h6>
                </a>
            </div>
        </section>

    </main>

</body>
</html>

</body>
</html>