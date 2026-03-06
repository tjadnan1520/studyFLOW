<?php
/**
 * Get Assignments API
 * Returns assignments for a class or user
 */
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verify authentication via session
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$user = [
    'user_id' => $_SESSION['user_id'],
    'role' => $_SESSION['role']
];

$user_id = $user['user_id'];
$role = $user['role'];

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$upcoming = isset($_GET['upcoming']) && $_GET['upcoming'] === 'true';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

if ($class_id) {
    // Get assignments for a specific class
    // Verify access
    $has_access = false;
    
    if ($role === 'teacher') {
        $class = dbFetch("SELECT id FROM classes WHERE id = ? AND teacher_id = ?", [$class_id, $user_id]);
        $has_access = (bool)$class;
    } else {
        $member = dbFetch("SELECT id FROM class_members WHERE class_id = ? AND student_id = ?", [$class_id, $user_id]);
        $has_access = (bool)$member;
    }
    
    if (!$has_access) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    
    $sql = "SELECT a.*, c.class_name 
            FROM assignments a 
            JOIN classes c ON a.class_id = c.id 
            WHERE a.class_id = ?";
    $params = [$class_id];
    
    if ($upcoming) {
        $sql .= " AND a.due_date >= NOW()";
    }
    
    $sql .= " ORDER BY a.due_date ASC LIMIT ?";
    $params[] = $limit;
    
    $assignments = dbFetchAll($sql, $params);
    
} else {
    // Get all assignments for user's classes
    if ($role === 'teacher') {
        $sql = "SELECT a.*, c.class_name 
                FROM assignments a 
                JOIN classes c ON a.class_id = c.id 
                WHERE c.teacher_id = ?";
        $params = [$user_id];
    } else {
        $sql = "SELECT a.*, c.class_name,
                       (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) as submitted
                FROM assignments a 
                JOIN classes c ON a.class_id = c.id 
                JOIN class_members cm ON c.id = cm.class_id
                WHERE cm.student_id = ?";
        $params = [$user_id, $user_id];
    }
    
    if ($upcoming) {
        $sql .= " AND a.due_date >= NOW()";
    }
    
    $sql .= " ORDER BY a.due_date ASC LIMIT ?";
    $params[] = $limit;
    
    $assignments = dbFetchAll($sql, $params);
}

// Add submission counts for teachers
if ($role === 'teacher') {
    foreach ($assignments as &$assignment) {
        $counts = dbFetch("SELECT COUNT(*) as total, COUNT(grade) as graded FROM submissions WHERE assignment_id = ?", [$assignment['id']]);
        $assignment['submission_count'] = $counts['total'];
        $assignment['graded_count'] = $counts['graded'];
    }
}

echo json_encode([
    'success' => true,
    'data' => $assignments
]);
