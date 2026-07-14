<?php
// admin/events.php - Events Management Page (Fixed version)
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get database connection
$db = Database::getInstance()->getConnection();

// Handle event deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $event_id = (int)$_GET['delete'];
    
    // Check if event exists
    $check_stmt = $db->prepare("SELECT event_id, event_name FROM events WHERE event_id = ?");
    $check_stmt->bind_param("i", $event_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $event = $result->fetch_assoc();
        
        // Check if event has registrations
        $reg_check = $db->prepare("SELECT COUNT(*) as count FROM event_registrations WHERE event_id = ?");
        $reg_check->bind_param("i", $event_id);
        $reg_check->execute();
        $reg_result = $reg_check->get_result();
        $reg_count = $reg_result->fetch_assoc()['count'];
        
        if ($reg_count > 0) {
            setFlashMessage("Cannot delete event '{$event['event_name']}' because it has {$reg_count} registration(s).", "warning");
        } else {
            // No registrations, can safely delete
            $delete_stmt = $db->prepare("DELETE FROM events WHERE event_id = ?");
            $delete_stmt->bind_param("i", $event_id);
            
            if ($delete_stmt->execute()) {
                setFlashMessage("Event '{$event['event_name']}' deleted successfully!", "success");
            } else {
                setFlashMessage("Error deleting event: " . $db->error, "danger");
            }
        }
    } else {
        setFlashMessage("Event not found!", "warning");
    }
    
    header('Location: events.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date_range']) ? $_GET['date_range'] : 'upcoming';

