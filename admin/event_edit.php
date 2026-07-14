<?php
// admin/event_edit.php - Edit Event Details
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

// Get event data
$stmt = $db->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setFlashMessage("Event not found!", "danger");
    header('Location: events.php');
    exit();
}

$event = $result->fetch_assoc();

// Check if event has passed
$is_past = strtotime($event['event_date']) < strtotime(date('Y-m-d'));
$has_registrations = false;

// Check if event has registrations
$reg_check = $db->prepare("SELECT COUNT(*) as count FROM event_registrations WHERE event_id = ?");
$reg_check->bind_param("i", $event_id);
$reg_check->execute();
$reg_result = $reg_check->get_result();
$registration_count = $reg_result->fetch_assoc()['count'];
$has_registrations = $registration_count > 0;

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_event'])) {
    // Get and sanitize form data
    $event_name = sanitize($_POST['event_name'] ?? '');
    $event_description = sanitize($_POST['event_description'] ?? '');
    $event_type = sanitize($_POST['event_type'] ?? '');
    $event_date = $_POST['event_date'] ?? date('Y-m-d');
    $event_time = $_POST['event_time'] ?? '09:00:00';
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
    $location = sanitize($_POST['location'] ?? '');
    $organizer = sanitize($_POST['organizer'] ?? '');
    $max_participants = !empty($_POST['max_participants']) ? (int)$_POST['max_participants'] : 0;
    $status = $_POST['status'] ?? 'Active';
    
    // Validation
    $errors = [];
    
    if (empty($event_name)) {
        $errors[] = "Event name is required!";
    }
    
    if (empty($event_date)) {
        $errors[] = "Event date is required!";
    }
    
    if (empty($event_time)) {
        $errors[] = "Event time is required!";
    }
    
    // Validate that end date is not before start date
    if (!empty($end_date) && strtotime($end_date) < strtotime($event_date)) {
        $errors[] = "End date cannot be before start date!";
    }
    
    // Check if reducing capacity below current registrations
    if ($max_participants > 0 && $max_participants < $registration_count) {
        $errors[] = "Cannot reduce capacity below current registrations ($registration_count). Please cancel some registrations first.";
    }
    
    // If no errors, update database
    if (empty($errors)) {
        $update_query = "UPDATE events SET 
                         event_name = ?, 
                         event_description = ?, 
                         event_type = ?,
                         event_date = ?, 
                         event_time = ?, 
                         end_date = ?, 
                         end_time = ?, 
                         location = ?, 
                         organizer = ?, 
                         max_participants = ?,
                         status = ?
                         WHERE event_id = ?";
        
        $stmt = $db->prepare($update_query);
        $stmt->bind_param(
            "sssssssssisi",
            $event_name,
            $event_description,
            $event_type,
            $event_date,
            $event_time,
            $end_date,
            $end_time,
            $location,
            $organizer,
            $max_participants,
            $status,
            $event_id
        );
        
        if ($stmt->execute()) {
            setFlashMessage("Event updated successfully!", "success");
            header('Location: event_view.php?id=' . $event_id);
            exit();
        } else {
            $errors[] = "Failed to update event: " . $db->error;
        }
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

// Get event types for dropdown
$event_types = $db->query("SELECT DISTINCT event_type FROM events WHERE event_type IS NOT NULL UNION SELECT 'General' UNION SELECT 'Worship' UNION SELECT 'Bible Study' UNION SELECT 'Prayer Meeting' UNION SELECT 'Youth' UNION SELECT 'Children' UNION SELECT 'Fellowship' UNION SELECT 'Outreach'");

// Set page title
$page_title = "Edit Event - " . $event['event_name'];

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
                        <i class="fas fa-edit me-3 text-primary"></i>
                        Edit Event
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Event ID: #<?php echo $event['event_id']; ?> | 
                        Created: <?php echo date('M d, Y', strtotime($event['created_at'])); ?>
                        <?php if ($has_registrations): ?>
                            | <span class="text-warning"><i class="fas fa-users me-1"></i><?php echo $registration_count; ?> registrations</span>
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

    <!-- Warning if event has passed -->
    <?php if ($is_past): ?>
        <div class="alert alert-warning mb-4">
            <i class="fas fa-history me-2"></i>
            <strong>Note:</strong> This event has already passed (<?php echo date('M d, Y', strtotime($event['event_date'])); ?>). 
            Changes may affect historical records.
        </div>
    <?php endif; ?>

    <!-- Warning if event has registrations -->
    <?php if ($has_registrations): ?>
        <div class="alert alert-info mb-4">
            <i class="fas fa-users me-2"></i>
            <strong><?php echo $registration_count; ?> people registered for this event.</strong>
            Changes to date, time, or location will be communicated to registrants. 
            <a href="event_view.php?id=<?php echo $event_id; ?>" class="alert-link">Manage registrations</a>
        </div>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Edit Event Form -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="eventTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">
                                <i class="fas fa-info-circle me-2"></i>Basic Information
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab">
                                <i class="fas fa-calendar me-2"></i>Schedule
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">
                                <i class="fas fa-cog me-2"></i>Settings
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body p-4">
                    <form method="POST" action="" id="eventForm">
                        <div class="tab-content" id="eventTabsContent">
                            <!-- Basic Information Tab -->
                            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="event_name" class="form-label fw-bold">
                                            Event Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control form-control-lg" 
                                               id="event_name" name="event_name" 
                                               value="<?php echo htmlspecialchars($event['event_name']); ?>" 
                                               placeholder="Enter event name"
                                               required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="event_type" class="form-label fw-bold">Event Type</label>
                                        <select class="form-select" id="event_type" name="event_type">
                                            <option value="">Select Type</option>
                                            <?php 
                                            $types_used = [];
                                            while ($type = $event_types->fetch_assoc()): 
                                                $type_name = $type['event_type'];
                                                if (in_array($type_name, $types_used)) continue;
                                                $types_used[] = $type_name;
                                            ?>
                                                <option value="<?php echo $type_name; ?>" 
                                                    <?php echo $event['event_type'] == $type_name ? 'selected' : ''; ?>>
                                                    <?php echo $type_name; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="organizer" class="form-label fw-bold">Organizer</label>
                                        <input type="text" class="form-control" id="organizer" name="organizer" 
                                               value="<?php echo htmlspecialchars($event['organizer'] ?? ''); ?>"
                                               placeholder="Enter organizer name">
                                    </div>
                                    
                                    <div class="col-12">
                                        <label for="event_description" class="form-label fw-bold">Description</label>
                                        <textarea class="form-control" id="event_description" name="event_description" 
                                                  rows="5" placeholder="Enter event description"><?php echo htmlspecialchars($event['event_description'] ?? ''); ?></textarea>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Provide details about the event, agenda, speakers, etc.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Schedule Tab -->
                            <div class="tab-pane fade" id="schedule" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="event_date" class="form-label fw-bold">
                                            Start Date <span class="text-danger">*</span>
                                        </label>
                                        <input type="date" class="form-control" id="event_date" name="event_date" 
                                               value="<?php echo $event['event_date']; ?>" 
                                               min="<?php echo $is_past ? '' : date('Y-m-d'); ?>"
                                               required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="event_time" class="form-label fw-bold">
                                            Start Time <span class="text-danger">*</span>
                                        </label>
                                        <input type="time" class="form-control" id="event_time" name="event_time" 
                                               value="<?php echo $event['event_time']; ?>"
                                               required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="end_date" class="form-label fw-bold">End Date</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" 
                                               value="<?php echo $event['end_date']; ?>"
                                               min="<?php echo $event['event_date']; ?>">
                                        <div class="form-text">Leave blank if same as start date</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="end_time" class="form-label fw-bold">End Time</label>
                                        <input type="time" class="form-control" id="end_time" name="end_time" 
                                               value="<?php echo $event['end_time']; ?>">
                                    </div>
                                    
                                    <div class="col-12">
                                        <label for="location" class="form-label fw-bold">Location</label>
                                        <input type="text" class="form-control" id="location" name="location" 
                                               value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>"
                                               placeholder="Enter venue or online link">
                                        <div class="form-text">Building, room, or virtual meeting link</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Settings Tab -->
                            <div class="tab-pane fade" id="settings" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="max_participants" class="form-label fw-bold">Maximum Participants</label>
                                        <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                               value="<?php echo $event['max_participants']; ?>" 
                                               min="0" step="1">
                                        <div class="form-text">Enter 0 for unlimited capacity</div>
                                        <?php if ($has_registrations && $event['max_participants'] > 0): ?>
                                            <div class="small text-warning mt-1">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                Current registrations: <?php echo $registration_count; ?>. Cannot reduce below this number.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="status" class="form-label fw-bold">Event Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="Active" <?php echo ($event['status'] ?? 'Active') == 'Active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="Cancelled" <?php echo ($event['status'] ?? '') == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            <option value="Completed" <?php echo ($event['status'] ?? '') == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <?php if ($has_registrations): ?>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Changing status to "Cancelled" will notify registered participants.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <a href="event_view.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                    <button type="submit" name="update_event" class="btn btn-primary px-5" id="submitBtn">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Danger Zone (for admin only) -->
    <?php if (isAdmin() && !$has_registrations): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-danger shadow-sm">
                <div class="card-header bg-danger bg-opacity-10 text-danger py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Danger Zone
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h6 class="mb-1">Delete this event</h6>
                            <p class="text-muted small mb-md-0">Once deleted, this event cannot be recovered.</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <button class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $event_id; ?>, '<?php echo htmlspecialchars($event['event_name']); ?>')">
                                <i class="fas fa-trash me-2"></i>Delete Event
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
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
                <p>Are you sure you want to delete <strong id="deleteEventName"></strong>?</p>
                <p class="text-warning mb-0">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    This action cannot be undone. All registration data will be permanently removed.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete Event</a>
            </div>
        </div>
    </div>
