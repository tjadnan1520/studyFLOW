<?php
/**
 * Assignment View Page
 * Shows assignment details and submissions
 */
require_once '../config/database.php';

// Check authentication
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$assignment_id = isset($_GET['id']) ? decodeId($_GET['id']) : 0;

// Get assignment with class info
$assignment = dbFetch("
    SELECT a.*, c.class_name, c.teacher_id
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    WHERE a.id = ?
", [$assignment_id]);

if (!$assignment) {
    redirect('../dashboard_' . ($_SESSION['role'] === 'teacher' ? 'teacher' : 'student') . '.php');
}

$class_id = $assignment['class_id'];
$is_teacher = $assignment['teacher_id'] == $user_id;
$encoded_assignment_id = encodeId($assignment_id);
$encoded_class_id = encodeId($class_id);

// Verify access
if (!$is_teacher) {
    $member = dbFetch("SELECT id FROM class_members WHERE class_id = ? AND student_id = ?", [$class_id, $user_id]);
    if (!$member) {
        redirect('../dashboard_student.php');
    }
}

// For teachers: get submissions
$submissions = [];
if ($is_teacher) {
    $submissions = dbFetchAll("
        SELECT s.*, u.name as student_name, u.email as student_email
        FROM submissions s
        JOIN users u ON s.student_id = u.id
        WHERE s.assignment_id = ?
        ORDER BY s.submitted_at DESC
    ", [$assignment_id]);
}

// For students: get their submission
$my_submission = null;
if (!$is_teacher) {
    $my_submission = dbFetch("SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?", [$assignment_id, $user_id]);
}

// Calculate status
$now = new DateTime();
$due = new DateTime($assignment['due_date']);
$is_overdue = $now > $due;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($assignment['title']) ?> - StudyFlow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="../index.php" class="logo">StudyFlow</a>
            </div>
            <nav class="sidebar-nav">
                <a href="../dashboard_<?= $is_teacher ? 'teacher' : 'student' ?>.php" class="nav-item">
                    <span class="nav-icon"></span> Dashboard
                </a>
                <a href="../classes/class_view.php?id=<?= $encoded_class_id ?>" class="nav-item">
                    <span class="nav-icon"></span> <?= sanitize($assignment['class_name']) ?>
                </a>
                <a href="assignment_view.php?id=<?= $encoded_assignment_id ?>" class="nav-item active">
                    <span class="nav-icon"></span> Assignment
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="user-name"><?= sanitize($_SESSION['user_name']) ?></span>
                    <span class="user-role"><?= ucfirst($_SESSION['role']) ?></span>
                </div>
                <a href="../auth/logout.php" class="btn btn-outline btn-sm">Logout</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <div>
                    <a href="../classes/class_view.php?id=<?= $encoded_class_id ?>" class="btn btn-outline btn-sm">Back to Class</a>
                    <h1><?= sanitize($assignment['title']) ?></h1>
                    <p class="header-subtitle"><?= sanitize($assignment['class_name']) ?></p>
                </div>
                <?php if ($is_teacher): ?>
                <a href="grade_assignment.php?id=<?= $encoded_assignment_id ?>" class="btn btn-primary">Grade Submissions</a>
                <?php elseif (!$my_submission): ?>
                <a href="submit_assignment.php?id=<?= $encoded_assignment_id ?>" class="btn btn-primary">Submit Work</a>
                <?php endif; ?>
            </header>
            
            <!-- Assignment Details -->
            <div class="card assignment-details">
                <div class="assignment-meta">
                    <div class="meta-item">
                        <strong>Due Date:</strong>
                        <span class="<?= $is_overdue ? 'text-danger' : '' ?>">
                            <?= date('F j, Y \a\t g:i A', strtotime($assignment['due_date'])) ?>
                            <?= $is_overdue ? '(Overdue)' : '' ?>
                        </span>
                    </div>
                    <div class="meta-item">
                        <strong>Points:</strong>
                        <span><?= $assignment['points'] ?></span>
                    </div>
                    <div class="meta-item">
                        <strong>Posted:</strong>
                        <span><?= date('F j, Y', strtotime($assignment['created_at'])) ?></span>
                    </div>
                </div>
                
                <div class="assignment-description">
                    <h3>Instructions</h3>
                    <div class="description-content">
                        <?= nl2br(sanitize($assignment['description'])) ?>
                    </div>
                </div>
                
                <?php if ($assignment['file_path']): ?>
                <div class="assignment-attachments">
                    <h3>Attachments</h3>
                    <a href="../<?= sanitize($assignment['file_path']) ?>" class="attachment-link" download>
                        <span class="icon">F</span>
                        Download Attachment
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($is_teacher): ?>
            <!-- Teacher: Submissions List -->
            <div class="card submissions-section">
                <h2>Submissions (<?= count($submissions) ?>)</h2>
                
                <?php if (empty($submissions)): ?>
                <div class="empty-state">
                    <div class="empty-icon">S</div>
                    <h3>No submissions yet</h3>
                    <p>Students haven't submitted their work yet.</p>
                </div>
                <?php else: ?>
                <div class="submissions-list">
                    <?php foreach ($submissions as $sub): ?>
                    <div class="submission-item">
                        <div class="student-info">
                            <div class="student-avatar"><?= strtoupper(substr($sub['student_name'], 0, 1)) ?></div>
                            <div class="student-details">
                                <strong><?= sanitize($sub['student_name']) ?></strong>
                                <span class="submit-time">Submitted <?= date('M j, g:i A', strtotime($sub['submitted_at'])) ?></span>
                            </div>
                        </div>
                        <div class="submission-status">
                            <?php if ($sub['grade'] !== null): ?>
                            <span class="grade-badge"><?= $sub['grade'] ?>/<?= $assignment['points'] ?></span>
                            <?php else: ?>
                            <span class="status-badge pending">Needs grading</span>
                            <?php endif; ?>
                        </div>
                        <a href="grade_assignment.php?id=<?= $encoded_assignment_id ?>&submission=<?= encodeId($sub['id']) ?>" class="btn btn-sm btn-outline">View</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <!-- Student: My Submission -->
            <div class="card my-submission-section">
                <h2>Your Work</h2>
                
                <?php if ($my_submission): ?>
                <div class="submission-details">
                    <div class="submission-meta">
                        <p><strong>Submitted:</strong> <?= date('F j, Y \a\t g:i A', strtotime($my_submission['submitted_at'])) ?></p>
                        <?php if ($my_submission['grade'] !== null): ?>
                        <p><strong>Grade:</strong> <span class="grade-badge large"><?= $my_submission['grade'] ?>/<?= $assignment['points'] ?></span></p>
                        <?php if ($my_submission['feedback']): ?>
                        <div class="feedback-section">
                            <strong>Feedback:</strong>
                            <div class="feedback-content"><?= nl2br(sanitize($my_submission['feedback'])) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <p class="status-badge pending">Waiting for grade</p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($my_submission['text_content'])): ?>
                    <div class="submission-content">
                        <h4>Your answer:</h4>
                        <div class="content-box"><?= nl2br(sanitize($my_submission['text_content'])) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($my_submission['file_path']): ?>
                    <div class="submission-file">
                        <a href="../<?= sanitize($my_submission['file_path']) ?>" class="attachment-link" download>
                            <span class="icon">F</span>
                            View your submission
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="not-submitted">
                    <?php if ($is_overdue): ?>
                    <div class="alert alert-warning">
                        This assignment is past due. You can still submit but it may be marked as late.
                    </div>
                    <?php endif; ?>
                    <p>You haven't submitted your work yet.</p>
                    <a href="submit_assignment.php?id=<?= $encoded_assignment_id ?>" class="btn btn-primary">Submit Work</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
