<?php
// admin/event_register.php - Admin Manual Event Registration
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

// Get event details
$event_query = "SELECT e.*, 
                       (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as current_registrations
                FROM events e
                WHERE e.event_id = ?";

$event_stmt = $db->prepare($event_query);
$event_stmt->bind_param("i", $event_id);
$event_stmt->execute();
$event_result = $event_stmt->get_result();

if ($event_result->num_rows == 0) {
    setFlashMessage("Event not found!", "danger");
    header('Location: events.php');
    exit();
}

$event = $event_result->fetch_assoc();

// Check if event is full
$is_full = ($event['max_participants'] > 0 && $event['current_registrations'] >= $event['max_participants']);

// Get all members for dropdown
$members_query = "SELECT member_id, first_name, last_name, email, phone 
                  FROM members 
                  WHERE membership_status = 'Active'
                  ORDER BY last_name, first_name";
$members_result = $db->query($members_query);

// Get current registrations list
$registrations_query = "SELECT er.*, 
                               m.first_name, m.last_name, m.email, m.phone,
                               CONCAT(m.first_name, ' ', m.last_name) as member_name
                        FROM event_registrations er
                        JOIN members m ON er.member_id = m.member_id
                        WHERE er.event_id = ?
                        ORDER BY er.registration_date DESC";
$registrations_stmt = $db->prepare($registrations_query);
$registrations_stmt->bind_param("i", $event_id);
$registrations_stmt->execute();
$registrations = $registrations_stmt->get_result();

// Handle manual registration
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_member'])) {
    $member_id = (int)$_POST['member_id'];
    $guest_count = (int)($_POST['guest_count'] ?? 0);
    $special_needs = sanitize($_POST['special_needs'] ?? '');
    $dietary_restrictions = sanitize($_POST['dietary_restrictions'] ?? '');
    $send_email = isset($_POST['send_email']) ? 1 : 0;
    
    // Validate member selection
    if ($member_id <= 0) {
        $error = "Please select a member to register.";
    } else {
        // Check if already registered
        $check_query = "SELECT registration_id FROM event_registrations WHERE event_id = ? AND member_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bind_param("ii", $event_id, $member_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "This member is already registered for the event!";
        } elseif ($is_full) {
            $error = "Event has reached maximum capacity!";
        } else {
            // Insert registration
            $insert_query = "INSERT INTO event_registrations (event_id, member_id, registration_date, guest_count, special_needs, dietary_restrictions, status) 
                             VALUES (?, ?, NOW(), ?, ?, ?, 'confirmed')";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bind_param("iiiss", $event_id, $member_id, $guest_count, $special_needs, $dietary_restrictions);
            
            if ($insert_stmt->execute()) {
                $success = "Member registered successfully!";
                
                // Send confirmation email if requested
                if ($send_email) {
                    // Get member email
                    $member_info = $db->prepare("SELECT first_name, last_name, email FROM members WHERE member_id = ?");
                    $member_info->bind_param("i", $member_id);
                    $member_info->execute();
                    $member_data = $member_info->get_result()->fetch_assoc();
                    
                    // Send email (in development mode, just log)
                    if ($_SERVER['HTTP_HOST'] == 'localhost') {
                        error_log("Email would be sent to {$member_data['email']} for event registration");
                    }
                }
                
                // Refresh registrations list
                $registrations_stmt->execute();
                $registrations = $registrations_stmt->get_result();
                
                // Update event registrations count
                $event['current_registrations']++;
                $is_full = ($event['max_participants'] > 0 && $event['current_registrations'] >= $event['max_participants']);
            } else {
                $error = "Failed to register member: " . $db->error;
            }
        }
    }
}

// Handle cancellation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $reg_id = (int)$_GET['cancel'];
    
    $cancel_stmt = $db->prepare("DELETE FROM event_registrations WHERE registration_id = ? AND event_id = ?");
    $cancel_stmt->bind_param("ii", $reg_id, $event_id);
    
    if ($cancel_stmt->execute()) {
        setFlashMessage("Registration cancelled successfully!", "success");
        header("Location: event_register.php?id=$event_id");
        exit();
    }
}

