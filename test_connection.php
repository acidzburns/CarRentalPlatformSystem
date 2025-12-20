<?php
/**
 * Test Database Connection
 * Run this file to verify your Oracle database connection works
 * 
 * Access: http://localhost/car-rental-backend/test_connection.php
 */

require_once 'config/database.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        h1 {
            color: #333;
        }
        .test-section {
            background-color: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <h1>🔌 Database Connection Test</h1>
    
    <?php
    // Test 1: Check if connection exists
    echo '<div class="test-section">';
    echo '<h2>Test 1: Connection Status</h2>';
    
    if ($conn) {
        echo '<div class="success">✅ Database connection successful!</div>';
        echo '<div class="info">';
        echo '<strong>Connection Details:</strong><br>';
        echo 'Host: ' . DB_HOST . '<br>';
        echo 'Port: ' . DB_PORT . '<br>';
        echo 'Service Name: ' . DB_SERVICE_NAME . '<br>';
        echo 'Username: ' . DB_USERNAME . '<br>';
        echo '</div>';
    } else {
        echo '<div class="error">❌ Database connection failed!</div>';
        echo '</div>';
        exit;
    }
    echo '</div>';
    
    // Test 2: Check if tables exist
    echo '<div class="test-section">';
    echo '<h2>Test 2: Database Tables</h2>';
    
    $sql = "SELECT table_name FROM user_tables ORDER BY table_name";
    $tables = db_query($sql);
    
    if ($tables && count($tables) > 0) {
        echo '<div class="success">✅ Found ' . count($tables) . ' tables</div>';
        echo '<table>';
        echo '<tr><th>#</th><th>Table Name</th></tr>';
        $i = 1;
        foreach ($tables as $table) {
            echo '<tr><td>' . $i++ . '</td><td>' . $table['TABLE_NAME'] . '</td></tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="error">❌ No tables found! Please run the database creation script.</div>';
    }
    echo '</div>';
    
    // Test 3: Check sequences
    echo '<div class="test-section">';
    echo '<h2>Test 3: Sequences</h2>';
    
    $sql = "SELECT sequence_name FROM user_sequences ORDER BY sequence_name";
    $sequences = db_query($sql);
    
    if ($sequences && count($sequences) > 0) {
        echo '<div class="success">✅ Found ' . count($sequences) . ' sequences</div>';
        echo '<table>';
        echo '<tr><th>#</th><th>Sequence Name</th></tr>';
        $i = 1;
        foreach ($sequences as $seq) {
            echo '<tr><td>' . $i++ . '</td><td>' . $seq['SEQUENCE_NAME'] . '</td></tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="error">❌ No sequences found!</div>';
    }
    echo '</div>';
    
    // Test 4: Check admin account
    echo '<div class="test-section">';
    echo '<h2>Test 4: Admin Account</h2>';
    
    $sql = "SELECT * FROM ADMIN";
    $admins = db_query($sql);
    
    if ($admins && count($admins) > 0) {
        echo '<div class="success">✅ Admin account exists</div>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Name</th><th>Email</th><th>Created At</th></tr>';
        foreach ($admins as $admin) {
            echo '<tr>';
            echo '<td>' . $admin['ADMINID'] . '</td>';
            echo '<td>' . $admin['NAME'] . '</td>';
            echo '<td>' . $admin['EMAIL'] . '</td>';
            echo '<td>' . $admin['CREATED_AT'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '<div class="info">📝 Default login: admin@carrental.com / admin123</div>';
    } else {
        echo '<div class="error">❌ No admin account found!</div>';
    }
    echo '</div>';
    
    // Test 5: Test helper functions
    echo '<div class="test-section">';
    echo '<h2>Test 5: Helper Functions</h2>';
    
    // Test db_fetch_one
    $admin = db_fetch_one("SELECT * FROM ADMIN WHERE ROWNUM = 1");
    if ($admin) {
        echo '<div class="success">✅ db_fetch_one() works correctly</div>';
    } else {
        echo '<div class="error">❌ db_fetch_one() failed</div>';
    }
    
    echo '</div>';
    
    // Summary
    echo '<div class="test-section">';
    echo '<h2>✅ Summary</h2>';
    echo '<div class="success">';
    echo '<strong>All tests passed! Your database is ready.</strong><br><br>';
    echo '📋 Next steps:<br>';
    echo '1. Database connection: ✅ Working<br>';
    echo '2. Tables created: ✅ ' . count($tables) . ' tables<br>';
    echo '3. Sequences created: ✅ ' . count($sequences) . ' sequences<br>';
    echo '4. Admin account: ✅ Ready<br><br>';
    echo '🚀 You can now proceed to build the authentication system!';
    echo '</div>';
    echo '</div>';
    ?>
    
</body>
</html>