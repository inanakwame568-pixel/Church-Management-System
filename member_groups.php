<?php
// member_groups.php - Member Groups View and Management
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/member_auth.php';

// Require member login
requireMember();

// Get database connection
$db = Database::getInstance()->getConnection();

// Get current member info
$member_id = getCurrentMemberId();
$user_name = getCurrentUserName();

// Handle join group request
$message = '';
$error = '';

if (isset($_GET['join']) && is_numeric($_GET['join'])) {
    $group_id = (int)$_GET['join'];
    
    // Check if already in group
    $check_stmt = $db->prepare("SELECT group_member_id FROM group_members WHERE group_id = ? AND member_id = ?");
    $check_stmt->bind_param("ii", $group_id, $member_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error = "You are already a member of this group.";
    } else {
        // Check if group has capacity
        $capacity_check = $db->prepare("SELECT max_capacity, 
                                        (SELECT COUNT(*) FROM group_members WHERE group_id = ? AND status = 'Active') as current_members
                                        FROM `groups` WHERE group_id = ?");
        $capacity_check->bind_param("ii", $group_id, $group_id);
        $capacity_check->execute();
        $group_info = $capacity_check->get_result()->fetch_assoc();
        
        if ($group_info['max_capacity'] > 0 && $group_info['current_members'] >= $group_info['max_capacity']) {
            $error = "Sorry, this group has reached its maximum capacity.";
        } else {
            // Join group
            $join_stmt = $db->prepare("INSERT INTO group_members (group_id, member_id, joined_date, role, status) VALUES (?, ?, CURDATE(), 'Member', 'Active')");
            $join_stmt->bind_param("ii", $group_id, $member_id);
            
            if ($join_stmt->execute()) {
                // Update current members count
                $update_count = $db->prepare("UPDATE `groups` SET current_members = current_members + 1 WHERE group_id = ?");
                $update_count->bind_param("i", $group_id);
                $update_count->execute();
                
                $message = "Successfully joined the group!";
            } else {
                $error = "Failed to join group. Please try again.";
            }
        }
    }
}

// Handle leave group request
if (isset($_GET['leave']) && is_numeric($_GET['leave'])) {
    $group_id = (int)$_GET['leave'];
    
    // Check if user is leader (can't leave if leader)
    $check_leader = $db->prepare("SELECT role FROM group_members WHERE group_id = ? AND member_id = ?");
    $check_leader->bind_param("ii", $group_id, $member_id);
    $check_leader->execute();
    $member_info = $check_leader->get_result()->fetch_assoc();
    
    if ($member_info['role'] == 'Leader' || $member_info['role'] == 'Co-Leader') {
        $error = "Group leaders cannot leave. Please transfer leadership first or contact an administrator.";
    } else {
        // Leave group
        $leave_stmt = $db->prepare("DELETE FROM group_members WHERE group_id = ? AND member_id = ?");
        $leave_stmt->bind_param("ii", $group_id, $member_id);
        
        if ($leave_stmt->execute()) {
            // Update current members count
            $update_count = $db->prepare("UPDATE `groups` SET current_members = current_members - 1 WHERE group_id = ?");
            $update_count->bind_param("i", $group_id);
            $update_count->execute();
            
            $message = "You have left the group.";
        } else {
            $error = "Failed to leave group. Please try again.";
        }
    }
}

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'mygroups';
$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get member's groups
$mygroups_query = "SELECT g.*, gm.role, gm.joined_date,
                          (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id AND status = 'Active') as member_count,
                          CONCAT(l.first_name, ' ', l.last_name) as leader_name
                   FROM `groups` g
                   JOIN group_members gm ON g.group_id = gm.group_id
                   LEFT JOIN members l ON g.leader_id = l.member_id
                   WHERE gm.member_id = ? AND gm.status = 'Active'
                   ORDER BY g.group_name";
$mygroups_stmt = $db->prepare($mygroups_query);
$mygroups_stmt->bind_param("i", $member_id);
$mygroups_stmt->execute();
$mygroups = $mygroups_stmt->get_result();

// Get available groups (not joined)
$available_query = "SELECT g.*, 
                           (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id AND status = 'Active') as member_count,
                           CONCAT(l.first_name, ' ', l.last_name) as leader_name,
                           CONCAT(cl.first_name, ' ', cl.last_name) as co_leader_name
                    FROM `groups` g
                    LEFT JOIN members l ON g.leader_id = l.member_id
                    LEFT JOIN members cl ON g.co_leader_id = cl.member_id
                    WHERE g.status = 'Active' 
                    AND g.group_id NOT IN (SELECT group_id FROM group_members WHERE member_id = ?)
                    AND (g.max_capacity = 0 OR g.max_capacity > (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id AND status = 'Active'))";

$available_params = [$member_id];
$available_types = "i";

// Add category filter
if ($category != 'all') {
    $available_query .= " AND g.group_type = ?";
    $available_params[] = $category;
    $available_types .= "s";
}

// Add search filter
if (!empty($search)) {
    $available_query .= " AND (g.group_name LIKE ? OR g.description LIKE ? OR g.meeting_location LIKE ?)";
    $search_term = "%$search%";
    $available_params[] = $search_term;
    $available_params[] = $search_term;
    $available_params[] = $search_term;
    $available_types .= "sss";
}

$available_query .= " ORDER BY g.group_name";

$available_stmt = $db->prepare($available_query);
if (!empty($available_params)) {
    $available_stmt->bind_param($available_types, ...$available_params);
}
$available_stmt->execute();
$available_groups = $available_stmt->get_result();

// Get all groups (for discovery)
$all_groups_query = "SELECT g.*, 
                            (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id AND status = 'Active') as member_count,
                            CONCAT(l.first_name, ' ', l.last_name) as leader_name,
                            CASE WHEN gm.member_id IS NOT NULL THEN 1 ELSE 0 END as is_member
                     FROM `groups` g
                     LEFT JOIN members l ON g.leader_id = l.member_id
                     LEFT JOIN group_members gm ON g.group_id = gm.group_id AND gm.member_id = ?
                     WHERE g.status = 'Active'";
$all_groups_params = [$member_id];
$all_groups_types = "i";

if ($category != 'all') {
    $all_groups_query .= " AND g.group_type = ?";
    $all_groups_params[] = $category;
    $all_groups_types .= "s";
}

if (!empty($search)) {
    $all_groups_query .= " AND (g.group_name LIKE ? OR g.description LIKE ? OR g.meeting_location LIKE ?)";
    $search_term = "%$search%";
    $all_groups_params[] = $search_term;
    $all_groups_params[] = $search_term;
    $all_groups_params[] = $search_term;
    $all_groups_types .= "sss";
}

$all_groups_query .= " ORDER BY g.group_name";

$all_groups_stmt = $db->prepare($all_groups_query);
if (!empty($all_groups_params)) {
    $all_groups_stmt->bind_param($all_groups_types, ...$all_groups_params);
}
$all_groups_stmt->execute();
$all_groups = $all_groups_stmt->get_result();

// Get unique group categories for filter
$categories = $db->query("SELECT DISTINCT group_type FROM `groups` WHERE group_type IS NOT NULL ORDER BY group_type");

// Get statistics
$stats = [];

// Total groups member is in
$stats['my_groups'] = $mygroups->num_rows;

// Total available groups
$stats['available_groups'] = $available_groups->num_rows;

// Get upcoming meetings for member's groups
$upcoming_meetings = $db->prepare("
    SELECT gm.meeting_id, gm.meeting_date, gm.topic, g.group_name, g.group_id
    FROM group_meetings gm
    JOIN `groups` g ON gm.group_id = g.group_id
    JOIN group_members mem ON g.group_id = mem.group_id
    WHERE mem.member_id = ? AND mem.status = 'Active' AND gm.meeting_date >= CURDATE()
    ORDER BY gm.meeting_date ASC
    LIMIT 5
");
$upcoming_meetings->bind_param("i", $member_id);
$upcoming_meetings->execute();
$upcoming_meetings_result = $upcoming_meetings->get_result();

// Set page title
$page_title = "Small Groups";

// Include member header
include 'member_header.php';
?>

<div class="fade-in">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-users me-2 text-primary"></i>Small Groups & Ministries</h2>
            <p class="text-muted mb-0">Connect with others and grow in your faith</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary p-3">
                <i class="fas fa-user-friends me-2"></i>
                <?php echo $stats['my_groups']; ?> My Groups
            </span>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['my_groups']; ?></div>
                <div class="stat-label">Groups You're In</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['available_groups']; ?></div>
                <div class="stat-label">Groups You Can Join</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $upcoming_meetings_result->num_rows; ?></div>
                <div class="stat-label">Upcoming Meetings</div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="groupTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $filter == 'mygroups' ? 'active' : ''; ?>" 
                    id="mygroups-tab" data-bs-toggle="tab" data-bs-target="#mygroups" 
                    type="button" role="tab">
                <i class="fas fa-user-check me-2"></i>My Groups
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $filter == 'available' ? 'active' : ''; ?>" 
                    id="available-tab" data-bs-toggle="tab" data-bs-target="#available" 
                    type="button" role="tab">
                <i class="fas fa-door-open me-2"></i>Available to Join
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $filter == 'all' ? 'active' : ''; ?>" 
                    id="all-tab" data-bs-toggle="tab" data-bs-target="#all" 
                    type="button" role="tab">
                <i class="fas fa-globe me-2"></i>All Groups
            </button>
        </li>
    </ul>

    <!-- Filter Bar -->
    <div class="member-card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <input type="hidden" name="filter" id="filterInput" value="<?php echo $filter; ?>">
                
                <div class="col-md-4">
                    <select name="category" class="form-select">
                        <option value="all">All Categories</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['group_type']; ?>" <?php echo $category == $cat['group_type'] ? 'selected' : ''; ?>>
                                <?php echo $cat['group_type']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-5">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search groups..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-member-primary w-100">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content" id="groupTabsContent">
        <!-- My Groups Tab -->
        <div class="tab-pane fade <?php echo $filter == 'mygroups' ? 'show active' : ''; ?>" id="mygroups" role="tabpanel">
            <?php if ($mygroups->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while ($group = $mygroups->fetch_assoc()): ?>
                        <div class="col-lg-6">
                            <div class="member-card group-card border-success border-2">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="card-title fw-bold mb-1">
                                                <?php echo htmlspecialchars($group['group_name']); ?>
                                            </h5>
                                            <span class="badge bg-info"><?php echo $group['group_type']; ?></span>
                                            <span class="badge bg-<?php 
                                                echo $group['role'] == 'Leader' ? 'warning' : 
                                                    ($group['role'] == 'Co-Leader' ? 'success' : 'secondary'); 
                                            ?> ms-2">
                                                <i class="fas fa-<?php 
                                                    echo $group['role'] == 'Leader' ? 'crown' : 
                                                        ($group['role'] == 'Co-Leader' ? 'star' : 'user'); 
                                                ?> me-1"></i>
                                                <?php echo $group['role']; ?>
                                            </span>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="group_view.php?id=<?php echo $group['group_id']; ?>">
                                                    <i class="fas fa-eye me-2"></i>View Details
                                                </a></li>
                                                <li><a class="dropdown-item" href="group_schedule.php?id=<?php echo $group['group_id']; ?>">
                                                    <i class="fas fa-calendar me-2"></i>View Schedule
                                                </a></li>
                                                <li><a class="dropdown-item" href="group_roster.php?id=<?php echo $group['group_id']; ?>">
                                                    <i class="fas fa-users me-2"></i>View Members
                                                </a></li>
                                                <?php if ($group['role'] != 'Leader' && $group['role'] != 'Co-Leader'): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="?leave=<?php echo $group['group_id']; ?>&filter=mygroups" 
                                                           onclick="return confirm('Are you sure you want to leave this group?')">
                                                            <i class="fas fa-sign-out-alt me-2"></i>Leave Group
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($group['description'])): ?>
                                        <p class="card-text text-muted small mb-3">
                                            <?php echo htmlspecialchars(substr($group['description'], 0, 150)); ?>
                                            <?php if (strlen($group['description']) > 150): ?>...<?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="group-details mb-3">
                                        <div class="row g-2 small">
                                            <?php if ($group['leader_name']): ?>
                                                <div class="col-6">
                                                    <i class="fas fa-user-tie me-1 text-primary"></i>
                                                    <strong>Leader:</strong> <?php echo htmlspecialchars($group['leader_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="col-6">
                                                <i class="fas fa-users me-1 text-success"></i>
                                                <strong>Members:</strong> <?php echo $group['member_count']; ?>
                                                <?php if ($group['max_capacity'] > 0): ?>
                                                    / <?php echo $group['max_capacity']; ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($group['meeting_day']): ?>
                                                <div class="col-6">
                                                    <i class="fas fa-clock me-1 text-warning"></i>
                                                    <strong>Meets:</strong> <?php echo $group['meeting_day']; ?>s
                                                    <?php if ($group['meeting_time']): ?>
                                                        <?php echo date('g:i A', strtotime($group['meeting_time'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($group['meeting_location']): ?>
                                                <div class="col-6">
                                                    <i class="fas fa-map-marker-alt me-1 text-danger"></i>
                                                    <strong>Location:</strong> <?php echo htmlspecialchars($group['meeting_location']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="col-6">
                                                <i class="fas fa-calendar-plus me-1 text-info"></i>
                                                <strong>Joined:</strong> <?php echo date('M d, Y', strtotime($group['joined_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Next Meeting (if available) -->
                                    <?php
                                    $next_meeting = $db->prepare("
                                        SELECT meeting_date, topic FROM group_meetings 
                                        WHERE group_id = ? AND meeting_date >= CURDATE() 
                                        ORDER BY meeting_date ASC LIMIT 1
                                    ");
                                    $next_meeting->bind_param("i", $group['group_id']);
                                    $next_meeting->execute();
                                    $next = $next_meeting->get_result()->fetch_assoc();
                                    ?>
                                    
                                    <?php if ($next): ?>
                                        <div class="alert alert-info py-2 mb-0 mt-3">
                                            <small>
                                                <i class="fas fa-bell me-1"></i>
                                                <strong>Next Meeting:</strong> <?php echo date('M d', strtotime($next['meeting_date'])); ?>
                                                <?php if ($next['topic']): ?> - <?php echo $next['topic']; ?><?php endif; ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="member-card text-center py-5">
                    <i class="fas fa-users-slash fa-4x text-muted mb-3"></i>
                    <h5>You haven't joined any groups yet</h5>
                    <p class="text-muted mb-3">Browse available groups and join one that interests you!</p>
                    <button class="btn btn-member-primary" onclick="document.getElementById('available-tab').click()">
                        <i class="fas fa-door-open me-2"></i>Browse Groups
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Available Groups Tab -->
        <div class="tab-pane fade <?php echo $filter == 'available' ? 'show active' : ''; ?>" id="available" role="tabpanel">
            <?php if ($available_groups->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while ($group = $available_groups->fetch_assoc()): 
                        $capacity_percentage = $group['max_capacity'] > 0 ? round(($group['member_count'] / $group['max_capacity']) * 100) : 0;
                        $spots_left = $group['max_capacity'] > 0 ? $group['max_capacity'] - $group['member_count'] : 999;
                    ?>
                        <div class="col-lg-6">
                            <div class="member-card group-card">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title fw-bold mb-0">
                                            <?php echo htmlspecialchars($group['group_name']); ?>
                                        </h5>
                                        <span class="badge bg-info"><?php echo $group['group_type']; ?></span>
                                    </div>
                                    
                                    <?php if (!empty($group['description'])): ?>
                                        <p class="card-text text-muted small mb-3">
                                            <?php echo htmlspecialchars(substr($group['description'], 0, 150)); ?>
                                            <?php if (strlen($group['description']) > 150): ?>...<?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="group-details mb-3">
                                        <div class="row g-2 small">
                                            <?php if ($group['leader_name']): ?>
                                                <div class="col-6">
                                                    <i class="fas fa-user-tie me-1 text-primary"></i>
                                                    <strong>Leader:</strong> <?php echo htmlspecialchars($group['leader_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="col-6">
                                                <i class="fas fa-users me-1 text-success"></i>
                                                <strong>Members:</strong> <?php echo $group['member_count']; ?>
                                                <?php if ($group['max_capacity'] > 0): ?>
                                                    / <?php echo $group['max_capacity']; ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($group['meeting_day']): ?>
                                                <div class="col-6">
                                                    <i class="fas fa-clock me-1 text-warning"></i>
                                                    <strong>Meets:</strong> <?php echo $group['meeting_day']; ?>s
                                                    <?php if ($group['meeting_time']): ?>
                                                        <?php echo date('g:i A', strtotime($group['meeting_time'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($group['meeting_location']): ?>
                                                <div class="col-6">
                                                    <i class="fas fa-map-marker-alt me-1 text-danger"></i>
                                                    <?php echo htmlspecialchars($group['meeting_location']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Capacity Indicator -->
                                    <?php if ($group['max_capacity'] > 0): ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>Capacity</span>
                                                <span class="<?php 
                                                    if ($spots_left <= 3) echo 'text-danger fw-bold';
                                                    elseif ($spots_left <= 5) echo 'text-warning';
                                                ?>">
                                                    <?php echo $spots_left; ?> spots left
                                                </span>
                                            </div>
                                            <div class="progress" style="height: 5px;">
                                                <div class="progress-bar <?php 
                                                    if ($capacity_percentage >= 90) echo 'bg-danger';
                                                    elseif ($capacity_percentage >= 75) echo 'bg-warning';
                                                    else echo 'bg-success';
                                                ?>" style="width: <?php echo $capacity_percentage; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-grid">
                                        <a href="?join=<?php echo $group['group_id']; ?>&filter=available&category=<?php echo $category; ?>&search=<?php echo urlencode($search); ?>" 
                                           class="btn btn-member-primary"
                                           onclick="return confirm('Join this group?')">
                                            <i class="fas fa-door-open me-2"></i>Join Group
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="member-card text-center py-5">
                    <i class="fas fa-search fa-4x text-muted mb-3"></i>
                    <h5>No groups available to join</h5>
                    <p class="text-muted mb-0">Try adjusting your filters or check back later.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- All Groups Tab -->
        <div class="tab-pane fade <?php echo $filter == 'all' ? 'show active' : ''; ?>" id="all" role="tabpanel">
            <?php if ($all_groups->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while ($group = $all_groups->fetch_assoc()): ?>
                        <div class="col-lg-6">
                            <div class="member-card group-card <?php echo $group['is_member'] ? 'border-success border-2' : ''; ?>">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title fw-bold mb-0">
                                            <?php echo htmlspecialchars($group['group_name']); ?>
                                            <?php if ($group['is_member']): ?>
                                                <span class="badge bg-success ms-2">You're In</span>
                                            <?php endif; ?>
                                        </h5>
                                        <span class="badge bg-info"><?php echo $group['group_type']; ?></span>
                                    </div>
                                    
                                    <?php if (!empty($group['description'])): ?>
                                        <p class="card-text text-muted small mb-3">
                                            <?php echo htmlspecialchars(substr($group['description'], 0, 150)); ?>
                                            <?php if (strlen($group['description']) > 150): ?>...<?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="group-details mb-3">
                                        <div class="row g-2 small">
                                            <?php if ($group['leader_name']): ?>
                                                <div class="col-6">
                                                    <i class="fas fa-user-tie me-1 text-primary"></i>
                                                    <?php echo htmlspecialchars($group['leader_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="col-6">
                                                <i class="fas fa-users me-1 text-success"></i>
                                                <?php echo $group['member_count']; ?> members
                                            </div>
                                            
                                            <?php if ($group['meeting_day']): ?>
                                                <div class="col-6">
                                                    <i class="fas fa-clock me-1 text-warning"></i>
                                                    <?php echo $group['meeting_day']; ?>s
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($group['meeting_location']): ?>
                                                <div class="col-6">
                                                    <i class="fas fa-map-marker-alt me-1 text-danger"></i>
                                                    <?php echo htmlspecialchars($group['meeting_location']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <?php if ($group['is_member']): ?>
                                            <a href="group_view.php?id=<?php echo $group['group_id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-eye me-2"></i>View Group
                                            </a>
                                        <?php else: ?>
                                            <a href="?join=<?php echo $group['group_id']; ?>&filter=all&category=<?php echo $category; ?>&search=<?php echo urlencode($search); ?>" 
                                               class="btn btn-member-primary"
                                               onclick="return confirm('Join this group?')">
                                                <i class="fas fa-door-open me-2"></i>Join Group
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="member-card text-center py-5">
                    <i class="fas fa-users-slash fa-4x text-muted mb-3"></i>
                    <h5>No groups found</h5>
                    <p class="text-muted mb-0">Try adjusting your filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Group Meetings -->
    <?php if ($upcoming_meetings_result->num_rows > 0): ?>
        <div class="member-card mt-4">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2 text-warning"></i>Upcoming Group Meetings</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php while ($meeting = $upcoming_meetings_result->fetch_assoc()): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($meeting['group_name']); ?></h6>
                                    <p class="small text-muted mb-0">
                                        <i class="fas fa-calendar me-1"></i><?php echo date('l, F j, Y', strtotime($meeting['meeting_date'])); ?>
                                        <?php if ($meeting['topic']): ?>
                                            <span class="mx-2">|</span>
                                            <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($meeting['topic']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <a href="group_view.php?id=<?php echo $meeting['group_id']; ?>" class="btn btn-sm btn-outline-primary">
                                    Details
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Group card specific styles */
.group-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    overflow: hidden;
}

.group-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
}

.border-success {
    border-left: 4px solid #10b981 !important;
}

/* Group details */
.group-details {
    background: #f8fafc;
    padding: 12px;
    border-radius: 8px;
    font-size: 0.9rem;
}

/* Capacity progress */
.progress {
    background-color: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.3s ease;
}

/* Tab navigation */
.nav-tabs .nav-link {
    color: #64748b;
    border: none;
    padding: 12px 24px;
    font-weight: 500;
}

.nav-tabs .nav-link:hover {
    border: none;
    color: #4361ee;
}

.nav-tabs .nav-link.active {
    color: #4361ee;
    background: transparent;
    border-bottom: 3px solid #4361ee;
}

/* Badge styles */
.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

/* Responsive */
@media (max-width: 768px) {
    .nav-tabs .nav-link {
        padding: 8px 12px;
        font-size: 0.9rem;
    }
    
    .group-details .row .col-6 {
        width: 100%;
        margin-bottom: 5px;
    }
}

/* Animation for new groups */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.group-card {
    animation: slideIn 0.3s ease-out;
}
</style>

<script>
// Tab handling
document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function (e) {
        // Update hidden filter input
        const targetId = e.target.getAttribute('data-bs-target').replace('#', '');
        document.getElementById('filterInput').value = targetId;
        
        // Update URL without reload
        const url = new URL(window.location);
        url.searchParams.set('filter', targetId);
        window.history.pushState({}, '', url);
    });
});

// Auto-submit on filter change
document.querySelector('select[name="category"]').addEventListener('change', function() {
    this.form.submit();
});

// Search with debounce
let searchTimeout;
document.querySelector('input[name="search"]').addEventListener('keyup', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (this.value.length >= 3 || this.value.length === 0) {
            this.form.submit();
        }
    }, 500);
});

// Tooltip initialization
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Confirm leave group
function confirmLeave(groupName) {
    return confirm(`Are you sure you want to leave "${groupName}"?`);
}

// Load appropriate tab based on URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const filter = urlParams.get('filter');
    
    if (filter) {
        const tab = document.querySelector(`[data-bs-target="#${filter}"]`);
        if (tab) {
            new bootstrap.Tab(tab).show();
        }
    }
});

// Copy group invite link (if applicable)
function copyInviteLink(groupId) {
    const dummy = document.createElement('input');
    const text = window.location.origin + '/group_join.php?id=' + groupId;
    
    dummy.value = text;
    document.body.appendChild(dummy);
    dummy.select();
    document.execCommand('copy');
    document.body.removeChild(dummy);
    
    alert('Invite link copied to clipboard!');
}
</script>

<?php
// Include member footer
include 'member_footer.php';
?>