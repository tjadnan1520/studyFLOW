<?php
/**
 * Attendance API Endpoint
 * Handles attendance CRUD operations
 */
require_once '../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($user_id);
        break;
    case 'POST':
        handlePost($user_id);
        break;
    case 'PUT':
        handlePut($user_id);
        break;
    case 'DELETE':
        handleDelete($user_id);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function handleGet($user_id) {
    $class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
    $date = isset($_GET['date']) ? $_GET['date'] : null;
    $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
    
    // Verify access
    if (!verifyClassAccess($class_id, $user_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    $sql = "SELECT a.*, u.name as student_name, u.email as student_email 
            FROM attendance a 
            JOIN users u ON a.student_id = u.id 
            WHERE a.class_id = ?";
    $params = [$class_id];
    
    if ($date) {
        $sql .= " AND a.date = ?";
        $params[] = $date;
    }
    
    if ($student_id) {
        $sql .= " AND a.student_id = ?";
        $params[] = $student_id;
    }
    
    $sql .= " ORDER BY a.date DESC, u.name";
    
    $records = dbFetchAll($sql, $params);
    
    echo json_encode(['success' => true, 'data' => $records]);
}

function handlePost($user_id) {
    // Only teachers can mark attendance
    if (!isTeacher()) {
        http_response_code(403);
        echo json_encode(['error' => 'Only teachers can mark attendance']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    $class_id = isset($data['class_id']) ? (int)$data['class_id'] : 0;
    $student_id = isset($data['student_id']) ? (int)$data['student_id'] : 0;
    $date = isset($data['date']) ? $data['date'] : date('Y-m-d');
    $status = isset($data['status']) ? $data['status'] : 'present';
    $notes = isset($data['notes']) ? trim($data['notes']) : '';
    
    // Validate
    if (!$class_id || !$student_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Class ID and Student ID are required']);
        return;
    }
    
    if (!in_array($status, ['present', 'absent', 'late', 'excused'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        return;
    }
    
    // Verify teacher owns the class
    $class = dbFetch("SELECT id FROM classes WHERE id = ? AND teacher_id = ?", [$class_id, $user_id]);
    if (!$class) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    // Insert or update
    try {
        dbExecute("
            INSERT INTO attendance (class_id, student_id, date, status, notes, marked_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes), marked_by = VALUES(marked_by)
        ", [$class_id, $student_id, $date, $status, $notes, $user_id]);
        echo json_encode(['success' => true, 'message' => 'Attendance recorded']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save attendance']);
    }
}

function handlePut($user_id) {
    handlePost($user_id);
}

function handleDelete($user_id) {
    // Only teachers can delete attendance
    if (!isTeacher()) {
        http_response_code(403);
        echo json_encode(['error' => 'Only teachers can delete attendance']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Attendance ID is required']);
        return;
    }
    
    // Verify teacher owns the class
    $attendance = dbFetch("
        SELECT a.id FROM attendance a
        JOIN classes c ON a.class_id = c.id
        WHERE a.id = ? AND c.teacher_id = ?
    ", [$id, $user_id]);
    
    if (!$attendance) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    dbExecute("DELETE FROM attendance WHERE id = ?", [$id]);
    
    echo json_encode(['success' => true, 'message' => 'Attendance deleted']);
}

function verifyClassAccess($class_id, $user_id) {
    // Check if teacher
    $class = dbFetch("SELECT id FROM classes WHERE id = ? AND teacher_id = ?", [$class_id, $user_id]);
    if ($class) {
        return true;
    }
    
    // Check if student member
    $member = dbFetch("SELECT id FROM class_members WHERE class_id = ? AND student_id = ?", [$class_id, $user_id]);
    if ($member) {
        return true;
    }
    
    return false;
}
