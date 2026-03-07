<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    if (isTeacher()) {
        redirect('dashboard_teacher.php');
    } else {
        redirect('dashboard_student.php');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyFlow - Learning Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="landing-container">
        <header class="landing-header">
            <div class="logo">StudyFlow</div>
            <nav class="landing-nav">
                <a href="auth/login.php" class="btn btn-outline">Login</a>
                <a href="auth/register.php" class="btn btn-primary">Sign Up</a>
            </nav>
        </header>
        
        <main class="landing-main">
            <section class="hero">
                <h1>Welcome to StudyFlow</h1>
                <p>A powerful learning management system for teachers and students. Create classes, share assignments, and track progress all in one place.</p>
                <div class="hero-buttons">
                    <a href="auth/register.php?role=teacher" class="btn btn-primary btn-large">I'm a Teacher</a>
                    <a href="auth/register.php?role=student" class="btn btn-secondary btn-large">I'm a Student</a>
                </div>
            </section>
            
            <section class="features">
                <div class="feature-card">
                    <div class="feature-icon"></div>
                    <h3>Create Assignments</h3>
                    <p>Easily create and distribute assignments to your students with due dates and point values.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"></div>
                    <h3>Manage Classes</h3>
                    <p>Organize your classes, invite students with unique codes, and keep everything in one place.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"></div>
                    <h3>Track Progress</h3>
                    <p>Monitor student submissions, provide feedback, and track grades effortlessly.</p>
                </div>
            </section>
        </main>
        
        <footer class="landing-footer">
            <p>&copy; <?= date('Y') ?> StudyFlow. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