// Build the query - REMOVED event_type and status references
$query = "SELECT e.*, 
                 (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as registrations,
                 (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id AND attended = 1) as attended
          FROM events e
          WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM events WHERE 1=1";
$params = [];
$types = "";

// Add search condition
if (!empty($search)) {
    $query .= " AND (e.event_name LIKE ? OR e.event_description LIKE ? OR e.location LIKE ?)";
    $count_query .= " AND (event_name LIKE ? OR event_description LIKE ? OR location LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

// Add date range filter
switch ($date_filter) {
    case 'upcoming':
        $query .= " AND e.event_date >= CURDATE()";
        $count_query .= " AND event_date >= CURDATE()";
        break;
    case 'past':
        $query .= " AND e.event_date < CURDATE()";
        $count_query .= " AND event_date < CURDATE()";
        break;
    case 'today':
        $query .= " AND e.event_date = CURDATE()";
        $count_query .= " AND event_date = CURDATE()";
        break;
    case 'week':
        $query .= " AND e.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $count_query .= " AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $query .= " AND MONTH(e.event_date) = MONTH(CURDATE()) AND YEAR(e.event_date) = YEAR(CURDATE())";
        $count_query .= " AND MONTH(event_date) = MONTH(CURDATE()) AND YEAR(event_date) = YEAR(CURDATE())";
        break;
}

// Add ordering
$query .= " ORDER BY e.event_date ASC, e.event_time ASC LIMIT ? OFFSET ?";
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
$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
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

// Set page title
$page_title = "Events Management";

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
                        <i class="fas fa-calendar-alt me-3 text-primary"></i>
                        Events Management
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-calendar-check me-2"></i>Total Events: <?php echo $total_records; ?>
                    </p>
                </div>
                <div>
                    <a href="event_add.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i>Create New Event
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
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" 
                                       class="form-control border-start-0 ps-0" 
                                       name="search" 
                                       placeholder="Search events by name, description, or location..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <select name="date_range" class="form-select">
                                <option value="upcoming" <?php echo $date_filter == 'upcoming' ? 'selected' : ''; ?>>Upcoming Events</option>
                                <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="past" <?php echo $date_filter == 'past' ? 'selected' : ''; ?>>Past Events</option>
                                <option value="all" <?php echo $date_filter == 'all' ? 'selected' : ''; ?>>All Events</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Apply Filters
                            </button>
                        </div>
                        
                        <div class="col-md-2">
                            <?php if (!empty($search) || $date_filter != 'upcoming'): ?>
                                <a href="events.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-times me-2"></i>Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Events Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($events)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                            <h5>No events found</h5>
                            <p class="text-muted">Try adjusting your filters or create a new event</p>
                            <?php if (empty($search) && $date_filter == 'upcoming'): ?>
                                <a href="event_add.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-plus-circle me-2"></i>Create Your First Event
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Event</th>
                                        <th>Date & Time</th>
                                        <th>Location</th>
                                        <th>Organizer</th>
                                        <th>Registrations</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <?php
                                        $is_past = strtotime($event['event_date']) < strtotime(date('Y-m-d'));
                                        $is_today = $event['event_date'] == date('Y-m-d');
                                        $row_class = '';
                                        if ($is_today) $row_class = 'table-info';
                                        elseif ($is_past) $row_class = 'table-light';
                                        ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td>
                                                <div>
                                                    <h6 class="mb-1">
                                                        <a href="event_view.php?id=<?php echo $event['event_id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($event['event_name']); ?>
                                                        </a>
                                                    </h6>
                                                    <?php if (!empty($event['event_description'])): ?>
                                                        <small class="text-muted">
                                                            <?php echo substr(htmlspecialchars($event['event_description']), 0, 50); ?>
                                                            <?php echo strlen($event['event_description']) > 50 ? '...' : ''; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold">
                                                    <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                                    <?php if (!empty($event['end_time'])): ?>
                                                        - <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                                    <?php endif; ?>
                                                </small>
                                                <?php if ($is_today): ?>
                                                    <span class="badge bg-warning text-dark d-block mt-1">Today</span>
                                                <?php elseif ($is_past): ?>
                                                    <span class="badge bg-secondary d-block mt-1">Past</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-map-marker-alt me-1 text-muted"></i>
                                                <?php echo htmlspecialchars($event['location'] ?: 'TBD'); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($event['organizer'] ?: 'Not specified'); ?>
                                            </td>
                                            <td>
                                                <div class="text-center">
                                                    <span class="fw-bold"><?php echo $event['registrations']; ?></span>
                                                    <?php if ($event['max_participants'] > 0): ?>
                                                        <small class="text-muted"> / <?php echo $event['max_participants']; ?></small>
                                                    <?php endif; ?>
                                                    <?php if ($event['registrations'] > 0): ?>
                                                        <br>
                                                        <small class="text-success">
                                                            <i class="fas fa-user-check me-1"></i><?php echo $event['attended']; ?> attended
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                if ($is_past) {
                                                    $status_class = 'secondary';
                                                    $status_text = 'Completed';
                                                } elseif ($is_today) {
                                                    $status_class = 'warning';
                                                    $status_text = 'Today';
                                                } else {
                                                    $status_class = 'success';
                                                    $status_text = 'Upcoming';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="event_view.php?id=<?php echo $event['event_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="event_edit.php?id=<?php echo $event['event_id']; ?>" 
                                                       class="btn btn-sm btn-outline-secondary" 
                                                       title="Edit Event">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="event_register.php?id=<?php echo $event['event_id']; ?>" 
                                                       class="btn btn-sm btn-outline-success" 
                                                       title="Manage Registrations">
                                                        <i class="fas fa-user-plus"></i>
                                                    </a>
                                                    <?php if ($event['registrations'] == 0): ?>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-danger" 
                                                                title="Delete Event"
                                                                onclick="confirmDelete(<?php echo $event['event_id']; ?>, '<?php echo htmlspecialchars($event['event_name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="btn btn-sm btn-outline-danger disabled" 
                                                              title="Cannot delete - has registrations">
                                                            <i class="fas fa-trash"></i>
                                                        </span>
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
                                            <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&date_range=<?php echo urlencode($date_filter); ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                        
                                        <?php
                                        $start = max(1, $page - 2);
                                        $end = min($total_pages, $page + 2);
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&date_range=<?php echo urlencode($date_filter); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&date_range=<?php echo urlencode($date_filter); ?>">
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
                <p>Are you sure you want to delete <strong id="eventName"></strong>?</p>
                <p class="text-muted small mt-2">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Delete Event
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.table > :not(caption) > * > * {
    padding: 1rem 0.75rem;
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
}

.btn-group .btn i {
    font-size: 0.875rem;
}

.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

.table tbody tr {
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    background-color: rgba(67, 97, 238, 0.05);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .table {
        font-size: 0.875rem;
    }
    
    .btn-group .btn {
        padding: 0.2rem 0.4rem;
    }
    
    .btn-group {
        display: flex;
        flex-wrap: wrap;
        gap: 2px;
    }
}
</style>

<script>
function confirmDelete(eventId, eventName) {
    document.getElementById('eventName').textContent = eventName;
    document.getElementById('confirmDeleteBtn').href = '?delete=' + eventId;
    
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

// Live search with debounce
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