// Handle bulk registration
if (isset($_POST['bulk_register']) && isset($_POST['selected_members'])) {
    $selected_members = $_POST['selected_members'];
    $guest_count = (int)($_POST['bulk_guest_count'] ?? 0);
    $registered_count = 0;
    $failed_count = 0;
    
    foreach ($selected_members as $member_id) {
        $member_id = (int)$member_id;
        
        // Check if already registered
        $check_query = "SELECT registration_id FROM event_registrations WHERE event_id = ? AND member_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bind_param("ii", $event_id, $member_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows == 0 && !$is_full) {
            $insert_query = "INSERT INTO event_registrations (event_id, member_id, registration_date, guest_count, status) 
                             VALUES (?, ?, NOW(), ?, 'confirmed')";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bind_param("iii", $event_id, $member_id, $guest_count);
            
            if ($insert_stmt->execute()) {
                $registered_count++;
                $event['current_registrations']++;
                $is_full = ($event['max_participants'] > 0 && $event['current_registrations'] >= $event['max_participants']);
            } else {
                $failed_count++;
            }
        } else {
            $failed_count++;
        }
    }
    
    if ($registered_count > 0) {
        $success = "$registered_count member(s) registered successfully!";
        if ($failed_count > 0) {
            $success .= " ($failed_count failed - already registered or event full)";
        }
        
        // Refresh registrations list
        $registrations_stmt->execute();
        $registrations = $registrations_stmt->get_result();
    } else {
        $error = "No members were registered. They may already be registered or the event is full.";
    }
}