</div>

<style>
/* Form styling */
.form-label {
    font-size: 0.9rem;
    margin-bottom: 0.3rem;
}

.form-control, .form-select {
    border: 2px solid #e9ecef;
    padding: 0.6rem 1rem;
    transition: all 0.3s ease;
    border-radius: 10px;
}

.form-control:focus, .form-select:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.1);
}

.form-control-lg {
    font-size: 1rem;
}

/* Tab styling */
.nav-tabs .nav-link {
    color: #64748b;
    border: none;
    padding: 0.75rem 1.5rem;
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

/* Card styling */
.card {
    border-radius: 16px;
    overflow: hidden;
}

/* Responsive */
@media (max-width: 768px) {
    .nav-tabs .nav-link {
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .d-flex.justify-content-between {
        flex-direction: column;
    }
}
</style>

<script>
// Form validation
document.getElementById('eventForm')?.addEventListener('submit', function(e) {
    const eventName = document.getElementById('event_name').value.trim();
    const eventDate = document.getElementById('event_date').value;
    const eventTime = document.getElementById('event_time').value;
    const endDate = document.getElementById('end_date').value;
    const maxParticipants = parseInt(document.getElementById('max_participants').value);
    const currentRegistrations = <?php echo $registration_count; ?>;
    
    let isValid = true;
    let errorMessages = [];
    
    if (!eventName) {
        errorMessages.push('Event name is required');
        document.getElementById('event_name').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('event_name').classList.remove('is-invalid');
    }
    
    if (!eventDate) {
        errorMessages.push('Event date is required');
        document.getElementById('event_date').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('event_date').classList.remove('is-invalid');
    }
    
    if (!eventTime) {
        errorMessages.push('Event time is required');
        document.getElementById('event_time').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('event_time').classList.remove('is-invalid');
    }
    
    // Validate end date not before start date
    if (endDate && eventDate && new Date(endDate) < new Date(eventDate)) {
        errorMessages.push('End date cannot be before start date');
        document.getElementById('end_date').classList.add('is-invalid');
        isValid = false;
    } else if (document.getElementById('end_date')) {
        document.getElementById('end_date').classList.remove('is-invalid');
    }
    
    // Validate capacity
    if (maxParticipants > 0 && maxParticipants < currentRegistrations) {
        errorMessages.push(`Cannot reduce capacity below current registrations (${currentRegistrations})`);
        document.getElementById('max_participants').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('max_participants').classList.remove('is-invalid');
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fix the following errors:\n- ' + errorMessages.join('\n- '));
    } else {
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    }
});

// Set min date for end date based on start date
document.getElementById('event_date').addEventListener('change', function() {
    const endDateInput = document.getElementById('end_date');
    if (endDateInput) {
        endDateInput.min = this.value;
        if (endDateInput.value && new Date(endDateInput.value) < new Date(this.value)) {
            endDateInput.value = '';
        }
    }
});

// Update end date minimum when start date changes
document.getElementById('event_date').dispatchEvent(new Event('change'));

// Preview changes summary
function showPreview() {
    const name = document.getElementById('event_name').value || 'Event Name';
    const date = document.getElementById('event_date').value;
    const time = document.getElementById('event_time').value;
    const location = document.getElementById('location').value || 'TBD';
    
    let previewHtml = `
        <div class="alert alert-info mt-3">
            <strong>Preview:</strong><br>
            <strong>${escapeHtml(name)}</strong><br>
            <i class="fas fa-calendar me-1"></i> ${date ? new Date(date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : 'Date TBD'}
            ${time ? ` at ${time}` : ''}<br>
            <i class="fas fa-map-marker-alt me-1"></i> ${escapeHtml(location)}
        </div>
    `;
    
    const existingPreview = document.querySelector('.alert-info');
    if (existingPreview) existingPreview.remove();
    
    const scheduleTab = document.getElementById('schedule');
    if (scheduleTab) {
        scheduleTab.insertAdjacentHTML('beforeend', previewHtml);
    }
}

// Add change listeners for preview
['event_name', 'event_date', 'event_time', 'location'].forEach(id => {
    const element = document.getElementById(id);
    if (element) element.addEventListener('change', showPreview);
});

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Delete confirmation
function confirmDelete(eventId, eventName) {
    document.getElementById('deleteEventName').textContent = eventName;
    document.getElementById('confirmDeleteBtn').href = 'events.php?delete=' + eventId;
    
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Warn before leaving with unsaved changes
let formChanged = false;
document.querySelectorAll('#eventForm input, #eventForm select, #eventForm textarea').forEach(element => {
    element.addEventListener('change', function() {
        formChanged = true;
    });
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
    }
});

// Character counter for description
const descriptionField = document.getElementById('event_description');
if (descriptionField) {
    descriptionField.addEventListener('input', function() {
        const maxLength = 2000;
        const currentLength = this.value.length;
        const remaining = maxLength - currentLength;
        
        let counter = document.getElementById('charCounter');
        if (!counter) {
            counter = document.createElement('small');
            counter.id = 'charCounter';
            counter.className = 'text-muted float-end';
            this.parentNode.appendChild(counter);
        }
        
        counter.textContent = `${currentLength}/${maxLength} characters`;
        
        if (remaining < 200) {
            counter.classList.add('text-warning');
        } else {
            counter.classList.remove('text-warning');
        }
    });
    
    descriptionField.dispatchEvent(new Event('input'));
}

// Initialize preview
showPreview();
</script>

<?php include '../footer.php'; ?>