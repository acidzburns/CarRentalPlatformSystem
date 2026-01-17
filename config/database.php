<?php
// Database Configuration for Oracle 23ai
define('DB_HOST', 'localhost');
define('DB_PORT', '1521');
define('DB_SERVICE_NAME', 'FREEPDB1');
define('DB_USERNAME', 'car');
define('DB_PASSWORD', 'car');

// Connection String
$tns = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=" . DB_HOST . ")(PORT=" . DB_PORT . "))(CONNECT_DATA=(SERVICE_NAME=" . DB_SERVICE_NAME . ")))";

$conn = oci_connect(DB_USERNAME, DB_PASSWORD, $tns);

if (!$conn) {
    $e = oci_error();
    die("Database Connection Failed: " . $e['message']);
}

/**
 * Helper: db_query
 * Used for SELECT queries (returns multiple rows)
 */
function db_query($sql, $params = []) {
    global $conn;
    $stid = oci_parse($conn, $sql);
    
    foreach ($params as $key => &$val) {
        oci_bind_by_name($stid, ":" . $key, $val);
    }
    
    if (!@oci_execute($stid)) {
        return false;
    }
    
    $results = [];
    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
        $results[] = $row;
    }
    oci_free_statement($stid);
    return $results;
}

/**
 * Helper: db_fetch_one
 * Used for Login (returns single row)
 */
function db_fetch_one($sql, $params = []) {
    global $conn;
    $stid = oci_parse($conn, $sql);
    
    foreach ($params as $key => &$val) {
        oci_bind_by_name($stid, ":" . $key, $val);
    }
    
    if (!@oci_execute($stid)) {
        return false;
    }
    
    $row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($stid);
    return $row;
}

/**
 * Helper: db_execute
 * REQUIRED: This was missing! used for INSERT/UPDATE (Registration)
 */
function db_execute($sql, $params = []) {
    global $conn;
    $stid = oci_parse($conn, $sql);
    
    foreach ($params as $key => &$val) {
        oci_bind_by_name($stid, ":" . $key, $val);
    }
    
    // OCI_COMMIT_ON_SUCCESS ensures the data is actually saved
    if (!@oci_execute($stid, OCI_COMMIT_ON_SUCCESS)) {
        return false;
    }
    return true;
}

/**
 * Helper: Sanitize Input
 * Used by Login/Register pages
 */
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

/**
 * Helper: db_fetch_all
 * Alias for db_query - returns all rows found
 * Required by: admin/verify_lessors.php
 */
function db_fetch_all($sql, $params = []) {
    // This simply calls the existing db_query function
    return db_query($sql, $params);
}

function db_last_insert_id($sequence_name) {
    global $conn;
    
    // In Oracle, we get the last inserted ID by checking the current value of the sequence
    $sql = "SELECT " . $sequence_name . ".CURRVAL AS ID FROM DUAL";
    
    $stid = oci_parse($conn, $sql);
    
    if (!@oci_execute($stid)) {
        return false;
    }
    
    $row = oci_fetch_array($stid, OCI_ASSOC);
    oci_free_statement($stid);
    
    return $row['ID'];
}
?>

