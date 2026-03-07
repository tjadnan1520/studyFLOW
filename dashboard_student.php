<?php
require_once 'config/database.php';

if (!isLoggedIn() || !isStudent()) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

$classes = dbFetchAll("
    SELECT c.*, u.name as teacher_name,
           (SELECT COUNT(*) FROM assignments WHERE class_id = c.id) as assignment_count
    FROM classes c 
    JOIN class_members cm ON c.id = cm.class_id
    JOIN users u ON c.teacher_id = u.id
    WHERE cm.student_id = ?
    ORDER BY cm.joined_at DESC
", [$user_id]);

$upcoming_assignments = dbFetchAll("
    SELECT a.*, c.class_name, c.id as class_id,
           (SELECT id FROM submissions WHERE assignment_id = a.id AND student_id = ?) as submission_id
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN class_members cm ON c.id = cm.class_id
    WHERE cm.student_id = ? AND a.due_date > NOW()
    ORDER BY a.due_date ASC
    LIMIT 5
", [$user_id, $user_id]);

$recent_grades_count = dbFetch("
    SELECT COUNT(*) as total
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN classes c ON a.class_id = c.id
    WHERE s.student_id = ? AND s.status = 'graded'
", [$user_id])['total'] ?? 0;

$recent_grades = dbFetchAll("
    SELECT s.*, a.title as assignment_title, a.points, c.class_name
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN classes c ON a.class_id = c.id
    WHERE s.student_id = ? AND s.status = 'graded'
    ORDER BY s.graded_at DESC
    LIMIT 5
", [$user_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - StudyFlow</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="logo">StudyFlow</a>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard_student.php" class="nav-item active">
                    <span class="nav-icon"></span> Dashboard
                </a>
                <a href="classes/join_class.php" class="nav-item">
                    <span class="nav-icon"></span> Join Class
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="user-name"><?= sanitize($_SESSION['user_name']) ?></span>
                    <span class="user-role">Student</span>
                </div>
                <a href="auth/logout.php" class="btn btn-outline btn-sm">Logout</a>
            </div>
        </aside>
        
        <main class="main-content">
            <header class="content-header">
                <h1>Welcome back, <?= sanitize($_SESSION['user_name']) ?>!</h1>
                <button class="btn btn-primary" onclick="location.href='classes/join_class.php'">
                    + Join Class
                </button>
            </header>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= count($classes) ?></div>
                    <div class="stat-label">Enrolled Classes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($upcoming_assignments) ?></div>
                    <div class="stat-label">Pending Assignments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $recent_grades_count ?></div>
                    <div class="stat-label">Recent Grades</div>
                </div>
            </div>
            
            <?php if (!empty($upcoming_assignments)): ?>
            <section class="dashboard-section">
                <h2>Upcoming Assignments</h2>
                <div class="assignments-list">
                    <?php foreach ($upcoming_assignments as $assignment): ?>
                    <div class="assignment-card" onclick="location.href='classes/class_view.php?id=<?= encodeId($assignment['class_id']) ?>&assignment=<?= encodeId($assignment['id']) ?>'">
                        <div class="assignment-info">
                            <strong><?= sanitize($assignment['title']) ?></strong>
                            <span class="class-badge"><?= sanitize($assignment['class_name']) ?></span>
                        </div>
                        <div class="assignment-meta">
                            <span class="due-date">Due: <?= date('M j, g:i a', strtotime($assignment['due_date'])) ?></span>
                            <?php if ($assignment['submission_id']): ?>
                            <span class="status-badge submitted">Submitted</span>
                            <?php else: ?>
                            <span class="status-badge pending">Not submitted</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
            <?php if (!empty($recent_grades)): ?>
            <section class="dashboard-section">
                <h2>Recent Grades</h2>
                <div class="grades-list">
                    <?php foreach ($recent_grades as $grade): ?>
                    <div class="grade-card">
                        <div class="grade-info">
                            <strong><?= sanitize($grade['assignment_title']) ?></strong>
                            <span class="class-badge"><?= sanitize($grade['class_name']) ?></span>
                        </div>
                        <div class="grade-score">
                            <span class="score"><?= $grade['grade'] ?>/<?= $grade['points'] ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
            <section class="dashboard-section">
                <h2>Your Classes</h2>
                <?php if (empty($classes)): ?>
                <div class="empty-state">
                    <div class="empty-icon"></div>
                    <h3>No classes yet</h3>
                    <p>Join a class using a class code from your teacher!</p>
                    <a href="classes/join_class.php" class="btn btn-primary">Join Class</a>
                </div>
                <?php else: ?>
                <div class="classes-grid">
                    <?php foreach ($classes as $class): ?>
                    <div class="class-card" onclick="location.href='classes/class_view.php?id=<?= encodeId($class['id']) ?>'">
                        <div class="class-card-header" style="background: linear-gradient(135deg, #2e7d32, #1b5e20);">
                            <h3><?= sanitize($class['class_name']) ?></h3>
                            <span class="teacher-name"><?= sanitize($class['teacher_name']) ?></span>
                        </div>
                        <div class="class-card-body">
                            <p><?= sanitize($class['description'] ?? 'No description') ?></p>
                            <div class="class-stats">
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
