<?php
/**
 * Simple autoloader for PHPMailer
 */

spl_autoload_register(function ($class) {
    // PHPMailer namespace prefix
    $prefix = 'PHPMailer\\PHPMailer\\';
    
    // Base directory for PHPMailer
    $baseDir = __DIR__ . '/phpmailer/phpmailer/src/';
    
    // Check if the class uses the PHPMailer namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get the relative class name
    $relativeClass = substr($class, $len);
    
    // Build the file path
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
