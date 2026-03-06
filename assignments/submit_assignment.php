<?php
/**
 * Submit Assignment Page
 * Allows students to submit their work
 */
require_once '../config/database.php';

// Check authentication
if (!isLoggedIn() || !isStudent()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$assignment_id = isset($_GET['id']) ? decodeId($_GET['id']) : 0;

// Get assignment with class info
$assignment = dbFetch("
    SELECT a.*, c.class_name, c.id as class_id
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    WHERE a.id = ?
", [$assignment_id]);

if (!$assignment) {
    redirect('../dashboard_student.php');
}

// Verify student is in the class
$member = dbFetch("SELECT id FROM class_members WHERE class_id = ? AND student_id = ?", [$assignment['class_id'], $user_id]);
if (!$member) {
    redirect('../dashboard_student.php');
}

// Check for existing submission
$existing_submission = dbFetch("SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?", [$assignment_id, $user_id]);

$encoded_assignment_id = encodeId($assignment_id);
$encoded_class_id = encodeId($assignment['class_id']);

if ($existing_submission) {
    redirect('../classes/class_view.php?id=' . $encoded_class_id . '&assignment=' . $encoded_assignment_id);
}

$error = '';
$is_late = new DateTime() > new DateTime($assignment['due_date']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    
    // Handle file upload
    $file_path = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/submissions/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = $user_id . '_' . $assignment_id . '_' . time() . '_' . basename($_FILES['submission_file']['name']);
        $target_path = $upload_dir . $file_name;
        
        // Check file size (max 10MB)
        if ($_FILES['submission_file']['size'] > 10 * 1024 * 1024) {
            $error = 'File size must be less than 10MB.';
        } else {
            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $target_path)) {
                $file_path = 'uploads/submissions/' . $file_name;
            } else {
                $error = 'Failed to upload file.';
            }
        }
    }
    
    if (empty($content) && !$file_path) {
        $error = 'Please provide your work (text or file).';
    }
    
    if (!$error) {
        try {
            dbExecute("
                INSERT INTO submissions (assignment_id, student_id, text_content, file_path, submitted_at)
                VALUES (?, ?, ?, ?, NOW())
            ", [$assignment_id, $user_id, $content, $file_path]);
            
            redirect('../classes/class_view.php?id=' . $encoded_class_id . '&assignment=' . $encoded_assignment_id);
        } catch (Exception $e) {
            $error = 'Failed to submit. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Work - <?= sanitize($assignment['title']) ?></title>
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
                <a href="../dashboard_student.php" class="nav-item">
                    <span class="nav-icon"></span> Dashboard
                </a>
                <a href="../classes/class_view.php?id=<?= $encoded_class_id ?>" class="nav-item">
                    <span class="nav-icon"></span> <?= sanitize($assignment['class_name']) ?>
                </a>
                <a href="assignment_view.php?id=<?= $encoded_assignment_id ?>" class="nav-item">
                    <span class="nav-icon"></span> Assignment
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
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <div>
                    <a href="assignment_view.php?id=<?= $encoded_assignment_id ?>" class="btn btn-outline btn-sm">Back</a>
                    <h1>Submit Your Work</h1>
                    <p class="header-subtitle"><?= sanitize($assignment['title']) ?></p>
                </div>
            </header>
            
            <?php if ($is_late): ?>
            <div class="alert alert-warning">
                This assignment is past due. Your submission will be marked as late.
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>
            
            <!-- Assignment Info -->
            <div class="card assignment-summary">
                <h3><?= sanitize($assignment['title']) ?></h3>
                <div class="assignment-meta">
                    <span><strong>Due:</strong> <?= date('F j, Y \a\t g:i A', strtotime($assignment['due_date'])) ?></span>
                    <span><strong>Points:</strong> <?= $assignment['points'] ?></span>
                </div>
                <?php if ($assignment['description']): ?>
                <div class="assignment-instructions">
                    <strong>Instructions:</strong>
                    <p><?= nl2br(sanitize($assignment['description'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Submission Form -->
            <div class="card">
                <h2>Your Work</h2>
                <form method="POST" enctype="multipart/form-data" class="form-stacked">
                    <div class="form-group">
                        <label for="content">Your Answer</label>
                        <textarea id="content" name="content" rows="8" 
                                  placeholder="Type your answer here..."><?= sanitize($_POST['content'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="submission_file">Or Upload a File</label>
                        <input type="file" id="submission_file" name="submission_file" class="file-input">
                        <p class="form-hint">Maximum file size: 10MB. Accepted formats: PDF, DOC, DOCX, TXT, images, etc.</p>
                    </div>
                    
                    <div class="form-actions">
                        <a href="assignment_view.php?id=<?= $encoded_assignment_id ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
