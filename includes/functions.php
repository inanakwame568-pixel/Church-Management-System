<?php
// includes/functions.php - Fixed version with improved role handling
require_once 'db_connection.php';
require_once 'session.php';

// ============= SANITIZATION FUNCTIONS =============

function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function sanitizeArray($data) {
    foreach ($data as $key => $value) {
        if (is_string($value)) {
            $data[$key] = sanitize($value);
        }
    }
    return $data;
}

// ============= SESSION FUNCTIONS =============

function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . APP_URL . '/login.php?session_expired=1');
        exit();
    }
}

function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header('Location: ' . APP_URL . '/admin/dashboard.php');
        exit();
    }
}

function hasRole($role) {
    $user_role = getCurrentUserRole();
    return $user_role === $role;
}

function isAdmin() {
    return hasRole('admin');
}

function isPastor() {
    return hasRole('pastor');
}

function isSecretary() {
    return hasRole('secretary');
}

/**
 * Check if current user is a member (not admin)
 * @return bool
 */
function isMember() {
    $role = getCurrentUserRole();
    return in_array($role, ['member', 'viewer', 'user']);
}

/**
 * Get member ID from user ID
 * @param int $user_id
 * @return int|null
 */
function getMemberIdFromUserId($user_id) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT member_id FROM members WHERE email = (SELECT email FROM users WHERE user_id = ?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['member_id'];
    }
    return null;
}

/**
 * Get current member ID
 * @return int|null
 */
function getCurrentMemberId() {
    $user_id = getCurrentUserId();
    if (!$user_id) return null;
    return getMemberIdFromUserId($user_id);
}

/**
 * Require member login - redirect if not logged in or not a member
 */
function requireMember() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit();
    }
    
    if (!isMember() && !isAdmin()) {
        // Admins can also access member area (optional)
        // If you want to restrict admins, remove the isAdmin() check
        header('Location: ' . APP_URL . '/admin/dashboard.php');
        exit();
    }
}

/**
 * Get current user ID from session or database
 * @return int|null
 */
function getCurrentUserId() {
    startSession();
    
    // Return from session if available
    if (isset($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    
    return null;
}

/**
 * Get current user name from session or database
 * @return string|null
 */
function getCurrentUserName() {
    startSession();
    
    // Return from session if available
    if (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
        return $_SESSION['user_name'];
    }
    
    // If user is logged in but name not in session, fetch from database
    if (isset($_SESSION['user_id'])) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT full_name FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $full_name = $row['full_name'];
                $_SESSION['user_name'] = $full_name;
                return $full_name;
            }
        } catch (Exception $e) {
            error_log("Error fetching user name: " . $e->getMessage());
        }
    }
    
    return 'User';
}

/**
 * Get current user role from session or database
 * This is the fixed function that ensures correct role is always returned
 * @return string|null
 */
function getCurrentUserRole() {
    startSession();
    
    // Return from session if available and valid
    if (isset($_SESSION['user_role']) && !empty($_SESSION['user_role'])) {
        return $_SESSION['user_role'];
    }
    
    // If user is logged in but role not in session, fetch from database
    if (isset($_SESSION['user_id'])) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT role FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $role = $row['role'];
                // Store in session for future use
                $_SESSION['user_role'] = $role;
                return $role;
            }
        } catch (Exception $e) {
            error_log("Error fetching user role: " . $e->getMessage());
        }
    }
    
    return null;
}

/**
 * Get user role display name (for UI)
 * @return string
 */
function getUserRoleDisplay() {
    $role = getCurrentUserRole();
    
    $display_names = [
        'admin' => 'Administrator',
        'pastor' => 'Pastor',
        'secretary' => 'Secretary',
        'member' => 'Member',
        'viewer' => 'Member',
        'user' => 'Member'
    ];
    
    return $display_names[$role] ?? 'User';
}

/**
 * Get user role icon class
 * @return string
 */
function getUserRoleIcon() {
    $role = getCurrentUserRole();
    
    $icons = [
        'admin' => 'fa-user-shield',
        'pastor' => 'fa-cross',
        'secretary' => 'fa-clipboard',
        'member' => 'fa-user',
        'viewer' => 'fa-user',
        'user' => 'fa-user'
    ];
    
    return $icons[$role] ?? 'fa-user';
}

/**
 * Get user role badge class
 * @return string
 */
function getUserRoleBadgeClass() {
    $role = getCurrentUserRole();
    
    $classes = [
        'admin' => 'bg-danger',
        'pastor' => 'bg-primary',
        'secretary' => 'bg-info',
        'member' => 'bg-success',
        'viewer' => 'bg-success',
        'user' => 'bg-success'
    ];
    
    return $classes[$role] ?? 'bg-secondary';
}

/**
 * Refresh user session data from database
 * Useful after updating user profile
 */
