<?php
/**
 * Admin Login Page
 * UC22: Admin Login
 */

require_once '../config/database.php';
require_once '../config/session.php';

// Redirect if already logged in
redirect_if_logged_in();

// Initialize variables
$error = "";
$email = "";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } else {

        // NOTE: Using mysqli prepared statements, NOT PDO-style placeholders
        // Use :email placeholder for Oracle
$sql = "SELECT ADMINID, NAME, EMAIL, PASSWORD FROM ADMIN WHERE EMAIL = :email";
$admin = db_fetch_one($sql, ['email' => $email]);

        if ($admin) {

            $password_valid = false;

            // Hashed password check
            if (password_verify($password, $admin['PASSWORD'])) {
                $password_valid = true;
            }
            // Plain text fallback (migration)
            elseif ($password === $admin['PASSWORD']) {
                $password_valid = true;

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE ADMIN SET PASSWORD = :pass WHERE ADMINID = :id";
                db_execute($update_sql, ['pass' => $hashed_password, 'id' => $admin['ADMINID']]);
            }

            if ($password_valid) {
                set_session(
                    $admin['ADMINID'],
                    $admin['NAME'],
                    $admin['EMAIL'],
                    'admin'
                );

                header('Location: ../admin/dashboard.php');
                exit();
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Admin account not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login | Car Rental System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/sys_globals.css">
    <link rel="stylesheet" href="../assets/css/sys_components.css">
    <link rel="stylesheet" href="../assets/css/sys_auth.css">

    <style>
        body.auth-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #1e293b, #020617);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, sans-serif;
        }

        .auth-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .auth-card {
            background: #ffffff;
            padding: 32px;
            border-radius: 14px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }

        .auth-card h1 {
            text-align: center;
            margin-bottom: 6px;
        }

        .auth-card p {
            text-align: center;
            color: #555;
            margin-bottom: 20px;
        }

        .auth-card label {
            font-weight: 600;
            display: block;
            margin-bottom: 6px;
        }

        .auth-card input {
            width: 100%;
            padding: 10px;
            margin-bottom: 14px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        .auth-card button {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }

        .auth-card button:hover {
            background: #1e40af;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 14px;
        }

        .auth-links {
            margin-top: 15px;
            text-align: center;
            font-size: 14px;
        }

        .auth-links a {
            color: #2563eb;
            text-decoration: none;
            margin: 0 5px;
        }

        .auth-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body class="auth-page">

<div class="auth-container">
    <div class="auth-card">

        <h1>Administrator Login</h1>
        <p>Secure access to system administration</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="email">Admin Email</label>
            <input type="email" name="email" id="email"
                   value="<?= htmlspecialchars($email) ?>" required autofocus>

            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>

            <button type="submit">Login</button>
        </form>

        <div class="auth-links">
            <a href="renter_login.php">Customer Login</a> |
            <a href="lessor_login.php">Car Owner Login</a> |
            <a href="../index.php">Home</a>
        </div>

    </div>
</div>

</body>
</html>
