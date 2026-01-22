<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);

    if (!isset($_SESSION['reset_token'], $_SESSION['reset_token_expiry'], $_SESSION['reset_email'])) {
        $error = "No reset request found. Please start over.";
    } elseif (time() > $_SESSION['reset_token_expiry']) {
        $error = "Reset code has expired. Please request a new one.";
        unset($_SESSION['reset_token'], $_SESSION['reset_token_expiry'], $_SESSION['reset_email']);
    } elseif ($code == $_SESSION['reset_token']) {
        header("Location: reset_password.php");
        exit();
    } else {
        $error = "Incorrect code. Please try again.";
    }
}

$time_remaining = 0;
if (isset($_SESSION['reset_token_expiry'])) {
    $time_remaining = $_SESSION['reset_token_expiry'] - time();
    if ($time_remaining < 0) $time_remaining = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Reset Code - Car Rental System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-height: 100vh; display: flex; flex-direction: column; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); }
        .navbar { background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); padding: 1rem 0; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .nav-container { max-width: 1200px; margin: 0 auto; padding: 0 2rem; }
        .nav-logo { color: white; font-size: 1.5rem; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 10px; }
        .main-content { flex: 1; display: flex; align-items: center; justify-content: center; padding: 3rem 1rem; }
        .container { background: white; padding: 3rem; border-radius: 16px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1); max-width: 500px; width: 100%; }
        .header-section { text-align: center; margin-bottom: 2rem; }
        .icon-wrapper { display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 50%; margin-bottom: 1.5rem; }
        .icon-wrapper i { font-size: 40px; color: #2563eb; }
        h2 { color: #1e293b; font-size: 1.875rem; font-weight: 700; margin-bottom: 0.5rem; }
        .subtitle { color: #64748b; font-size: 1rem; line-height: 1.6; }
        .email-display { background: #f8fafc; padding: 0.75rem 1rem; border-radius: 8px; margin-top: 1rem; color: #2563eb; font-weight: 600; border: 2px solid #e0e7ff; }
        .timer-box { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center; border-left: 4px solid #f59e0b; }
        .timer-box p { color: #92400e; margin: 0; font-weight: 600; }
        #countdown { font-size: 1.5rem; font-weight: 700; color: #b45309; margin-top: 0.5rem; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        input[type="text"] { width: 100%; padding: 1.25rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 2rem; text-align: center; letter-spacing: 0.5rem; font-weight: 700; font-family: monospace; }
        input[type="text"]:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .btn { width: 100%; padding: 1rem; background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4); }
        .resend-section { text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0; }
        .resend-section a { color: #2563eb; text-decoration: none; font-weight: 600; }
        .footer { background: #1e293b; color: white; text-align: center; padding: 1.5rem; margin-top: auto; }
        @media (max-width: 640px) { .container { padding: 2rem 1.5rem; margin: 1rem; } input[type="text"] { font-size: 1.75rem; letter-spacing: 0.25rem; } }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="nav-logo"><i class="fas fa-car"></i> Car Rental System</a>
        </div>
    </nav>
    <div class="main-content">
        <div class="container">
            <div class="header-section">
                <div class="icon-wrapper"><i class="fas fa-shield-alt"></i></div>
                <h2>Verify Reset Code</h2>
                <p class="subtitle">We've sent a 6-digit code to your email</p>
                <?php if(isset($_SESSION['reset_email'])): ?>
                    <div class="email-display"><i class="fas fa-envelope"></i> <?= htmlspecialchars($_SESSION['reset_email']) ?></div>
                <?php endif; ?>
            </div>
            <?php if($time_remaining > 0): ?>
                <div class="timer-box">
                    <p><i class="fas fa-clock"></i> Code expires in:</p>
                    <div id="countdown"><?= gmdate("i:s", $time_remaining) ?></div>
                </div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="code" style="display: block; text-align: center; margin-bottom: 1rem; font-weight: 600;">Enter 6-Digit Code</label>
                    <input type="text" name="code" id="code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus>
                </div>
                <button type="submit" class="btn"><i class="fas fa-check-circle"></i> Verify Code</button>
            </form>
            <div class="resend-section">
                <p style="color: #64748b; margin-bottom: 0.5rem;">Didn't receive the code?</p>
                <a href="forgot_password.php"><i class="fas fa-redo"></i> Resend Code</a>
            </div>
        </div>
    </div>
    
    <script>
        <?php if($time_remaining > 0): ?>
        let timeLeft = <?= $time_remaining ?>;
        const countdownElement = document.getElementById('countdown');
        const timer = setInterval(function() {
            if (timeLeft <= 0) {
                clearInterval(timer);
                countdownElement.innerHTML = '<span style="color: #dc2626;">Expired</span>';
                location.reload();
            } else {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                countdownElement.textContent = minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
            }
            timeLeft--;
        }, 1000);
        <?php endif; ?>
        const codeInput = document.getElementById('code');
        codeInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>