<?php
// event_register.php - Event Registration Page for Members
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/member_auth.php';

// Require member login
requireMember();

// Get database connection
$db = Database::getInstance()->getConnection();

// Check if event ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage("Invalid event ID!", "danger");
    header('Location: events.php');
    exit();
}

$event_id = (int)$_GET['id'];
$member_id = getCurrentMemberId();

// Get event details
$event_query = "SELECT e.*, 
                       (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as current_registrations
                FROM events e
                WHERE e.event_id = ? AND e.status = 'Active'";

$event_stmt = $db->prepare($event_query);
$event_stmt->bind_param("i", $event_id);
$event_stmt->execute();
$event_result = $event_stmt->get_result();

if ($event_result->num_rows == 0) {
    setFlashMessage("Event not found or no longer available!", "danger");
    header('Location: events.php');
    exit();
}

$event = $event_result->fetch_assoc();

// Check if event has passed
$is_past = strtotime($event['event_date']) < strtotime(date('Y-m-d'));
if ($is_past) {
    setFlashMessage("This event has already passed. Cannot register.", "warning");
    header('Location: events.php');
    exit();
}

// Check if event is full
$is_full = ($event['max_participants'] > 0 && $event['current_registrations'] >= $event['max_participants']);
if ($is_full) {
    setFlashMessage("Sorry, this event has reached maximum capacity.", "warning");
    header('Location: events.php');
    exit();
}

// Check if already registered
$check_query = "SELECT registration_id FROM event_registrations WHERE event_id = ? AND member_id = ?";
$check_stmt = $db->prepare($check_query);
$check_stmt->bind_param("ii", $event_id, $member_id);
$check_stmt->execute();
$already_registered = $check_stmt->get_result()->num_rows > 0;

// Get member details for pre-filling
$member_query = "SELECT first_name, last_name, email, phone FROM members WHERE member_id = ?";
$member_stmt = $db->prepare($member_query);
$member_stmt->bind_param("i", $member_id);
$member_stmt->execute();
$member = $member_stmt->get_result()->fetch_assoc();

// Handle registration submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($already_registered) {
        $error = "You are already registered for this event!";
    } else {
        // Get additional registration info
        $guest_count = (int)($_POST['guest_count'] ?? 0);
        $special_needs = sanitize($_POST['special_needs'] ?? '');
        $dietary_restrictions = sanitize($_POST['dietary_restrictions'] ?? '');
        
        // Insert registration
        $insert_query = "INSERT INTO event_registrations (event_id, member_id, registration_date, guest_count, special_needs, dietary_restrictions, status) 
                         VALUES (?, ?, NOW(), ?, ?, ?, 'confirmed')";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bind_param("iiiss", $event_id, $member_id, $guest_count, $special_needs, $dietary_restrictions);
        
        if ($insert_stmt->execute()) {
            $success = "You have successfully registered for this event!";
            
            // Log the registration
            error_log("Member $member_id registered for event $event_id");
            
            // Redirect to event details after 2 seconds
            header("Refresh: 2; url=event_details.php?id=$event_id");
        } else {
            $error = "Registration failed. Please try again.";
        }
    }
}

// Cancel registration
if (isset($_GET['cancel']) && $_GET['cancel'] == 1 && $already_registered) {
    $delete_stmt = $db->prepare("DELETE FROM event_registrations WHERE event_id = ? AND member_id = ?");
    $delete_stmt->bind_param("ii", $event_id, $member_id);
    
    if ($delete_stmt->execute()) {
        setFlashMessage("Your registration has been cancelled.", "success");
        header("Location: event_details.php?id=$event_id");
        exit();
    }
}

// Set page title
$page_title = "Register for " . $event['event_name'];

