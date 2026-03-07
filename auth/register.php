<?php
require_once '../config/database.php';

if (isLoggedIn()) {
    redirect(isTeacher() ? '../dashboard_teacher.php' : '../dashboard_student.php');
}

$errors = [];
$success = false;
$defaultRole = isset($_GET['role']) && $_GET['role'] === 'teacher' ? 'teacher' : 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email.';
    }
    
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
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (!in_array($role, ['teacher', 'student'])) {
        $role = 'student';
    }
    
    if (empty($errors)) {
        $existingUser = dbFetch("SELECT id FROM users WHERE email = ?", [$email]);
        
        if ($existingUser) {
            $errors[] = 'Email already registered.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            dbExecute("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)", [$name, $email, $hashedPassword, $role]);
            
            if (dbLastInsertId()) {
                $success = true;
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - StudyFlow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="../index.php" class="logo">StudyFlow</a>
                <h1>Create Account</h1>
                <p>Join StudyFlow today</p>
            </div>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                Registration successful! <a href="login.php">Sign in now</a>
            </div>
            <?php else: ?>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $e): ?>
                    <li><?= sanitize($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required
                           value="<?= sanitize($_POST['name'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required
                           value="<?= sanitize($_POST['email'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                    <small class="form-hint">Must contain: 8+ characters, uppercase, lowercase, number, and special character</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="form-group">
                    <label for="role">I am a:</label>
                    <select id="role" name="role" class="form-control">
                        <option value="student" <?= ($defaultRole === 'student') ? 'selected' : '' ?>>Student</option>
                        <option value="teacher" <?= ($defaultRole === 'teacher') ? 'selected' : '' ?>>Teacher</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Sign in</a></p>
            </div>
        </div>
    </div>
</body>
</html>
