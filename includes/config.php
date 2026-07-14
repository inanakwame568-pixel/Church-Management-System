<?php
// includes/config.php - UPDATED VERSION
// REMOVED session_start() from here

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'Elijah');
define('DB_PASS', 'livingstone@05533C'); // Put your actual password
define('DB_NAME', 'church_management');

// Application configuration
define('APP_NAME', 'Church Management System');
define('APP_URL', 'http://localhost/church-management-system');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/church-management-system/uploads/');

// Error reporting (turn off in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Time zone
date_default_timezone_set('America/New_York');

// Pagination
define('RECORDS_PER_PAGE', 20);

// No session_start() here - sessions will be started in individual pages when needed
?>