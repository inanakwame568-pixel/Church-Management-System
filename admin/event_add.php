<?php
// admin/event_add.php - Add/Edit Events
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get database connection
$db = Database::getInstance()->getConnection();

// Check if editing existing event
$edit_mode = isset($_GET['id']) && is_numeric($_GET['id']);
$event_id = $edit_mode ? (int)$_GET['id'] : 0;

// Initialize form data
$form_data = [
    'event_name' => '',
    'event_description' => '',
    'event_date' => date('Y-m-d'),
    'event_time' => '09:00:00',
    'end_date' => '',
    'end_time' => '',
    'location' => '',
    'organizer' => '',
    'max_participants' => 0
];

// If editing, load existing data
if ($edit_mode) {
    $stmt = $db->prepare("SELECT * FROM events WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $form_data = $result->fetch_assoc();
        // Format date for input fields
        $form_data['event_date'] = date('Y-m-d', strtotime($form_data['event_date']));
        if (!empty($form_data['end_date'])) {
            $form_data['end_date'] = date('Y-m-d', strtotime($form_data['end_date']));
        }
    } else {
        setFlashMessage("Event not found!", "danger");
        header('Location: events.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $form_data['event_name'] = sanitize($_POST['event_name'] ?? '');
    $form_data['event_description'] = sanitize($_POST['event_description'] ?? '');
    $form_data['event_date'] = $_POST['event_date'] ?? date('Y-m-d');
    $form_data['event_time'] = $_POST['event_time'] ?? '09:00:00';
    $form_data['end_date'] = $_POST['end_date'] ?? null;
    $form_data['end_time'] = $_POST['end_time'] ?? null;
    $form_data['location'] = sanitize($_POST['location'] ?? '');
    $form_data['organizer'] = sanitize($_POST['organizer'] ?? '');
    $form_data['max_participants'] = !empty($_POST['max_participants']) ? (int)$_POST['max_participants'] : 0;
    
    // Validation
    $errors = [];
    
    if (empty($form_data['event_name'])) {
        $errors[] = "Event name is required!";
    }
    
    if (empty($form_data['event_date'])) {
        $errors[] = "Event date is required!";
    } else {
        // Check if date is valid
        $date_obj = DateTime::createFromFormat('Y-m-d', $form_data['event_date']);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $form_data['event_date']) {
            $errors[] = "Invalid event date format!";
        }
    }
    
    if (empty($form_data['event_time'])) {
        $errors[] = "Event time is required!";
    }
    
    // Validate end date if provided
    if (!empty($form_data['end_date'])) {
        $end_date_obj = DateTime::createFromFormat('Y-m-d', $form_data['end_date']);
        if (!$end_date_obj || $end_date_obj->format('Y-m-d') !== $form_data['end_date']) {
            $errors[] = "Invalid end date format!";
        } elseif (strtotime($form_data['end_date']) < strtotime($form_data['event_date'])) {
            $errors[] = "End date cannot be before start date!";
        }
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        try {
            if ($edit_mode) {
                // Update existing event
                $query = "UPDATE events SET 
                         event_name = ?, 
                         event_description = ?, 
                         event_date = ?, 
                         event_time = ?, 
                         end_date = ?, 
                         end_time = ?, 
                         location = ?, 
                         organizer = ?, 
                         max_participants = ? 
                         WHERE event_id = ?";
                
                $stmt = $db->prepare($query);
                $stmt->bind_param(
                    "ssssssssii",
                    $form_data['event_name'],
                    $form_data['event_description'],
                    $form_data['event_date'],
                    $form_data['event_time'],
                    $form_data['end_date'],
                    $form_data['end_time'],
                    $form_data['location'],
                    $form_data['organizer'],
                    $form_data['max_participants'],
                    $event_id
                );
                
                if ($stmt->execute()) {
                    setFlashMessage("Event updated successfully!", "success");
                    header('Location: events.php');
                    exit();
                } else {
                    $errors[] = "Failed to update event: " . $db->error;
                }
            } else {
                // Insert new event
                $query = "INSERT INTO events (
                         event_name, 
                         event_description, 
                         event_date, 
                         event_time, 
                         end_date, 
                         end_time, 
                         location, 
                         organizer, 
                         max_participants,
                         created_by
                         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $db->prepare($query);
                $created_by = getCurrentUserId();
                $stmt->bind_param(
                    "ssssssssii",
                    $form_data['event_name'],
                    $form_data['event_description'],
                    $form_data['event_date'],
                    $form_data['event_time'],
                    $form_data['end_date'],
                    $form_data['end_time'],
                    $form_data['location'],
                    $form_data['organizer'],
                    $form_data['max_participants'],
                    $created_by
                );
                
                if ($stmt->execute()) {
                    $new_event_id = $db->insert_id;
                    setFlashMessage("Event created successfully!", "success");
                    header('Location: event_view.php?id=' . $new_event_id);
                    exit();
                } else {
                    $errors[] = "Failed to create event: " . $db->error;
                }
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
            error_log("Event save error: " . $e->getMessage());
        }
    }
}

// Set page title
$page_title = $edit_mode ? "Edit Event" : "Create New Event";

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
                        <i class="fas fa-<?php echo $edit_mode ? 'edit' : 'plus-circle'; ?> me-3 text-primary"></i>
                        <?php echo $edit_mode ? 'Edit Event' : 'Create New Event'; ?>
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <?php echo $edit_mode ? 'Update event details' : 'Add a new event to the calendar'; ?>
                    </p>
                </div>
                <div>
                    <a href="events.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Events
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Event Form -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="" id="eventForm">
                        <!-- Basic Information -->
                        <h5 class="mb-3 fw-bold">Basic Information</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label for="event_name" class="form-label fw-bold">
                                    Event Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       id="event_name" 
                                       name="event_name" 
                                       value="<?php echo htmlspecialchars($form_data['event_name']); ?>" 
                                       placeholder="Enter event name"
                                       required>
                            </div>
                            
                            <div class="col-12">
                                <label for="event_description" class="form-label fw-bold">Description</label>
                                <textarea class="form-control" 
                                          id="event_description" 
                                          name="event_description" 
                                          rows="4" 
                                          placeholder="Enter event description"><?php echo htmlspecialchars($form_data['event_description']); ?></textarea>
                                <small class="text-muted">Provide details about the event, agenda, speakers, etc.</small>
                            </div>
                        </div>

                        <!-- Date and Time -->
                        <h5 class="mb-3 fw-bold">Date & Time</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="event_date" class="form-label fw-bold">
                                    Start Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" 
                                       class="form-control" 
                                       id="event_date" 
                                       name="event_date" 
                                       value="<?php echo $form_data['event_date']; ?>" 
                                       min="<?php echo date('Y-m-d'); ?>"
                                       required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="event_time" class="form-label fw-bold">
                                    Start Time <span class="text-danger">*</span>
                                </label>
                                <input type="time" 
                                       class="form-control" 
                                       id="event_time" 
                                       name="event_time" 
                                       value="<?php echo $form_data['event_time']; ?>"
                                       required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="end_date" class="form-label fw-bold">End Date</label>
                                <input type="date" 
                                       class="form-control" 
                                       id="end_date" 
                                       name="end_date" 
                                       value="<?php echo $form_data['end_date']; ?>" 
                                       min="<?php echo date('Y-m-d'); ?>">
                                <small class="text-muted">Leave blank if same as start date</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="end_time" class="form-label fw-bold">End Time</label>
                                <input type="time" 
                                       class="form-control" 
                                       id="end_time" 
                                       name="end_time" 
                                       value="<?php echo $form_data['end_time']; ?>">
                                <small class="text-muted">Leave blank if no specific end time</small>
                            </div>
                        </div>

                        <!-- Location and Organizer -->
                        <h5 class="mb-3 fw-bold">Location & Organization</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="location" class="form-label fw-bold">Location</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="location" 
                                       name="location" 
                                       value="<?php echo htmlspecialchars($form_data['location']); ?>" 
                                       placeholder="Enter venue or online link">
                                <small class="text-muted">Building, room, or virtual meeting link</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="organizer" class="form-label fw-bold">Organizer</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="organizer" 
                                       name="organizer" 
                                       value="<?php echo htmlspecialchars($form_data['organizer']); ?>" 
                                       placeholder="Enter organizer name">
                                <small class="text-muted">Person or ministry organizing the event</small>
                            </div>
                        </div>

                        <!-- Capacity Settings -->
                        <h5 class="mb-3 fw-bold">Capacity Settings</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="max_participants" class="form-label fw-bold">Maximum Participants</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="max_participants" 
                                       name="max_participants" 
                                       value="<?php echo $form_data['max_participants']; ?>" 
                                       min="0"
                                       placeholder="0 for unlimited">
                                <small class="text-muted">Enter 0 for unlimited capacity</small>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <hr>
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="events.php" class="btn btn-secondary px-4">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary px-5" id="submitBtn">
                                        <i class="fas fa-<?php echo $edit_mode ? 'save' : 'plus-circle'; ?> me-2"></i>
                                        <?php echo $edit_mode ? 'Update Event' : 'Create Event'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Form styling */
.form-label {
    color: #495057;
    margin-bottom: 0.5rem;
}

.form-control, .form-select {
    border: 2px solid #e9ecef;
    padding: 0.6rem 1rem;
    transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.1);
}

.form-control-lg {
    font-size: 1rem;
}

/* Card styling */
.card {
    border-radius: 1rem;
    overflow: hidden;
}

/* Section headers */
h5 {
    color: #4361ee;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e9ecef;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .d-flex {
        flex-direction: column;
    }
}
</style>

