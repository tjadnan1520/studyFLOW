<?php
require_once '../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$class_id = isset($_GET['class_id']) ? decodeId($_GET['class_id']) : 0;

$class = dbFetch("SELECT * FROM classes WHERE id = ? AND teacher_id = ?", [$class_id, $user_id]);

if (!$class) {
    redirect('../dashboard_teacher.php');
}

$records = dbFetchAll("
    SELECT a.date, u.name as student_name, u.email, a.status, a.notes
    FROM attendance a
    JOIN users u ON a.student_id = u.id
    WHERE a.class_id = ?
    ORDER BY a.date DESC, u.name
", [$class_id]);

$filename = preg_replace('/[^a-zA-Z0-9]/', '_', $class['class_name']) . '_attendance_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['Date', 'Student Name', 'Email', 'Status', 'Notes']);

foreach ($records as $record) {
    fputcsv($output, [
        $record['date'],
        $record['student_name'],
        $record['email'],
        ucfirst($record['status']),
        $record['notes']
    ]);
}

fclose($output);
exit;
