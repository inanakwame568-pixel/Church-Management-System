<?php
// ajax/get_more_announcements.php - Load more for infinite scroll (FIXED)
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/member_auth.php';

// Set header for JSON response
header('Content-Type: application/json');

// Allow both GET and POST requests
$request_method = $_SERVER['REQUEST_METHOD'];

// Check if it's an AJAX request (optional - remove strict check)
// if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
//     echo json_encode(['success' => false, 'error' => 'Invalid request method', 'ajax' => false]);
//     exit();
// }

// Require member login
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated', 'redirect' => APP_URL . '/login.php']);
    exit();
}

// Get database connection
$db = Database::getInstance()->getConnection();
$member_id = getCurrentMemberId();

if (!$member_id) {
    echo json_encode(['success' => false, 'error' => 'Member ID not found']);
    exit();
}

// Get parameters from either GET or POST
$page = isset($_GET['page']) ? (int)$_GET['page'] : (isset($_POST['page']) ? (int)$_POST['page'] : 1);
$filter = isset($_GET['filter']) ? $_GET['filter'] : (isset($_POST['filter']) ? $_POST['filter'] : 'all');
$category = isset($_GET['category']) ? $_GET['category'] : (isset($_POST['category']) ? $_POST['category'] : 'all');
$search = isset($_GET['search']) ? trim($_GET['search']) : (isset($_POST['search']) ? trim($_POST['search']) : '');
$limit = 5; // Items per page
$offset = ($page - 1) * $limit;

// Build query
$query = "SELECT a.*, 
                 u.full_name as author_name,
                 (SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = a.announcement_id) as read_count,
                 CASE WHEN ar.read_id IS NOT NULL THEN 1 ELSE 0 END as is_read,
                 DATEDIFF(NOW(), a.created_at) as days_ago,
                 CASE 
                    WHEN a.priority = 'urgent' THEN 1
                    WHEN a.priority = 'high' THEN 2
                    WHEN a.priority = 'normal' THEN 3
                    ELSE 4
                 END as priority_order
          FROM announcements a
          LEFT JOIN users u ON a.created_by = u.user_id
          LEFT JOIN announcement_reads ar ON a.announcement_id = ar.announcement_id AND ar.member_id = ?
          WHERE (a.target_audience = 'all' OR a.target_audience = 'members')
          AND a.status = 'published'";

$params = [$member_id];
$types = "i";

// Add filter conditions
if ($filter == 'recent') {
    $query .= " AND a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filter == 'unread') {
    $query .= " AND ar.read_id IS NULL";
} elseif ($filter == 'urgent') {
    $query .= " AND a.priority = 'urgent'";
}

// Add category filter
if ($category != 'all') {
    $query .= " AND a.category = ?";
    $params[] = $category;
    $types .= "s";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (a.title LIKE ? OR a.content LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

// Exclude pinned announcements (they're shown separately)
$query .= " AND a.is_pinned = 0";

// Add ordering and pagination
$query .= " ORDER BY priority_order, a.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Prepare and execute
$stmt = $db->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->error]);
    exit();
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$announcements = $stmt->get_result();

// Check if this is the last page
$count_query = str_replace(
    "SELECT a.*, u.full_name as author_name, (SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = a.announcement_id) as read_count, CASE WHEN ar.read_id IS NOT NULL THEN 1 ELSE 0 END as is_read, DATEDIFF(NOW(), a.created_at) as days_ago, CASE WHEN a.priority = 'urgent' THEN 1 WHEN a.priority = 'high' THEN 2 WHEN a.priority = 'normal' THEN 3 ELSE 4 END as priority_order",
    "SELECT COUNT(*) as total",
    $query
);
$count_query = preg_replace('/LIMIT \d+ OFFSET \d+/', '', $count_query);

$count_stmt = $db->prepare($count_query);
if (!empty($params)) {
    // Remove limit and offset parameters for count query
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
}
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['total'];
$has_more = ($offset + $limit) < $total;

// Output as JSON
$html = '';
if ($announcements->num_rows > 0) {
    while ($announcement = $announcements->fetch_assoc()) {
        $priority_class = '';
        $priority_icon = '';
        
        if ($announcement['priority'] == 'urgent') {
            $priority_class = 'urgent-announcement';
            $priority_icon = '<i class="fas fa-exclamation-circle text-danger me-2"></i>';
        } elseif ($announcement['priority'] == 'high') {
            $priority_class = 'high-announcement';
            $priority_icon = '<i class="fas fa-arrow-up text-warning me-2"></i>';
        } else {
            $priority_icon = '<i class="fas fa-info-circle text-info me-2"></i>';
        }
        
        $html .= '
        <div class="col-12 announcement-item" data-announcement-id="' . $announcement['announcement_id'] . '">
            <div class="member-card announcement-card ' . $priority_class . ($announcement['is_read'] ? '' : ' unread') . '">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h5 class="card-title mb-1">
                                ' . $priority_icon . '
                                ' . htmlspecialchars($announcement['title']) . '
                                ' . ($announcement['priority'] == 'urgent' ? '<span class="badge bg-danger ms-2">URGENT</span>' : '') . '
                                ' . (!$announcement['is_read'] ? '<span class="badge bg-primary ms-2">New</span>' : '') . '
                            </h5>
                            <div class="d-flex gap-3 small text-muted mb-2">
                                <span><i class="fas fa-calendar me-1"></i>' . getTimeAgo($announcement['created_at']) . '</span>
                                <span><i class="fas fa-user me-1"></i>' . htmlspecialchars($announcement['author_name'] ?? 'Administrator') . '</span>
                                ' . ($announcement['category'] ? '<span><i class="fas fa-tag me-1"></i>' . $announcement['category'] . '</span>' : '') . '
                                <span><i class="fas fa-eye me-1"></i>' . $announcement['read_count'] . ' reads</span>
                            </div>
                        </div>
                        ' . (!$announcement['is_read'] ? '
                        <button class="btn btn-sm btn-outline-success mark-read-btn" onclick="markAsRead(' . $announcement['announcement_id'] . ')">
                            <i class="fas fa-check"></i>
                        </button>' : '') . '
                    </div>
                    <div class="announcement-content">
                        ' . nl2br(htmlspecialchars(substr($announcement['content'], 0, 300))) . '
                        ' . (strlen($announcement['content']) > 300 ? '... <a href="#" onclick="viewAnnouncement(\'' . htmlspecialchars($announcement['title']) . '\', \'' . htmlspecialchars(addslashes($announcement['content'])) . '\', \'' . htmlspecialchars($announcement['author_name'] ?? 'Administrator') . '\', \'' . date('F j, Y', strtotime($announcement['created_at'])) . '\'); return false;">Read more</a>' : '') . '
                    </div>
                </div>
            </div>
        </div>';
    }
}

// Return JSON response
echo json_encode([
    'success' => true,
    'html' => $html,
    'has_more' => $has_more,
    'current_page' => $page,
    'total_records' => $total
]);
exit();

function getTimeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    
    if ($seconds <= 60) return "Just Now";
    if ($minutes <= 60) return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    if ($hours <= 24) return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    if ($days <= 7) return ($days == 1) ? "yesterday" : "$days days ago";
    if ($weeks <= 4.3) return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    return date('M j, Y', strtotime($timestamp));
}
?>