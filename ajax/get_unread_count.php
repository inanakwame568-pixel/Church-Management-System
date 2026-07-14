<?php
// ajax/get_unread_count.php - Get current unread count
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/member_auth.php';

// Set header for JSON response
header('Content-Type: application/json');

// Check if it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Require member login
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get database connection
$db = Database::getInstance()->getConnection();
$member_id = getCurrentMemberId();

if (!$member_id) {
    echo json_encode(['success' => false, 'error' => 'Member ID not found']);
    exit();
}

try {
    // Get unread count
    $query = "SELECT COUNT(*) as count FROM announcements a
              WHERE a.status = 'published' 
              AND (a.target_audience = 'all' OR a.target_audience = 'members')
              AND a.announcement_id NOT IN (
                  SELECT announcement_id FROM announcement_reads WHERE member_id = ?
              )";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $unread = $result->fetch_assoc()['count'];
    
    // Get urgent count
    $urgent_query = "SELECT COUNT(*) as count FROM announcements 
                     WHERE priority = 'urgent' 
                     AND status = 'published' 
                     AND (target_audience = 'all' OR target_audience = 'members')
                     AND announcement_id NOT IN (
                         SELECT announcement_id FROM announcement_reads WHERE member_id = ?
                     )";
    
    $urgent_stmt = $db->prepare($urgent_query);
    $urgent_stmt->bind_param("i", $member_id);
    $urgent_stmt->execute();
    $urgent_result = $urgent_stmt->get_result();
    $urgent = $urgent_result->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'unread' => (int)$unread,
        'urgent' => (int)$urgent,
        'total' => (int)($unread + $urgent)
    ]);
    
} catch (Exception $e) {
    error_log("Error getting unread count: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'unread' => 0,
        'urgent' => 0,
        'total' => 0
    ]);
}
?>