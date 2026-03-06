<?php
/**
 * Get Classes API
 * Returns classes for the authenticated user
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

$class_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($class_id) {
    // Get specific class
    $class = dbFetch("
        SELECT c.*, u.name as teacher_name,
               (SELECT COUNT(*) FROM class_members WHERE class_id = c.id) as student_count
        FROM classes c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.id = ?
    ", [$class_id]);
    
    if (!$class) {
        http_response_code(404);
        echo json_encode(['error' => 'Class not found']);
        exit;
    }
    
    // Verify access
    $has_access = false;
    if ($role === 'teacher' && $class['teacher_id'] == $user_id) {
        $has_access = true;
    } else {
        $member = dbFetch("SELECT id FROM class_members WHERE class_id = ? AND student_id = ?", [$class_id, $user_id]);
        $has_access = (bool)$member;
    }
    
    if (!$has_access) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $class
    ]);
    
} else {
    // Get all classes for user
    if ($role === 'teacher') {
        $classes = dbFetchAll("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM class_members WHERE class_id = c.id) as student_count,
                   (SELECT COUNT(*) FROM assignments WHERE class_id = c.id) as assignment_count
            FROM classes c
            WHERE c.teacher_id = ?
            ORDER BY c.created_at DESC
        ", [$user_id]);
    } else {
        $classes = dbFetchAll("
            SELECT c.*, u.name as teacher_name,
                   (SELECT COUNT(*) FROM class_members WHERE class_id = c.id) as student_count,
                   (SELECT COUNT(*) FROM assignments WHERE class_id = c.id) as assignment_count
            FROM classes c
            JOIN class_members cm ON c.id = cm.class_id
            JOIN users u ON c.teacher_id = u.id
            WHERE cm.student_id = ?
            ORDER BY cm.joined_at DESC
        ", [$user_id]);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $classes
    ]);
}
