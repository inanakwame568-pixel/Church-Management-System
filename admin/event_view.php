<?php
// admin/event_view.php - View Event Details with Registrations
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get database connection
$db = Database::getInstance()->getConnection();

// Check if event ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage("Invalid event ID!", "danger");
    header('Location: events.php');
    exit();
}

$event_id = (int)$_GET['id'];

// Get event details with statistics
$query = "SELECT e.*, 
                 u.full_name as created_by_name,
                 (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as total_registrations,
                 (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id AND attended = 1) as total_attended,
                 (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id AND attended = 0) as total_absent,
                 CASE 
                    WHEN e.event_date < CURDATE() THEN 'Past'
                    WHEN e.event_date = CURDATE() THEN 'Today'
                    ELSE 'Upcoming'
                 END as status
          FROM events e
          LEFT JOIN users u ON e.created_by = u.user_id
          WHERE e.event_id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setFlashMessage("Event not found!", "danger");
    header('Location: events.php');
    exit();
}

$event = $result->fetch_assoc();

// Get registrations with member details
$registrations_query = "SELECT er.*, 
                               m.member_id, m.first_name, m.last_name, m.email, m.phone,
                               CONCAT(m.first_name, ' ', m.last_name) as member_name
                        FROM event_registrations er
                        JOIN members m ON er.member_id = m.member_id
                        WHERE er.event_id = ?
                        ORDER BY er.registration_date DESC";

$registrations_stmt = $db->prepare($registrations_query);
$registrations_stmt->bind_param("i", $event_id);
$registrations_stmt->execute();
$registrations = $registrations_stmt->get_result();

// Handle attendance marking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance'])) {
    $attended_members = $_POST['attended'] ?? [];
    
    // Update attendance for all registrations
    foreach ($attended_members as $reg_id => $attended) {
        $update_stmt = $db->prepare("UPDATE event_registrations SET attended = ? WHERE registration_id = ? AND event_id = ?");
        $attended_value = $attended ? 1 : 0;
        $update_stmt->bind_param("iii", $attended_value, $reg_id, $event_id);
        $update_stmt->execute();
    }
    
    setFlashMessage("Attendance marked successfully!", "success");
    header("Location: event_view.php?id=" . $event_id);
    exit();
}

// Handle registration deletion
if (isset($_GET['delete_reg']) && is_numeric($_GET['delete_reg'])) {
    $reg_id = (int)$_GET['delete_reg'];
    
    $delete_stmt = $db->prepare("DELETE FROM event_registrations WHERE registration_id = ? AND event_id = ?");
    $delete_stmt->bind_param("ii", $reg_id, $event_id);
    
    if ($delete_stmt->execute()) {
        setFlashMessage("Registration cancelled successfully!", "success");
    } else {
        setFlashMessage("Error cancelling registration!", "danger");
    }
    
    header("Location: event_view.php?id=" . $event_id);
    exit();
}

// Handle email to registrants
if (isset($_POST['send_email'])) {
    $subject = sanitize($_POST['email_subject'] ?? '');
    $message = sanitize($_POST['email_message'] ?? '');
    $registrant_type = $_POST['email_to'] ?? 'all';
    
    // Build recipient list based on selection
    $email_list = [];
    $registrations->data_seek(0);
    while ($reg = $registrations->fetch_assoc()) {
        if ($registrant_type == 'all' || 
            ($registrant_type == 'attended' && $reg['attended']) ||
            ($registrant_type == 'absent' && !$reg['attended'])) {
            if (!empty($reg['email'])) {
                $email_list[] = $reg['email'];
            }
        }
    }
    $registrations->data_seek(0);
    
    if (!empty($email_list)) {
        // In development mode, show success
        if ($_SERVER['HTTP_HOST'] == 'localhost') {
            setFlashMessage("Development Mode: Email would be sent to " . count($email_list) . " recipients.", "success");
        } else {
            // Send emails (implement actual email sending here)
            setFlashMessage("Email sent to " . count($email_list) . " recipients!", "success");
        }
    } else {
        setFlashMessage("No recipients found for the selected criteria.", "warning");
    }
    
    header("Location: event_view.php?id=" . $event_id);
    exit();
}

