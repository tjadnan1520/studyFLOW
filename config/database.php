<?php
require_once __DIR__ . '/env.php';

if (!Env::getBool('APP_DEBUG', false)) {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

define('DB_HOST', Env::get('DB_HOST', 'localhost'));
define('DB_NAME', Env::get('DB_NAME', 'studyflow'));
define('DB_USER', Env::get('DB_USER', 'root'));
define('DB_PASS', Env::get('DB_PASS', ''));

define('APP_NAME', Env::get('APP_NAME', 'StudyFlow'));
define('APP_DEBUG', Env::getBool('APP_DEBUG', false));

function getConnection() {
    static $mysqli = null;
    
    if ($mysqli === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        try {
            $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $mysqli->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) { 
            if (APP_DEBUG) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                die("Database connection failed. Please try again later.");
            }
        }
    }
    
    return $mysqli;
}

function dbQuery($sql, $params = [], $types = null) {
    $mysqli = getConnection();
    $stmt = $mysqli->prepare($sql);
    
    if (!empty($params)) {
        if ($types === null) {
            $types = str_repeat('s', count($params));
        }
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result !== false ? $result : true;
}

function dbFetch($sql, $params = []) {
    $result = dbQuery($sql, $params);
    if ($result instanceof mysqli_result) {
        return $result->fetch_assoc();
    }
    return null;
}

function dbFetchAll($sql, $params = []) {
    $result = dbQuery($sql, $params);
    if ($result instanceof mysqli_result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

function dbExecute($sql, $params = []) {
    $mysqli = getConnection();
    $stmt = $mysqli->prepare($sql);
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt->affected_rows;
}

function dbLastInsertId() {
    return getConnection()->insert_id;
}

function dbBeginTransaction() {
    getConnection()->begin_transaction();
}

function dbCommit() {
    getConnection()->commit();
}

function dbRollback() {
    getConnection()->rollback();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isTeacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateClassCode($length = 6) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

function encodeId($id) {
    $salt = 'classroom2026';
    $encoded = base64_encode($salt . '|' . $id . '|' . substr(md5($id . $salt), 0, 8));
    return rtrim(strtr($encoded, '+/', '-_'), '=');
}

function decodeId($hash) {
    $salt = 'classroom2026';
    $decoded = base64_decode(strtr($hash, '-_', '+/'));
    if (!$decoded) return 0;
    
    $parts = explode('|', $decoded);
    if (count($parts) !== 3 || $parts[0] !== $salt) return 0;
    
    $id = (int)$parts[1];
    $checksum = substr(md5($id . $salt), 0, 8);
    
    if ($parts[2] !== $checksum) return 0;
    return $id;
}

function validateApiSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['role'] ?? ''
    ];
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError($message, $statusCode = 400) {
    jsonResponse(['error' => $message], $statusCode);
}

function jsonSuccess($data, $message = null) {
    $response = ['success' => true, 'data' => $data];
    if ($message) {
        $response['message'] = $message;
    }
    jsonResponse($response);
}

function requireApiAuth() {
    $user = validateApiSession();
    if (!$user) {
        jsonError('Unauthorized', 401);
    }
    return $user;
}
