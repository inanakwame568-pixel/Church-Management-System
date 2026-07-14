<?php
// admin/group_members.php
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

// Handle add member
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_member'])) {
    $memberId = $_POST['member_id'];
    $role = $_POST['role'];
    $joinedDate = $_POST['joined_date'];
    
    // Check if already a member
    $checkStmt = $db->prepare("SELECT group_member_id FROM group_members WHERE group_id = ? AND member_id = ?");
    $checkStmt->bind_param("ii", $groupId, $memberId);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows == 0) {
        $stmt = $db->prepare("
            INSERT INTO group_members (group_id, member_id, role, joined_date, added_by) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iissi", $groupId, $memberId, $role, $joinedDate, $_SESSION['user_id']);
        $stmt->execute();
        
        // Update member count
        $db->query("UPDATE groups SET current_members = current_members + 1 WHERE group_id = $groupId");
        
        $_SESSION['message'] = "Member added successfully!";
    }
}

// Handle remove member
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $memberId = $_GET['remove'];
    $stmt = $db->prepare("DELETE FROM group_members WHERE group_id = ? AND member_id = ?");
    $stmt->bind_param("ii", $groupId, $memberId);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Update member count
        $db->query("UPDATE groups SET current_members = current_members - 1 WHERE group_id = $groupId");
        $_SESSION['message'] = "Member removed successfully!";
    }
    
    header("Location: group_members.php?id=$groupId");
    exit();
}

// Get current members
$membersStmt = $db->prepare("
    SELECT gm.*, 
           m.first_name, m.last_name, m.email, m.phone, m.member_id
    FROM group_members gm
    JOIN members m ON gm.member_id = m.member_id
    WHERE gm.group_id = ?
    ORDER BY gm.role, m.last_name, m.first_name
");
$membersStmt->bind_param("i", $groupId);
$membersStmt->execute();
$members = $membersStmt->get_result();

// Get available members (not in group)
$availableStmt = $db->prepare("
    SELECT m.* 
    FROM members m
    WHERE m.membership_status = 'Active'
    AND m.member_id NOT IN (
        SELECT member_id FROM group_members WHERE group_id = ?
    )
    ORDER BY m.last_name, m.first_name
");
$availableStmt->bind_param("i", $groupId);
$availableStmt->execute();
$availableMembers = $availableStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Members - <?php echo htmlspecialchars($group['group_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .member-list {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .member-row {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .member-row:last-child {
            border-bottom: none;
        }
        
        .member-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .member-info {
            flex: 2;
        }
        
        .member-name {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .member-contact {
            font-size: 0.9rem;
            color: #666;
        }
        
        .member-role {
            width: 150px;
            text-align: center;
        }
        
        .role-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
        }
        
        .role-leader {
            background: var(--primary-color);
            color: white;
        }
        
        .role-co-leader {
            background: var(--secondary-color);
            color: white;
        }
        
        .role-member {
            background: var(--light-bg);
            color: var(--dark-text);
        }
        
        .member-joined {
            width: 100px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .member-actions {
            width: 100px;
            text-align: right;
        }
        
        .add-member-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
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
                <h1><?php echo htmlspecialchars($group['group_name']); ?> - Members</h1>
                <div class="header-actions">
                    <a href="group_view.php?id=<?php echo $groupId; ?>" class="btn btn-secondary">View Group</a>
                    <a href="groups.php" class="btn btn-secondary">Back to Groups</a>
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

            <!-- Current Members -->
            <div class="member-list">
                <h2>Current Members (<?php echo $members->num_rows; ?>)</h2>
                
                <?php if ($members->num_rows > 0): ?>
                    <?php while ($member = $members->fetch_assoc()): ?>
                        <div class="member-row">
                            <div class="member-avatar">
                                <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                            </div>
                            <div class="member-info">
                                <div class="member-name"><?php echo $member['first_name'] . ' ' . $member['last_name']; ?></div>
                                <div class="member-contact">
                                    <?php echo $member['email']; ?> • <?php echo $member['phone']; ?>
                                </div>
                            </div>
                            <div class="member-role">
                                <span class="role-badge role-<?php echo strtolower(str_replace('-', '', $member['role'])); ?>">
                                    <?php echo $member['role']; ?>
                                </span>
                            </div>
                            <div class="member-joined">
                                Joined: <?php echo date('M d, Y', strtotime($member['joined_date'])); ?>
                            </div>
                            <div class="member-actions">
                                <a href="?id=<?php echo $groupId; ?>&remove=<?php echo $member['member_id']; ?>" 
                                   class="btn-small btn-danger"
                                   onclick="return confirm('Remove this member from the group?')">Remove</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; padding: 30px; color: #666;">
                        No members in this group yet. Add members using the form below.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Add Member Form -->
            <?php if ($availableMembers->num_rows > 0): ?>
                <div class="add-member-section">
                    <h2>Add New Member</h2>
                    <form method="POST" class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <select name="member_id" required>
                                <option value="">Select Member</option>
                                <?php while ($member = $availableMembers->fetch_assoc()): ?>
                                    <option value="<?php echo $member['member_id']; ?>">
                                        <?php echo $member['last_name'] . ', ' . $member['first_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <select name="role" required>
                                <option value="Member">Member</option>
                                <option value="Assistant">Assistant</option>
                                <option value="Secretary">Secretary</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <input type="date" name="joined_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="add_member" class="btn btn-primary">Add Member</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/script.js"></script>
</body>
</html>