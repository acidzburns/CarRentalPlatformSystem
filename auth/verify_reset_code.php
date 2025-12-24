<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);

    if (!isset($_SESSION['reset_token'], $_SESSION['reset_token_expiry'], $_SESSION['reset_email'])) {
        $error = "No reset request found. Please start over.";
    } elseif (time() > $_SESSION['reset_token_expiry']) {
        $error = "Reset code has expired. Please request a new one.";
        unset($_SESSION['reset_token'], $_SESSION['reset_token_expiry'], $_SESSION['reset_email']);
    } elseif ($code == $_SESSION['reset_token']) {
        // Code matches, redirect to reset password
        header("Location: reset_password.php");
        exit();
    } else {
        $error = "Incorrect code. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify Reset Code</title>
</head>
<body>
    <h2>Enter the 6-digit reset code sent to your email</h2>
    <?php if($error): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="POST" action="">
        <input type="text" name="code" maxlength="6" required>
        <button type="submit">Verify Code</button>
    </form>
</body>
</html>
