<?php
// admin/members.php - Member Management Page
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get database connection
$db = Database::getInstance()->getConnection();

// Handle member deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $member_id = (int)$_GET['delete'];
    
    // Check if member exists
    $check_stmt = $db->prepare("SELECT member_id FROM members WHERE member_id = ?");
    $check_stmt->bind_param("i", $member_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Soft delete - update status instead of actually deleting
        $delete_stmt = $db->prepare("UPDATE members SET membership_status = 'Deleted' WHERE member_id = ?");
        $delete_stmt->bind_param("i", $member_id);
        
        if ($delete_stmt->execute()) {
            setFlashMessage("Member deleted successfully!", "success");
        } else {
            setFlashMessage("Error deleting member: " . $db->error, "danger");
        }
    } else {
        setFlashMessage("Member not found!", "warning");
    }
    
    header('Location: members.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build the query
$query = "SELECT m.*, 
                 (SELECT COUNT(*) FROM attendance WHERE member_id = m.member_id AND attended = 1) as total_attendance,
                 (SELECT SUM(amount) FROM donations WHERE member_id = m.member_id) as total_donations
          FROM members m
          WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM members WHERE 1=1";
$params = [];
$types = "";

// Add search condition
if (!empty($search)) {
    $query .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?)";
    $count_query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= "ssss";
}

// Add status filter
if (!empty($status_filter)) {
    $query .= " AND m.membership_status = ?";
    $count_query .= " AND membership_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Add ordering and pagination
$query .= " ORDER BY m.membership_date DESC LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

// Prepare and execute the main query
$stmt = $db->prepare($query);

// Check if prepare was successful
if (!$stmt) {
    die("Error preparing query: " . $db->error);
}

// Bind parameters dynamically
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
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

// Get unique statuses for filter dropdown
$statuses = $db->query("SELECT DISTINCT membership_status FROM members ORDER BY membership_status");

// Set page title
$page_title = "Members Management";

// Include header
include '../header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center bg-white p-4 rounded-3 shadow-sm">
                <div>
                    <h1 class="display-6 fw-bold mb-2">
                        <i class="fas fa-users me-3 text-primary"></i>
                        Members Management
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-users me-2"></i>Total Members: <?php echo $total_records; ?>
                    </p>
                </div>
                <div>
                    <a href="member_add.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Add New Member
                    </a>
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
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" 
                                       class="form-control border-start-0 ps-0" 
                                       name="search" 
                                       placeholder="Search by name, email, or phone..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <?php while ($status = $statuses->fetch_assoc()): ?>
                                    <option value="<?php echo $status['membership_status']; ?>" 
                                            <?php echo $status_filter == $status['membership_status'] ? 'selected' : ''; ?>>
                                        <?php echo $status['membership_status']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                        <div class="col-md-2">
                            <?php if (!empty($search) || !empty($status_filter)): ?>
                                <a href="members.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-times me-2"></i>Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Members Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($members)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-4x text-muted mb-3"></i>
                            <h5>No members found</h5>
                            <p class="text-muted">Try adjusting your search or filter criteria</p>
                            <?php if (empty($search) && empty($status_filter)): ?>
                                <a href="member_add.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-user-plus me-2"></i>Add Your First Member
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Attendance</th>
                                        <th>Donations</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle bg-primary bg-opacity-10 me-3">
                                                        <span class="initials">
                                                            <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h6>
                                                        <small class="text-muted">ID: #<?php echo $member['member_id']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <i class="fas fa-envelope me-2 text-muted small"></i>
                                                    <?php echo htmlspecialchars($member['email'] ?: 'N/A'); ?>
                                                </div>
                                                <div class="mt-1">
                                                    <i class="fas fa-phone me-2 text-muted small"></i>
                                                    <?php echo htmlspecialchars($member['phone'] ?: 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = 'secondary';
                                                if ($member['membership_status'] == 'Active') $status_class = 'success';
                                                if ($member['membership_status'] == 'Inactive') $status_class = 'warning';
                                                if ($member['membership_status'] == 'Visitor') $status_class = 'info';
                                                if ($member['membership_status'] == 'Deleted') $status_class = 'danger';
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo $member['membership_status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $member['membership_date'] ? date('M d, Y', strtotime($member['membership_date'])) : 'N/A'; ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo $member['membership_date'] ? timeAgo($member['membership_date']) : ''; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="fw-bold"><?php echo $member['total_attendance'] ?? 0; ?></span>
                                                <small class="text-muted">services</small>
                                            </td>
                                            <td>
                                                <?php if ($member['total_donations']): ?>
                                                    <span class="fw-bold text-success"><?php echo formatCurrency($member['total_donations']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">$0.00</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="member_view.php?id=<?php echo $member['member_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="member_edit.php?id=<?php echo $member['member_id']; ?>" 
                                                       class="btn btn-sm btn-outline-secondary" 
                                                       title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($member['membership_status'] != 'Deleted'): ?>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-danger" 
                                                                title="Delete"
                                                                onclick="confirmDelete(<?php echo $member['member_id']; ?>, '<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer bg-white border-0 py-3">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center mb-0">
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <?php if ($i >= $page - 2 && $i <= $page + 2): ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
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
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="memberName"></strong>?</p>
                <p class="text-muted small">This action cannot be undone. The member will be marked as deleted but their records will be preserved.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Delete Member
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.initials {
    font-size: 16px;
    font-weight: 600;
    color: var(--bs-primary);
}

.table > :not(caption) > * > * {
    padding: 1rem 0.75rem;
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
}

.btn-group .btn i {
    font-size: 0.875rem;
}

.pagination {
    margin-bottom: 0;
}

.page-link {
    padding: 0.5rem 0.75rem;
    color: var(--bs-primary);
}

.page-item.active .page-link {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .table {
        font-size: 0.875rem;
    }
    
    .btn-group .btn {
        padding: 0.2rem 0.4rem;
    }
    
    .avatar-circle {
        width: 32px;
        height: 32px;
    }
    
    .initials {
        font-size: 14px;
    }
}
</style>

<script>
function confirmDelete(memberId, memberName) {
    document.getElementById('memberName').textContent = memberName;
    document.getElementById('confirmDeleteBtn').href = '?delete=' + memberId;
    
    // Show the modal
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}

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

// Live search (optional - can be implemented with AJAX)
let searchTimeout;
document.querySelector('input[name="search"]').addEventListener('keyup', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        document.querySelector('form').submit();
    }, 500);
});
</script>

<?php
// Include footer
include '../footer.php';
?>