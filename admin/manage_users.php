<?php
/**
 * UC23: Manage Users (SRS Pages 79-82)
 * Admin manages all user accounts (renters and lessors)
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Check if admin is logged in
require_login('admin');

$adminid = get_user_id();
$errors = [];
$success = "";

// Get filters from URL
$user_type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// UC23 Step 2: Retrieve and display list of all users
$users = [];

// ✅ IMPROVED FILTER LOGIC - Works for both renters and lessors
$fetch_renters = false;
$fetch_lessors = false;

if ($user_type_filter == 'all') {
    $fetch_renters = true;
    $fetch_lessors = true;
} elseif ($user_type_filter == 'renter') {
    $fetch_renters = true;
} elseif ($user_type_filter == 'lessor') {
    $fetch_lessors = true;
}

// Fetch Renters
if ($fetch_renters) {
    // Check if STATUS column exists
    $check_status_sql = "SELECT count(*) as COUNT FROM USER_TAB_COLUMNS WHERE TABLE_NAME = 'RENTER' AND COLUMN_NAME = 'STATUS'";
    $stmt_check = oci_parse($conn, $check_status_sql);
    oci_execute($stmt_check);
    $row_check = oci_fetch_assoc($stmt_check);
    $has_status_col = ($row_check['COUNT'] > 0);

    if ($has_status_col) {
        $status_select = "NVL(STATUS, 'ACTIVE')";
        $status_condition_active = " AND (STATUS IS NULL OR STATUS = 'ACTIVE')";
        $status_condition_suspended = " AND STATUS = 'SUSPENDED'";
    } else {
        $status_select = "'ACTIVE'";
        $status_condition_active = ""; // Always active if column doesn't exist
        $status_condition_suspended = " AND 1=0"; // Impossible to be suspended
    }

    $renter_sql = "SELECT RENTERID as USER_ID, NAME, EMAIL, PHONE, CREATED_AT, 
                   'RENTER' as USER_TYPE, " . $status_select . " as STATUS,
                   TO_CHAR(CREATED_AT, 'DD-MON-YYYY') as CREATED_FMT
                   FROM RENTER
                   WHERE 1=1";
    
    // Apply status filter
    if ($status_filter == 'active') {
        $renter_sql .= $status_condition_active;
    } elseif ($status_filter == 'suspended') {
        $renter_sql .= $status_condition_suspended;
    }
    // Skip renters if filtering by lessor-specific statuses (verified, pending, rejected)
    elseif (in_array($status_filter, ['verified', 'pending', 'rejected'])) {
        $fetch_renters = false;
    }
    
    // UC23 Step 4: Search functionality
    if ($fetch_renters && !empty($search_query)) {
        $renter_sql .= " AND (LOWER(NAME) LIKE LOWER(:search) 
                        OR LOWER(EMAIL) LIKE LOWER(:search2) 
                        OR PHONE LIKE :search3)";
    }
    
    if ($fetch_renters) {
        $renter_sql .= " ORDER BY CREATED_AT DESC";
        
        $stmt_renter = oci_parse($conn, $renter_sql);
        
        if (!$stmt_renter) {
            $e = oci_error($conn);
            error_log("RENTER PARSE ERROR: " . print_r($e, true));
        }
        
        if (!empty($search_query)) {
            $search_param = '%' . $search_query . '%';
            oci_bind_by_name($stmt_renter, ':search', $search_param);
            oci_bind_by_name($stmt_renter, ':search2', $search_param);
            oci_bind_by_name($stmt_renter, ':search3', $search_param);
        }
        
        $result = oci_execute($stmt_renter);
        
        if (!$result) {
            $e = oci_error($stmt_renter);
            error_log("RENTER EXECUTE ERROR: " . print_r($e, true));
            // Show error to admin for debugging
            set_flash_message("Database error fetching renters: " . $e['message'], "error");
        } else {
            while ($row = oci_fetch_assoc($stmt_renter)) {
                $users[] = $row;
            }
        }
    }
}

// Fetch Lessors
if ($fetch_lessors) {
    $lessor_sql = "SELECT LESSORID as USER_ID, NAME, EMAIL, PHONE, CREATED_AT,
                   'LESSOR' as USER_TYPE, VERIFICATION_STATUS as STATUS,
                   TO_CHAR(CREATED_AT, 'DD-MON-YYYY') as CREATED_FMT
                   FROM LESSOR
                   WHERE 1=1";
    
    // UC23 Step 3: Filter by status
    if ($status_filter == 'verified') {
        $lessor_sql .= " AND VERIFICATION_STATUS = 'VERIFIED'";
    } elseif ($status_filter == 'pending') {
        $lessor_sql .= " AND VERIFICATION_STATUS = 'PENDING'";
    } elseif ($status_filter == 'rejected') {
        $lessor_sql .= " AND VERIFICATION_STATUS = 'REJECTED'";
    } elseif ($status_filter == 'suspended') {
        $lessor_sql .= " AND VERIFICATION_STATUS = 'SUSPENDED'";
    }
    // Skip lessors if filtering by active (renter-specific)
    elseif ($status_filter == 'active') {
        $fetch_lessors = false;
    }
    
    // UC23 Step 4: Search functionality
    if ($fetch_lessors && !empty($search_query)) {
        $lessor_sql .= " AND (LOWER(NAME) LIKE LOWER(:search) 
                        OR LOWER(EMAIL) LIKE LOWER(:search2) 
                        OR PHONE LIKE :search3)";
    }
    
    if ($fetch_lessors) {
        $lessor_sql .= " ORDER BY CREATED_AT DESC";
        
        $stmt_lessor = oci_parse($conn, $lessor_sql);
        
        if (!empty($search_query)) {
            $search_param = '%' . $search_query . '%';
            oci_bind_by_name($stmt_lessor, ':search', $search_param);
            oci_bind_by_name($stmt_lessor, ':search2', $search_param);
            oci_bind_by_name($stmt_lessor, ':search3', $search_param);
        }
        
        oci_execute($stmt_lessor);
        
        while ($row = oci_fetch_assoc($stmt_lessor)) {
            $users[] = $row;
        }
    }
}

// Get user statistics
$stats = [
    'total_renters' => 0,
    'active_renters' => 0,
    'suspended_renters' => 0,
    'total_lessors' => 0,
    'verified_lessors' => 0,
    'pending_lessors' => 0,
    'rejected_lessors' => 0,
    'suspended_lessors' => 0
];

// Count renters by status
// Count renters by status
if (!isset($has_status_col)) {
    // Check if STATUS column exists (if not already checked)
    $check_status_sql = "SELECT count(*) as COUNT FROM USER_TAB_COLUMNS WHERE TABLE_NAME = 'RENTER' AND COLUMN_NAME = 'STATUS'";
    $stmt_check = oci_parse($conn, $check_status_sql);
    oci_execute($stmt_check);
    $row_check = oci_fetch_assoc($stmt_check);
    $has_status_col = ($row_check['COUNT'] > 0);
}

if ($has_status_col) {
    $renter_count_sql = "SELECT 
                         COUNT(*) as TOTAL,
                         COUNT(CASE WHEN NVL(STATUS, 'ACTIVE') = 'ACTIVE' THEN 1 END) as ACTIVE,
                         COUNT(CASE WHEN STATUS = 'SUSPENDED' THEN 1 END) as SUSPENDED
                         FROM RENTER";
} else {
    // If no STATUS column, all are ACTIVE, none are SUSPENDED
    $renter_count_sql = "SELECT 
                         COUNT(*) as TOTAL,
                         COUNT(*) as ACTIVE,
                         0 as SUSPENDED
                         FROM RENTER";
}
$stmt_rcount = oci_parse($conn, $renter_count_sql);
oci_execute($stmt_rcount);
$rcount = oci_fetch_assoc($stmt_rcount);
$stats['total_renters'] = $rcount['TOTAL'];
$stats['active_renters'] = $rcount['ACTIVE'];
$stats['suspended_renters'] = $rcount['SUSPENDED'];

// Count lessors by status
$lessor_count_sql = "SELECT 
                     COUNT(*) as TOTAL,
                     COUNT(CASE WHEN VERIFICATION_STATUS = 'VERIFIED' THEN 1 END) as VERIFIED,
                     COUNT(CASE WHEN VERIFICATION_STATUS = 'PENDING' THEN 1 END) as PENDING,
                     COUNT(CASE WHEN VERIFICATION_STATUS = 'REJECTED' THEN 1 END) as REJECTED,
                     COUNT(CASE WHEN VERIFICATION_STATUS = 'SUSPENDED' THEN 1 END) as SUSPENDED
                     FROM LESSOR";
$stmt_lcount = oci_parse($conn, $lessor_count_sql);
oci_execute($stmt_lcount);
$lcount = oci_fetch_assoc($stmt_lcount);
$stats['total_lessors'] = $lcount['TOTAL'];
$stats['verified_lessors'] = $lcount['VERIFIED'];
$stats['pending_lessors'] = $lcount['PENDING'];
$stats['rejected_lessors'] = $lcount['REJECTED'];
$stats['suspended_lessors'] = $lcount['SUSPENDED'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Royal Car Rental</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/manage_users.css?v=<?php echo time(); ?>">
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
            <!-- Logic to fetch pending lessors would go here or be shared -->
        </a>
        
        <a href="manage_users.php" class="active">
            <i class="fas fa-users"></i> <span>Manage Users</span>
        </a>
        
        <a href="manage_bookings.php">
            <i class="fas fa-calendar-alt"></i> <span>Bookings</span>
        </a>
        
        <a href="manage_tickets.php">
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
                <h2>👥 User Management</h2>
                <p>Monitor and control all system users</p>
            </div>
            <div class="admin-badge">
                <i class="fas fa-users-cog"></i>
                Access Level: Admin
            </div>
        </header>

        <!-- Flash Messages -->
        <?php if (function_exists('display_flash_message')) display_flash_message(); ?>

        <!-- Statistics Cards -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon renters-icon"><i class="fas fa-user"></i></div>
                    <div class="stat-title">Renters Data</div>
                </div>
                <div class="stat-content">
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $stats['total_renters']; ?></span>
                        <span class="stat-label">Total Users</span>
                    </div>
                    <div class="stat-item" style="text-align: right;">
                        <span class="stat-value" style="color: #059669;"><?php echo $stats['active_renters']; ?></span>
                        <span class="stat-label">Active Now</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon lessors-icon"><i class="fas fa-store"></i></div>
                    <div class="stat-title">Lessors Data</div>
                </div>
                <div class="stat-content">
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $stats['total_lessors']; ?></span>
                        <span class="stat-label">Total Shops</span>
                    </div>
                    <div class="stat-item" style="text-align: right;">
                        <span class="stat-value" style="color: #7c3aed;"><?php echo $stats['verified_lessors']; ?></span>
                        <span class="stat-label">Verified</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filter Section -->
        <section class="filter-card">
            <h3><i class="fas fa-search"></i> Advanced Search & Filters</h3>
            <form method="GET">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>User Role</label>
                        <select name="type">
                            <option value="all" <?php echo $user_type_filter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="renter" <?php echo $user_type_filter == 'renter' ? 'selected' : ''; ?>>Renters Only</option>
                            <option value="lessor" <?php echo $user_type_filter == 'lessor' ? 'selected' : ''; ?>>Lessors Only</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Account Status</label>
                        <select name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>Any Status</option>
                            <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="verified" <?php echo $status_filter == 'verified' ? 'selected' : ''; ?>>Verified (Lessors)</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending (Lessors)</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Search Keyword</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search name or email...">
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="manage_users.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </section>

        <!-- Users Table -->
        <section class="table-card">
            <div class="table-header">
                <h3>System Users (<?php echo count($users); ?>)</h3>
            </div>
            
            <?php if (empty($users)): ?>
                <div style="padding: 60px; text-align: center; color: #64748b;">
                    <i class="fas fa-user-slash" style="font-size: 40px; margin-bottom: 20px; opacity: 0.3;"></i>
                    <p>No users found matching your filters.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>UID</th>
                                <th>Role</th>
                                <th>Full Name</th>
                                <th>Email Address</th>
                                <th>Account Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td style="font-weight: 700; color: #64748b;">#<?php echo $user['USER_ID']; ?></td>
                                    <td>
                                        <span class="badge type-<?php echo strtolower($user['USER_TYPE']); ?>">
                                            <?php echo $user['USER_TYPE']; ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($user['NAME']); ?></td>
                                    <td style="color: #64748b; font-size: 13px;"><?php echo htmlspecialchars($user['EMAIL']); ?></td>
                                    <td>
                                        <span class="badge status-<?php echo strtolower($user['STATUS']); ?>">
                                            <?php echo $user['STATUS']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="view_user.php?type=<?php echo strtolower($user['USER_TYPE']); ?>&id=<?php echo $user['USER_ID']; ?>" title="View Profile"><i class="fas fa-eye"></i></a>
                                            <a href="edit_user.php?type=<?php echo strtolower($user['USER_TYPE']); ?>&id=<?php echo $user['USER_ID']; ?>" title="Edit Info"><i class="fas fa-edit"></i></a>
                                            
                                            <?php if ($user['STATUS'] == 'SUSPENDED'): ?>
                                                <a href="unsuspend_user.php?type=<?php echo strtolower($user['USER_TYPE']); ?>&id=<?php echo $user['USER_ID']; ?>" onclick="return confirm('Reactivate this account?')" class="success" title="Reactivate"><i class="fas fa-user-check"></i></a>
                                            <?php else: ?>
                                                <a href="suspend_user.php?type=<?php echo strtolower($user['USER_TYPE']); ?>&id=<?php echo $user['USER_ID']; ?>" onclick="return confirm('Suspend this account?')" class="danger" title="Suspend"><i class="fas fa-user-slash"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Guidelines -->
        <section class="info-section">
            <h4><i class="fas fa-info-circle"></i> Administrative Guidelines</h4>
            <ul>
                <li><strong>Suspend Account:</strong> Restricted for users with severe policy violations.</li>
                <li><strong>Verification:</strong> Only Lessors require manual verification of shop details.</li>
                <li><strong>Phone Access:</strong> Ensure phone numbers are verified for secure communication.</li>
            </ul>
        </section>

    </main>
</body>
</html>