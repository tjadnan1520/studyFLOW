<?php
require_once '../config/database.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_code = strtoupper(trim($_POST['class_code'] ?? ''));
    
    if (empty($class_code)) {
        $error = 'Please enter a class code.';
    } else {
        $class = dbFetch("SELECT id FROM classes WHERE class_code = ?", [$class_code]);
        
        if (!$class) {
            $error = 'Invalid class code. Please check and try again.';
        } else {
            $existing = dbFetch("SELECT id FROM class_members WHERE class_id = ? AND student_id = ?", [$class['id'], $user_id]);
            
            if ($existing) {
                $error = 'You are already enrolled in this class.';
            } else {
                dbExecute("INSERT INTO class_members (class_id, student_id) VALUES (?, ?)", [$class['id'], $user_id]);
                redirect("class_view.php?id=" . encodeId($class['id']));
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
    <title>Join Class - StudyFlow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="../index.php" class="logo">StudyFlow</a>
            </div>
            <nav class="sidebar-nav">
                <a href="../dashboard_student.php" class="nav-item">
                    <span class="nav-icon"></span> Dashboard
                </a>
                <a href="join_class.php" class="nav-item active">
                    <span class="nav-icon"></span> Join Class
                </a> 
            </nav> 
            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="user-name"><?= sanitize($_SESSION['user_name']) ?></span>
                    <span class="user-role">Student</span>
                </div>
                <a href="../auth/logout.php" class="btn btn-outline btn-sm">Logout</a>
            </div>
        </aside>
        
        <main class="main-content">
            <header class="content-header">
                <h1>Join a Class</h1>
            </header>
            
            <div class="form-container">
                <div class="join-info">
                    <p>Enter the class code provided by your teacher to join a class.</p>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-error"><?= sanitize($error) ?></div>
                <?php endif; ?>
                
                <form method="POST" class="styled-form">
                    <div class="form-group">
                        <label for="class_code">Class Code</label>
                        <input type="text" id="class_code" name="class_code" required
                               placeholder="Enter class code (e.g., ABC123)"
                               maxlength="10" style="text-transform: uppercase;"
                               value="<?= sanitize($_POST['class_code'] ?? '') ?>">
                    </div>
                    
                    <div class="form-actions">
                        <a href="../dashboard_student.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary">Join Class</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script src="../assets/js/app.js"></script>
</body>
</html>
