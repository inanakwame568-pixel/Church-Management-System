<?php
// admin/groups.php - Groups Management Page (FIXED VERSION)
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get database connection
$db = Database::getInstance()->getConnection();

// Handle group deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $group_id = (int)$_GET['delete'];
    
    // Check if group has members
    $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM group_members WHERE group_id = ?");
    $check_stmt->bind_param("i", $group_id);
    $check_stmt->execute();
    $member_count = $check_stmt->get_result()->fetch_assoc()['count'];
    
    if ($member_count > 0) {
        setFlashMessage("Cannot delete group with existing members. Please remove members first or deactivate the group.", "warning");
    } else {
        // Check if group has meetings
        $meeting_check = $db->prepare("SELECT COUNT(*) as count FROM group_meetings WHERE group_id = ?");
        $meeting_check->bind_param("i", $group_id);
        $meeting_check->execute();
        $meeting_count = $meeting_check->get_result()->fetch_assoc()['count'];
        
        if ($meeting_count > 0) {
            // Delete meetings first (or use ON DELETE CASCADE)
            $delete_meetings = $db->prepare("DELETE FROM group_meetings WHERE group_id = ?");
            $delete_meetings->bind_param("i", $group_id);
            $delete_meetings->execute();
        }
        
        // Now delete the group
        $delete_stmt = $db->prepare("DELETE FROM `groups` WHERE group_id = ?");
        $delete_stmt->bind_param("i", $group_id);
        
        if ($delete_stmt->execute()) {
            setFlashMessage("Group deleted successfully!", "success");
        } else {
            setFlashMessage("Error deleting group: " . $db->error, "danger");
        }
    }
    
    header('Location: groups.php');
    exit();
}

