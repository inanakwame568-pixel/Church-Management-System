<?php
// ajax/mark_announcement_read.php - Mark single announcement as read (FIXED)
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/member_auth.php';

// Set header for JSON response
header('Content-Type: application/json');

// Allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method. POST required.']);
    exit();
}

// Require member login
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get POST data (supports both JSON and form data)
$input = [];
if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    $input = $_POST;
}

$announcement_id = isset($input['announcement_id']) ? (int)$input['announcement_id'] : (isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0);

if (!$announcement_id) {
    echo json_encode(['success' => false, 'error' => 'Announcement ID required']);
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
    // Check if announcement exists
    $check_stmt = $db->prepare("SELECT announcement_id FROM announcements WHERE announcement_id = ?");
    $check_stmt->bind_param("i", $announcement_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        echo json_encode(['success' => false, 'error' => 'Announcement not found']);
        exit();
    }
    
    // Check if already marked as read
    $read_check = $db->prepare("SELECT read_id FROM announcement_reads WHERE announcement_id = ? AND member_id = ?");
    $read_check->bind_param("ii", $announcement_id, $member_id);
    $read_check->execute();
    
    if ($read_check->get_result()->num_rows == 0) {
        // Mark as read
        $insert_stmt = $db->prepare("INSERT INTO announcement_reads (announcement_id, member_id, read_at) VALUES (?, ?, NOW())");
        $insert_stmt->bind_param("ii", $announcement_id, $member_id);
        
        if ($insert_stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Announcement marked as read'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to mark as read'
            ]);
        }
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Already marked as read'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error marking announcement as read: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>