<script>
// Form validation
document.getElementById('eventForm').addEventListener('submit', function(e) {
    const eventName = document.getElementById('event_name').value.trim();
    const eventDate = document.getElementById('event_date').value;
    const eventTime = document.getElementById('event_time').value;
    const endDate = document.getElementById('end_date').value;
    const submitBtn = document.getElementById('submitBtn');
    
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
    
    // Validate end date is not before start date
    if (endDate && eventDate) {
        if (new Date(endDate) < new Date(eventDate)) {
            errorMessages.push('End date cannot be before start date');
            document.getElementById('end_date').classList.add('is-invalid');
            isValid = false;
        } else {
            document.getElementById('end_date').classList.remove('is-invalid');
        }
    }
    
    if (!isValid) {
        e.preventDefault();
        // Show error messages
        alert('Please fix the following errors:\n- ' + errorMessages.join('\n- '));
    } else {
        // Disable button to prevent double submission
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + 
                             (submitBtn.innerHTML.includes('Update') ? 'Updating...' : 'Creating...');
    }
});

// Preview event date and time
function updatePreview() {
    const name = document.getElementById('event_name').value || 'Event Name';
    const date = document.getElementById('event_date').value;
    const time = document.getElementById('event_time').value;
    const location = document.getElementById('location').value || 'TBD';
    
    let previewHtml = `
        <div class="card bg-light mt-3">
            <div class="card-body">
                <h6 class="mb-2">Preview:</h6>
                <p class="mb-1"><strong>${name}</strong></p>
    `;
    
    if (date) {
        const dateObj = new Date(date);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        previewHtml += `<p class="mb-1 small"><i class="fas fa-calendar me-2"></i>${dateObj.toLocaleDateString('en-US', options)}`;
        if (time) {
            previewHtml += ` at ${time}`;
        }
        previewHtml += `</p>`;
    }
    
    previewHtml += `<p class="mb-1 small"><i class="fas fa-map-marker-alt me-2"></i>${location}</p>`;
    previewHtml += `</div></div>`;
    
    // You could add a preview element to show this
}