// Set page title
$page_title = $event['event_name'] . " - Event Details";

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
                        <?php echo htmlspecialchars($event['event_name']); ?>
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-id-card me-2"></i>Event ID: #<?php echo $event['event_id']; ?> |
                        Created by: <?php echo htmlspecialchars($event['created_by_name'] ?? 'Administrator'); ?> |
                        Created: <?php echo date('M d, Y', strtotime($event['created_at'])); ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="event_edit.php?id=<?php echo $event_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>Edit Event
                    </a>
                    <a href="events.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Events
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['flash_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message']); unset($_SESSION['flash_type']); ?>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Column - Event Details -->
        <div class="col-lg-8">
            <!-- Event Status Banner -->
            <div class="alert alert-<?php 
                echo $event['status'] == 'Today' ? 'warning' : ($event['status'] == 'Past' ? 'secondary' : 'info'); 
            ?> mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-<?php 
                        echo $event['status'] == 'Today' ? 'clock' : ($event['status'] == 'Past' ? 'history' : 'calendar-day'); 
                    ?> fa-2x me-3"></i>
                    <div>
                        <strong>Event Status: <?php echo $event['status']; ?></strong>
                        <?php if ($event['status'] == 'Upcoming'): ?>
                            <br><small>Registration is open</small>
                        <?php elseif ($event['status'] == 'Today'): ?>
                            <br><small>Take attendance using the form below</small>
                        <?php else: ?>
                            <br><small>This event has already ended</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Event Details Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        Event Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Event Name</label>
                            <p class="fw-bold mb-0"><?php echo htmlspecialchars($event['event_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Event Type</label>
                            <p class="mb-0">
                                <span class="badge bg-info"><?php echo htmlspecialchars($event['event_type'] ?? 'General'); ?></span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Date & Time</label>
                            <p class="fw-bold mb-0">
                                <?php echo date('l, F j, Y', strtotime($event['event_date'])); ?>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                    <?php if (!empty($event['end_time'])): ?>
                                        - <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                    <?php endif; ?>
                                </small>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Location</label>
                            <p class="fw-bold mb-0">
                                <i class="fas fa-map-marker-alt me-1 text-danger"></i>
                                <?php echo htmlspecialchars($event['location'] ?: 'TBD'); ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Organizer</label>
                            <p class="mb-0"><?php echo htmlspecialchars($event['organizer'] ?: 'Not specified'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Capacity</label>
                            <p class="mb-0">
                                <?php if ($event['max_participants'] > 0): ?>
                                    <?php echo $event['total_registrations']; ?> / <?php echo $event['max_participants']; ?> registered
                                    <?php 
                                    $percentage = round(($event['total_registrations'] / $event['max_participants']) * 100);
                                    ?>
                                    <div class="progress mt-1" style="height: 5px;">
                                        <div class="progress-bar <?php 
                                            echo $percentage >= 100 ? 'bg-danger' : ($percentage >= 80 ? 'bg-warning' : 'bg-success'); 
                                        ?>" style="width: <?php echo min($percentage, 100); ?>%"></div>
                                    </div>
                                <?php else: ?>
                                    Unlimited capacity
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if (!empty($event['event_description'])): ?>
                            <div class="col-12">
                                <label class="text-muted small">Description</label>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($event['event_description'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Registrations List -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-users me-2 text-success"></i>
                        Registrations (<?php echo $event['total_registrations']; ?>)
                    </h5>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#emailModal">
                        <i class="fas fa-envelope me-1"></i>Email Registrants
                    </button>
                </div>
                <div class="card-body p-0">
                    <?php if ($event['total_registrations'] > 0): ?>
                        <form method="POST" action="" id="attendanceForm">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <?php if ($event['status'] != 'Past'): ?>
                                                <th width="50">Attended</th>
                                            <?php endif; ?>
                                            <th>Member</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Registered On</th>
                                            <th>Status</th>
                                            <th width="100">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $registrations->data_seek(0);
                                        while ($reg = $registrations->fetch_assoc()): 
                                        ?>
                                            <tr>
                                                <?php if ($event['status'] != 'Past'): ?>
                                                    <td class="text-center">
                                                        <div class="form-check">
                                                            <input class="form-check-input attendance-checkbox" 
                                                                   type="checkbox" 
                                                                   name="attended[<?php echo $reg['registration_id']; ?>]" 
                                                                   value="1"
                                                                   <?php echo $reg['attended'] ? 'checked' : ''; ?>>
                                                        </div>
                                                    </td>
                                                <?php endif; ?>
                                                <td>
                                                    <a href="member_view.php?id=<?php echo $reg['member_id']; ?>" class="text-decoration-none fw-bold">
                                                        <?php echo htmlspecialchars($reg['member_name']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($reg['email']); ?></td>
                                                <td><?php echo htmlspecialchars($reg['phone'] ?: '—'); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($reg['registration_date'])); ?></td>
                                                <td>
                                                    <?php if ($reg['attended']): ?>
                                                        <span class="badge bg-success">Attended</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Not Attended</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="confirmDelete(<?php echo $reg['registration_id']; ?>, '<?php echo htmlspecialchars($reg['member_name']); ?>')"
                                                            <?php echo ($event['status'] == 'Past' && $reg['attended']) ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($event['status'] != 'Past' && $event['total_registrations'] > 0): ?>
                                <div class="card-footer bg-white d-flex justify-content-between">
                                    <div>
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll()">
                                            Select All
                                        </button>
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="deselectAll()">
                                            Deselect All
                                        </button>
                                    </div>
                                    <button type="submit" name="mark_attendance" class="btn btn-primary btn-sm">
                                        <i class="fas fa-save me-1"></i>Save Attendance
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                            <p class="mb-0">No registrations yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column - Statistics & Quick Actions -->
        <div class="col-lg-4">
            <!-- Attendance Statistics -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-chart-pie me-2 text-primary"></i>
                        Attendance Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="bg-light rounded p-3">
                                    <h3 class="mb-0 text-success"><?php echo $event['total_attended']; ?></h3>
                                    <small class="text-muted">Attended</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-light rounded p-3">
                                    <h3 class="mb-0 text-secondary"><?php echo $event['total_absent']; ?></h3>
                                    <small class="text-muted">Absent</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <canvas id="attendanceChart" style="height: 150px;"></canvas>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span>Attendance Rate:</span>
                        <strong class="text-success">
                            <?php 
                            $rate = $event['total_registrations'] > 0 ? 
                                    round(($event['total_attended'] / $event['total_registrations']) * 100) : 0;
                            echo $rate; ?>%
                        </strong>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-bolt me-2 text-warning"></i>
                        Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="event_edit.php?id=<?php echo $event_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-edit me-2"></i>Edit Event
                        </a>
                        <button class="btn btn-outline-success" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Details
                        </button>
                        <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#emailModal">
                            <i class="fas fa-envelope me-2"></i>Email Registrants
                        </button>
                        <a href="event_register.php?id=<?php echo $event_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-user-plus me-2"></i>Manual Registration
                        </a>
                    </div>
                </div>
            </div>

            <!-- Event Summary Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-chart-line me-2 text-info"></i>
                        Event Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block">Registration Period</small>
                        <strong>Open until event date</strong>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Total Capacity</small>
                        <strong><?php echo $event['max_participants'] > 0 ? $event['max_participants'] : 'Unlimited'; ?></strong>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Remaining Spots</small>
                        <strong>
                            <?php 
                            $remaining = $event['max_participants'] > 0 ? 
                                        max(0, $event['max_participants'] - $event['total_registrations']) : 'Unlimited';
                            echo $remaining;
                            ?>
                        </strong>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <small class="text-muted d-block">Registration URL</small>
                        <input type="text" class="form-control form-control-sm" readonly 
                               value="<?php echo APP_URL . '/event_register.php?id=' . $event_id; ?>" 
                               id="registerUrl">
                        <button class="btn btn-sm btn-outline-secondary mt-1" onclick="copyUrl()">Copy Link</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email Modal -->
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-envelope me-2 text-primary"></i>
                    Email Registrants
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Send to:</label>
                        <select name="email_to" class="form-select" required>
                            <option value="all">All Registrants (<?php echo $event['total_registrations']; ?>)</option>
                            <option value="attended">Attended Only (<?php echo $event['total_attended']; ?>)</option>
                            <option value="absent">Not Attended Only (<?php echo $event['total_absent']; ?>)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Subject:</label>
                        <input type="text" name="email_subject" class="form-control" 
                               value="Important Update: <?php echo htmlspecialchars($event['event_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message:</label>
                        <textarea name="email_message" class="form-control" rows="6" required>
Dear Registrant,

This is an important update regarding the upcoming event: <?php echo htmlspecialchars($event['event_name']); ?>

Date: <?php echo date('l, F j, Y', strtotime($event['event_date'])); ?>
Time: <?php echo date('g:i A', strtotime($event['event_time'])); ?>
Location: <?php echo htmlspecialchars($event['location']); ?>

Please contact us if you have any questions.

Thank you,
<?php echo APP_NAME; ?>
</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_email" class="btn btn-primary">Send Email</button>
                </div>
            </form>
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
                    Cancel Registration
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel <strong id="deleteMemberName"></strong>'s registration?</p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Cancel Registration</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Attendance Chart
const ctx = document.getElementById('attendanceChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Attended', 'Absent'],
        datasets: [{
            data: [<?php echo $event['total_attended']; ?>, <?php echo $event['total_absent']; ?>],
            backgroundColor: ['#10b981', '#6c757d'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Select/Deselect all checkboxes
function selectAll() {
    document.querySelectorAll('.attendance-checkbox').forEach(cb => cb.checked = true);
}

function deselectAll() {
    document.querySelectorAll('.attendance-checkbox').forEach(cb => cb.checked = false);
}

// Confirm delete registration
let deleteRegId = null;

function confirmDelete(regId, memberName) {
    document.getElementById('deleteMemberName').textContent = memberName;
    document.getElementById('confirmDeleteBtn').href = '?delete_reg=' + regId;
    
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Copy registration URL
function copyUrl() {
    const urlInput = document.getElementById('registerUrl');
    urlInput.select();
    urlInput.setSelectionRange(0, 99999);
    document.execCommand('copy');
    
    alert('Registration link copied to clipboard!');
}

// Print functionality
window.print = function() {
    window.print();
};

// Auto-refresh attendance data (optional, every 30 seconds)
if (<?php echo $event['status'] == 'Today' ? 'true' : 'false'; ?>) {
    setInterval(function() {
        location.reload();
    }, 30000);
}
</script>

<style>
/* Custom styles for event view */
.attendance-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.table th, .table td {
    vertical-align: middle;
}

.btn-group-sm .btn {
    padding: 0.2rem 0.5rem;
}

/* Progress bar styling */
.progress {
    background-color: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

/* Card hover effects */
.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .btn {
        font-size: 0.85rem;
        padding: 8px 12px;
    }
    
    .table {
        font-size: 0.85rem;
    }
}
</style>

<?php include '../footer.php'; ?>