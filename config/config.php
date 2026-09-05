<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'jobhub');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('APP_URL', 'http://localhost/company.job.panel/');
define('BASE_URL', 'http://localhost/company.job.panel/');
define('MAIN_URL', 'http://localhost/employees.website/');


// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