function refreshUserSession() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT full_name, role FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $_SESSION['user_name'] = $row['full_name'];
            $_SESSION['user_role'] = $row['role'];
            return true;
        }
    } catch (Exception $e) {
        error_log("Error refreshing user session: " . $e->getMessage());
    }
    
    return false;
}

// ============= VALIDATION FUNCTIONS =============

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    return strlen($clean) >= 10 && strlen($clean) <= 15;
}

function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function validatePassword($password) {
    return strlen($password) >= 6 && 
           preg_match('/[A-Za-z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

// ============= FORMATTING FUNCTIONS =============

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'M d, Y g:i A') {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

function formatPhone($phone) {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($clean) == 10) {
        return '(' . substr($clean, 0, 3) . ') ' . substr($clean, 3, 3) . '-' . substr($clean, 6);
    }
    return $phone;
}

function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) return "Just Now";
    if ($minutes <= 60) return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    if ($hours <= 24) return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    if ($days <= 7) return ($days == 1) ? "yesterday" : "$days days ago";
    if ($weeks <= 4.3) return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    if ($months <= 12) return ($months == 1) ? "1 month ago" : "$months months ago";
    return ($years == 1) ? "1 year ago" : "$years years ago";
}

function truncateText($text, $length = 100, $append = '...') {
    if (strlen($text) <= $length) return $text;
    $text = substr($text, 0, $length);
    $text = substr($text, 0, strrpos($text, ' '));
    return $text . $append;
}

function calculateAge($dob) {
    if (empty($dob)) return null;
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

// ============= DATABASE HELPER FUNCTIONS =============

function getMemberName($member_id) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['name'] ?? 'Unknown';
}

function getMemberById($member_id) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getUserById($user_id) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function generateCSRFToken() {
    startSession();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============= DASHBOARD STATS =============

function generateDashboardStats() {
    $db = Database::getInstance()->getConnection();
    $stats = [];
    
    try {
        // Total active members
        $result = $db->query("SELECT COUNT(*) as count FROM members WHERE membership_status = 'Active'");
        $stats['total_members'] = $result->fetch_assoc()['count'] ?? 0;
        
        // Today's attendance
        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM attendance WHERE service_date = ? AND attended = 1");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['today_attendance'] = $result->fetch_assoc()['count'] ?? 0;
        
        // This month's donations
        $month = date('m');
        $year = date('Y');
        $stmt = $db->prepare("SELECT SUM(amount) as total FROM donations WHERE MONTH(donation_date) = ? AND YEAR(donation_date) = ?");
        $stmt->bind_param("ii", $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['monthly_donations'] = $result->fetch_assoc()['total'] ?? 0;
        
        // Upcoming events
        $result = $db->query("SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE()");
        $stats['upcoming_events'] = $result->fetch_assoc()['count'] ?? 0;
        
        // New members this month
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM members WHERE MONTH(membership_date) = ? AND YEAR(membership_date) = ?");
        $stmt->bind_param("ii", $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['new_members'] = $result->fetch_assoc()['count'] ?? 0;
        
        // Total donations this year
        $stmt = $db->prepare("SELECT SUM(amount) as total FROM donations WHERE YEAR(donation_date) = ?");
        $stmt->bind_param("i", $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['yearly_donations'] = $result->fetch_assoc()['total'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Error generating dashboard stats: " . $e->getMessage());
        $stats = [
            'total_members' => 0, 
            'today_attendance' => 0, 
            'monthly_donations' => 0, 
            'upcoming_events' => 0,
            'new_members' => 0,
            'yearly_donations' => 0
        ];
    }
    
    return $stats;
}

// ============= LOGGING FUNCTIONS =============

function logActivity($user_id, $action, $details = '') {
    try {
        $db = Database::getInstance()->getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issss", $user_id, $action, $details, $ip, $user_agent);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// ============= PERMISSION FUNCTIONS =============

function canAccessModule($module) {
    $role = getCurrentUserRole();
    
    // Define permissions for each role
    $permissions = [
        'admin' => ['*'], // Admin can access everything
        'pastor' => ['dashboard', 'members', 'attendance', 'events', 'reports'],
        'secretary' => ['dashboard', 'members', 'attendance', 'events', 'donations'],
        'member' => ['dashboard', 'profile', 'events'],
        'viewer' => ['dashboard', 'profile', 'events'],
        'user' => ['dashboard', 'profile', 'events']
    ];
    
    // Check if role has permission
    if ($role && isset($permissions[$role])) {
        return in_array('*', $permissions[$role]) || in_array($module, $permissions[$role]);
    }
    
    return false;
}

function requirePermission($module) {
    if (!canAccessModule($module)) {
        header('Location: ' . APP_URL . '/admin/dashboard.php?error=permission_denied');
        exit();
    }
}
?>