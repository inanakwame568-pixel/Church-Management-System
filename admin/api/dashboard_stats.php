<?php
// admin/api/dashboard_stats.php
header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$db = Database::getInstance()->getConnection();

// Get statistics
$stats = [];

// Total members
$result = $db->query("SELECT COUNT(*) as count FROM members WHERE membership_status = 'Active'");
$stats['total_members'] = $result->fetch_assoc()['count'];

// Today's attendance
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) as count FROM attendance WHERE service_date = ? AND attended = 1");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
$stats['today_attendance'] = $result->fetch_assoc()['count'];

// Monthly donations
$month = date('m');
$year = date('Y');
$stmt = $db->prepare("SELECT SUM(amount) as total FROM donations WHERE MONTH(donation_date) = ? AND YEAR(donation_date) = ?");
$stmt->bind_param("ii", $month, $year);
$stmt->execute();
$result = $stmt->get_result();
$stats['monthly_donations'] = $result->fetch_assoc()['total'] ?? 0;

// Upcoming events
$result = $db->query("SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE()");
$stats['upcoming_events'] = $result->fetch_assoc()['count'];

// Birthday this month
$result = $db->query("SELECT COUNT(*) as count FROM members WHERE MONTH(date_of_birth) = MONTH(CURDATE())");
$stats['birthdays_this_month'] = $result->fetch_assoc()['count'];

echo json_encode($stats);
?>