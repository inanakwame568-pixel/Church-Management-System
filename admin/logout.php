<?php
// admin/logout.php - Admin Logout Script
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Start session if not already started
startSession();

// Check if user is logged in
if (isLoggedIn()) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Log the logout activity (optional)
        $user_id = getCurrentUserId();
        $username = getCurrentUserName() ?? 'Unknown';
        
        // Insert logout record into activity log (if table exists)
        $log_query = "INSERT INTO activity_log (user_id, action, details, ip_address, created_at) 
                      VALUES (?, 'logout', ?, ?, NOW())";
        $log_stmt = $db->prepare($log_query);
        if ($log_stmt) {
            $details = "User logged out successfully";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $log_stmt->bind_param("iss", $user_id, $details, $ip);
            $log_stmt->execute();
        }
        
        // Clear all session data
        $_SESSION = array();
        
        // Destroy the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
        
        // Set logout message in a temporary cookie or session flash
        session_start();
        $_SESSION['logout_message'] = "You have been successfully logged out.";
        $_SESSION['logout_type'] = "success";
        
    } catch (Exception $e) {
        // Log error but still logout
        error_log("Logout error: " . $e->getMessage());
        
        // Still clear session even if logging fails
        $_SESSION = array();
        session_destroy();
    }
}

// Determine redirect URL
$redirect_url = '../login.php';

// Check if we should redirect to a specific page
if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $redirect = sanitize($_GET['redirect']);
    // Only allow safe redirects
    $allowed_redirects = ['login', 'index', 'home'];
    if (in_array($redirect, $allowed_redirects)) {
        if ($redirect == 'index' || $redirect == 'home') {
            $redirect_url = '../index.php';
        }
    }
}

// Add logout parameter to URL
$redirect_url .= '?logout=1';

// Perform the redirect
header("Location: " . $redirect_url);
exit();
?>