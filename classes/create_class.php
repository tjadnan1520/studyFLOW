<?php
require_once '../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_name = trim($_POST['class_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($class_name)) {
        $errors[] = 'Class name is required.';
    }
    
    if (empty($errors)) {
        $class_code = generateClassCode();
        
        while (dbFetch("SELECT id FROM classes WHERE class_code = ?", [$class_code])) {
            $class_code = generateClassCode();
        }
        
        dbExecute("INSERT INTO classes (class_name, description, teacher_id, class_code) VALUES (?, ?, ?, ?)", [$class_name, $description, $user_id, $class_code]);
        $class_id = dbLastInsertId();
        
        if ($class_id) {
            redirect("class_view.php?id=" . encodeId($class_id));
        } else {
            $errors[] = 'Failed to create class. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Class - StudyFlow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="../index.php" class="logo">StudyFlow</a>
            </div>
            <nav class="sidebar-nav">
                <a href="../dashboard_teacher.php" class="nav-item">
                    <span class="nav-icon"></span> Dashboard
                </a>
                <a href="create_class.php" class="nav-item active">
                    <span class="nav-icon"></span> Create Class
                </a>
                <a href="#" class="nav-item" id="viewAllClasses">
                    <span class="nav-icon"></span> My Classes
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="user-name"><?= sanitize($_SESSION['user_name']) ?></span>
                    <span class="user-role">Teacher</span>
                </div>
                <a href="../auth/logout.php" class="btn btn-outline btn-sm">Logout</a>
            </div>
        </aside>
        
        <main class="main-content">
            <header class="content-header">
                <h1>Create New Class</h1>
            </header>
            
            <div class="form-container">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $e): ?>
                        <li><?= sanitize($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="styled-form">
                    <div class="form-group">
                        <label for="class_name">Class Name *</label>
                        <input type="text" id="class_name" name="class_name" required
                               placeholder="e.g., Mathematics 101"
                               value="<?= sanitize($_POST['class_name'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"
                                  placeholder="Enter a description for your class..."><?= sanitize($_POST['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <a href="../dashboard_teacher.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Class</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script src="../assets/js/app.js"></script>
</body>
</html>