// Include header
include 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Success/Error Messages -->
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

            <?php if ($already_registered && !$success): ?>
                <!-- Already Registered View -->
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-header bg-success text-white text-center py-4">
                        <i class="fas fa-check-circle fa-3x mb-2"></i>
                        <h3 class="mb-0">You're Registered!</h3>
                    </div>
                    <div class="card-body p-4 text-center">
                        <p class="lead">You are already registered for:</p>
                        <h4 class="fw-bold text-primary"><?php echo htmlspecialchars($event['event_name']); ?></h4>
                        <p class="text-muted">
                            <i class="fas fa-calendar me-2"></i><?php echo date('l, F j, Y', strtotime($event['event_date'])); ?>
                            <br>
                            <i class="fas fa-clock me-2"></i><?php echo date('g:i A', strtotime($event['event_time'])); ?>
                            <?php if (!empty($event['location'])): ?>
                                <br>
                                <i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($event['location']); ?>
                            <?php endif; ?>
                        </p>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            A confirmation email has been sent to your registered email address.
                        </div>
                        <div class="d-flex justify-content-center gap-3 mt-4">
                            <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-2"></i>View Event Details
                            </a>
                            <a href="?cancel=1&id=<?php echo $event_id; ?>" class="btn btn-outline-danger" 
                               onclick="return confirm('Are you sure you want to cancel your registration?')">
                                <i class="fas fa-times me-2"></i>Cancel Registration
                            </a>
                        </div>
                    </div>
                </div>
            <?php elseif (!$success): ?>
                <!-- Registration Form -->
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-header bg-gradient-primary text-white text-center py-4">
                        <i class="fas fa-ticket-alt fa-3x mb-2"></i>
                        <h3 class="mb-0">Event Registration</h3>
                        <p class="mb-0 mt-2">Complete the form below to register</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <!-- Event Summary -->
                        <div class="event-summary mb-4 p-3 bg-light rounded-3">
                            <h5 class="fw-bold mb-3"><i class="fas fa-calendar-alt me-2 text-primary"></i>Event Details</h5>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <h4 class="mb-2"><?php echo htmlspecialchars($event['event_name']); ?></h4>
                                    <?php if (!empty($event['event_description'])): ?>
                                        <p class="text-muted small mb-2"><?php echo htmlspecialchars(substr($event['event_description'], 0, 150)); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <?php if ($event['max_participants'] > 0): ?>
                                        <span class="badge bg-primary p-2">
                                            <?php echo $event['current_registrations']; ?> / <?php echo $event['max_participants']; ?> registered
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <i class="fas fa-calendar text-primary me-2"></i>
                                            <strong>Date:</strong> <?php echo date('l, F j, Y', strtotime($event['event_date'])); ?>
                                        </div>
                                        <div class="col-md-4">
                                            <i class="fas fa-clock text-primary me-2"></i>
                                            <strong>Time:</strong> <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                            <?php if (!empty($event['end_time'])): ?>
                                                - <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                            <strong>Location:</strong> <?php echo htmlspecialchars($event['location'] ?: 'TBD'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Registration Form -->
                        <form method="POST" action="" id="registrationForm">
                            <h5 class="fw-bold mb-3"><i class="fas fa-user me-2 text-primary"></i>Your Information</h5>
                            
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">First Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($member['first_name']); ?>" readonly disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Last Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($member['last_name']); ?>" readonly disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Email Address</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($member['email']); ?>" readonly disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Phone Number</label>
                                    <input type="tel" class="form-control" value="<?php echo htmlspecialchars($member['phone'] ?? 'Not provided'); ?>" readonly disabled>
                                </div>
                            </div>
                            
                            <h5 class="fw-bold mb-3"><i class="fas fa-users me-2 text-primary"></i>Additional Details</h5>
                            
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label for="guest_count" class="form-label fw-bold">Number of Guests</label>
                                    <select class="form-select" id="guest_count" name="guest_count">
                                        <option value="0">0 (Just me)</option>
                                        <option value="1">1 guest</option>
                                        <option value="2">2 guests</option>
                                        <option value="3">3 guests</option>
                                        <option value="4">4 guests</option>
                                        <option value="5">5+ guests</option>
                                    </select>
                                    <div class="form-text">Including children and family members</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="dietary_restrictions" class="form-label fw-bold">Dietary Restrictions</label>
                                    <input type="text" class="form-control" id="dietary_restrictions" name="dietary_restrictions" 
                                           placeholder="Vegetarian, Gluten-free, Allergies, etc.">
                                </div>
                                
                                <div class="col-12">
                                    <label for="special_needs" class="form-label fw-bold">Special Accommodations</label>
                                    <textarea class="form-control" id="special_needs" name="special_needs" rows="2" 
                                              placeholder="Any accessibility or special needs we should be aware of?"></textarea>
                                </div>
                            </div>
                            
                            <!-- Terms and Conditions -->
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Form Actions -->
                            <div class="d-flex justify-content-between gap-3">
                                <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Event
                                </a>
                                <button type="submit" class="btn btn-primary px-4" id="submitBtn">
                                    <i class="fas fa-check-circle me-2"></i>Complete Registration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Terms and Conditions Modal -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Terms and Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>Event Registration Agreement</h6>
                <p>By registering for this event, you agree to the following:</p>
                <ul>
                    <li>You will provide accurate and complete information</li>
                    <li>You understand that event details may be subject to change</li>
                    <li>You agree to follow all event guidelines and safety protocols</li>
                    <li>Cancellations must be made at least 24 hours in advance</li>
                    <li>Photos may be taken during the event for promotional purposes</li>
                    <li>Your information will be used only for event-related communication</li>
                </ul>
                <h6 class="mt-3">Privacy Policy</h6>
                <p>Your personal information will be kept confidential and used solely for event registration and communication purposes. We do not share your information with third parties.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Registration page specific styles */
.bg-gradient-primary {
    background: linear-gradient(135deg, #4361ee 0%, #06b6d4 100%);
}

.event-summary {
    border-left: 4px solid #4361ee;
}

.form-control:focus, .form-select:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.1);
}

