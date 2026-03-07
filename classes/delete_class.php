<?php
require_once '../config/database.php';

if (!isLoggedIn() || !isTeacher()) {
    redirect('../auth/login.php');
}

$class_id = isset($_GET['id']) ? decodeId($_GET['id']) : 0;

if (!$class_id) {
    redirect('../dashboard_teacher.php');
}

$user_id = $_SESSION['user_id'];

$class = dbFetch("SELECT id FROM classes WHERE id = ? AND teacher_id = ?", [$class_id, $user_id]);

if (!$class) {
    redirect('../dashboard_teacher.php');
}

dbExecute("DELETE FROM classes WHERE id = ?", [$class_id]);

redirect('../dashboard_teacher.php');
?>