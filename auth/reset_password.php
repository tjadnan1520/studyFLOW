<?php
require_once '../config/database.php';
require_once '../config/mail.php';

if (isLoggedIn()) {
    redirect(isTeacher() ? '../dashboard_teacher.php' : '../dashboard_student.php');
}

$message = '';
$messageType = '';
$resetComplete = false;
$codeVerified = false;

$email = trim($_POST['email'] ?? $_SESSION['reset_email'] ?? '');
$code = trim($_POST['code'] ?? '');

if (empty($email)) {
    redirect('forgot_password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($code) && empty($_POST['password'])) {
    $resetData = verifyResetCode($email, $code);
    if ($resetData) {
        $codeVerified = true;
        $_SESSION['reset_code'] = $code;
        $_SESSION['reset_email'] = $email;
    } else {
        $message = 'Invalid or expired verification code. Please request a new code.';
        $messageType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $code = trim($_POST['code'] ?? $_SESSION['reset_code'] ?? '');
    $email = trim($_POST['email'] ?? $_SESSION['reset_email'] ?? '');
    
    $errors = [];
    
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':\"\\|,.<>\/?]/', $password)) {
        $errors[] = 'Password must contain at least one special character (!@#$%^&*()_+-=[]{};\':"|,.<>/?).';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (empty($errors)) {
        $result = resetPassword($email, $code, $password);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        $resetComplete = $result['success'];
        
        if ($resetComplete) {
            unset($_SESSION['reset_code']);
            unset($_SESSION['reset_email']);
        }
    } else {
        $message = implode(' ', $errors);
        $messageType = 'error';
        $codeVerified = true; // Keep showing the password form
    }
}

if (!$codeVerified && isset($_SESSION['reset_code']) && isset($_SESSION['reset_email'])) {
    $resetData = verifyResetCode($_SESSION['reset_email'], $_SESSION['reset_code']);
    if ($resetData) {
        $codeVerified = true;
        $code = $_SESSION['reset_code'];
        $email = $_SESSION['reset_email'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
        }
        .strength-weak { width: 33%; background: #ef4444; }
        .strength-medium { width: 66%; background: #f59e0b; }
        .strength-strong { width: 100%; background: #10b981; }
        .password-hint {
            font-size: 0.75rem;
            color: #666;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="../index.php" class="logo"><?= APP_NAME ?></a>
                <h1>Reset Password</h1>
                <?php if ($codeVerified && !$resetComplete): ?>
                <p>Create a new password for your account</p>
                <?php endif; ?>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= sanitize($message) ?></div>
            <?php endif; ?>
            
            <?php if ($resetComplete): ?>
            <div class="reset-sent-info">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 16px; display: block;">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M9 12l2 2 4-4"/>
                </svg>
                <p>Your password has been reset successfully!</p>
                <a href="login.php" class="btn btn-primary btn-block" style="margin-top: 16px;">Sign In Now</a>
            </div>
            
            <?php elseif ($codeVerified): ?>
            <form method="POST" class="auth-form" id="resetForm">
                <input type="hidden" name="email" value="<?= sanitize($email) ?>">
                <input type="hidden" name="code" value="<?= sanitize($code) ?>">
                
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter new password" minlength="8">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                    <p class="password-hint">Must contain: 8+ characters, uppercase, lowercase, number, and special character</p>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Confirm new password">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
            </form>
            
            <script>
                const password = document.getElementById('password');
                const strengthBar = document.getElementById('strengthBar');
                const confirmPassword = document.getElementById('confirm_password');
                
                password.addEventListener('input', function() {
                    const val = this.value;
                    let strength = 0;
                    
                    if (val.length >= 8) strength++;
                    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) strength++;
                    if (/[0-9]/.test(val)) strength++;
                    if (/[!@#$%^&*()_+\-=\[\]{};\':\"\\|,.<>\/?]/.test(val)) strength++;
                    
                    strengthBar.className = 'password-strength-bar';
                    if (strength <= 1) strengthBar.classList.add('strength-weak');
                    else if (strength <= 3) strengthBar.classList.add('strength-medium');
                    else if (strength === 4) strengthBar.classList.add('strength-strong');
                });
                
                document.getElementById('resetForm').addEventListener('submit', function(e) {
                    const val = password.value;
                    if (val.length < 8) {
                        e.preventDefault();
                        alert('Password must be at least 8 characters.');
                        return;
                    }
                    if (!/[A-Z]/.test(val)) {
                        e.preventDefault();
                        alert('Password must contain at least one uppercase letter.');
                        return;
                    }
                    if (!/[a-z]/.test(val)) {
                        e.preventDefault();
                        alert('Password must contain at least one lowercase letter.');
                        return;
                    }
                    if (!/[0-9]/.test(val)) {
                        e.preventDefault();
                        alert('Password must contain at least one number.');
                        return;
                    }
                    if (!/[!@#$%^&*()_+\-=\[\]{};\':\"\\|,.<>\/?]/.test(val)) {
                        e.preventDefault();
                        alert('Password must contain at least one special character.');
                        return;
                    }
                    if (password.value !== confirmPassword.value) {
                        e.preventDefault();
                        alert('Passwords do not match.');
                    }
                });
            </script>
            
            <?php else: ?>
            <div class="reset-sent-info">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 16px; display: block;">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <p>Invalid or expired verification code.</p>
                <a href="forgot_password.php" class="btn btn-primary btn-block" style="margin-top: 16px;">Request New Code</a>
            </div>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p><a href="login.php">&larr; Back to Sign In</a></p>
            </div>
        </div>
    </div>
</body>
</html>
