<?php
// includes/member_auth.php - Simplified version
// This file just includes functions.php and doesn't redeclare anything

require_once 'config.php';
require_once 'db_connection.php';
require_once 'functions.php';

// No function declarations here - they're all in functions.php now
// This file exists for backward compatibility and convenience

// You can add helper functions specific to member area if needed,
// but make sure they don't conflict with functions.php

/**
 * Get member dashboard statistics
 * @param int $member_id
 * @return array
 */
function getMemberDashboardStats($member_id) {
    $db = Database::getInstance()->getConnection();
    $stats = [];
    
    // Donation stats
    $donation_query = "SELECT 
                        COUNT(*) as donation_count,
                        SUM(amount) as total_amount,
                        MAX(donation_date) as last_donation
                       FROM donations 
                       WHERE member_id = ?";
    $donation_stmt = $db->prepare($donation_query);
    $donation_stmt->bind_param("i", $member_id);
    $donation_stmt->execute();
    $stats['donations'] = $donation_stmt->get_result()->fetch_assoc();
    
    // Attendance stats
    $attendance_query = "SELECT 
                          COUNT(*) as total_attended,
                          COUNT(DISTINCT service_date) as days_attended
                         FROM attendance 
                         WHERE member_id = ? AND attended = 1";
    $attendance_stmt = $db->prepare($attendance_query);
    $attendance_stmt->bind_param("i", $member_id);
    $attendance_stmt->execute();
    $stats['attendance'] = $attendance_stmt->get_result()->fetch_assoc();
    
    // Group stats
    $groups_query = "SELECT COUNT(*) as group_count
                     FROM group_members 
                     WHERE member_id = ? AND status = 'Active'";
    $groups_stmt = $db->prepare($groups_query);
    $groups_stmt->bind_param("i", $member_id);
    $groups_stmt->execute();
    $stats['groups'] = $groups_stmt->get_result()->fetch_assoc();
    
    return $stats;
}

/**
 * Get member's upcoming events
 * @param int $member_id
 * @param int $limit
 * @return mysqli_result
 */
function getMemberUpcomingEvents($member_id, $limit = 5) {
    $db = Database::getInstance()->getConnection();
    $query = "SELECT e.*, 
                     (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as registrations,
                     (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id AND member_id = ?) as registered
              FROM events e
              WHERE e.event_date >= CURDATE()
              ORDER BY e.event_date ASC
              LIMIT ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ii", $member_id, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Get member's groups
 * @param int $member_id
 * @return mysqli_result
 */
function getMemberGroups($member_id) {
    $db = Database::getInstance()->getConnection();
    $query = "SELECT g.*, gm.role, gm.joined_date
              FROM `groups` g
              JOIN group_members gm ON g.group_id = gm.group_id
              WHERE gm.member_id = ? AND gm.status = 'Active'
              ORDER BY g.group_name";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Get available groups for member to join
 * @param int $member_id
 * @param int $limit
 * @return mysqli_result
 */
function getAvailableGroups($member_id, $limit = 5) {
    $db = Database::getInstance()->getConnection();
    $query = "SELECT g.*, 
                     (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id AND status = 'Active') as member_count
              FROM `groups` g
              WHERE g.status = 'Active' 
              AND g.group_id NOT IN (SELECT group_id FROM group_members WHERE member_id = ?)
              ORDER BY g.group_name
              LIMIT ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ii", $member_id, $limit);
    $stmt->execute();
    return $stmt->get_result();
}
?>