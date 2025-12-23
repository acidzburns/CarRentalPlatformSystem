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

// Fetch Renters
if ($user_type_filter == 'all' || $user_type_filter == 'renter') {
    $renter_sql = "SELECT RENTERID as USER_ID, NAME, EMAIL, PHONE, CREATED_AT, 
                   'RENTER' as USER_TYPE, 'ACTIVE' as STATUS,
                   TO_CHAR(CREATED_AT, 'DD-MON-YYYY') as CREATED_FMT
                   FROM RENTER
                   WHERE 1=1";
    
    // UC23 Step 4: Search functionality
    if (!empty($search_query)) {
        $renter_sql .= " AND (LOWER(NAME) LIKE LOWER(:search) 
                        OR LOWER(EMAIL) LIKE LOWER(:search2) 
                        OR PHONE LIKE :search3)";
    }
    
    $renter_sql .= " ORDER BY CREATED_AT DESC";
    
    $stmt_renter = oci_parse($conn, $renter_sql);
    
    if (!empty($search_query)) {
        $search_param = '%' . $search_query . '%';
        oci_bind_by_name($stmt_renter, ':search', $search_param);
        oci_bind_by_name($stmt_renter, ':search2', $search_param);
        oci_bind_by_name($stmt_renter, ':search3', $search_param);
    }
    
    oci_execute($stmt_renter);
    
    while ($row = oci_fetch_assoc($stmt_renter)) {
        $users[] = $row;
    }
}

// Fetch Lessors
if ($user_type_filter == 'all' || $user_type_filter == 'lessor') {
    $lessor_sql = "SELECT LESSORID as USER_ID, NAME, EMAIL, PHONE, CREATED_AT,
                   'LESSOR' as USER_TYPE, VERIFICATION_STATUS as STATUS,
                   TO_CHAR(CREATED_AT, 'DD-MON-YYYY') as CREATED_FMT
                   FROM LESSOR
                   WHERE 1=1";
    
    // UC23 Step 3: Filter by status
    if ($status_filter != 'all') {
        $lessor_sql .= " AND VERIFICATION_STATUS = :status";
    }
    
    // UC23 Step 4: Search functionality
    if (!empty($search_query)) {
        $lessor_sql .= " AND (LOWER(NAME) LIKE LOWER(:search) 
                        OR LOWER(EMAIL) LIKE LOWER(:search2) 
                        OR PHONE LIKE :search3)";
    }
    
    $lessor_sql .= " ORDER BY CREATED_AT DESC";
    
    $stmt_lessor = oci_parse($conn, $lessor_sql);
    
    if ($status_filter != 'all') {
        $status_upper = strtoupper($status_filter);
        oci_bind_by_name($stmt_lessor, ':status', $status_upper);
    }
    
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

// Get user statistics
$stats = [
    'total_renters' => 0,
    'total_lessors' => 0,
    'verified_lessors' => 0,
    'pending_lessors' => 0,
    'rejected_lessors' => 0,
    'suspended_lessors' => 0
];

// Count renters
$renter_count_sql = "SELECT COUNT(*) as TOTAL FROM RENTER";
$stmt_rcount = oci_parse($conn, $renter_count_sql);
oci_execute($stmt_rcount);
$rcount = oci_fetch_assoc($stmt_rcount);
$stats['total_renters'] = $rcount['TOTAL'];

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
    <title>Manage Users - Admin</title>
