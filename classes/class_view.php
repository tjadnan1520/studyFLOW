<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$class_id = isset($_GET['id']) ? decodeId($_GET['id']) : 0;

if (!$class_id) {
    redirect(isTeacher() ? '../dashboard_teacher.php' : '../dashboard_student.php');
}

$encoded_class_id = encodeId($class_id);
$user_id = $_SESSION['user_id'];

$class = dbFetch("
    SELECT c.*, u.name as teacher_name 
    FROM classes c 
    JOIN users u ON c.teacher_id = u.id 
    WHERE c.id = ?
", [$class_id]);

if (!$class) {
    redirect(isTeacher() ? '../dashboard_teacher.php' : '../dashboard_student.php');
}

$has_access = false;
$is_owner = false;
if (isTeacher() && $class['teacher_id'] == $user_id) {
    $has_access = true;
    $is_owner = true;
} elseif (isStudent()) {
    $member = dbFetch("SELECT id FROM class_members WHERE class_id = ? AND student_id = ?", [$class_id, $user_id]);
    if ($member) {
        $has_access = true;
        $is_owner = false;
    }
}

if (!$has_access) {
    redirect(isTeacher() ? '../dashboard_teacher.php' : '../dashboard_student.php');
}

$students = dbFetchAll("
    SELECT u.id, u.name, u.email, cm.joined_at 
    FROM users u 
    JOIN class_members cm ON u.id = cm.student_id 
    WHERE cm.class_id = ?
    ORDER BY u.name
", [$class_id]);

$assignments = dbFetchAll("
    SELECT a.*,
           (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submission_count
    FROM assignments a 
    WHERE a.class_id = ?
    ORDER BY a.created_at DESC
", [$class_id]);

$announcements = dbFetchAll("
    SELECT an.*, u.name as author_name 
    FROM announcements an 
    JOIN users u ON an.user_id = u.id 
    WHERE an.class_id = ?
    ORDER BY an.created_at DESC
    LIMIT 10
", [$class_id]);

$selected_assignment = null;
$student_submission = null;
$submission_error = '';
$assignment_submissions = [];
$selected_submission = null;
$grade_success = '';
$grade_error = '';

if (isset($_GET['assignment'])) {
    $selected_assignment_id = decodeId($_GET['assignment']);
    $selected_assignment = dbFetch("
        SELECT a.* FROM assignments a WHERE a.id = ? AND a.class_id = ?
    ", [$selected_assignment_id, $class_id]);
    
    if ($selected_assignment) {
        if (!$is_owner) {
            $student_submission = dbFetch("
                SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?
            ", [$selected_assignment_id, $user_id]);
        } else {
            $assignment_submissions = dbFetchAll("
                SELECT s.*, u.name as student_name, u.email as student_email
                FROM submissions s
                JOIN users u ON s.student_id = u.id
                WHERE s.assignment_id = ?
                ORDER BY s.submitted_at DESC
            ", [$selected_assignment_id]);
            
            if (isset($_GET['submission'])) {
                $selected_submission_id = decodeId($_GET['submission']);
                $selected_submission = dbFetch("
                    SELECT s.*, u.name as student_name, u.email as student_email
                    FROM submissions s
                    JOIN users u ON s.student_id = u.id
                    WHERE s.id = ? AND s.assignment_id = ?
                ", [$selected_submission_id, $selected_assignment_id]);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission']) && $is_owner) {
    $grade_submission_id = (int)$_POST['submission_id'];
    $grade_assignment_id = (int)$_POST['assignment_id'];
    $grade = isset($_POST['grade']) ? (int)$_POST['grade'] : null;
    $feedback = trim($_POST['feedback'] ?? '');
    
    // Verify submission belongs to this assignment
    $verify_sub = dbFetch("SELECT s.id, a.points FROM submissions s JOIN assignments a ON s.assignment_id = a.id WHERE s.id = ? AND a.id = ? AND a.class_id = ?", [$grade_submission_id, $grade_assignment_id, $class_id]);
    
    if ($verify_sub) {
        if ($grade === null || $grade < 0) {
            $grade_error = 'Please enter a valid grade.';
        } elseif ($grade > $verify_sub['points']) {
            $grade_error = 'Grade cannot exceed maximum points (' . $verify_sub['points'] . ').';
        } else {
            dbExecute("UPDATE submissions SET grade = ?, feedback = ? WHERE id = ?", [$grade, $feedback, $grade_submission_id]);
            $grade_success = 'Grade saved successfully!';
            
            $selected_submission = dbFetch("
                SELECT s.*, u.name as student_name, u.email as student_email
                FROM submissions s
                JOIN users u ON s.student_id = u.id
                WHERE s.id = ?
            ", [$grade_submission_id]);
            
            $assignment_submissions = dbFetchAll("
                SELECT s.*, u.name as student_name, u.email as student_email
                FROM submissions s
                JOIN users u ON s.student_id = u.id
                WHERE s.assignment_id = ?
                ORDER BY s.submitted_at DESC
            ", [$grade_assignment_id]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_work']) && !$is_owner) {
    $submit_assignment_id = (int)$_POST['assignment_id'];
    $content = trim($_POST['text_content'] ?? '');
    
    // Verify assignment belongs to this class
    $submit_assignment = dbFetch("SELECT id FROM assignments WHERE id = ? AND class_id = ?", [$submit_assignment_id, $class_id]);
    
    if ($submit_assignment) {
        $existing = dbFetch("SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?", [$submit_assignment_id, $user_id]);
        
        if (!$existing) {
            $file_name = null;
            $file_type = null;
            $file_content = null;
            
            if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['submission_file']['size'] <= 10 * 1024 * 1024) {
                    $file_name = basename($_FILES['submission_file']['name']);
                    $file_type = $_FILES['submission_file']['type'];
                    $file_content = file_get_contents($_FILES['submission_file']['tmp_name']);
                }
            }
            
            if (!empty($content) || $file_content) {
                dbExecute("
                    INSERT INTO submissions (assignment_id, student_id, text_content, file_name, file_type, file_content, submitted_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ", [$submit_assignment_id, $user_id, $content, $file_name, $file_type, $file_content]);
                
                redirect("class_view.php?id=" . $encoded_class_id . "&assignment=" . encodeId($submit_assignment_id));
            } else {
                $submission_error = 'Please provide your work (text or file).';
            }
        }
    }
}

$announcement_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement'])) {
    $content = sanitize($_POST['content'] ?? '');
    if (!empty($content)) {
        dbExecute("INSERT INTO announcements (class_id, user_id, content) VALUES (?, ?, ?)", [$class_id, $user_id, $content]);
        redirect("class_view.php?id=" . $encoded_class_id);
    }
}

$today_stats = [];
$recent_dates = [];
if ($is_owner) {
    $today = date('Y-m-d');
    $stats = dbFetchAll("
        SELECT a.status, COUNT(*) as count
        FROM attendance a
        WHERE a.class_id = ? AND a.date = ?
        GROUP BY a.status
    ", [$class_id, $today]);
    foreach ($stats as $row) {
        $today_stats[$row['status']] = $row['count'];
    }
    
    $dates = dbFetchAll("
        SELECT DISTINCT date 
        FROM attendance 
        WHERE class_id = ? 
        ORDER BY date DESC 
        LIMIT 5
    ", [$class_id]);
    $recent_dates = array_column($dates, 'date');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($class['class_name']) ?> - StudyFlow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="../index.php" class="logo">StudyFlow</a>
            </div>
            <nav class="sidebar-nav">
                <a href="<?= $is_owner ? '../dashboard_teacher.php' : '../dashboard_student.php' ?>" class="nav-item">
                    <span class="nav-icon"></span> Dashboard
                </a>
                <a href="class_view.php?id=<?= $encoded_class_id ?>" class="nav-item active">
                    <span class="nav-icon"></span> <?= sanitize($class['class_name']) ?>
                </a>
                <?php if ($is_owner): ?>
                <a href="../assignments/create_assignment.php?class_id=<?= $encoded_class_id ?>" class="nav-item">
                    <span class="nav-icon"></span> New Assignment
                </a>
                <a href="../attendance/take_attendance.php?class_id=<?= $encoded_class_id ?>" class="nav-item">
                    <span class="nav-icon"></span> Take Attendance
                </a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="user-name"><?= sanitize($_SESSION['user_name']) ?></span>
                    <span class="user-role"><?= ucfirst($_SESSION['role']) ?></span>
                </div>
                <a href="../auth/logout.php" class="btn btn-outline btn-sm">Logout</a>
            </div>
        </aside>
        
        <main class="main-content">
            <header class="content-header">
                <div>
                    <h1><?= sanitize($class['class_name']) ?></h1>
                    <p class="header-subtitle">Teacher: <?= sanitize($class['teacher_name']) ?> | Code: <strong><?= $class['class_code'] ?></strong></p>
                </div>
                <?php if ($is_owner): ?>
                <div class="header-actions">
                    <a href="../assignments/create_assignment.php?class_id=<?= $encoded_class_id ?>" class="btn btn-primary">+ New Assignment</a>
                    <a href="delete_class.php?id=<?= $encoded_class_id ?>" class="btn btn-outline" onclick="return confirm('Are you sure you want to delete this class?')">Delete Class</a>
                </div>
                <?php endif; ?>
            </header>
            
            <!-- Class Description -->
            <?php if (!empty($class['description'])): ?>
            <div class="card">
                <p><?= nl2br(sanitize($class['description'])) ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= count($students) ?></div>
                    <div class="stat-label">Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($assignments) ?></div>
                    <div class="stat-label">Assignments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($announcements) ?></div>
                    <div class="stat-label">Announcements</div>
                </div>
            </div>
            
            <!-- Post Announcement (Owner only) -->
            <?php if ($is_owner): ?>
            <section class="dashboard-section">
                <h2>Post Announcement</h2>
                <form method="POST" class="announcement-form">
                    <input type="hidden" name="announcement" value="1">
                    <div class="form-group">
                        <textarea name="content" rows="3" placeholder="Share something with your class..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Post</button>
                </form>
            </section>
            <?php endif; ?>
            
            <!-- Announcements -->
            <?php if (!empty($announcements)): ?>
            <section class="dashboard-section">
                <h2>Announcements</h2>
                <div class="announcements-list">
                    <?php foreach ($announcements as $ann): ?>
                    <div class="announcement-card">
                        <div class="announcement-header">
                            <strong><?= sanitize($ann['author_name']) ?></strong>
                            <span class="announcement-time"><?= date('M j, Y g:i a', strtotime($ann['created_at'])) ?></span>
                        </div>
                        <div class="announcement-content">
                            <?= nl2br(sanitize($ann['content'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- Selected Assignment View -->
            <?php if ($selected_assignment): ?>
            <section class="dashboard-section assignment-detail-section">
                <div class="assignment-detail-header">
                    <a href="class_view.php?id=<?= $encoded_class_id ?>" class="btn btn-outline">&larr; Back to Class</a>
                    <h2> <?= sanitize($selected_assignment['title']) ?></h2>
                </div>
                
                <div class="assignment-detail-card">
                    <h3> Assignment Details</h3>
                    <div class="assignment-detail-content">
                        <div class="assignment-detail-meta">
                            <span class="points-badge"> <?= $selected_assignment['points'] ?> points</span>
                            <span class="due-date"> Due: <?= date('M j, Y g:i a', strtotime($selected_assignment['due_date'])) ?></span>
                            <?php if (new DateTime() > new DateTime($selected_assignment['due_date'])): ?>
                            <span class="late-badge"> Past Due</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($selected_assignment['description'])): ?>
                        <div class="assignment-description">
                            <?= nl2br(sanitize($selected_assignment['description'])) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($selected_assignment['file_name'])): ?>
                        <div class="assignment-attachment">
                            <a href="../api/download_file.php?type=assignment&id=<?= encodeId($selected_assignment['id']) ?>" target="_blank" class="btn btn-outline">
                                📎 <?= sanitize($selected_assignment['file_name']) ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!$is_owner): ?>
                <div class="submit-work-section">
                    <?php if ($student_submission): ?>
                    <h3>Your Submission</h3>
                    <div class="submission-status-content">
                        <p class="submit-time"> Submitted: <?= date('M j, Y g:i a', strtotime($student_submission['submitted_at'])) ?></p>
                        
                        <?php if (!empty($student_submission['text_content'])): ?>
                        <div class="submission-content">
                            <?= nl2br(sanitize($student_submission['text_content'])) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($student_submission['file_name'])): ?>
                        <p><a href="../api/download_file.php?type=submission&id=<?= encodeId($student_submission['id']) ?>" target="_blank"> <?= sanitize($student_submission['file_name']) ?></a></p>
                        <?php endif; ?>
                        
                        <?php if ($student_submission['grade'] !== null): ?>
                        <div class="grade-display">
                            <strong> Grade: <?= $student_submission['grade'] ?>/<?= $selected_assignment['points'] ?></strong>
                            <?php if (!empty($student_submission['feedback'])): ?>
                            <p class="feedback"><?= nl2br(sanitize($student_submission['feedback'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <p class="pending-grade"> Awaiting grade...</p>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <h3><i class="icon"></i> Submit Your Work</h3>
                        <?php if ($submission_error): ?>
                        <div class="alert alert-error"><?= sanitize($submission_error) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" class="submission-form">
                            <input type="hidden" name="submit_work" value="1">
                            <input type="hidden" name="assignment_id" value="<?= $selected_assignment['id'] ?>">
                            
                            <div class="form-group">
                                <label for="text_content">Your Answer</label>
                                <textarea id="text_content" name="text_content" rows="6" 
                                          placeholder="Type your answer here..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="submission_file">Or Upload a File (optional)</label>
                                <input type="file" id="submission_file" name="submission_file">
                                <small>Max file size: 10MB</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Submit Work</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="submissions-section">
                    <h3> Submissions (<?= count($assignment_submissions) ?>)</h3>
                    <?php if (empty($assignment_submissions)): ?>
                    <div class="no-submissions">
                        <p>No submissions yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="submissions-list">
                        <?php foreach ($assignment_submissions as $sub): ?>
                        <div class="submission-item <?= ($selected_submission && $selected_submission['id'] == $sub['id']) ? 'active' : '' ?>">
                            <div class="submission-info">
                                <h4><?= sanitize($sub['student_name']) ?></h4>
                                <span class="submit-time"><?= date('M j, g:i a', strtotime($sub['submitted_at'])) ?></span>
                            </div>
                            <div class="submission-actions">
                                <?php if ($sub['grade'] !== null): ?>
                                <span class="graded-badge">✓ <?= $sub['grade'] ?>/<?= $selected_assignment['points'] ?></span>
                                <?php else: ?>
                                <span class="pending-badge">⏳ Pending</span>
                                <?php endif; ?>
                                <a href="class_view.php?id=<?= $encoded_class_id ?>&assignment=<?= encodeId($selected_assignment['id']) ?>&submission=<?= encodeId($sub['id']) ?>" class="btn-grade">Grade</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($selected_submission): ?>
                <div class="grading-section">
                    <h3>Grade Submission</h3>
                    <div class="grading-content">
                        <?php if ($grade_success): ?>
                        <div class="alert alert-success"><?= $grade_success ?></div>
                        <?php endif; ?>
                        <?php if ($grade_error): ?>
                        <div class="alert alert-error"><?= $grade_error ?></div>
                        <?php endif; ?>
                        
                        <div class="student-info-card">
                            <h4>👤 <?= sanitize($selected_submission['student_name']) ?></h4>
                            <p class="submit-time"> Submitted: <?= date('M j, Y g:i a', strtotime($selected_submission['submitted_at'])) ?></p>
                        </div>
                        
                        <?php if (!empty($selected_submission['text_content'])): ?>
                        <div class="submission-content-view">
                            <h5>Student's Answer:</h5>
                            <div class="content-box">
                                <?= nl2br(sanitize($selected_submission['text_content'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($selected_submission['file_name'])): ?>
                        <div class="submission-file">
                            <h5>Uploaded File:</h5>
                            <a href="../api/download_file.php?type=submission&id=<?= encodeId($selected_submission['id']) ?>" target="_blank" class="btn btn-outline"><?= sanitize($selected_submission['file_name']) ?></a>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" class="grading-form">
                            <input type="hidden" name="grade_submission" value="1">
                            <input type="hidden" name="submission_id" value="<?= $selected_submission['id'] ?>">
                            <input type="hidden" name="assignment_id" value="<?= $selected_assignment['id'] ?>">
                            
                            <div class="form-group">
                                <label for="grade">Grade (out of <?= $selected_assignment['points'] ?>)</label>
                                <input type="number" id="grade" name="grade" min="0" max="<?= $selected_assignment['points'] ?>" 
                                       value="<?= $selected_submission['grade'] ?? '' ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="feedback">Feedback (optional)</label>
                                <textarea id="feedback" name="feedback" rows="4" placeholder="Provide feedback to the student..."><?= sanitize($selected_submission['feedback'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="grading-actions">
                                <button type="submit" class="btn btn-primary">Save Grade</button>
                                <a href="class_view.php?id=<?= $encoded_class_id ?>&assignment=<?= encodeId($selected_assignment['id']) ?>" class="btn btn-outline">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </section>
            <?php else: ?>
            
            <section class="dashboard-section">
                <h2>Assignments</h2>
                <?php if (empty($assignments)): ?>
                <div class="empty-state">
                    <div class="empty-icon"></div>
                    <h3>No assignments yet</h3>
                    <?php if ($is_owner): ?>
                    <p>Create your first assignment for this class.</p>
                    <a href="../assignments/create_assignment.php?class_id=<?= $encoded_class_id ?>" class="btn btn-primary">Create Assignment</a>
                    <?php else: ?>
                    <p>Your teacher hasn't posted any assignments yet.</p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="assignments-list">
                    <?php foreach ($assignments as $assignment): ?>
                    <?php 
                        $has_submitted = false;
                        if (!$is_owner) {
                            $sub_check = dbFetch("SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?", [$assignment['id'], $user_id]);
                            $has_submitted = (bool)$sub_check;
                        }
                    ?>
                    <div class="assignment-card" onclick="location.href='class_view.php?id=<?= $encoded_class_id ?>&assignment=<?= encodeId($assignment['id']) ?>'">
                        <div class="assignment-info">
                            <strong><?= sanitize($assignment['title']) ?></strong>
                            <span class="assignment-points"><?= $assignment['points'] ?> points</span>
                        </div>
                        <div class="assignment-meta">
                            <span class="due-date">Due: <?= date('M j, Y g:i a', strtotime($assignment['due_date'])) ?></span>
                            <?php if ($is_owner): ?>
                            <span class="submission-count"><?= $assignment['submission_count'] ?> submissions</span>
                            <?php elseif ($has_submitted): ?>
                            <span class="submitted-badge">Submitted</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            
            <section class="dashboard-section">
                <h2>Students (<?= count($students) ?>)</h2>
                <?php if (empty($students)): ?>
                <div class="empty-state">
                    <div class="empty-icon"></div>
                    <h3>No students yet</h3>
                    <?php if ($is_owner): ?>
                    <p>Share the class code <strong><?= $class['class_code'] ?></strong> with your students so they can join.</p>
                    <?php else: ?>
                    <p>No other students have joined this class yet.</p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="students-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <?php if ($is_owner): ?>
                                <th>Email</th>
                                <?php endif; ?>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= sanitize($student['name']) ?></td>
                                <?php if ($is_owner): ?>
                                <td><?= sanitize($student['email']) ?></td>
                                <?php endif; ?>
                                <td><?= date('M j, Y', strtotime($student['joined_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>
            
            <?php if ($is_owner): ?>
            <section class="dashboard-section">
                <h2>Attendance</h2>
                <div class="attendance-actions">
                    <a href="../attendance/take_attendance.php?class_id=<?= $encoded_class_id ?>" class="btn btn-primary">Take Attendance</a>
                    <a href="../attendance/view_attendance.php?class_id=<?= $encoded_class_id ?>" class="btn btn-outline">View Records</a>
                </div>
                <?php if (!empty($recent_dates)): ?>
                <p>Recent attendance taken: <?= implode(', ', array_map(fn($d) => date('M j', strtotime($d)), $recent_dates)) ?></p>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </main>
    </div>
    <script src="../assets/js/app.js"></script>
</body>
</html>