.form-control[readonly] {
    background-color: #f8f9fa;
}

.card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .display-6 {
        font-size: 1.8rem;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .d-flex {
        flex-direction: column;
    }
}

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.card {
    animation: fadeIn 0.5s ease-out;
}

/* Checkbox styling */
.form-check-input:checked {
    background-color: #4361ee;
    border-color: #4361ee;
}
</style>

<script>
// Form validation
document.getElementById('registrationForm')?.addEventListener('submit', function(e) {
    const terms = document.getElementById('terms');
    const submitBtn = document.getElementById('submitBtn');
    
    if (!terms.checked) {
        e.preventDefault();
        alert('Please agree to the Terms and Conditions to continue.');
    } else {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Registering...';
    }
});

// Guest count validation
document.getElementById('guest_count')?.addEventListener('change', function() {
    const guestCount = parseInt(this.value);
    const specialNeedsField = document.getElementById('special_needs');
    
    if (guestCount > 2) {
        specialNeedsField.placeholder = "Please list names of all guests if applicable";
        specialNeedsField.classList.add('border-warning');
    } else {
        specialNeedsField.placeholder = "Any accessibility or special needs we should be aware of?";
        specialNeedsField.classList.remove('border-warning');
    }
});

// Dietary restrictions preview
document.getElementById('dietary_restrictions')?.addEventListener('input', function() {
    if (this.value.length > 0) {
        this.classList.add('border-success');
    } else {
        this.classList.remove('border-success');
    }
});

// Auto-save form data to localStorage (optional)
const formFields = ['guest_count', 'dietary_restrictions', 'special_needs'];

formFields.forEach(field => {
    const element = document.getElementById(field);
    if (element) {
        const savedValue = localStorage.getItem(`event_reg_${field}_<?php echo $event_id; ?>`);
        if (savedValue && element.type !== 'checkbox') {
            element.value = savedValue;
        }
        
        element.addEventListener('input', function() {
            localStorage.setItem(`event_reg_${field}_<?php echo $event_id; ?>`, this.value);
        });
    }
});

// Clear saved form data after successful registration
window.addEventListener('beforeunload', function() {
    if (document.querySelector('.alert-success')) {
        formFields.forEach(field => {
            localStorage.removeItem(`event_reg_${field}_<?php echo $event_id; ?>`);
        });
    }
});

// Prevent double submission
let submitted = false;
document.getElementById('registrationForm')?.addEventListener('submit', function(e) {
    if (submitted) {
        e.preventDefault();
    }
    submitted = true;
});

// Add tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

<?php
// Include footer
include 'footer.php';
?>