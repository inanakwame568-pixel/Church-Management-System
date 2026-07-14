<?php
// admin/group_attendance.php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
requireLogin();

$db = Database::getInstance()->getConnection();
$groupId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get group info
$groupStmt = $db->prepare("SELECT * FROM groups WHERE group_id = ?");
$groupStmt->bind_param("i", $groupId);
$groupStmt->execute();
$group = $groupStmt->get_result()->fetch_assoc();

if (!$group) {
    header('Location: groups.php');
    exit();
}

// Handle creating new meeting
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_meeting'])) {
    $meetingDate = $_POST['meeting_date'];
    $topic = $db->real_escape_string($_POST['topic']);
    $notes = $db->real_escape_string($_POST['notes']);
    
    $stmt = $db->prepare("
        INSERT INTO group_meetings (group_id, meeting_date, topic, notes, created_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssi", $groupId, $meetingDate, $topic, $notes, $_SESSION['user_id']);
    $stmt->execute();
    
    $meetingId = $db->insert_id;
    header("Location: group_attendance.php?id=$groupId&meeting=$meetingId");
    exit();
}

// Handle attendance marking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_attendance'])) {
    $meetingId = $_POST['meeting_id'];
    $presentMembers = $_POST['present_members'] ?? [];
    
    // Get all group members
    $membersStmt = $db->prepare("
        SELECT group_member_id FROM group_members 
        WHERE group_id = ? AND status = 'Active'
    ");
    $membersStmt->bind_param("i", $groupId);
    $membersStmt->execute();
    $members = $membersStmt->get_result();
    
    // Clear existing attendance for this meeting
    $clearStmt = $db->prepare("DELETE FROM group_meeting_attendance WHERE meeting_id = ?");
    $clearStmt->bind_param("i", $meetingId);
    $clearStmt->execute();
    
    // Insert attendance
    $attendanceCount = 0;
    $insertStmt = $db->prepare("
        INSERT INTO group_meeting_attendance (meeting_id, group_member_id, attended, check_in_time)
        VALUES (?, ?, ?, NOW())
    ");
    
    while ($member = $members->fetch_assoc()) {
        $attended = in_array($member['group_member_id'], $presentMembers) ? 1 : 0;
        if ($attended) $attendanceCount++;
        
        $insertStmt->bind_param("ii", $meetingId, $member['group_member_id']);
        $insertStmt->execute();
    }
    
    // Update meeting attendance count
    $updateStmt = $db->prepare("UPDATE group_meetings SET attendance_count = ? WHERE meeting_id = ?");
    $updateStmt->bind_param("ii", $attendanceCount, $meetingId);
    $updateStmt->execute();
    
    $_SESSION['message'] = "Attendance saved successfully!";
}

// Get selected meeting
$selectedMeeting = isset($_GET['meeting']) ? (int)$_GET['meeting'] : null;

// Get all meetings
$meetingsStmt = $db->prepare("
    SELECT * FROM group_meetings 
    WHERE group_id = ? 
    ORDER BY meeting_date DESC
");
$meetingsStmt->bind_param("i", $groupId);
$meetingsStmt->execute();
$meetings = $meetingsStmt->get_result();

// Get attendance for selected meeting
$attendance = [];
$presentMembers = [];
if ($selectedMeeting) {
    $attendanceStmt = $db->prepare("
        SELECT gma.*, gm.member_id, m.first_name, m.last_name
        FROM group_meeting_attendance gma
        JOIN group_members gm ON gma.group_member_id = gm.group_member_id
        JOIN members m ON gm.member_id = m.member_id
        WHERE gma.meeting_id = ?
        ORDER BY m.last_name, m.first_name
    ");
    $attendanceStmt->bind_param("i", $selectedMeeting);
    $attendanceStmt->execute();
    $attendance = $attendanceStmt->get_result();
    
    // Get present member IDs
    $presentStmt = $db->prepare("
        SELECT group_member_id FROM group_meeting_attendance 
        WHERE meeting_id = ? AND attended = 1
    ");
    $presentStmt->bind_param("i", $selectedMeeting);
    $presentStmt->execute();
    $presentResult = $presentStmt->get_result();
    while ($row = $presentResult->fetch_assoc()) {
        $presentMembers[] = $row['group_member_id'];
    }
}

// Get all active members for attendance marking
$activeMembersStmt = $db->prepare("
    SELECT gm.group_member_id, m.first_name, m.last_name, m.member_id
    FROM group_members gm
    JOIN members m ON gm.member_id = m.member_id
    WHERE gm.group_id = ? AND gm.status = 'Active'
    ORDER BY m.last_name, m.first_name
");
$activeMembersStmt->bind_param("i", $groupId);
$activeMembersStmt->execute();
$activeMembers = $activeMembersStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Attendance - <?php echo htmlspecialchars($group['group_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .attendance-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .meetings-sidebar {
            background: white;
            border-radius: 8px;
            padding: 20px;
        }
        
        .meeting-item {
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .meeting-item:hover {
            background: var(--light-bg);
        }
        
        .meeting-item.active {
            background: var(--secondary-color);
            color: white;
        }
        
        .meeting-date {
            font-weight: bold;
        }
        
        .meeting-topic {
            font-size: 0.9rem;
            color: #666;
        }
        
        .meeting-item.active .meeting-topic {
            color: rgba(255,255,255,0.9);
        }
        
        .attendance-main {
            background: white;
            border-radius: 8px;
            padding: 20px;
        }
        
        .attendance-table {
            width: 100%;
            margin-top: 20px;
        }
        
        .attendance-table th {
            background: var(--primary-color);
            color: white;
            padding: 10px;
        }
        
        .attendance-table td {
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .attendance-checkbox {
            width: 50px;
            text-align: center;
        }
        
        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: var(--light-bg);
            padding: 15px;
            border-radius: 5px;
            flex: 1;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
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
            <header class="content-header">
                <h1>Attendance - <?php echo htmlspecialchars($group['group_name']); ?></h1>
                <div class="header-actions">
                    <a href="group_view.php?id=<?php echo $groupId; ?>" class="btn btn-secondary">Back to Group</a>
                </div>
            </header>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['message'];
                    unset($_SESSION['message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="attendance-container">
                <!-- Meetings List -->
                <div class="meetings-sidebar">
                    <h3>Meetings</h3>
                    
                    <!-- New Meeting Form -->
                    <form method="POST" class="new-meeting-form">
                        <h4>Create New Meeting</h4>
                        <div class="form-group">
                            <input type="date" name="meeting_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <input type="text" name="topic" placeholder="Topic/Theme">
                        </div>
                        <div class="form-group">
                            <textarea name="notes" placeholder="Notes" rows="2"></textarea>
                        </div>
                        <button type="submit" name="create_meeting" class="btn btn-primary btn-block">Create Meeting</button>
                    </form>
                    
                    <hr style="margin: 20px 0;">
                    
                    <!-- Previous Meetings -->
                    <h4>Previous Meetings</h4>
                    <?php if ($meetings->num_rows > 0): ?>
                        <?php while ($meeting = $meetings->fetch_assoc()): ?>
                            <a href="?id=<?php echo $groupId; ?>&meeting=<?php echo $meeting['meeting_id']; ?>" 
                               style="text-decoration: none; color: inherit;">
                                <div class="meeting-item <?php echo ($selectedMeeting == $meeting['meeting_id']) ? 'active' : ''; ?>">
                                    <div class="meeting-date"><?php echo date('M d, Y', strtotime($meeting['meeting_date'])); ?></div>
                                    <div class="meeting-topic"><?php echo $meeting['topic'] ?: 'No topic'; ?></div>
                                    <div>👥 <?php echo $meeting['attendance_count']; ?> present</div>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No meetings recorded yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Attendance Area -->
                <div class="attendance-main">
                    <?php if ($selectedMeeting): ?>
                        <?php
                        $meetingInfo = $db->prepare("SELECT * FROM group_meetings WHERE meeting_id = ?");
                        $meetingInfo->bind_param("i", $selectedMeeting);
                        $meetingInfo->execute();
                        $currentMeeting = $meetingInfo->get_result()->fetch_assoc();
                        ?>
                        
                        <h2>Meeting: <?php echo date('F j, Y', strtotime($currentMeeting['meeting_date'])); ?></h2>
                        <?php if ($currentMeeting['topic']): ?>
                            <h3><?php echo htmlspecialchars($currentMeeting['topic']); ?></h3>
                        <?php endif; ?>
                        
                        <div class="stats-row">
                            <div class="stat-box">
                                <div class="stat-label">Total Members</div>
                                <div class="stat-value"><?php echo $activeMembers->num_rows; ?></div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Present</div>
                                <div class="stat-value"><?php echo $currentMeeting['attendance_count']; ?></div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Attendance Rate</div>
                                <div class="stat-value">
                                    <?php 
                                    if ($activeMembers->num_rows > 0) {
                                        echo round(($currentMeeting['attendance_count'] / $activeMembers->num_rows) * 100) . '%';
                                    } else {
                                        echo '0%';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" id="attendanceForm">
                            <input type="hidden" name="meeting_id" value="<?php echo $selectedMeeting; ?>">
                            
                            <table class="attendance-table">
                                <thead>
                                    <tr>
                                        <th class="attendance-checkbox">Present</th>
                                        <th>Member Name</th>
                                        <th>Check-in Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $activeMembers->data_seek(0);
                                    while ($member = $activeMembers->fetch_assoc()): 
                                    ?>
                                    <tr>
                                        <td class="attendance-checkbox">
                                            <input type="checkbox" name="present_members[]" 
                                                   value="<?php echo $member['group_member_id']; ?>"
                                                   <?php echo in_array($member['group_member_id'], $presentMembers) ? 'checked' : ''; ?>>
                                        </td>
                                        <td><?php echo $member['last_name'] . ', ' . $member['first_name']; ?></td>
                                        <td>
                                            <?php 
                                            // Find check-in time for this member
                                            $attendance->data_seek(0);
                                            $checkInTime = '';
                                            while ($record = $attendance->fetch_assoc()) {
                                                if ($record['group_member_id'] == $member['group_member_id'] && $record['attended']) {
                                                    $checkInTime = date('g:i A', strtotime($record['check_in_time']));
                                                    break;
                                                }
                                            }
                                            echo $checkInTime;
                                            $attendance->data_seek(0);
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            
                            <div class="form-actions" style="margin-top: 20px;">
                                <button type="submit" name="save_attendance" class="btn btn-primary">Save Attendance</button>
                                <button type="button" class="btn btn-secondary" onclick="selectAll()">Select All</button>
                                <button type="button" class="btn btn-secondary" onclick="deselectAll()">Deselect All</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div style="text-align: center; padding: 50px;">
                            <h3>Select a meeting to view or mark attendance</h3>
                            <p>Or create a new meeting using the form on the left.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        function selectAll() {
            document.querySelectorAll('input[name="present_members[]"]').forEach(cb => cb.checked = true);
        }
        
        function deselectAll() {
            document.querySelectorAll('input[name="present_members[]"]').forEach(cb => cb.checked = false);
        }
    </script>
</body>
</html>