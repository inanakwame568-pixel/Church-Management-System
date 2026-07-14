<?php
// admin/group_add.php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
requireLogin();

$db = Database::getInstance()->getConnection();
$editMode = isset($_GET['id']);
$groupId = $editMode ? $_GET['id'] : null;
$message = '';
$error = '';

// Get group data if editing
$groupData = [];
if ($editMode) {
    $stmt = $db->prepare("SELECT * FROM groups WHERE group_id = ?");
    $stmt->bind_param("i", $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    $groupData = $result->fetch_assoc();
    
    if (!$groupData) {
        header('Location: groups.php');
        exit();
    }
}

// Get potential leaders (members who can lead groups)
$leaders = $db->query("
    SELECT member_id, first_name, last_name 
    FROM members 
    WHERE membership_status = 'Active' 
    ORDER BY last_name, first_name
");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $groupName = $db->real_escape_string($_POST['group_name']);
    $groupType = $_POST['group_type'];
    $description = $db->real_escape_string($_POST['description']);
    $leaderId = !empty($_POST['leader_id']) ? $_POST['leader_id'] : null;
    $coLeaderId = !empty($_POST['co_leader_id']) ? $_POST['co_leader_id'] : null;
    $meetingDay = $_POST['meeting_day'];
    $meetingTime = $_POST['meeting_time'];
    $meetingLocation = $db->real_escape_string($_POST['meeting_location']);
    $meetingFrequency = $_POST['meeting_frequency'];
    $maxCapacity = !empty($_POST['max_capacity']) ? $_POST['max_capacity'] : 0;
    $status = $_POST['status'];
    $visibility = $_POST['visibility'];
    
    if ($editMode) {
        // Update existing group
        $stmt = $db->prepare("
            UPDATE groups SET 
                group_name = ?, group_type = ?, description = ?, 
                leader_id = ?, co_leader_id = ?, meeting_day = ?, 
                meeting_time = ?, meeting_location = ?, meeting_frequency = ?,
                max_capacity = ?, status = ?, visibility = ?
            WHERE group_id = ?
        ");
        $stmt->bind_param(
            "sssiissssissi",
            $groupName, $groupType, $description,
            $leaderId, $coLeaderId, $meetingDay,
            $meetingTime, $meetingLocation, $meetingFrequency,
            $maxCapacity, $status, $visibility,
            $groupId
        );
    } else {
        // Insert new group
        $stmt = $db->prepare("
            INSERT INTO groups (
                group_name, group_type, description, leader_id, co_leader_id,
                meeting_day, meeting_time, meeting_location, meeting_frequency,
                max_capacity, status, visibility, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssiissssissi",
            $groupName, $groupType, $description,
            $leaderId, $coLeaderId, $meetingDay,
            $meetingTime, $meetingLocation, $meetingFrequency,
            $maxCapacity, $status, $visibility,
            $_SESSION['user_id']
        );
    }
    
    if ($stmt->execute()) {
        $_SESSION['message'] = $editMode ? "Group updated successfully!" : "Group created successfully!";
        $_SESSION['message_type'] = 'success';
        header('Location: groups.php');
        exit();
    } else {
        $error = "Error: " . $db->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $editMode ? 'Edit' : 'Add'; ?> Group - <?php echo APP_NAME; ?></title>
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
                <h1><?php echo $editMode ? 'Edit Group' : 'Create New Group'; ?></h1>
                <div class="header-actions">
                    <a href="groups.php" class="btn btn-secondary">Back to Groups</a>
                </div>
            </header>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" class="form-container" id="groupForm">
                <!-- Basic Information -->
                <div class="form-section">
                    <h2>Basic Information</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="group_name">Group Name *</label>
                            <input type="text" id="group_name" name="group_name" 
                                   value="<?php echo $editMode ? htmlspecialchars($groupData['group_name']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="group_type">Group Type *</label>
                            <select id="group_type" name="group_type" required>
                                <option value="">Select Type</option>
                                <option value="Small Group" <?php echo ($editMode && $groupData['group_type'] == 'Small Group') ? 'selected' : ''; ?>>Small Group</option>
                                <option value="Choir" <?php echo ($editMode && $groupData['group_type'] == 'Choir') ? 'selected' : ''; ?>>Choir</option>
                                <option value="Ministry" <?php echo ($editMode && $groupData['group_type'] == 'Ministry') ? 'selected' : ''; ?>>Ministry</option>
                                <option value="Committee" <?php echo ($editMode && $groupData['group_type'] == 'Committee') ? 'selected' : ''; ?>>Committee</option>
                                <option value="Class" <?php echo ($editMode && $groupData['group_type'] == 'Class') ? 'selected' : ''; ?>>Class</option>
                                <option value="Prayer Team" <?php echo ($editMode && $groupData['group_type'] == 'Prayer Team') ? 'selected' : ''; ?>>Prayer Team</option>
                                <option value="Youth" <?php echo ($editMode && $groupData['group_type'] == 'Youth') ? 'selected' : ''; ?>>Youth</option>
                                <option value="Children" <?php echo ($editMode && $groupData['group_type'] == 'Children') ? 'selected' : ''; ?>>Children</option>
                                <option value="Men" <?php echo ($editMode && $groupData['group_type'] == 'Men') ? 'selected' : ''; ?>>Men</option>
                                <option value="Women" <?php echo ($editMode && $groupData['group_type'] == 'Women') ? 'selected' : ''; ?>>Women</option>
                                <option value="Seniors" <?php echo ($editMode && $groupData['group_type'] == 'Seniors') ? 'selected' : ''; ?>>Seniors</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"><?php echo $editMode ? htmlspecialchars($groupData['description']) : ''; ?></textarea>
                    </div>
                </div>

                <!-- Leadership -->
                <div class="form-section">
                    <h2>Leadership</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="leader_id">Group Leader</label>
                            <select id="leader_id" name="leader_id">
                                <option value="">Select Leader</option>
                                <?php while ($leader = $leaders->fetch_assoc()): ?>
                                    <option value="<?php echo $leader['member_id']; ?>"
                                            <?php echo ($editMode && $groupData['leader_id'] == $leader['member_id']) ? 'selected' : ''; ?>>
                                        <?php echo $leader['last_name'] . ', ' . $leader['first_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="co_leader_id">Co-Leader (Optional)</label>
                            <select id="co_leader_id" name="co_leader_id">
                                <option value="">Select Co-Leader</option>
                                <?php 
                                $leaders->data_seek(0);
                                while ($leader = $leaders->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $leader['member_id']; ?>"
                                            <?php echo ($editMode && $groupData['co_leader_id'] == $leader['member_id']) ? 'selected' : ''; ?>>
                                        <?php echo $leader['last_name'] . ', ' . $leader['first_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Meeting Information -->
                <div class="form-section">
                    <h2>Meeting Information</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="meeting_day">Meeting Day</label>
                            <select id="meeting_day" name="meeting_day">
                                <option value="">Select Day</option>
                                <option value="Monday" <?php echo ($editMode && $groupData['meeting_day'] == 'Monday') ? 'selected' : ''; ?>>Monday</option>
                                <option value="Tuesday" <?php echo ($editMode && $groupData['meeting_day'] == 'Tuesday') ? 'selected' : ''; ?>>Tuesday</option>
                                <option value="Wednesday" <?php echo ($editMode && $groupData['meeting_day'] == 'Wednesday') ? 'selected' : ''; ?>>Wednesday</option>
                                <option value="Thursday" <?php echo ($editMode && $groupData['meeting_day'] == 'Thursday') ? 'selected' : ''; ?>>Thursday</option>
                                <option value="Friday" <?php echo ($editMode && $groupData['meeting_day'] == 'Friday') ? 'selected' : ''; ?>>Friday</option>
                                <option value="Saturday" <?php echo ($editMode && $groupData['meeting_day'] == 'Saturday') ? 'selected' : ''; ?>>Saturday</option>
                                <option value="Sunday" <?php echo ($editMode && $groupData['meeting_day'] == 'Sunday') ? 'selected' : ''; ?>>Sunday</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="meeting_time">Meeting Time</label>
                            <input type="time" id="meeting_time" name="meeting_time" 
                                   value="<?php echo $editMode ? $groupData['meeting_time'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="meeting_frequency">Frequency</label>
                            <select id="meeting_frequency" name="meeting_frequency">
                                <option value="Weekly" <?php echo ($editMode && $groupData['meeting_frequency'] == 'Weekly') ? 'selected' : ''; ?>>Weekly</option>
                                <option value="Bi-weekly" <?php echo ($editMode && $groupData['meeting_frequency'] == 'Bi-weekly') ? 'selected' : ''; ?>>Bi-weekly</option>
                                <option value="Monthly" <?php echo ($editMode && $groupData['meeting_frequency'] == 'Monthly') ? 'selected' : ''; ?>>Monthly</option>
                                <option value="Quarterly" <?php echo ($editMode && $groupData['meeting_frequency'] == 'Quarterly') ? 'selected' : ''; ?>>Quarterly</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <label for="meeting_location">Meeting Location</label>
                            <input type="text" id="meeting_location" name="meeting_location" 
                                   value="<?php echo $editMode ? htmlspecialchars($groupData['meeting_location']) : ''; ?>">
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <label for="max_capacity">Max Capacity (0 for unlimited)</label>
                            <input type="number" id="max_capacity" name="max_capacity" min="0" 
                                   value="<?php echo $editMode ? $groupData['max_capacity'] : '0'; ?>">
                        </div>
                    </div>
                </div>

                <!-- Status and Visibility -->
                <div class="form-section">
                    <h2>Settings</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="Active" <?php echo ($editMode && $groupData['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo ($editMode && $groupData['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Forming" <?php echo ($editMode && $groupData['status'] == 'Forming') ? 'selected' : ''; ?>>Forming</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="visibility">Visibility</label>
                            <select id="visibility" name="visibility">
                                <option value="Public" <?php echo ($editMode && $groupData['visibility'] == 'Public') ? 'selected' : ''; ?>>Public</option>
                                <option value="Private" <?php echo ($editMode && $groupData['visibility'] == 'Private') ? 'selected' : ''; ?>>Private</option>
                                <option value="By Invitation" <?php echo ($editMode && $groupData['visibility'] == 'By Invitation') ? 'selected' : ''; ?>>By Invitation</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editMode ? 'Update Group' : 'Create Group'; ?>
                    </button>
                    <a href="groups.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </main>
    </div>

    <script src="../assets/js/script.js"></script>
</body>
</html>