// Handle status update (activate/deactivate)
if (isset($_GET['status']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $group_id = (int)$_GET['id'];
    $new_status = $_GET['status'] == 'active' ? 'Active' : 'Inactive';
    
    $update_stmt = $db->prepare("UPDATE `groups` SET status = ? WHERE group_id = ?");
    $update_stmt->bind_param("si", $new_status, $group_id);
    
    if ($update_stmt->execute()) {
        setFlashMessage("Group status updated successfully!", "success");
    } else {
        setFlashMessage("Error updating group status!", "danger");
    }
    
    header('Location: groups.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 12;
$offset = ($page - 1) * $records_per_page;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$leader_filter = isset($_GET['leader']) ? (int)$_GET['leader'] : 0;

// Build the query - FIXED: Added backticks around `groups` table name
$query = "SELECT g.*, 
                 CONCAT(l.first_name, ' ', l.last_name) as leader_name,
                 CONCAT(cl.first_name, ' ', cl.last_name) as co_leader_name,
                 (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id AND status = 'Active') as member_count,
                 (SELECT COUNT(*) FROM group_meetings WHERE group_id = g.group_id) as meeting_count,
                 (SELECT MAX(meeting_date) FROM group_meetings WHERE group_id = g.group_id) as last_meeting
          FROM `groups` g
          LEFT JOIN members l ON g.leader_id = l.member_id
          LEFT JOIN members cl ON g.co_leader_id = cl.member_id
          WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM `groups` WHERE 1=1";
$params = [];
$types = "";

// Add search condition
if (!empty($search)) {
    $query .= " AND (g.group_name LIKE ? OR g.description LIKE ? OR g.meeting_location LIKE ?)";
    $count_query .= " AND (group_name LIKE ? OR description LIKE ? OR meeting_location LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

// Add type filter
if (!empty($type_filter)) {
    $query .= " AND g.group_type = ?";
    $count_query .= " AND group_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

// Add status filter
if (!empty($status_filter)) {
    $query .= " AND g.status = ?";
    $count_query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Add leader filter
if ($leader_filter > 0) {
    $query .= " AND (g.leader_id = ? OR g.co_leader_id = ?)";
    $count_query .= " AND (leader_id = ? OR co_leader_id = ?)";
    $params[] = $leader_filter;
    $params[] = $leader_filter;
    $types .= "ii";
}

// Add ordering
$query .= " ORDER BY g.status, g.group_name LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

// Prepare and execute the main query
$stmt = $db->prepare($query);

if (!$stmt) {
    die("Error preparing query: " . $db->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$groups = [];
while ($row = $result->fetch_assoc()) {
    $groups[] = $row;
}

// Get total records for pagination
$count_stmt = $db->prepare($count_query);
if (!$count_stmt) {
    die("Error preparing count query: " . $db->error);
}

if (!empty($params)) {
    // Remove the last two parameters (limit and offset) for count query
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get unique group types for filter dropdown - FIXED: Added backticks
$types_result = $db->query("SELECT DISTINCT group_type FROM `groups` WHERE group_type IS NOT NULL ORDER BY group_type");
$group_types = [];
if ($types_result) {
    while ($row = $types_result->fetch_assoc()) {
        $group_types[] = $row['group_type'];
    }
}

// Get potential leaders for filter
$leaders = $db->query("
    SELECT DISTINCT m.member_id, m.first_name, m.last_name 
    FROM members m
    WHERE m.member_id IN (SELECT leader_id FROM `groups` WHERE leader_id IS NOT NULL)
       OR m.member_id IN (SELECT co_leader_id FROM `groups` WHERE co_leader_id IS NOT NULL)
    ORDER BY m.last_name, m.first_name
");

// Get summary statistics - FIXED: Added backticks
$stats = [
    'total' => $db->query("SELECT COUNT(*) as count FROM `groups`")->fetch_assoc()['count'],
    'active' => $db->query("SELECT COUNT(*) as count FROM `groups` WHERE status = 'Active'")->fetch_assoc()['count'],
    'forming' => $db->query("SELECT COUNT(*) as count FROM `groups` WHERE status = 'Forming'")->fetch_assoc()['count'],
    'inactive' => $db->query("SELECT COUNT(*) as count FROM `groups` WHERE status = 'Inactive'")->fetch_assoc()['count'],
    'total_members' => $db->query("SELECT COUNT(*) as count FROM group_members WHERE status = 'Active'")->fetch_assoc()['count'],
    'avg_size' => 0
];

$stats['avg_size'] = $stats['active'] > 0 ? round($stats['total_members'] / $stats['active'], 1) : 0;

// Set page title
$page_title = "Groups Management";

// Include header
include '../header.php';
?>

<!-- Rest of your HTML remains exactly the same -->
<!-- ... -->

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center bg-white p-4 rounded-3 shadow-sm">
                <div>
                    <h1 class="display-6 fw-bold mb-2">
                        <i class="fas fa-users-cog me-3 text-primary"></i>
                        Groups Management
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-users me-2"></i>Total Groups: <?php echo $stats['total']; ?> | 
                        Active: <?php echo $stats['active']; ?> | 
                        Members: <?php echo $stats['total_members']; ?>
                    </p>
                </div>
                <div>
                    <a href="group_add.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i>Create New Group
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stats-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-primary bg-opacity-10 rounded-3 p-3">
                            <i class="fas fa-users text-primary fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Total Groups</h6>
                            <h3 class="mb-0 fw-bold"><?php echo $stats['total']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-success bg-opacity-10 rounded-3 p-3">
                            <i class="fas fa-check-circle text-success fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Active Groups</h6>
                            <h3 class="mb-0 fw-bold"><?php echo $stats['active']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-info bg-opacity-10 rounded-3 p-3">
                            <i class="fas fa-user-plus text-info fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Group Members</h6>
                            <h3 class="mb-0 fw-bold"><?php echo $stats['total_members']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-warning bg-opacity-10 rounded-3 p-3">
                            <i class="fas fa-chart-line text-warning fa-2x"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-muted mb-1">Avg Group Size</h6>
                            <h3 class="mb-0 fw-bold"><?php echo $stats['avg_size']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" 
                                       class="form-control border-start-0 ps-0" 
                                       name="search" 
                                       placeholder="Search groups..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <select name="type" class="form-select">
                                <option value="">All Types</option>
                                <?php foreach ($group_types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $type_filter == $type ? 'selected' : ''; ?>>
                                        <?php echo $type; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="Active" <?php echo $status_filter == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Forming" <?php echo $status_filter == 'Forming' ? 'selected' : ''; ?>>Forming</option>
                                <option value="Inactive" <?php echo $status_filter == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <select name="leader" class="form-select">
                                <option value="0">All Leaders</option>
                                <?php while ($leader = $leaders->fetch_assoc()): ?>
                                    <option value="<?php echo $leader['member_id']; ?>" <?php echo $leader_filter == $leader['member_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($leader['first_name'] . ' ' . $leader['last_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Apply
                            </button>
                        </div>
                        
                        <div class="col-md-1">
                            <?php if (!empty($search) || !empty($type_filter) || !empty($status_filter) || $leader_filter > 0): ?>
                                <a href="groups.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Groups Grid -->
    <div class="row g-4">
        <?php if (empty($groups)): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-users fa-4x text-muted mb-3"></i>
                        <h5>No groups found</h5>
                        <p class="text-muted">Try adjusting your filters or create a new group</p>
                        <?php if (empty($search) && empty($type_filter) && empty($status_filter) && $leader_filter == 0): ?>
                            <a href="group_add.php" class="btn btn-primary mt-3">
                                <i class="fas fa-plus-circle me-2"></i>Create Your First Group
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($groups as $group): ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card group-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <!-- Group Type Badge -->
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="badge bg-primary"><?php echo htmlspecialchars($group['group_type']); ?></span>
                                <span class="badge bg-<?php 
                                    echo $group['status'] == 'Active' ? 'success' : 
                                        ($group['status'] == 'Forming' ? 'info' : 'secondary'); 
                                ?>">
                                    <?php echo $group['status']; ?>
                                </span>
                            </div>
                            
                            <!-- Group Name -->
                            <h5 class="card-title fw-bold mb-2">
                                <a href="group_view.php?id=<?php echo $group['group_id']; ?>" class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($group['group_name']); ?>
                                </a>
                            </h5>
                            
                            <!-- Description -->
                            <?php if (!empty($group['description'])): ?>
                                <p class="card-text small text-muted mb-3">
                                    <?php echo substr(htmlspecialchars($group['description']), 0, 100); ?>
                                    <?php echo strlen($group['description']) > 100 ? '...' : ''; ?>
                                </p>
                            <?php endif; ?>
                            
                            <!-- Leader Info -->
                            <?php if ($group['leader_name']): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="leader-avatar bg-primary bg-opacity-10 rounded-circle me-2">
                                        <span class="initials">
                                            <?php 
                                            $initials = '';
                                            $name_parts = explode(' ', $group['leader_name']);
                                            foreach ($name_parts as $part) {
                                                $initials .= strtoupper(substr($part, 0, 1));
                                            }
                                            echo substr($initials, 0, 2);
                                            ?>
                                        </span>
                                    </div>
                                    <div class="small">
                                        <span class="text-muted">Leader:</span>
                                        <span class="fw-bold"><?php echo htmlspecialchars($group['leader_name']); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Group Details -->
                            <div class="group-details mt-3 pt-2 border-top">
                                <div class="row g-2 small">
                                    <div class="col-6">
                                        <i class="fas fa-users me-1 text-muted"></i>
                                        <span><?php echo $group['member_count']; ?> members</span>
                                    </div>
                                    <div class="col-6">
                                        <i class="fas fa-calendar me-1 text-muted"></i>
                                        <span><?php echo $group['meeting_count']; ?> meetings</span>
                                    </div>
                                    <?php if ($group['meeting_day']): ?>
                                        <div class="col-12">
                                            <i class="fas fa-clock me-1 text-muted"></i>
                                            <span>
                                                <?php echo $group['meeting_day']; ?>s 
                                                <?php echo $group['meeting_time'] ? date('g:i A', strtotime($group['meeting_time'])) : ''; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($group['meeting_location']): ?>
                                        <div class="col-12">
                                            <i class="fas fa-map-marker-alt me-1 text-muted"></i>
                                            <span><?php echo htmlspecialchars($group['meeting_location']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($group['last_meeting']): ?>
                                        <div class="col-12">
                                            <i class="fas fa-history me-1 text-muted"></i>
                                            <span>Last: <?php echo timeAgo($group['last_meeting']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Capacity Indicator -->
                            <?php if ($group['max_capacity'] > 0): ?>
                                <div class="mt-3">
                                    <?php 
                                    $capacity_percentage = round(($group['member_count'] / $group['max_capacity']) * 100);
                                    $capacity_class = $capacity_percentage >= 100 ? 'bg-danger' : 
                                                     ($capacity_percentage >= 80 ? 'bg-warning' : 'bg-success');
                                    ?>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>Capacity</span>
                                        <span><?php echo $group['member_count']; ?>/<?php echo $group['max_capacity']; ?></span>
                                    </div>
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar <?php echo $capacity_class; ?>" 
                                             style="width: <?php echo min($capacity_percentage, 100); ?>%"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <div class="mt-3 pt-2 border-top d-flex justify-content-between">
                                <div class="btn-group">
                                    <a href="group_view.php?id=<?php echo $group['group_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="group_edit.php?id=<?php echo $group['group_id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary" 
                                       title="Edit Group">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="group_members.php?id=<?php echo $group['group_id']; ?>" 
                                       class="btn btn-sm btn-outline-info" 
                                       title="Manage Members">
                                        <i class="fas fa-users"></i>
                                    </a>
                                    <a href="group_attendance.php?id=<?php echo $group['group_id']; ?>" 
                                       class="btn btn-sm btn-outline-success" 
                                       title="Take Attendance">
                                        <i class="fas fa-calendar-check"></i>
                                    </a>
                                </div>
                                <div class="btn-group">
                                    <?php if ($group['status'] == 'Active'): ?>
                                        <a href="?status=inactive&id=<?php echo $group['group_id']; ?>" 
                                           class="btn btn-sm btn-outline-warning" 
                                           title="Deactivate"
                                           onclick="return confirm('Deactivate this group?')">
                                            <i class="fas fa-ban"></i>
                                        </a>
                                    <?php elseif ($group['status'] == 'Inactive'): ?>
                                        <a href="?status=active&id=<?php echo $group['group_id']; ?>" 
                                           class="btn btn-sm btn-outline-success" 
                                           title="Activate"
                                           onclick="return confirm('Activate this group?')">
                                            <i class="fas fa-check-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($group['member_count'] == 0): ?>
                                        <a href="?delete=<?php echo $group['group_id']; ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this group?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="col-12 mt-4">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&leader=<?php echo $leader_filter; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&leader=<?php echo $leader_filter; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&leader=<?php echo $leader_filter; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
/* Group card styling */
.group-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
}

.group-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.15) !important;
}

.leader-avatar {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.leader-avatar .initials {
    font-size: 12px;
    font-weight: 600;
    color: var(--bs-primary);
}

.stats-card {
    transition: all 0.3s ease;
    border-radius: 10px;
}

.stats-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

.stats-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.group-details {
    font-size: 0.85rem;
    color: #6c757d;
}

.btn-group {
    gap: 2px;
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
}

.btn-group .btn i {
    font-size: 0.875rem;
}

/* Pagination */
.pagination {
    margin-bottom: 0;
}

.page-link {
    padding: 0.5rem 0.75rem;
    color: var(--bs-primary);
    border-radius: 8px;
    margin: 0 2px;
}

.page-item.active .page-link {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
    color: white;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .group-card {
        margin-bottom: 15px;
    }
    
    .btn-group {
        flex-wrap: wrap;
    }
    
    .stats-card {
        margin-bottom: 10px;
    }
}
</style>

<script>
// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(alert) {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(function() {
            alert.remove();
        }, 500);
    });
}, 5000);

// Live search with debounce
let searchTimeout;
document.querySelector('input[name="search"]').addEventListener('keyup', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        document.querySelector('form').submit();
    }, 500);
});

// Filter change auto-submit
document.querySelectorAll('select[name="type"], select[name="status"], select[name="leader"]').forEach(select => {
    select.addEventListener('change', function() {
        document.querySelector('form').submit();
    });
});

// Group card hover effects
document.querySelectorAll('.group-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
    });
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

// Confirm deactivation
function confirmDeactivation(groupName) {
    return confirm(`Are you sure you want to deactivate "${groupName}"?`);
}

// Confirm activation
function confirmActivation(groupName) {
    return confirm(`Are you sure you want to activate "${groupName}"?`);
}

// Tooltip initialization
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

<?php
// Include footer
include '../footer.php';
?>