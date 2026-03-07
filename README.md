# StudyFlow

A modern Learning Management System (LMS) built with PHP and MySQL. StudyFlow enables teachers to create classes, distribute assignments, track attendance, and grade student work — all in one platform.

## Features

### For Teachers
- **Class Management** - Create and manage multiple classes with unique join codes
- **Assignment Creation** - Create assignments with descriptions, due dates, point values, and file attachments
- **Grading System** - Review student submissions, assign grades, and provide feedback
- **Attendance Tracking** - Mark daily attendance with status options (present, absent, late, excused)
- **Announcements** - Post announcements to keep students informed
- **Export Data** - Export attendance records as CSV

### For Students
- **Join Classes** - Enter class codes to enroll in courses
- **View Assignments** - Access assignment details, due dates, and attached materials
- **Submit Work** - Upload files or submit text responses
- **Track Grades** - View grades and teacher feedback on submissions
- **Dashboard** - Overview of enrolled classes, upcoming assignments, and recent grades

### Security
- Password hashing with PHP's `password_hash()`
- Prepared statements to prevent SQL injection
- Session-based authentication
- Password reset via email verification codes
- ID obfuscation in URLs

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (optional, for PHPMailer)

## Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/studyflow.git
   cd studyflow
   ```

2. **Create the database**
   - Import the SQL schema:
   ```bash
   mysql -u root -p < config/setup.sql
   ```
   - Or run `config/setup.sql` in phpMyAdmin

3. **Configure environment**
   - Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
   - Update the `.env` file with your database credentials and settings

4. **Set up the web server**
   - Point your web server's document root to the project directory
   - Ensure `mod_rewrite` is enabled (for Apache)

5. **(Optional) Install PHPMailer for email functionality**
   ```bash
   composer require phpmailer/phpmailer
   ```

## Configuration

Edit the `.env` file with your settings:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=studyflow
DB_USER=your_username
DB_PASS=your_password

# App Configuration
APP_NAME=StudyFlow
APP_URL=http://localhost/studyflow
APP_DEBUG=false

# Email Configuration (for password reset)
EMAIL_HOST=smtp.example.com
EMAIL_PORT=587
EMAIL_USER=your_email@example.com
EMAIL_PASSWORD=your_app_password
EMAIL_FROM_NAME=StudyFlow
```

## Usage

### Teachers
1. Register as a teacher
2. Create a class from the dashboard
3. Share the class code with students
4. Create assignments with due dates
5. Take attendance and grade submissions

### Students
1. Register as a student
2. Join a class using the code from your teacher
3. View and complete assignments
4. Track your grades and submissions

