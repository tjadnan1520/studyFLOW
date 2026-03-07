<?php
require_once 'config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

$classes = dbFetchAll("
    SELECT c.*, 
           (SELECT COUNT(*) FROM class_members WHERE class_id = c.id) as student_count,
           (SELECT COUNT(*) FROM assignments WHERE class_id = c.id) as assignment_count
    FROM classes c 
    WHERE c.teacher_id = ? 
    ORDER BY c.created_at DESC
", [$user_id]);

$to_grade_count = dbFetch("
    SELECT COUNT(*) as total
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN classes c ON a.class_id = c.id
    WHERE c.teacher_id = ? AND s.status = 'submitted'
", [$user_id])['total'] ?? 0;

$pending_submissions = dbFetchAll("
    SELECT s.*, a.title as assignment_title, u.name as student_name, c.class_name
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN classes c ON a.class_id = c.id
    JOIN users u ON s.student_id = u.id
    WHERE c.teacher_id = ? AND s.status = 'submitted'
    ORDER BY s.submitted_at DESC
    LIMIT 5
", [$user_id]);
?>   
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - StudyFlow</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="logo">StudyFlow</a>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard_teacher.php" class="nav-item active">
                    <span class="nav-icon"></span> Dashboard
                </a>
                <a href="classes/create_class.php" class="nav-item">
                    <span class="nav-icon"></span> Create Class
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="user-name"><?= sanitize($_SESSION['user_name']) ?></span>
                    <span class="user-role">Teacher</span>
                </div>
                <a href="auth/logout.php" class="btn btn-outline btn-sm">Logout</a>
            </div>
        </aside>
        
        <main class="main-content">
            <header class="content-header">
                <h1>Welcome back, <?= sanitize($_SESSION['user_name']) ?>!</h1>
                <button class="btn btn-primary" onclick="location.href='classes/create_class.php'">
                    + Create Class
                </button>
            </header>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= count($classes) ?></div>
                    <div class="stat-label">Active Classes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= array_sum(array_column($classes, 'student_count')) ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $to_grade_count ?></div>
                    <div class="stat-label">To Grade</div>
                </div>
            </div>
            
            <section class="dashboard-section">
                <h2>Your Classes</h2>
                <?php if (empty($classes)): ?>
                <div class="empty-state">
                    <div class="empty-icon"></div>
                    <h3>No classes yet</h3>
                    <p>Create your first class to get started!</p>
                    <a href="classes/create_class.php" class="btn btn-primary">Create Class</a>
                </div>
                <?php else: ?>
                <div class="classes-grid">
                    <?php foreach ($classes as $class): ?>
                    <div class="class-card" onclick="location.href='classes/class_view.php?id=<?= encodeId($class['id']) ?>'">
                        <div class="class-card-header" style="background: linear-gradient(135deg, #2e7d32, #1b5e20);">
                            <h3><?= sanitize($class['class_name']) ?></h3>
                            <span class="class-code">Code: <?= $class['class_code'] ?></span>
                        </div>
                        <div class="class-card-body">
                            <p><?= sanitize($class['description'] ?? 'No description') ?></p>
                            <div class="class-stats">
                                <span><?= $class['student_count'] ?> students</span>
                                <span><?= $class['assignment_count'] ?> assignments</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="assets/js/app.js"></script>
</body>
</html>
