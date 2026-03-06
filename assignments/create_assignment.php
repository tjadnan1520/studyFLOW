<?php
/**
 * Create Assignment Page
 * Allows teachers to create new assignments
 */
require_once '../config/database.php';

// Check authentication
if (!isLoggedIn() || !isTeacher()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$class_id = isset($_GET['class_id']) ? decodeId($_GET['class_id']) : 0;
$encoded_class_id = encodeId($class_id);

// Verify teacher owns the class
$class = dbFetch("SELECT * FROM classes WHERE id = ? AND teacher_id = ?", [$class_id, $user_id]);

if (!$class) {
    redirect('../dashboard_teacher.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = $_POST['due_date'] ?? '';
    $due_time = $_POST['due_time'] ?? '23:59';
    $points = (int)($_POST['points'] ?? 100);
    
    // Validation
    if (empty($title)) {
        $error = 'Assignment title is required.';
    } elseif (empty($due_date)) {
        $error = 'Due date is required.';
    } elseif ($points < 0 || $points > 1000) {
        $error = 'Points must be between 0 and 1000.';
    } else {
        $due_datetime = $due_date . ' ' . $due_time . ':00';
        
        // Handle file upload
        $file_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/assignments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['attachment']['name']);
            $target_path = $upload_dir . $file_name;
            
            // Check file size (max 10MB)
            if ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
                $error = 'File size must be less than 10MB.';
            } else {
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)) {
                    $file_path = 'uploads/assignments/' . $file_name;
                } else {
                    $error = 'Failed to upload file.';
                }
            }
        }
        
        if (!$error) {
            try {
                dbExecute("
                    INSERT INTO assignments (class_id, title, description, due_date, points, file_path, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ", [$class_id, $title, $description, $due_datetime, $points, $file_path]);
                
                $assignment_id = dbLastInsertId();
                redirect('../classes/class_view.php?id=' . $encoded_class_id . '&assignment=' . encodeId($assignment_id));
            } catch (Exception $e) {
                $error = 'Failed to create assignment. Please try again.';
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
    <title>Create Assignment - <?= sanitize($class['class_name']) ?></title>
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
                    <span class="nav-icon"></span> <?= sanitize($class['class_name']) ?>
                </a>
                <a href="create_assignment.php?class_id=<?= $encoded_class_id ?>" class="nav-item active">
                    <span class="nav-icon"></span> New Assignment
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
                    <a href="../classes/class_view.php?id=<?= $encoded_class_id ?>" class="btn btn-outline btn-sm">Cancel</a>
                    <h1>Create Assignment</h1>
                    <p class="header-subtitle"><?= sanitize($class['class_name']) ?></p>
                </div>
            </header>
            
            <?php if ($error): ?>
            <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>
            
            <div class="card">
                <form method="POST" enctype="multipart/form-data" class="form-stacked">
                    <div class="form-group">
                        <label for="title">Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" placeholder="Assignment title" 
                               value="<?= sanitize($_POST['title'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Instructions</label>
                        <textarea id="description" name="description" rows="6" 
                                  placeholder="Add instructions for your students..."><?= sanitize($_POST['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="due_date">Due Date <span class="required">*</span></label>
                            <input type="date" id="due_date" name="due_date" 
                                   value="<?= sanitize($_POST['due_date'] ?? '') ?>" 
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="due_time">Due Time</label>
                            <input type="time" id="due_time" name="due_time" 
                                   value="<?= sanitize($_POST['due_time'] ?? '23:59') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="points">Points</label>
                            <input type="number" id="points" name="points" 
                                   value="<?= sanitize($_POST['points'] ?? '100') ?>" 
                                   min="0" max="1000">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="attachment">Attachment (optional)</label>
                        <input type="file" id="attachment" name="attachment" class="file-input">
                        <p class="form-hint">Maximum file size: 10MB</p>
                    </div>
                    
                    <div class="form-actions">
                        <a href="../classes/class_view.php?id=<?= $encoded_class_id ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Assignment</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
