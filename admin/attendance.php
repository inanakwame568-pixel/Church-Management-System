<?php
// admin/attendance.php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
requireLogin();

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Handle attendance marking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance'])) {
    $serviceDate = $_POST['service_date'];
    $serviceType = $_POST['service_type'];
    $presentMembers = $_POST['present_members'] ?? [];
    
    // Delete existing attendance for this date/service
    $deleteStmt = $db->prepare("DELETE FROM attendance WHERE service_date = ? AND service_type = ?");
    $deleteStmt->bind_param("ss", $serviceDate, $serviceType);
    $deleteStmt->execute();
    
    // Insert new attendance records
    $insertStmt = $db->prepare("INSERT INTO attendance (member_id, service_date, service_type, attended, recorded_by) VALUES (?, ?, ?, 1, ?)");
    
    foreach ($presentMembers as $memberId) {
        $insertStmt->bind_param("issi", $memberId, $serviceDate, $serviceType, $_SESSION['user_id']);
        $insertStmt->execute();
    }
    
    $message = "Attendance recorded for " . count($presentMembers) . " members.";
}

// Get active members
$members = $db->query("
    SELECT member_id, first_name, last_name 
    FROM members 
    WHERE membership_status = 'Active' 
    ORDER BY last_name, first_name
");

// Get today's attendance if exists
$today = date('Y-m-d');
$serviceType = $_GET['service_type'] ?? 'Sunday Service';
$attendanceQuery = $db->prepare("
    SELECT member_id 
    FROM attendance 
    WHERE service_date = ? AND service_type = ? AND attended = 1
");
$attendanceQuery->bind_param("ss", $today, $serviceType);
$attendanceQuery->execute();
$attendanceResult = $attendanceQuery->get_result();
$presentToday = [];
while ($row = $attendanceResult->fetch_assoc()) {
    $presentToday[] = $row['member_id'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- ... sidebar content ... -->
        </aside>

        <main class="main-content">
            <header class="content-header">
                <h1>Attendance Tracking</h1>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Attendance Form -->
            <div class="card">
                <div class="card-header">
                    <h2>Mark Attendance - <?php echo date('F j, Y'); ?></h2>
                </div>
                <div class="card-body">
                    <form method="POST" id="attendanceForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="service_type">Service Type:</label>
                                <select name="service_type" id="service_type" required>
                                    <option value="Sunday Service" <?php echo $serviceType == 'Sunday Service' ? 'selected' : ''; ?>>Sunday Service</option>
                                    <option value="Wednesday Service" <?php echo $serviceType == 'Wednesday Service' ? 'selected' : ''; ?>>Wednesday Service</option>
                                    <option value="Bible Study" <?php echo $serviceType == 'Bible Study' ? 'selected' : ''; ?>>Bible Study</option>
                                    <option value="Prayer Meeting" <?php echo $serviceType == 'Prayer Meeting' ? 'selected' : ''; ?>>Prayer Meeting</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="service_date">Date:</label>
                                <input type="date" name="service_date" id="service_date" 
                                       value="<?php echo $today; ?>" required>
                            </div>
                        </div>

                        <div class="attendance-list">
                            <div class="attendance-header">
                                <h3>Members</h3>
                                <div class="attendance-controls">
                                    <button type="button" onclick="selectAll()">Select All</button>
                                    <button type="button" onclick="deselectAll()">Deselect All</button>
                                </div>
                            </div>

                            <table class="attendance-table">
                                <thead>
                                    <tr>
                                        <th width="50">Present</th>
                                        <th>Name</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($member = $members->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="present_members[]" 
                                                   value="<?php echo $member['member_id']; ?>"
                                                   class="member-checkbox"
                                                   <?php echo in_array($member['member_id'], $presentToday) ? 'checked' : ''; ?>>
                                        </td>
                                        <td><?php echo $member['last_name'] . ', ' . $member['first_name']; ?></td>
                                        <td>
                                            <button type="button" class="btn-small" 
                                                    onclick="quickAdd(<?php echo $member['member_id']; ?>)">
                                                Quick Add
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="mark_attendance" class="btn btn-primary">
                                Save Attendance
                            </button>
                            <a href="attendance_report.php" class="btn btn-secondary">View Reports</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        function selectAll() {
            document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = true);
        }
        
        function deselectAll() {
            document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = false);
        }
        
        function quickAdd(memberId) {
            document.getElementById('quickAdd_' + memberId).checked = true;
        }
        
        // Auto-save functionality
        let autoSaveTimer;
        document.getElementById('attendanceForm').addEventListener('change', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                // You could implement auto-save via AJAX here
                console.log('Changes detected - ready to save');
            }, 2000);
        });
    </script>
</body>
</html>