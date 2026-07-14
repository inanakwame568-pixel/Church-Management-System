<?php
// event_details.php - Public Event Details Page
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get database connection
$db = Database::getInstance()->getConnection();

// Check if event ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: events.php');
    exit();
}

$event_id = (int)$_GET['id'];

// Get event details with statistics
$query = "SELECT e.*, 
                 (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id) as registrations,
                 (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.event_id AND attended = 1) as attended_count,
                 CASE 
                    WHEN e.event_date < CURDATE() THEN 'Past'
                    WHEN e.event_date = CURDATE() THEN 'Today'
                    ELSE 'Upcoming'
                 END as status
          FROM events e
          WHERE e.event_id = ? AND e.status = 'Active'";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: events.php');
    exit();
}

$event = $result->fetch_assoc();

// Check if current user is logged in and registered
$is_logged_in = isset($_SESSION['user_id']);
$is_registered = false;
$member_id = null;

if ($is_logged_in) {
    // Get member ID from user
    $user_id = $_SESSION['user_id'];
    $member_query = "SELECT member_id FROM members WHERE email = (SELECT email FROM users WHERE user_id = ?)";
    $member_stmt = $db->prepare($member_query);
    $member_stmt->bind_param("i", $user_id);
    $member_stmt->execute();
    $member_result = $member_stmt->get_result();
    if ($member_result->num_rows > 0) {
        $member_id = $member_result->fetch_assoc()['member_id'];
        
        // Check if registered
        $reg_query = "SELECT registration_id FROM event_registrations WHERE event_id = ? AND member_id = ?";
        $reg_stmt = $db->prepare($reg_query);
        $reg_stmt->bind_param("ii", $event_id, $member_id);
        $reg_stmt->execute();
        $is_registered = $reg_stmt->get_result()->num_rows > 0;
    }
}

// Get similar events
$similar_query = "SELECT event_id, event_name, event_date, event_time, location 
                  FROM events 
                  WHERE event_type = ? 
                  AND event_id != ? 
                  AND event_date >= CURDATE() 
                  AND status = 'Active'
                  ORDER BY event_date ASC 
                  LIMIT 3";
$similar_stmt = $db->prepare($similar_query);
$similar_stmt->bind_param("si", $event['event_type'], $event_id);
$similar_stmt->execute();
$similar_events = $similar_stmt->get_result();

// Set page title
$page_title = $event['event_name'];

// Include header
include 'header.php';
?>

