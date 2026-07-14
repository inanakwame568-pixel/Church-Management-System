<?php
// admin/group_view.php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
requireLogin();

$db = Database::getInstance()->getConnection();
$groupId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get group details with leader info
$groupStmt = $db->prepare("
    SELECT g.*, 
           CONCAT(l.first_name, ' ', l.last_name) as leader_name,
           CONCAT(cl.first_name, ' ', cl.last_name) as co_leader_name
    FROM groups g
    LEFT JOIN members l ON g.leader_id = l.member_id
    LEFT JOIN members cl ON g.co_leader_id = cl.member_id
    WHERE g.group_id = ?
");
$groupStmt->bind_param("i", $groupId);
$groupStmt->execute();
$group = $groupStmt->get_result()->fetch_assoc();

if (!$group) {
    header('Location: groups.php');
    exit();
}

// Get members count by role
$statsStmt = $db->prepare("
    SELECT role, COUNT(*) as count
    FROM group_members
    WHERE group_id = ? AND status = 'Active'
    GROUP BY role
");
$statsStmt->bind_param("i", $groupId);
$statsStmt->execute();
$roleStats = $statsStmt->get_result();

// Get recent attendance
$attendanceStmt = $db->prepare("
    SELECT meeting_date, attendance_count,
           (SELECT COUNT(*) FROM group_members WHERE group_id = ? AND status = 'Active') as total_members
    FROM group_meetings
    WHERE group_id = ?
    ORDER BY meeting_date DESC
    LIMIT 5
");
$attendanceStmt->bind_param("ii", $groupId, $groupId);
$attendanceStmt->execute();
$recentAttendance = $attendanceStmt->get_result();

// Get upcoming meetings (next 3)
$upcomingStmt = $db->prepare("
    SELECT * FROM group_meetings
    WHERE group_id = ? AND meeting_date >= CURDATE()
    ORDER BY meeting_date ASC
    LIMIT 3
");
$upcomingStmt->bind_param("i", $groupId);
$upcomingStmt->execute();
$upcomingMeetings = $upcomingStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($group['group_name']); ?> - Group Details</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .group-header {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .group-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .group-title h1 {
            margin: 0;
            font-size: 2rem;
        }
        
        .group-type-badge {
            background: var(--secondary-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 1rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .info-card {
            background: var(--light-bg);
            padding: 20px;
            border-radius: 8px;
        }
        
        .info-card h3 {
            margin: 0 0 10px 0;
            color: var(--primary-color);
            font-size: 1rem;
        }
        
        .info-card p {
            margin: 5px 0;
            font-size: 1.1rem;
        }
        
        .leader-section {
            display: flex;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .leader-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .leader-details h4 {
            margin: 0 0 5px 0;
        }
        
        .leader-details p {
            margin: 0;
            color: #666;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
            background: var(--light-bg);
            border-radius: 8px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            flex: 1;
            min-width: 150px;
            padding: 15px;
            text-align: center;
            background: white;
            border: 2px solid var(--secondary-color);
            color: var(--secondary-color);
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background: var(--secondary-color);
            color: white;
        }
        
        .description-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- ... sidebar content ... -->
        </aside>

        <main class="main-content">
            <div class="group-header">
                <div class="group-title">
                    <h1><?php echo htmlspecialchars($group['group_name']); ?></h1>
                    <span class="group-type-badge"><?php echo $group['group_type']; ?></span>
                </div>
                
                <div class="stats-container">
                    <?php
                    $totalMembers = 0;
                    $roleCounts = ['Leader' => 0, 'Co-Leader' => 0, 'Member' => 0];
                    while ($stat = $roleStats->fetch_assoc()) {
                        $roleCounts[$stat['role']] = $stat['count'];
                        $totalMembers += $stat['count'];
                    }
                    ?>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $totalMembers; ?></div>
                        <div class="stat-label">Total Members</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $roleCounts['Leader']; ?></div>
                        <div class="stat-label">Leaders</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">
                            <?php 
                            $avgAttendance = 0;
                            if ($recentAttendance->num_rows > 0) {
                                $total = 0;
                                $count = 0;
                                while ($att = $recentAttendance->fetch_assoc()) {
                                    if ($att['total_members'] > 0) {
                                        $total += ($att['attendance_count'] / $att['total_members']) * 100;
                                        $count++;
                                    }
                                }
                                $avgAttendance = $count > 0 ? round($total / $count) : 0;
                            }
                            echo $avgAttendance . '%';
                            ?>
                        </div>
                        <div class="stat-label">Avg. Attendance</div>
                    </div>
                </div>

                <div class="quick-actions">
                    <a href="group_members.php?id=<?php echo $groupId; ?>" class="action-btn">👥 Manage Members</a>
                    <a href="group_attendance.php?id=<?php echo $groupId; ?>" class="action-btn">📊 Take Attendance</a>
                    <a href="group_edit.php?id=<?php echo $groupId; ?>" class="action-btn">✏️ Edit Group</a>
                    <a href="group_email.php?id=<?php echo $groupId; ?>" class="action-btn">📧 Email Group</a>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-card">
                    <h3>📅 Meeting Schedule</h3>
                    <p><strong>Day:</strong> <?php echo $group['meeting_day'] ?: 'Not set'; ?></p>
                    <p><strong>Time:</strong> <?php echo $group['meeting_time'] ? date('g:i A', strtotime($group['meeting_time'])) : 'Not set'; ?></p>
                    <p><strong>Frequency:</strong> <?php echo $group['meeting_frequency']; ?></p>
                    <p><strong>Location:</strong> <?php echo $group['meeting_location'] ?: 'Not set'; ?></p>
                </div>

                <div class="info-card">
                    <h3>👥 Leadership</h3>
                    <?php if ($group['leader_name']): ?>
                        <p><strong>Leader:</strong> <?php echo $group['leader_name']; ?></p>
                    <?php endif; ?>
                    <?php if ($group['co_leader_name']): ?>
                        <p><strong>Co-Leader:</strong> <?php echo $group['co_leader_name']; ?></p>
                    <?php endif; ?>
                    <p><strong>Status:</strong> <span class="badge badge-<?php echo strtolower($group['status']); ?>"><?php echo $group['status']; ?></span></p>
                    <p><strong>Visibility:</strong> <?php echo $group['visibility']; ?></p>
                    <p><strong>Capacity:</strong> <?php echo $group['max_capacity'] > 0 ? $totalMembers . '/' . $group['max_capacity'] : 'Unlimited'; ?></p>
                </div>

                <div class="info-card">
                    <h3>📊 Recent Meetings</h3>
                    <?php if ($recentAttendance->num_rows > 0): ?>
                        <?php $recentAttendance->data_seek(0); ?>
                        <?php while ($meeting = $recentAttendance->fetch_assoc()): ?>
                            <p><strong><?php echo date('M d', strtotime($meeting['meeting_date'])); ?>:</strong> 
                               <?php echo $meeting['attendance_count']; ?>/<?php echo $meeting['total_members']; ?> attended</p>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No meetings recorded yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($group['description']): ?>
                <div class="description-box">
                    <h3>📝 Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($group['description'])); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($upcomingMeetings->num_rows > 0): ?>
                <div class="info-card" style="margin-top: 20px;">
                    <h3>📅 Upcoming Meetings</h3>
                    <?php while ($meeting = $upcomingMeetings->fetch_assoc()): ?>
                        <div style="padding: 10px; border-bottom: 1px solid var(--border-color);">
                            <strong><?php echo date('F j, Y', strtotime($meeting['meeting_date'])); ?></strong>
                            <?php if ($meeting['topic']): ?>
                                <p style="margin: 5px 0 0 0; color: #666;"><?php echo $meeting['topic']; ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/script.js"></script>
</body>
</html>