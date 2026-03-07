<?php
require_once '../config/database.php';
require_once '../config/mail.php';

if (isLoggedIn()) {
    redirect(isTeacher() ? '../dashboard_teacher.php' : '../dashboard_student.php');
}

$message = '';
$messageType = '';
$emailSent = false;
$userEmail = $_SESSION['reset_email'] ?? '';

if (isset($_GET['resend']) && isset($_SESSION['reset_email'])) {
    $email = $_SESSION['reset_email'];
    $result = sendPasswordResetEmail($email);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
    $emailSent = $result['success'];
    if ($emailSent && !empty($result['email'])) {
        $userEmail = $result['email'];
        $_SESSION['reset_email'] = $result['email'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        $result = sendPasswordResetEmail($email);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        $emailSent = $result['success'];
        
        if ($emailSent && !empty($result['email'])) {
            $userEmail = $result['email'];
            $_SESSION['reset_email'] = $result['email'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="../index.php" class="logo"><?= APP_NAME ?></a>
                <h1>Forgot Password?</h1>
                <p><?= $emailSent ? 'Enter the 6-digit code sent to your email' : 'No worries, we\'ll send you a verification code.' ?></p>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= sanitize($message) ?></div>
            <?php endif; ?>
            
            <?php if (!$emailSent): ?>
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="Enter your registered email"
                           value="<?= sanitize($_POST['email'] ?? '') ?>">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Send Verification Code</button>
            </form>
            <?php else: ?>
            <form action="reset_password.php" method="POST" class="auth-form" id="codeForm">
                <input type="hidden" name="email" value="<?= sanitize($userEmail ?: $_SESSION['reset_email'] ?? '') ?>">
                
                <div class="form-group">
                    <label for="code">Verification Code</label>
                    <input type="text" id="code" name="code" required 
                           placeholder="Enter 6-digit code" 
                           maxlength="6" 
                           pattern="[0-9]{6}"
                           inputmode="numeric"
                           style="text-align: center; font-size: 24px; letter-spacing: 8px; font-weight: bold;">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Verify Code</button>
            </form>
            
            <p style="font-size: 0.875rem; color: #666; margin-top: 16px; text-align: center;">
                Didn't receive the code? <a href="forgot_password.php?resend=1">Resend code</a>
            </p>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p><a href="login.php">&larr; Back to Sign In</a></p>
            </div>
        </div>
    </div>
</body>
</html>
