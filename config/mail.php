<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/database.php';

define('EMAIL_USER', Env::get('EMAIL_USER', ''));
define('EMAIL_PASSWORD', Env::get('EMAIL_PASSWORD', ''));
define('EMAIL_FROM_NAME', Env::get('EMAIL_FROM_NAME', ''));
define('EMAIL_HOST', Env::get('EMAIL_HOST', ''));
define('EMAIL_PORT', Env::get('EMAIL_PORT', ));

function sendEmail($to, $subject, $htmlBody, $textBody = '') {
    $phpmailerPath = dirname(__DIR__) . '/vendor/autoload.php';
    
    if (file_exists($phpmailerPath)) {
        require_once $phpmailerPath;
        return sendWithPHPMailer($to, $subject, $htmlBody, $textBody);
    }
    
    return sendWithSMTP($to, $subject, $htmlBody, $textBody);
}

function sendWithPHPMailer($to, $subject, $htmlBody, $textBody = '') {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = EMAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = EMAIL_USER;
        $mail->Password = EMAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = EMAIL_PORT;
        
        $mail->setFrom(EMAIL_USER, EMAIL_FROM_NAME);
        $mail->addAddress($to);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        if (APP_DEBUG) {
            error_log("PHPMailer Error: " . $e->getMessage());
        }
        return false;
    }
}

function sendWithSMTP($to, $subject, $htmlBody, $textBody = '') {
    $host = EMAIL_HOST;
    $port = EMAIL_PORT;
    $username = EMAIL_USER;
    $password = EMAIL_PASSWORD;
    $fromName = EMAIL_FROM_NAME;
    
    $socket = @fsockopen('tls://' . $host, 465, $errno, $errstr, 30);
    
    if (!$socket) {
        $socket = @fsockopen($host, 587, $errno, $errstr, 30);
        if ($socket) {
            smtpCommand($socket, "EHLO " . gethostname());
            smtpCommand($socket, "STARTTLS");
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }
    }
    
    if (!$socket) {
        if (APP_DEBUG) {
            error_log("SMTP Connection failed: $errstr ($errno)");
        }
        return sendWithMail($to, $subject, $htmlBody, $textBody);
    }
    
    try {
        fgets($socket, 515);
        
        smtpCommand($socket, "EHLO " . gethostname());
        smtpCommand($socket, "AUTH LOGIN");
        smtpCommand($socket, base64_encode($username));
        smtpCommand($socket, base64_encode($password));
        smtpCommand($socket, "MAIL FROM:<$username>");
        smtpCommand($socket, "RCPT TO:<$to>");
        smtpCommand($socket, "DATA");
        
        $boundary = md5(time());
        $headers = "From: $fromName <$username>\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        
        $message = $headers . "\r\n";
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $message .= ($textBody ?: strip_tags($htmlBody)) . "\r\n";
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $htmlBody . "\r\n";
        $message .= "--$boundary--\r\n";
        $message .= ".";
        
        smtpCommand($socket, $message);
        smtpCommand($socket, "QUIT");
        
        fclose($socket);
        return true;
    } catch (Exception $e) {
        fclose($socket);
        if (APP_DEBUG) {
            error_log("SMTP Error: " . $e->getMessage());
        }
        return sendWithMail($to, $subject, $htmlBody, $textBody);
    }
}

function smtpCommand($socket, $command) {
    fputs($socket, $command . "\r\n");
    return fgets($socket, 515);
}

function sendWithMail($to, $subject, $htmlBody, $textBody = '') {
    $boundary = md5(time());
    
    $headers = "From: " . EMAIL_FROM_NAME . " <" . EMAIL_USER . ">\r\n";
    $headers .= "Reply-To: " . EMAIL_USER . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    
    $body = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= ($textBody ?: strip_tags($htmlBody)) . "\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $htmlBody . "\r\n";
    $body .= "--$boundary--";
    
    return mail($to, $subject, $body, $headers);
}

function generateVerificationCode() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sendPasswordResetEmail($email) {
    ensurePasswordResetsTable();
    
    $email = trim($email);
    
    $user = dbFetch("SELECT id, name, email FROM users WHERE email = ?", [$email]);
    
    if (!$user) {
        return ['success' => false, 'message' => 'No account found with that email address.', 'email' => ''];
    }
    
    dbExecute("DELETE FROM password_resets WHERE user_id = ?", [$user['id']]);
    
    $code = generateVerificationCode();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    dbExecute(
        "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)",
        [$user['id'], $code, $expiresAt]
    );
    
    $subject = "Your Verification Code - " . APP_NAME;
    
    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #6366f1; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
            .code-box { background: #6366f1; color: white; font-size: 32px; font-weight: bold; letter-spacing: 8px; padding: 20px 30px; text-align: center; border-radius: 8px; margin: 20px 0; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>" . APP_NAME . "</h1>
            </div>
            <div class='content'>
                <h2>Password Reset Code</h2>
                <p>Hello {$user['name']},</p>
                <p>We received a request to reset your password. Use the verification code below:</p>
                <div class='code-box'>$code</div>
                <p><strong>This code will expire in 15 minutes.</strong></p>
                <p>If you didn't request this password reset, you can safely ignore this email.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    $textBody = "Password Reset Code\n\n";
    $textBody .= "Hello {$user['name']},\n\n";
    $textBody .= "Your verification code is: $code\n\n";
    $textBody .= "This code will expire in 15 minutes.\n\n";
    $textBody .= "If you didn't request this password reset, you can safely ignore this email.";
    
    $sent = sendEmail($user['email'], $subject, $htmlBody, $textBody);
    
    if ($sent) {
        return ['success' => true, 'message' => 'A 6-digit verification code has been sent to your email.', 'email' => $user['email']];
    } else {
        return ['success' => false, 'message' => 'Failed to send email. Please try again later.', 'email' => ''];
    }
}

function ensurePasswordResetsTable() {
    $mysqli = getConnection();
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(10) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

function verifyResetCode($email, $code) {
    ensurePasswordResetsTable();
    
    $email = trim($email);
    $code = trim($code);
    
    $user = dbFetch("SELECT id, email, name FROM users WHERE email = ?", [$email]);
    if (!$user) {
        return null;
    }
    
    $reset = dbFetch(
        "SELECT * FROM password_resets WHERE user_id = ? AND token = ? AND used = 0 AND expires_at > NOW()",
        [$user['id'], $code]
    );
    
    if ($reset) {
        $reset['email'] = $user['email'];
        $reset['name'] = $user['name'];
    }
    
    return $reset;
}

function resetPassword($email, $code, $newPassword) {
    $reset = verifyResetCode($email, $code);
    
    if (!$reset) {
        return ['success' => false, 'message' => 'Invalid or expired verification code.'];
    }
    
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    dbExecute("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $reset['user_id']]);
    
    dbExecute("UPDATE password_resets SET used = 1 WHERE id = ?", [$reset['id']]);
    
    return ['success' => true, 'message' => 'Password has been reset successfully.'];
}