</head>
<body>
    <h1>👥 Manage Users</h1>
    
    <nav>
        <a href="dashboard.php">Dashboard</a> |
        <a href="verify_lessors.php">Verify Lessors</a> |
        <a href="manage_users.php">Manage Users</a> |
        <a href="manage_tickets.php">Support Tickets</a> |
        <a href="reports.php">Reports</a> |
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
    
    <!-- UC23 Step 6: Display user statistics -->
    <h2>📊 User Statistics</h2>
    <table border="1" style="width: 100%;">
        <tr>
            <th>Total Renters</th>
            <th>Total Lessors</th>
            <th>Verified Lessors</th>
            <th>Pending Lessors</th>
            <th>Rejected Lessors</th>
            <th>Suspended Lessors</th>
            <th>Total Users</th>
        </tr>
        <tr>
            <td><?php echo $stats['total_renters']; ?></td>
            <td><?php echo $stats['total_lessors']; ?></td>
            <td style="color: green;"><?php echo $stats['verified_lessors']; ?></td>
            <td style="color: orange;"><?php echo $stats['pending_lessors']; ?></td>
            <td style="color: red;"><?php echo $stats['rejected_lessors']; ?></td>
            <td style="color: purple;"><?php echo $stats['suspended_lessors']; ?></td>
            <td><strong><?php echo $stats['total_renters'] + $stats['total_lessors']; ?></strong></td>
        </tr>
    </table>
    
    <hr>
    
    <!-- UC23 Step 3 & 4: Filter and search -->
    <h2>🔍 Filter & Search Users</h2>
    
    <form method="GET" action="">
        <div style="margin: 15px 0;">
            <label><strong>User Type:</strong></label><br>
            <select name="type" style="padding: 5px; width: 200px;">
                <option value="all" <?php echo $user_type_filter == 'all' ? 'selected' : ''; ?>>All Users</option>
                <option value="renter" <?php echo $user_type_filter == 'renter' ? 'selected' : ''; ?>>Renters Only</option>
                <option value="lessor" <?php echo $user_type_filter == 'lessor' ? 'selected' : ''; ?>>Lessors Only</option>
            </select>
        </div>
        
        <div style="margin: 15px 0;">
            <label><strong>Status (Lessors):</strong></label><br>
            <select name="status" style="padding: 5px; width: 200px;">
                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="verified" <?php echo $status_filter == 'verified' ? 'selected' : ''; ?>>Verified</option>
                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
            </select>
        </div>
        
        <div style="margin: 15px 0;">
            <label><strong>Search by Name/Email/Phone:</strong></label><br>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                   placeholder="Enter name, email, or phone" style="padding: 5px; width: 300px;">
        </div>
        
        <button type="submit" style="background: #2196F3; color: white; padding: 10px 20px; border: none; cursor: pointer;">
            🔍 Apply Filters
        </button>
        <a href="manage_users.php" style="display: inline-block; padding: 10px 20px; background: #ccc; color: #333; text-decoration: none;">
            Clear Filters
        </a>
    </form>
    
    <hr>
    
    <!-- UC23 Step 2: Display list of users -->
    <h2>👥 All Users (<?php echo count($users); ?>)</h2>
    
    <?php if (empty($users)): ?>
        <p>No users found matching your filters.</p>
    <?php else: ?>
        <table border="1" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th>ID</th>
                    <th>Type</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['USER_ID']; ?></td>
                        <td>
                            <span style="padding: 3px 8px; border-radius: 3px; 
                                <?php echo $user['USER_TYPE'] == 'RENTER' ? 'background: #e3f2fd; color: #0277bd;' : 'background: #f3e5f5; color: #7b1fa2;'; ?>">
                                <?php echo $user['USER_TYPE']; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($user['NAME']); ?></td>
                        <td><?php echo htmlspecialchars($user['EMAIL']); ?></td>
                        <td><?php echo htmlspecialchars($user['PHONE'] ?? 'N/A'); ?></td>
                        <td>
                            <?php if ($user['USER_TYPE'] == 'RENTER'): ?>
                                <span style="color: green;">✓ ACTIVE</span>
                            <?php else: ?>
                                <span style="color: 
                                    <?php 
                                    echo $user['STATUS'] == 'VERIFIED' ? 'green' : 
                                         ($user['STATUS'] == 'PENDING' ? 'orange' : 'red'); 
                                    ?>">
                                    <?php echo $user['STATUS']; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($user['CREATED_FMT']); ?></td>
                        <td>
                            <!-- UC23 Step 5: Select user to view details -->
                            <a href="view_user.php?type=<?php echo strtolower($user['USER_TYPE']); ?>&id=<?php echo $user['USER_ID']; ?>">
                                View Details
                            </a>
                            |
                            <!-- UC23 Step 7: Edit user option -->
                            <a href="edit_user.php?type=<?php echo strtolower($user['USER_TYPE']); ?>&id=<?php echo $user['USER_ID']; ?>">
                                Edit
                            </a>
                            <?php if ($user['USER_TYPE'] == 'LESSOR' && $user['STATUS'] == 'VERIFIED'): ?>
                            |
                            <!-- UC23 Step 11: Suspend/reactivate -->
                            <a href="suspend_user.php?type=lessor&id=<?php echo $user['USER_ID']; ?>" 
                               onclick="return confirm('Suspend this lessor account?')" 
                               style="color: red;">
                                Suspend
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <hr>
    
    <div style="background: #e3f2fd; padding: 15px; margin: 20px 0; border-left: 4px solid #2196F3;">
        <h3>ℹ️ User Management Guidelines</h3>
        <ul>
            <li><strong>View Details:</strong> See complete user information and activity history</li>
            <li><strong>Edit:</strong> Update user contact information (name, email, phone)</li>
            <li><strong>Suspend:</strong> Temporarily block user access (for violations or disputes)</li>
            <li><strong>Renters:</strong> Always active unless manually suspended</li>
            <li><strong>Lessors:</strong> Must be verified before listing cars</li>
        </ul>
    </div>
    
    <p><a href="dashboard.php">← Back to Dashboard</a></p>
</body>
</html>