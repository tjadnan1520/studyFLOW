<?php
/**
 * Grade Assignment Page
 * Allows teachers to grade student submissions
 */
require_once '../config/database.php';

// Check authentication
if (!isLoggedIn() || !isTeacher()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$assignment_id = isset($_GET['id']) ? decodeId($_GET['id']) : 0;
$submission_id = isset($_GET['submission']) ? decodeId($_GET['submission']) : 0;

// Get assignment with class info
$assignment = dbFetch("
    SELECT a.*, c.class_name, c.teacher_id
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    WHERE a.id = ? AND c.teacher_id = ?
", [$assignment_id, $user_id]);

if (!$assignment) {
    redirect('../dashboard_teacher.php');
}

$encoded_assignment_id = encodeId($assignment_id);
$encoded_class_id = encodeId($assignment['class_id']);

// Get all submissions
$submissions = dbFetchAll("
    SELECT s.*, u.name as student_name, u.email as student_email
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    WHERE s.assignment_id = ?
    ORDER BY s.submitted_at DESC
", [$assignment_id]);

// Get selected submission
$selected_submission = null;
if ($submission_id) {
    foreach ($submissions as $sub) {
        if ($sub['id'] == $submission_id) {
            $selected_submission = $sub;
            break;
        }
    }
}

if (!$selected_submission && !empty($submissions)) {
    $selected_submission = $submissions[0];
    $submission_id = $selected_submission['id'];
}

$error = '';
$success = '';

// Handle grading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_submission) {
    $grade = isset($_POST['grade']) ? (int)$_POST['grade'] : null;
    $feedback = trim($_POST['feedback'] ?? '');
    
    if ($grade === null || $grade < 0) {
        $error = 'Please enter a valid grade.';
    } elseif ($grade > $assignment['points']) {
        $error = 'Grade cannot exceed maximum points (' . $assignment['points'] . ').';
    } else {
        try {
            dbExecute("UPDATE submissions SET grade = ?, feedback = ? WHERE id = ?", [$grade, $feedback, $submission_id]);
            
            $success = 'Grade saved successfully!';
            
            // Refresh submission data
            $selected_submission = dbFetch("
                SELECT s.*, u.name as student_name, u.email as student_email
                FROM submissions s
                JOIN users u ON s.student_id = u.id
                WHERE s.id = ?
            ", [$submission_id]);
            
            // Refresh all submissions
            $submissions = dbFetchAll("
                SELECT s.*, u.name as student_name, u.email as student_email
                FROM submissions s
                JOIN users u ON s.student_id = u.id
                WHERE s.assignment_id = ?
                ORDER BY s.submitted_at DESC
            ", [$assignment_id]);
            
        } catch (Exception $e) {
            $error = 'Failed to save grade. Please try again.';
        }
    }
}

// Stats
$graded_count = count(array_filter($submissions, fn($s) => $s['grade'] !== null));
$total_count = count($submissions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Assignment - <?= sanitize($assignment['title']) ?></title>
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
                <a href="../dashboard_teacher.php" class="nav-item">
                    <span class="nav-icon"></span> Dashboard
                </a>
                <a href="../classes/class_view.php?id=<?= $encoded_class_id ?>" class="nav-item">
                    <span class="nav-icon"></span> <?= sanitize($assignment['class_name']) ?>
                </a>
                <a href="assignment_view.php?id=<?= $encoded_assignment_id ?>" class="nav-item">
                    <span class="nav-icon"></span> Assignment
                </a>
                <a href="grade_assignment.php?id=<?= $encoded_assignment_id ?>" class="nav-item active">
                    <span class="nav-icon"></span> Grade
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
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <div>
                    <a href="assignment_view.php?id=<?= $encoded_assignment_id ?>" class="btn btn-outline btn-sm">Back</a>
                    <h1>Grade Submissions</h1>
                    <p class="header-subtitle"><?= sanitize($assignment['title']) ?> - <?= $graded_count ?>/<?= $total_count ?> graded</p>
                </div>
            </header>
            
            <?php if ($success): ?>
            <div class="alert alert-success"><?= sanitize($success) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>
            
            <?php if (empty($submissions)): ?>
            <div class="empty-state">
                <div class="empty-icon">S</div>
                <h3>No submissions yet</h3>
                <p>Students haven't submitted their work yet.</p>
                <a href="assignment_view.php?id=<?= $encoded_assignment_id ?>" class="btn btn-primary">Back to Assignment</a>
            </div>
            <?php else: ?>
            <div class="grading-layout">
                <!-- Student List -->
                <div class="student-list-panel">
                    <h3>Students (<?= $total_count ?>)</h3>
                    <div class="student-list">
                        <?php foreach ($submissions as $sub): ?>
                        <a href="?id=<?= $encoded_assignment_id ?>&submission=<?= encodeId($sub['id']) ?>" 
                           class="student-list-item <?= $sub['id'] == $submission_id ? 'active' : '' ?>">
                            <div class="student-avatar"><?= strtoupper(substr($sub['student_name'], 0, 1)) ?></div>
                            <div class="student-info">
                                <strong><?= sanitize($sub['student_name']) ?></strong>
                                <span class="submit-time"><?= date('M j, g:i A', strtotime($sub['submitted_at'])) ?></span>
                            </div>
                            <?php if ($sub['grade'] !== null): ?>
                            <span class="grade-badge small"><?= $sub['grade'] ?></span>
                            <?php else: ?>
                            <span class="status-dot pending"></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Grading Panel -->
                <div class="grading-panel">
                    <?php if ($selected_submission): ?>
                    <div class="submission-header">
                        <div class="student-info large">
                            <div class="student-avatar large"><?= strtoupper(substr($selected_submission['student_name'], 0, 1)) ?></div>
                            <div>
                                <h2><?= sanitize($selected_submission['student_name']) ?></h2>
                                <span class="student-email"><?= sanitize($selected_submission['student_email']) ?></span>
                            </div>
                        </div>
                        <div class="submission-meta">
                            <span>Submitted: <?= date('F j, Y \a\t g:i A', strtotime($selected_submission['submitted_at'])) ?></span>
                        </div>
                    </div>
                    
                    <div class="submission-content-view">
                        <h3>Student Work</h3>
                        
                        <?php if (!empty($selected_submission['text_content'])): ?>
                        <div class="content-box">
                            <?= nl2br(sanitize($selected_submission['text_content'])) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($selected_submission['file_path'])): ?>
                        <div class="submission-file">
                            <a href="../<?= sanitize($selected_submission['file_path']) ?>" class="attachment-link" download>
                                <span class="icon"></span>
                                Download Submission
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($selected_submission['text_content']) && empty($selected_submission['file_path'])): ?>
                        <p class="text-muted">No content submitted.</p>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" class="grading-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="grade">Grade (out of <?= $assignment['points'] ?>)</label>
                                <input type="number" id="grade" name="grade" 
                                       value="<?= $selected_submission['grade'] ?? '' ?>" 
                                       min="0" max="<?= $assignment['points'] ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="feedback">Feedback (optional)</label>
                            <textarea id="feedback" name="feedback" rows="4" 
                                      placeholder="Add feedback for the student..."><?= sanitize($selected_submission['feedback'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Grade</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
