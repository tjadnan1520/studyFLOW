<?php
require_once '../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$class_id = isset($_GET['class_id']) ? decodeId($_GET['class_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$class = dbFetch("SELECT * FROM classes WHERE id = ? AND teacher_id = ?", [$class_id, $user_id]);

if (!$class) {
    redirect('../dashboard_teacher.php');
}

$encoded_class_id = encodeId($class_id);

$students = dbFetchAll("
    SELECT u.id, u.name, u.email 
    FROM users u 
    JOIN class_members cm ON u.id = cm.student_id 
    WHERE cm.class_id = ?
    ORDER BY u.name
", [$class_id]);

$existing_rows = dbFetchAll("
    SELECT student_id, status, notes 
    FROM attendance 
    WHERE class_id = ? AND date = ?
", [$class_id, $date]);
$existing_attendance = [];
foreach ($existing_rows as $row) {
    $existing_attendance[$row['student_id']] = $row;
}

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendance_data = [];
    
    foreach ($students as $student) {
        $status = isset($_POST['status'][$student['id']]) ? $_POST['status'][$student['id']] : 'present';
        $notes = isset($_POST['notes'][$student['id']]) ? trim($_POST['notes'][$student['id']]) : '';
        
        if (!in_array($status, ['present', 'absent', 'late', 'excused'])) {
            $status = 'present';
        }
        
        $attendance_data[] = [
            'student_id' => $student['id'],
            'status' => $status,
            'notes' => $notes
        ];
    }
    
    try {
        dbBeginTransaction();
        
        foreach ($attendance_data as $record) {
            dbExecute("
                INSERT INTO attendance (class_id, student_id, date, status, notes, marked_by)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes), marked_by = VALUES(marked_by)
            ", [
                $class_id,
                $record['student_id'],
                $date,
                $record['status'],
                $record['notes'],
                $user_id
            ]);
        }
        
        dbCommit();
        $message = 'Attendance saved successfully!';
        
        $existing_rows = dbFetchAll("SELECT student_id, status, notes FROM attendance WHERE class_id = ? AND date = ?", [$class_id, $date]);
        $existing_attendance = [];
        foreach ($existing_rows as $row) {
            $existing_attendance[$row['student_id']] = $row;
        }
        
    } catch (Exception $e) {
        dbRollback();
        $error = 'Failed to save attendance. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Attendance - <?= sanitize($class['class_name']) ?></title>
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
                <a href="take_attendance.php?class_id=<?= $encoded_class_id ?>" class="nav-item active">
                    <span class="nav-icon"></span> Take Attendance
                </a>
                <a href="view_attendance.php?class_id=<?= $encoded_class_id ?>" class="nav-item">
                    <span class="nav-icon"></span> View Records
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
                    <a href="../classes/class_view.php?id=<?= $encoded_class_id ?>" class="btn btn-outline btn-sm">Back to Class</a>
                    <h1>Take Attendance</h1>
                    <p class="header-subtitle"><?= sanitize($class['class_name']) ?></p>
                </div>
            </header>
            
            <?php if ($message): ?>
            <div class="alert alert-success"><?= sanitize($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>
            
            <!-- Date Selector -->
            <div class="attendance-controls">
                <form method="GET" class="date-selector-form">
                    <input type="hidden" name="class_id" value="<?= $class_id ?>">
                    <div class="form-group inline">
                        <label for="date">Date:</label>
                        <input type="date" id="date" name="date" value="<?= $date ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
                    </div>
                </form>
                
                <div class="quick-actions">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="markAll('present')">Mark All Present</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="markAll('absent')">Mark All Absent</button>
                </div>
            </div>
            
            <?php if (empty($students)): ?>
            <div class="empty-state">
                <div class="empty-icon">S</div>
                <h3>No students enrolled</h3>
                <p>There are no students in this class yet.</p>
            </div>
            <?php else: ?>
            <form method="POST" class="attendance-form">
                <div class="attendance-table-wrapper">
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th class="student-col">Student</th>
                                <th class="status-col">Status</th>
                                <th class="notes-col">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): 
                                $current_status = isset($existing_attendance[$student['id']]) 
                                    ? $existing_attendance[$student['id']]['status'] 
                                    : 'present';
                                $current_notes = isset($existing_attendance[$student['id']]) 
                                    ? $existing_attendance[$student['id']]['notes'] 
                                    : '';
                            ?>
                            <tr data-student-id="<?= $student['id'] ?>">
                                <td class="student-col">
                                    <div class="student-info">
                                        <div class="student-avatar"><?= strtoupper(substr($student['name'], 0, 1)) ?></div>
                                        <div class="student-details">
                                            <strong><?= sanitize($student['name']) ?></strong>
                                            <span class="student-email"><?= sanitize($student['email']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="status-col">
                                    <div class="status-buttons">
                                        <label class="status-option present <?= $current_status === 'present' ? 'selected' : '' ?>">
                                            <input type="radio" name="status[<?= $student['id'] ?>]" value="present" <?= $current_status === 'present' ? 'checked' : '' ?>>
                                            <span class="status-icon">P</span>
                                            <span class="status-label">Present</span>
                                        </label>
                                        <label class="status-option absent <?= $current_status === 'absent' ? 'selected' : '' ?>">
                                            <input type="radio" name="status[<?= $student['id'] ?>]" value="absent" <?= $current_status === 'absent' ? 'checked' : '' ?>>
                                            <span class="status-icon">A</span>
                                            <span class="status-label">Absent</span>
                                        </label>
                                        <label class="status-option late <?= $current_status === 'late' ? 'selected' : '' ?>">
                                            <input type="radio" name="status[<?= $student['id'] ?>]" value="late" <?= $current_status === 'late' ? 'checked' : '' ?>>
                                            <span class="status-icon">L</span>
                                            <span class="status-label">Late</span>
                                        </label>
                                        <label class="status-option excused <?= $current_status === 'excused' ? 'selected' : '' ?>">
                                            <input type="radio" name="status[<?= $student['id'] ?>]" value="excused" <?= $current_status === 'excused' ? 'checked' : '' ?>>
                                            <span class="status-icon">E</span>
                                            <span class="status-label">Excused</span>
                                        </label>
                                    </div>
                                </td>
                                <td class="notes-col">
                                    <input type="text" name="notes[<?= $student['id'] ?>]" value="<?= sanitize($current_notes) ?>" placeholder="Add note (optional)" class="note-input">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large">
                        Save Attendance
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
    // Mark all students with a specific status
    function markAll(status) {
        const radios = document.querySelectorAll(`input[type="radio"][value="${status}"]`);
        radios.forEach(radio => {
            radio.checked = true;
            updateStatusDisplay(radio);
        });
    }
    
    // Update visual display when status changes
    function updateStatusDisplay(radio) {
        const row = radio.closest('tr');
        const options = row.querySelectorAll('.status-option');
        options.forEach(opt => opt.classList.remove('selected'));
        radio.closest('.status-option').classList.add('selected');
    }
    
    // Add event listeners
    document.querySelectorAll('.status-option input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            updateStatusDisplay(this);
        });
    });
    </script>
</body>
</html>