// Add input event listeners for preview
['event_name', 'event_date', 'event_time', 'location'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', updatePreview);
});

// Set min date for end date based on start date
document.getElementById('event_date').addEventListener('change', function() {
    document.getElementById('end_date').min = this.value;
});

// Auto-capitalize first letter of event name
document.getElementById('event_name').addEventListener('blur', function() {
    if (this.value.length > 0) {
        this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1);
    }
});

// Character counter for description
document.getElementById('event_description').addEventListener('input', function() {
    const maxLength = 500;
    const currentLength = this.value.length;
    const remaining = maxLength - currentLength;
    
    // Create or update counter element
    let counter = document.getElementById('charCounter');
    if (!counter) {
        counter = document.createElement('small');
        counter.id = 'charCounter';
        counter.className = 'text-muted float-end';
        this.parentNode.appendChild(counter);
    }
    
    counter.textContent = `${currentLength}/${maxLength} characters`;
    
    if (remaining < 50) {
        counter.classList.add('text-warning');
    } else {
        counter.classList.remove('text-warning');
    }
    
    if (remaining < 0) {
        counter.classList.add('text-danger');
    }
});

// Warn user if they try to leave with unsaved changes
let formChanged = false;
document.querySelectorAll('#eventForm input, #eventForm textarea').forEach(element => {
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

// Initialize preview on page load
updatePreview();
</script>

<?php
// Include footer
include '../footer.php';
?>