<!-- Event Hero Section -->
<section class="event-hero" style="background: linear-gradient(135deg, rgba(67, 97, 238, 0.9), rgba(6, 182, 212, 0.9)), url('assets/images/event-hero.jpg') center/cover;">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center text-white">
                <h1 class="display-4 fw-bold mb-3"><?php echo htmlspecialchars($event['event_name']); ?></h1>
                <div class="d-flex justify-content-center gap-4 flex-wrap">
                    <span class="badge bg-light text-dark px-3 py-2">
                        <i class="fas fa-calendar me-2 text-primary"></i><?php echo date('l, F j, Y', strtotime($event['event_date'])); ?>
                    </span>
                    <span class="badge bg-light text-dark px-3 py-2">
                        <i class="fas fa-clock me-2 text-primary"></i><?php echo date('g:i A', strtotime($event['event_time'])); ?>
                        <?php if (!empty($event['end_time'])): ?>
                            - <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                        <?php endif; ?>
                    </span>
                    <?php if ($event['location']): ?>
                        <span class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-map-marker-alt me-2 text-danger"></i><?php echo htmlspecialchars($event['location']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<div class="container py-5">
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
                            <br><small>Registration is open - <?php echo $event['registrations']; ?> people registered</small>
                        <?php elseif ($event['status'] == 'Today'): ?>
                            <br><small>Happening today! Join us for this event</small>
                        <?php else: ?>
                            <br><small>This event has already ended</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Event Description -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        About This Event
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($event['event_description'])): ?>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($event['event_description'])); ?></p>
                    <?php else: ?>
                        <p class="text-muted mb-0">No description provided for this event.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Event Details Grid -->
            <div class="row g-4 mb-4">
                <?php if ($event['organizer']): ?>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-user-circle fa-2x text-primary mb-2"></i>
                                <h6 class="fw-bold mb-2">Organizer</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($event['organizer']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($event['max_participants'] > 0): ?>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                <h6 class="fw-bold mb-2">Capacity</h6>
                                <p class="mb-0">
                                    <?php echo $event['registrations']; ?> / <?php echo $event['max_participants']; ?> registered
                                </p>
                                <?php 
                                $percentage = $event['max_participants'] > 0 ? round(($event['registrations'] / $event['max_participants']) * 100) : 0;
                                ?>
                                <div class="progress mt-2" style="height: 5px;">
                                    <div class="progress-bar <?php 
                                        echo $percentage >= 100 ? 'bg-danger' : ($percentage >= 80 ? 'bg-warning' : 'bg-success'); 
                                    ?>" style="width: <?php echo min($percentage, 100); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Registration Section -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-ticket-alt me-2 text-success"></i>
                        Registration
                    </h5>
                </div>
                <div class="card-body text-center py-4">
                    <?php if ($event['status'] == 'Past'): ?>
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <h5>Event Has Ended</h5>
                        <p class="text-muted">This event has already taken place.</p>
                    <?php elseif ($event['status'] == 'Today'): ?>
                        <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                        <h5>Happening Today!</h5>
                        <p class="text-muted">Join us for this event today.</p>
                        <a href="event_register.php?id=<?php echo $event_id; ?>" class="btn btn-primary btn-lg mt-2">
                            <i class="fas fa-ticket-alt me-2"></i>Register Now
                        </a>
                    <?php elseif ($event['max_participants'] > 0 && $event['registrations'] >= $event['max_participants']): ?>
                        <i class="fas fa-ban fa-3x text-danger mb-3"></i>
                        <h5>Event is Full</h5>
                        <p class="text-muted">Sorry, this event has reached maximum capacity.</p>
                        <button class="btn btn-secondary" disabled>
                            <i class="fas fa-times me-2"></i>Registration Closed
                        </button>
                    <?php elseif ($is_registered): ?>
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5>You're Registered!</h5>
                        <p class="text-muted">You have successfully registered for this event.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="event_register.php?cancel=1&id=<?php echo $event_id; ?>" 
                               class="btn btn-outline-danger"
                               onclick="return confirm('Cancel your registration?')">
                                <i class="fas fa-times me-2"></i>Cancel Registration
                            </a>
                            <button class="btn btn-outline-primary" onclick="addToCalendar()">
                                <i class="fas fa-calendar-plus me-2"></i>Add to Calendar
                            </button>
                        </div>
                    <?php elseif ($is_logged_in): ?>
                        <i class="fas fa-ticket-alt fa-3x text-primary mb-3"></i>
                        <h5>Ready to Join?</h5>
                        <p class="text-muted">Register now to secure your spot for this event.</p>
                        <a href="event_register.php?id=<?php echo $event_id; ?>" class="btn btn-primary btn-lg">
                            <i class="fas fa-check-circle me-2"></i>Register Now
                        </a>
                    <?php else: ?>
                        <i class="fas fa-lock fa-3x text-muted mb-3"></i>
                        <h5>Login to Register</h5>
                        <p class="text-muted">Please login or create an account to register for this event.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="login.php?redirect=event_details.php?id=<?php echo $event_id; ?>" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </a>
                            <a href="register.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column - Sidebar -->
        <div class="col-lg-4">
            <!-- Share Event -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-share-alt me-2 text-primary"></i>
                        Share This Event
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-around">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(APP_URL . '/event_details.php?id=' . $event_id); ?>" 
                           target="_blank" class="btn btn-outline-primary rounded-circle" style="width: 45px; height: 45px;">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(APP_URL . '/event_details.php?id=' . $event_id); ?>&text=<?php echo urlencode('Check out this event: ' . $event['event_name']); ?>" 
                           target="_blank" class="btn btn-outline-info rounded-circle" style="width: 45px; height: 45px;">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://wa.me/?text=<?php echo urlencode('Check out this event: ' . $event['event_name'] . ' - ' . APP_URL . '/event_details.php?id=' . $event_id); ?>" 
                           target="_blank" class="btn btn-outline-success rounded-circle" style="width: 45px; height: 45px;">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <button onclick="copyEventLink()" class="btn btn-outline-secondary rounded-circle" style="width: 45px; height: 45px;">
                            <i class="fas fa-link"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Event Location Map -->
            <?php if ($event['location']): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-map-marker-alt me-2 text-danger"></i>
                        Location
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div style="height: 200px; background: #e9ecef; border-radius: 10px 10px 0 0; display: flex; align-items: center; justify-content: center;">
                        <div class="text-center">
                            <i class="fas fa-map fa-3x text-muted mb-2"></i>
                            <p class="mb-0">Map View Available</p>
                            <small class="text-muted"><?php echo htmlspecialchars($event['location']); ?></small>
                        </div>
                    </div>
                    <div class="card-body">
                        <a href="https://maps.google.com/?q=<?php echo urlencode($event['location']); ?>" target="_blank" class="btn btn-outline-primary w-100">
                            <i class="fas fa-directions me-2"></i>Get Directions
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Similar Events -->
            <?php if ($similar_events->num_rows > 0): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-calendar-alt me-2 text-primary"></i>
                        Similar Events
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php while ($similar = $similar_events->fetch_assoc()): ?>
                            <a href="event_details.php?id=<?php echo $similar['event_id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($similar['event_name']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i><?php echo date('M d', strtotime($similar['event_date'])); ?>
                                            <i class="fas fa-clock ms-2 me-1"></i><?php echo date('g:i A', strtotime($similar['event_time'])); ?>
                                        </small>
                                    </div>
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Event page specific styles */
.event-hero {
    padding: 80px 0;
    margin-top: -20px;
    color: white;
}

.badge.bg-light {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(5px);
}

.card {
    border-radius: 16px;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

.progress {
    background-color: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.3s ease;
}

/* Responsive */
@media (max-width: 768px) {
    .event-hero {
        padding: 60px 0;
    }
    
    .event-hero h1 {
        font-size: 1.8rem;
    }
    
    .btn-lg {
        width: 100%;
    }
    
    .d-flex.gap-3 {
        flex-direction: column;
        gap: 0.5rem !important;
    }
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeInUp 0.5s ease-out forwards;
}

/* Social share buttons */
.btn-outline-primary:hover,
.btn-outline-info:hover,
.btn-outline-success:hover,
.btn-outline-secondary:hover {
    transform: scale(1.1);
}

.btn-outline-primary,
.btn-outline-info,
.btn-outline-success,
.btn-outline-secondary {
    transition: all 0.3s ease;
}
</style>

<script>
// Add to calendar function
function addToCalendar() {
    const eventName = "<?php echo htmlspecialchars($event['event_name']); ?>";
    const eventDate = "<?php echo $event['event_date']; ?>";
    const eventTime = "<?php echo $event['event_time']; ?>";
    const eventLocation = "<?php echo htmlspecialchars($event['location']); ?>";
    
    const startDate = new Date(eventDate + 'T' + eventTime);
    const endDate = new Date(startDate.getTime() + (2 * 60 * 60 * 1000));
    
    const formatDate = (date) => {
        return date.toISOString().replace(/-|:|\.\d+/g, '');
    };
    
    const googleUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(eventName)}&dates=${formatDate(startDate)}/${formatDate(endDate)}&location=${encodeURIComponent(eventLocation)}&details=${encodeURIComponent('Join us for this event at our church!')}`;
    
    window.open(googleUrl, '_blank');
}

// Copy event link to clipboard
function copyEventLink() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(() => {
        alert('Event link copied to clipboard!');
    }).catch(() => {
        prompt('Copy this link:', url);
    });
}

// Track event view (optional analytics)
document.addEventListener('DOMContentLoaded', function() {
    console.log('Event viewed: <?php echo $event_id; ?> - <?php echo htmlspecialchars($event['event_name']); ?>');
    
    // You can add analytics tracking here
    // fetch('track_event_view.php', { method: 'POST', body: JSON.stringify({ event_id: <?php echo $event_id; ?> }) });
});
</script>

<?php
// Include footer
include 'footer.php';
?>