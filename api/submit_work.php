<?php
/**
 * Submit Work API
 * Allows students to submit assignment work via API
 */
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
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

// Only students can submit
if ($user['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Only students can submit work']);
    exit;
}

$user_id = $user['user_id'];

// Get input data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$assignment_id = isset($data['assignment_id']) ? (int)$data['assignment_id'] : 0;
$content = isset($data['content']) ? trim($data['content']) : '';

if (!$assignment_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Assignment ID is required']);
    exit;
}

// Verify assignment exists and student has access
$assignment = dbFetch("
    SELECT a.*, c.id as class_id
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN class_members cm ON c.id = cm.class_id
    WHERE a.id = ? AND cm.student_id = ?
", [$assignment_id, $user_id]);

if (!$assignment) {
    http_response_code(404);
    echo json_encode(['error' => 'Assignment not found or access denied']);
    exit;
}

// Check for existing submission
$existing = dbFetch("SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?", [$assignment_id, $user_id]);
if ($existing) {
    http_response_code(409);
    echo json_encode(['error' => 'You have already submitted this assignment']);
    exit;
}

// Handle file upload if present
$file_path = null;
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/submissions/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_name = $user_id . '_' . $assignment_id . '_' . time() . '_' . basename($_FILES['file']['name']);
    $target_path = $upload_dir . $file_name;
    
    // Check file size (max 10MB)
    if ($_FILES['file']['size'] > 10 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'File size must be less than 10MB']);
        exit;
    }
    
    if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
        $file_path = 'uploads/submissions/' . $file_name;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to upload file']);
        exit;
    }
}

// Validate content
if (empty($content) && !$file_path) {
    http_response_code(400);
    echo json_encode(['error' => 'Please provide content or upload a file']);
    exit;
}

// Create submission
try {
    dbExecute("
        INSERT INTO submissions (assignment_id, student_id, content, file_path, submitted_at)
        VALUES (?, ?, ?, ?, NOW())
    ", [$assignment_id, $user_id, $content, $file_path]);
    
    $submission_id = dbLastInsertId();
    
    // Check if late
    $is_late = new DateTime() > new DateTime($assignment['due_date']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Submission received' . ($is_late ? ' (marked as late)' : ''),
        'submission_id' => $submission_id,
        'is_late' => $is_late
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to submit work']);
}
