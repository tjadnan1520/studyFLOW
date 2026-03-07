<?php
require_once '../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$class_id = isset($_GET['class_id']) ? decodeId($_GET['class_id']) : 0;
$view = isset($_GET['view']) ? $_GET['view'] : 'summary';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

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

$session_row = dbFetch("SELECT COUNT(DISTINCT date) as total_sessions FROM attendance WHERE class_id = ?", [$class_id]);
$total_sessions = $session_row['total_sessions'];

$student_stats = dbFetchAll("
    SELECT 
        u.id, u.name, u.email,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
        COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_count,
        COUNT(a.id) as total_records
    FROM users u
    JOIN class_members cm ON u.id = cm.student_id
    LEFT JOIN attendance a ON u.id = a.student_id AND a.class_id = cm.class_id
    WHERE cm.class_id = ?
    GROUP BY u.id, u.name, u.email
    ORDER BY u.name
", [$class_id]);

$status_rows = dbFetchAll("
    SELECT status, COUNT(*) as count
    FROM attendance
    WHERE class_id = ?
    GROUP BY status
", [$class_id]);
$status_counts = [];
foreach ($status_rows as $row) {
    $status_counts[$row['status']] = $row['count'];
}

$month_start = $month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));
$month_records = dbFetchAll("
    SELECT a.*, u.name as student_name
    FROM attendance a
    JOIN users u ON a.student_id = u.id
    WHERE a.class_id = ? AND a.date BETWEEN ? AND ?
    ORDER BY a.date DESC, u.name
", [$class_id, $month_start, $month_end]);

$records_by_date = [];
foreach ($month_records as $record) {
    $date = $record['date'];
    if (!isset($records_by_date[$date])) {
        $records_by_date[$date] = [];
    }
    $records_by_date[$date][] = $record;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records - <?= sanitize($class['class_name']) ?></title>
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
                <a href="../classes/class_view.php?id=<?= $encoded_class_id ?>" class="nav-item">
                    <span class="nav-icon"></span> <?= sanitize($class['class_name']) ?>
                </a>
                <a href="take_attendance.php?class_id=<?= $encoded_class_id ?>" class="nav-item">
                    <span class="nav-icon"></span> Take Attendance
                </a>
                <a href="view_attendance.php?class_id=<?= $encoded_class_id ?>" class="nav-item active">
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
        
        <main class="main-content">
            <header class="content-header">
                <div>
                    <a href="../classes/class_view.php?id=<?= $encoded_class_id ?>" class="btn btn-outline btn-sm">Back to Class</a>
                    <h1>Attendance Records</h1>
                    <p class="header-subtitle"><?= sanitize($class['class_name']) ?></p>
                </div>
                <a href="take_attendance.php?class_id=<?= $encoded_class_id ?>" class="btn btn-primary">
                    + Take Attendance
                </a>
            </header>
            
            <div class="stats-grid attendance-stats">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_sessions ?></div>
                    <div class="stat-label">Total Sessions</div>
                </div>
                <div class="stat-card stat-present">
                    <div class="stat-number"><?= $status_counts['present'] ?? 0 ?></div>
                    <div class="stat-label">Present</div>
                </div>
                <div class="stat-card stat-absent">
                    <div class="stat-number"><?= $status_counts['absent'] ?? 0 ?></div>
                    <div class="stat-label">Absent</div>
                </div>
                <div class="stat-card stat-late">
                    <div class="stat-number"><?= $status_counts['late'] ?? 0 ?></div>
                    <div class="stat-label">Late</div>
                </div>
            </div>
            
            <div class="tabs attendance-tabs">
                <a href="?class_id=<?= $encoded_class_id ?>&view=summary" class="tab <?= $view === 'summary' ? 'active' : '' ?>">
                    Student Summary
                </a>
                <a href="?class_id=<?= $encoded_class_id ?>&view=detailed" class="tab <?= $view === 'detailed' ? 'active' : '' ?>">
                    Detailed Records
                </a>
            </div>
            
            <?php if ($view === 'summary'): ?>
            <div class="attendance-summary">
                <?php if (empty($students)): ?>
                <div class="empty-state">
                    <div class="empty-icon">S</div>
                    <h3>No students enrolled</h3>
                    <p>There are no students in this class yet.</p>
                </div>
                <?php else: ?>
                <div class="attendance-table-wrapper">
                    <table class="attendance-table summary-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th class="text-center">Present</th>
                                <th class="text-center">Absent</th>
                                <th class="text-center">Late</th>
                                <th class="text-center">Excused</th>
                                <th class="text-center">Attendance Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_stats as $stat): 
                                $total = $stat['present_count'] + $stat['absent_count'] + $stat['late_count'] + $stat['excused_count'];
                                $rate = $total > 0 ? round(($stat['present_count'] + $stat['late_count']) / $total * 100) : 0;
                                $rate_class = $rate >= 90 ? 'excellent' : ($rate >= 75 ? 'good' : ($rate >= 60 ? 'warning' : 'poor'));
                            ?>
                            <tr>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar"><?= strtoupper(substr($stat['name'], 0, 1)) ?></div>
                                        <div class="student-details">
                                            <strong><?= sanitize($stat['name']) ?></strong>
                                            <span class="student-email"><?= sanitize($stat['email']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="attendance-count present"><?= $stat['present_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="attendance-count absent"><?= $stat['absent_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="attendance-count late"><?= $stat['late_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="attendance-count excused"><?= $stat['excused_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="attendance-rate <?= $rate_class ?>">
                                        <div class="rate-bar">
                                            <div class="rate-fill" style="width: <?= $rate ?>%"></div>
                                        </div>
                                        <span class="rate-text"><?= $rate ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <div class="attendance-detailed">
                <div class="month-selector">
                    <form method="GET" class="month-form">
                        <input type="hidden" name="class_id" value="<?= $class_id ?>">
                        <input type="hidden" name="view" value="detailed">
                        <label for="month">Select Month:</label>
                        <input type="month" id="month" name="month" value="<?= $month ?>" onchange="this.form.submit()">
                    </form>
                </div>
                
                <?php if (empty($records_by_date)): ?>
                <div class="empty-state">
                    <div class="empty-icon">D</div>
                    <h3>No records for this month</h3>
                    <p>No attendance has been recorded for <?= date('F Y', strtotime($month_start)) ?>.</p>
                </div>
                <?php else: ?>
                <div class="records-timeline">
                    <?php foreach ($records_by_date as $date => $records): 
                        $present = count(array_filter($records, fn($r) => $r['status'] === 'present'));
                        $absent = count(array_filter($records, fn($r) => $r['status'] === 'absent'));
                        $late = count(array_filter($records, fn($r) => $r['status'] === 'late'));
                        $excused = count(array_filter($records, fn($r) => $r['status'] === 'excused'));
                    ?>
                    <div class="date-record">
                        <div class="date-header">
                            <div class="date-info">
                                <strong><?= date('l, F j, Y', strtotime($date)) ?></strong>
                                <span class="date-summary">
                                    <?= $present ?> present, <?= $absent ?> absent, <?= $late ?> late, <?= $excused ?> excused
                                </span>
                            </div>
                            <a href="take_attendance.php?class_id=<?= $encoded_class_id ?>&date=<?= $date ?>" class="btn btn-sm btn-outline">Edit</a>
                        </div>
                        <div class="date-students">
                            <?php foreach ($records as $record): ?>
                            <div class="student-record <?= $record['status'] ?>">
                                <span class="student-name"><?= sanitize($record['student_name']) ?></span>
                                <span class="status-badge <?= $record['status'] ?>"><?= ucfirst($record['status']) ?></span>
                                <?php if ($record['notes']): ?>
                                <span class="record-note" title="<?= sanitize($record['notes']) ?>">N</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="export-section">
                <h3>Export Attendance</h3>
                <div class="export-buttons">
                    <a href="export_attendance.php?class_id=<?= $encoded_class_id ?>&format=csv" class="btn btn-outline">
                        Export as CSV
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