// Set page title
$page_title = "Manage Registrations - " . $event['event_name'];

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
                        <i class="fas fa-user-plus me-3 text-primary"></i>
                        Manage Registrations
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Event: <strong><?php echo htmlspecialchars($event['event_name']); ?></strong> | 
                        Registered: <strong><?php echo $event['current_registrations']; ?></strong>
                        <?php if ($event['max_participants'] > 0): ?>
                            / <?php echo $event['max_participants']; ?> capacity
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <a href="event_view.php?id=<?php echo $event_id; ?>" class="btn btn-outline-primary me-2">
                        <i class="fas fa-eye me-2"></i>View Event
                    </a>
                    <a href="events.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Events
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Capacity Warning -->
    <?php if ($is_full): ?>
        <div class="alert alert-warning mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Event is at full capacity!</strong> No more registrations can be added.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Column - Manual Registration Form -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-user-plus me-2 text-primary"></i>
                        Manual Registration
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if (!$is_full): ?>
                        <form method="POST" action="" id="manualRegForm">
                            <div class="mb-3">
                                <label for="member_id" class="form-label fw-bold">Select Member <span class="text-danger">*</span></label>
                                <select class="form-select" id="member_id" name="member_id" required>
                                    <option value="">-- Select Member --</option>
                                    <?php while ($member = $members_result->fetch_assoc()): ?>
                                        <option value="<?php echo $member['member_id']; ?>">
                                            <?php echo htmlspecialchars($member['last_name'] . ', ' . $member['first_name']); ?>
                                            (<?php echo htmlspecialchars($member['email']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="guest_count" class="form-label fw-bold">Number of Guests</label>
                                <select class="form-select" id="guest_count" name="guest_count">
                                    <option value="0">0 (No guests)</option>
                                    <option value="1">1 guest</option>
                                    <option value="2">2 guests</option>
                                    <option value="3">3 guests</option>
                                    <option value="4">4 guests</option>
                                    <option value="5">5+ guests</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="dietary_restrictions" class="form-label fw-bold">Dietary Restrictions</label>
                                <input type="text" class="form-control" id="dietary_restrictions" name="dietary_restrictions" 
                                       placeholder="Vegetarian, Gluten-free, Allergies, etc.">
                            </div>
                            
                            <div class="mb-3">
                                <label for="special_needs" class="form-label fw-bold">Special Accommodations</label>
                                <textarea class="form-control" id="special_needs" name="special_needs" rows="2" 
                                          placeholder="Any accessibility or special needs?"></textarea>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="send_email" name="send_email" checked>
                                <label class="form-check-label" for="send_email">Send confirmation email to member</label>
                            </div>
                            
                            <button type="submit" name="register_member" class="btn btn-primary w-100" id="registerBtn">
                                <i class="fas fa-check-circle me-2"></i>Register Member
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-ban fa-3x text-muted mb-3"></i>
                            <p class="mb-0">Registration is closed due to capacity limit.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Bulk Registration -->
            <?php if (!$is_full): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-layer-group me-2 text-success"></i>
                        Bulk Registration
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="" id="bulkRegForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Members</label>
                            <div class="border rounded p-3" style="max-height: 250px; overflow-y: auto;">
                                <?php 
                                $members_result->data_seek(0);
                                while ($member = $members_result->fetch_assoc()): 
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input member-checkbox" type="checkbox" 
                                               name="selected_members[]" value="<?php echo $member['member_id']; ?>" 
                                               id="member_<?php echo $member['member_id']; ?>">
                                        <label class="form-check-label" for="member_<?php echo $member['member_id']; ?>">
                                            <?php echo htmlspecialchars($member['last_name'] . ', ' . $member['first_name']); ?>
                                            <small class="text-muted">(<?php echo htmlspecialchars($member['email']); ?>)</small>
                                        </label>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulk_guest_count" class="form-label fw-bold">Default Guest Count</label>
                            <select class="form-select" id="bulk_guest_count" name="bulk_guest_count">
                                <option value="0">0 guests</option>
                                <option value="1">1 guest</option>
                                <option value="2">2 guests</option>
                                <option value="3">3 guests</option>
                                <option value="4">4 guests</option>
                                <option value="5">5+ guests</option>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll()">
                                Select All
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="deselectAll()">
                                Deselect All
                            </button>
                        </div>
                        
                        <button type="submit" name="bulk_register" class="btn btn-success w-100 mt-3" id="bulkRegisterBtn">
                            <i class="fas fa-users me-2"></i>Register Selected Members
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Column - Current Registrations -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-users me-2 text-success"></i>
                        Current Registrations (<?php echo $event['current_registrations']; ?>)
                    </h5>
                    <div class="d-flex gap-2">
                        <input type="text" id="searchRegistrations" class="form-control form-control-sm" placeholder="Search..." style="width: 200px;">
                        <button class="btn btn-sm btn-outline-primary" onclick="exportRegistrationsToCSV()">
                            <i class="fas fa-file-csv me-1"></i>Export
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if ($registrations->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="registrationsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Member</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Guests</th>
                                        <th>Registered On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($reg = $registrations->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <a href="../member_view.php?id=<?php echo $reg['member_id']; ?>" class="text-decoration-none fw-bold">
                                                    <?php echo htmlspecialchars($reg['member_name']); ?>
                                                </a>
                                             </div>
                                            <td><?php echo htmlspecialchars($reg['email']); ?></td>
                                            <td><?php echo htmlspecialchars($reg['phone'] ?: '—'); ?></td>
                                            <td class="text-center"><?php echo $reg['guest_count']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($reg['registration_date'])); ?></td>
                                            <td>
                                                <a href="?cancel=<?php echo $reg['registration_id']; ?>&id=<?php echo $event_id; ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Cancel this registration?')">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                             </div>
                                             ?>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                            <p class="mb-0">No registrations yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styles */
.form-control:focus, .form-select:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.1);
}

.member-checkbox {
    margin-right: 8px;
}

.member-checkbox:checked {
    background-color: #4361ee;
    border-color: #4361ee;
}

.table td {
    vertical-align: middle;
}

/* Responsive */
@media (max-width: 768px) {
    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .d-flex.gap-2 {
        flex-direction: column;
    }
}
</style>

<script>
// Form validation for manual registration
document.getElementById('manualRegForm')?.addEventListener('submit', function(e) {
    const memberSelect = document.getElementById('member_id');
    const submitBtn = document.getElementById('registerBtn');
    
    if (!memberSelect.value) {
        e.preventDefault();
        alert('Please select a member to register.');
    } else {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Registering...';
    }
});

// Bulk registration validation
document.getElementById('bulkRegForm')?.addEventListener('submit', function(e) {
    const checkboxes = document.querySelectorAll('.member-checkbox:checked');
    const submitBtn = document.getElementById('bulkRegisterBtn');
    
    if (checkboxes.length === 0) {
        e.preventDefault();
        alert('Please select at least one member to register.');
    } else if (confirm(`Register ${checkboxes.length} member(s) for this event?`)) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Registering...';
    } else {
        e.preventDefault();
    }
});

// Select/Deselect all
function selectAll() {
    document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = true);
}

function deselectAll() {
    document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = false);
}

// Search registrations
document.getElementById('searchRegistrations')?.addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#registrationsTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});

// Export registrations to CSV
function exportRegistrationsToCSV() {
    const rows = document.querySelectorAll('#registrationsTable tbody tr');
    let csv = "Member Name,Email,Phone,Guests,Registered Date\n";
    
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            csv += `"${cells[0]?.innerText.trim()}","${cells[1]?.innerText.trim()}","${cells[2]?.innerText.trim()}","${cells[3]?.innerText.trim()}","${cells[4]?.innerText.trim()}"\n`;
        }
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'event_registrations.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// Quick add member (if needed)
function quickAddMember() {
    window.location.href = 'member_add.php?redirect=event_register.php?id=<?php echo $event_id; ?>';
}

// Reload page after bulk registration
document.getElementById('bulkRegForm')?.addEventListener('submit', function() {
    setTimeout(() => {
        location.reload();
    }, 2000);
});
</script>

<?php include '../footer.php'; ?>