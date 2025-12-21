<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Check if renter is logged in
require_login('renter');

$renterid = get_user_id();

// Get FPX reference from URL
if (!isset($_GET['ref'])) {
    header("Location: dashboard.php");
    exit();
}

$fpx_reference = $_GET['ref'];

// Get FPX transaction from session
if (!isset($_SESSION['fpx_transaction']) || $_SESSION['fpx_transaction']['fpx_reference'] != $fpx_reference) {
    set_flash_message("Invalid FPX transaction reference.", "error");
    header("Location: dashboard.php");
    exit();
}

$fpx_data = $_SESSION['fpx_transaction'];

// Handle FPX payment authorization
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action == 'approve') {
        // Payment approved - redirect to callback
        header("Location: fpx_callback.php?ref=" . $fpx_reference . "&status=success");
        exit();
    } else {
        // Payment cancelled/failed
        header("Location: fpx_callback.php?ref=" . $fpx_reference . "&status=failed");
        exit();
    }
}

// Get bank name from code
$bank_codes = [
    'ABMB0212' => 'Alliance Bank',
    'AMBB0209' => 'AmBank',
    'MB2U0227' => 'Maybank2u',
    'BCBB0235' => 'CIMB Bank',
    'PBB0233' => 'Public Bank',
    'RHB0218' => 'RHB Bank',
    'HLB0224' => 'Hong Leong Bank'
    // Add more as needed
];

$bank_name = isset($bank_codes[$fpx_data['bank']]) ? $bank_codes[$fpx_data['bank']] : 'Your Bank';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FPX Payment Gateway - <?php echo htmlspecialchars($bank_name); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
            margin: 0;
            padding: 20px;
        }
        .fpx-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .fpx-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        .fpx-body {
            padding: 30px;
        }
        .amount-box {
            background: #e8f5e9;
            border: 2px solid #4CAF50;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
            border-radius: 5px;
        }
        .amount-box h2 {
            margin: 0;
            color: #4CAF50;
            font-size: 32px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin: 10px 5px;
            width: calc(50% - 10px);
        }
        .btn-approve {
            background: #4CAF50;
            color: white;
        }
        .btn-cancel {
            background: #f44336;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .simulation-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="fpx-container">
        <div class="fpx-header">
            <h1> <?php echo htmlspecialchars($bank_name); ?></h1>
            <p>Internet Banking - FPX Payment Gateway</p>
        </div>
        
       
            
            <h2>Payment Authorization Required</h2>
            
            <div class="amount-box">
                <p style="margin: 0; color: #666;">Payment Amount</p>
                <h2>RM <?php echo number_format($fpx_data['amount'], 2); ?></h2>
            </div>
            
            <h3>Transaction Details</h3>
            <div class="info-row">
                <span><strong>Merchant:</strong></span>
                <span>Car Rental Platform System</span>
            </div>
            <div class="info-row">
                <span><strong>Reference:</strong></span>
                <span><?php echo htmlspecialchars($fpx_reference); ?></span>
            </div>
            <div class="info-row">
                <span><strong>Booking ID:</strong></span>
                <span>#<?php echo htmlspecialchars($fpx_data['bookingid']); ?></span>
            </div>
            <div class="info-row">
                <span><strong>Date & Time:</strong></span>
                <span><?php echo date('d M Y, h:i A', $fpx_data['timestamp']); ?></span>
            </div>
            
            <div style="background: #e3f2fd; padding: 15px; margin: 20px 0; border-radius: 5px;">
                
                </p>
                <p style="margin: 10px 0 0 0; font-size: 14px;">
                    <strong>Escrow Protection:</strong> Your payment will be securely held until booking completion.
                </p>
            </div>
            
            <form method="POST" action="">
                <div style="text-align: center;">
                    <button type="submit" name="action" value="approve" class="btn btn-approve">
                        ✓ Approve Payment
                    </button>
                    <button type="submit" name="action" value="cancel" class="btn btn-cancel">
                        ✗ Cancel
                    </button>
                </div>
            </form>
            
            <p style="text-align: center; font-size: 12px; color: #666; margin-top: 30px;">
             This is a secure FPX payment page<br>
                Your connection is encrypted and protected
            </p>
        </div>
    </div>
</body>
</html>