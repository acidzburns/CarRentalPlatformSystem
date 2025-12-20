<?php
/**
 * Database Configuration File
 * Oracle Database Connection using OCI8
 * 
 * Make sure you have Oracle Instant Client and PHP OCI8 extension installed
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '1521');
define('DB_SERVICE_NAME', 'FREEPDB1');
define('DB_USERNAME', 'car');
define('DB_PASSWORD', 'car');

// Character set
define('DB_CHARSET', 'AL32UTF8');

// Create connection string
$connection_string = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=".DB_HOST.")(PORT=".DB_PORT."))(CONNECT_DATA=(SERVICE_NAME=".DB_SERVICE_NAME.")))";

// Establish connection
$conn = oci_connect(DB_USERNAME, DB_PASSWORD, $connection_string, DB_CHARSET);

// Check if connection was successful
if (!$conn) {
    $error = oci_error();
    die("Database Connection Failed: " . htmlentities($error['message'], ENT_QUOTES));
}

/**
 * Execute SELECT query and return results as associative array
 * 
 * @param string $sql SQL query with bind variables (e.g., :email)
 * @param array $params Associative array of parameters (e.g., ['email' => 'test@example.com'])
 * @return array|false Returns array of results or false on error
 */
function db_query($sql, $params = []) {
    global $conn;
    
    // Parse the SQL statement
    $stmt = oci_parse($conn, $sql);
    
    if (!$stmt) {
        $error = oci_error($conn);
        return false;
    }
    
    // Bind parameters if provided
    foreach ($params as $key => $value) {
        // Remove colon if present in key
        $bind_key = (strpos($key, ':') === 0) ? $key : ':' . $key;
        oci_bind_by_name($stmt, $bind_key, $params[$key]);
    }
    
    // Execute the statement
    $result = oci_execute($stmt);
    
    if (!$result) {
        $error = oci_error($stmt);
        oci_free_statement($stmt);
        return false;
    }
    
    // Fetch all results
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $rows[] = $row;
    }
    
    oci_free_statement($stmt);
    return $rows;
}

/**
 * Execute INSERT/UPDATE/DELETE and return affected rows
 * 
 * @param string $sql SQL query with bind variables
 * @param array $params Associative array of parameters
 * @return bool Returns true on success, false on failure
 */
function db_execute($sql, $params = []) {
    global $conn;
    
    $stmt = oci_parse($conn, $sql);
    
    if (!$stmt) {
        return false;
    }
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $bind_key = (strpos($key, ':') === 0) ? $key : ':' . $key;
        oci_bind_by_name($stmt, $bind_key, $params[$key]);
    }
    
    // Execute with auto-commit
    $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
    
    oci_free_statement($stmt);
    return $result;
}

/**
 * Execute SELECT query and return single row
 * 
 * @param string $sql SQL query
 * @param array $params Parameters
 * @return array|false Returns single row or false
 */
function db_fetch_one($sql, $params = []) {
    $results = db_query($sql, $params);
    return ($results && count($results) > 0) ? $results[0] : false;
}

/**
 * Get the last inserted ID from a sequence
 * 
 * @param string $sequence_name Name of the sequence
 * @return int|false Returns the current value or false
 */
function db_last_insert_id($sequence_name) {
    global $conn;
    
    $sql = "SELECT {$sequence_name}.CURRVAL FROM DUAL";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    
    return $row ? $row['CURRVAL'] : false;
}

/**
 * Sanitize input to prevent SQL injection
 * Note: Always use bind parameters instead when possible
 * 
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Close database connection
 */
function db_close() {
    global $conn;
    if ($conn) {
        oci_close($conn);
    }
}

// Register shutdown function to close connection
register_shutdown_function('db_close');

?>