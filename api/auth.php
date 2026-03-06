<?php
/**
 * Authentication API
 * Handles login, register, and session operations
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

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'register':
        handleRegister();
        break;
    case 'verify':
        handleVerify();
        break;
    case 'logout':
        handleLogout();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function handleLogin() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }
    
    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    
    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        return;
    }
    
    $user = dbFetch("SELECT id, name, email, password, role FROM users WHERE email = ?", [$email]);
    
    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password']);
        return;
    }
    
    // Start session
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
}

function handleRegister() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }
    
    $name = isset($data['name']) ? trim($data['name']) : '';
    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    $role = isset($data['role']) ? $data['role'] : 'student';
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Name, email, and password are required']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        return;
    }
    
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 6 characters']);
        return;
    }
    
    if (!in_array($role, ['student', 'teacher'])) {
        $role = 'student';
    }
    
    // Check if email exists
    $existingUser = dbFetch("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existingUser) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already registered']);
        return;
    }
    
    // Create user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        dbExecute("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())", [$name, $email, $hashed_password, $role]);
        $user_id = dbLastInsertId();
        
        // Start session
        session_start();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['role'] = $role;
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user_id,
                'name' => $name,
                'email' => $email,
                'role' => $role
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create account']);
    }
}

function handleVerify() {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        return;
    }
    
    // Get fresh user data
    $user = dbFetch("SELECT id, name, email, role FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
}

function handleLogout() {
    session_start();
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => 'Logged out']);
}
